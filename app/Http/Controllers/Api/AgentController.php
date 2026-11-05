<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poste;
use App\Models\PosteCommande;
use App\Models\PosteMessage;
use App\Models\Session;
use App\Services\PosteSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    private const REGEX_MAC = '/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/';

    /**
     * Handshake : l'agent s'annonce. Crée le poste s'il est inconnu.
     * Le nom d'un poste existant n'est PAS écrasé (l'admin garde la main).
     * Si le poste revient après une panne / un redémarrage avec une session ouverte,
     * le temps de panne est déduit et Internet est rétabli ; la réponse contient la session en cours.
     */
    public function handshake(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $data = $request->validate([
            'identifiant_unique' => ['required', 'string', 'max:64'],
            'nom_poste'          => ['required', 'string', 'max:100'],
            'adresse_ip'         => ['nullable', 'ip'],
            'adresse_mac'        => ['nullable', 'string', 'regex:' . self::REGEX_MAC],
            'agent_version'      => ['nullable', 'string', 'max:32'],
        ]);

        $mac = isset($data['adresse_mac'])
            ? strtoupper(str_replace('-', ':', $data['adresse_mac']))
            : null;

        $poste = Poste::where('identifiant_unique', $data['identifiant_unique'])->first();

        $retourApresPanne = false;
        $ancienSigne      = null;

        if ($poste === null) {
            $poste = Poste::create([
                'nom_poste'              => $data['nom_poste'],
                'identifiant_unique'     => $data['identifiant_unique'],
                'adresse_ip'             => $data['adresse_ip'] ?? null,
                'adresse_mac'            => $mac,
                'agent_version'          => $data['agent_version'] ?? null,
                'agent_token'            => Str::random(64),
                'etat'                   => Poste::ETAT_DISPONIBLE,
                'actif'                  => true,
                'dernier_heartbeat'      => now(),
                'derniere_communication' => now(),
            ]);
            $nouveau = true;
        } else {
            $retourApresPanne = !$poste->estEnLigne();
            $ancienSigne      = $poste->dernier_heartbeat;

            $poste->update([
                'adresse_ip'             => $data['adresse_ip'] ?? $poste->adresse_ip,
                'adresse_mac'            => $mac ?? $poste->adresse_mac,
                'agent_version'          => $data['agent_version'] ?? $poste->agent_version,
                'agent_token'            => $poste->agent_token ?: Str::random(64),
                'dernier_heartbeat'      => now(),
                'derniere_communication' => now(),
                'etat'                   => $poste->etat === Poste::ETAT_HORS_LIGNE
                    ? ($poste->sessionActive() ? Poste::ETAT_OCCUPE : Poste::ETAT_DISPONIBLE)
                    : $poste->etat,
            ]);
            $nouveau = false;
        }

        // Redémarrage du PC en pleine session : déduction de la panne + Internet rétabli
        if ($retourApresPanne) {
            $sessions->traiterRetourEnLigne($poste, $ancienSigne);
            $poste->refresh();
        }

        $etat    = $poste->etatAffiche();
        $session = $poste->sessionActive();

        return response()->json([
            'ok'                   => true,
            'nouveau'              => $nouveau,
            'poste_id'             => $poste->id,
            'agent_token'          => $poste->agent_token,
            'nom_poste'            => $poste->nom_poste,
            'etat'                 => $etat,
            'doit_etre_verrouille' => $this->doitEtreVerrouille($etat),
            'session'              => $session ? $this->resumeSession($session) : null,
            'message'              => $nouveau ? 'Poste enregistré.' : 'Poste reconnu.',
        ]);
    }

    /**
     * Heartbeat : « je suis vivant » + l'agent récupère l'état à appliquer.
     * C'est aussi ici que l'on constate l'expiration d'un forfait (réactivité de ~5 s).
     */
    public function heartbeat(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) {
            return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);
        }

        $data = $request->validate(['adresse_ip' => ['nullable', 'ip']]);

        // À lire AVANT la mise à jour du heartbeat
        $etaitEnLigne = $poste->estEnLigne();
        $ancienSigne  = $poste->dernier_heartbeat;

        $maj = ['dernier_heartbeat' => now(), 'derniere_communication' => now()];
        if (!empty($data['adresse_ip'])) {
            $maj['adresse_ip'] = $data['adresse_ip'];
        }
        if ($poste->etat === Poste::ETAT_HORS_LIGNE) {
            $maj['etat'] = $poste->sessionActive() ? Poste::ETAT_OCCUPE : Poste::ETAT_DISPONIBLE;
        }
        $poste->update($maj);

        if ($etaitEnLigne) {
            $sessions->expirerSiNecessaire($poste);
        } else {
            $sessions->traiterRetourEnLigne($poste, $ancienSigne);
        }
        $poste->refresh();

        $etat    = $poste->etatAffiche();
        $session = $poste->sessionActive();

        return response()->json([
            'ok'                   => true,
            'nom_poste'            => $poste->nom_poste,
            'etat'                 => $etat,
            'doit_etre_verrouille' => $this->doitEtreVerrouille($etat),
            'session'              => $session ? $this->resumeSession($session) : null,
        ]);
    }

    /**
     * Activation demandée par le client (bouton ACTIVER de l'écran de verrouillage).
     * Crée une session postpayée (libre-service), connecte le poste au réseau.
     */
    public function activer(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) {
            return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);
        }

        // 1) Transaction courte : verrou de ligne pour éviter un double clic / une double session
        [$code, $corps, $posteFinal] = DB::transaction(function () use ($poste, $sessions) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $p->update(['dernier_heartbeat' => now(), 'derniere_communication' => now()]);

            switch ($p->etatAffiche()) {
                case Poste::ETAT_DESACTIVE:
                    return [403, ['ok' => false, 'message' => 'Poste désactivé, contactez le gérant.'], null];

                case Poste::ETAT_SUSPENDU:
                    return [409, ['ok' => false, 'message' => "Votre session est en pause. Adressez-vous à l'accueil."], null];

                case Poste::ETAT_VERROUILLE:
                    return [409, ['ok' => false, 'message' => "Ce poste est verrouillé par l'administrateur."], null];

                case Poste::ETAT_OCCUPE:
                    $s = $p->sessionActive();
                    // On renvoie aussi le poste : connecterReseau() est idempotent et ré-assure Internet
                    return [200, [
                        'ok'      => true,
                        'message' => 'Session déjà active.',
                        'session' => $s ? $this->resumeSession($s) : null,
                    ], $s ? $p : null];
            }

            // Disponible → on démarre une session libre-service
            $session = $sessions->creerSession($p, 'postpaye', null);

            // Trace d'audit : commande « demarrer » déjà exécutée (l'agent se déverrouille via la réponse)
            $this->tracer($p, 'demarrer', 'Activation depuis le poste client',
                ['session_id' => $session->id, 'type' => 'postpaye']);

            $p->update(['etat' => Poste::ETAT_OCCUPE]);

            return [200, [
                'ok'      => true,
                'message' => 'Poste activé.',
                'session' => $this->resumeSession($session),
            ], $p];
        });

        // 2) Hors transaction : l'appel MikroTik peut être lent, il ne doit pas bloquer la base
        if ($posteFinal !== null) {
            $reseau = $sessions->connecterReseau($posteFinal);
            if ($reseau === false) {
                $corps['avertissement'] = "Connexion Internet momentanément indisponible. Prévenez l'accueil.";
            }
        }

        return response()->json($corps, $code);
    }

    /** Widget client : bouton « Suspendre ». Chrono figé, poste « suspendu », Internet coupé. */
    public function suspendreSession(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) {
            return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);
        }

        $ok = DB::transaction(function () use ($poste, $sessions) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $p->update(['dernier_heartbeat' => now(), 'derniere_communication' => now()]);

            $session = $p->sessionActive();
            if ($session === null || !$sessions->suspendre($p)) {
                return false;
            }

            $this->tracer($p, 'suspendre', 'Suspension depuis le widget client', ['session_id' => $session->id]);
            return true;
        });

        if (!$ok) {
            return response()->json(['ok' => false, 'message' => 'Aucune session active à suspendre.'], 409);
        }

        // Hors transaction : coupure Internet (mode dégradé si MikroTik injoignable)
        $corps = ['ok' => true, 'message' => 'Session suspendue.'];
        if ($sessions->deconnecterReseau($poste) === false) {
            $corps['avertissement'] = "Coupure Internet non confirmée. Prévenez l'accueil.";
        }

        return response()->json($corps);
    }

    /** L'écran doit-il être verrouillé pour cet état ? (source unique handshake / heartbeat) */
    private function doitEtreVerrouille(string $etat): bool
    {
        return in_array($etat, [
            Poste::ETAT_DISPONIBLE,
            Poste::ETAT_VERROUILLE,
            Poste::ETAT_DESACTIVE,
            Poste::ETAT_SUSPENDU,
        ], true);
    }

    /** Widget client : bouton « Quitter ». Clôture la session, calcule le montant, coupe Internet. */
    public function fermerSession(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) {
            return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);
        }

        // 1) Transaction courte
        $resultat = DB::transaction(function () use ($poste, $sessions) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $p->update(['dernier_heartbeat' => now(), 'derniere_communication' => now()]);

            $session = $sessions->cloturer($p, 'client');
            if ($session === null) {
                return null;
            }

            $this->tracer($p, 'fermer', 'Fermeture depuis le widget client', ['session_id' => $session->id]);
            return [$p, (int) $session->montant_total];
        });

        if ($resultat === null) {
            return response()->json(['ok' => false, 'message' => 'Aucune session active à fermer.'], 409);
        }

        [$posteFinal, $montant] = $resultat;

        // 2) Hors transaction : coupure Internet (mode dégradé si MikroTik injoignable)
        $corps = ['ok' => true, 'message' => 'Session terminée.', 'montant' => $montant];
        if ($sessions->deconnecterReseau($posteFinal) === false) {
            $corps['avertissement'] = "Coupure Internet non confirmée. Prévenez l'accueil.";
        }

        return response()->json($corps);
    }

        /**
     * Chat client → admin : l'agent envoie le texte saisi par le client.
     * Stocké dans poste_messages (sens « client », non lu) ; l'interface /postes l'affiche
     * et signale les non-lus. Limité à 20 messages par minute et par poste.
     */
    public function message(Request $request): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) {
            return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);
        }

        $data  = $request->validate(['texte' => ['required', 'string', 'max:500']]);
        $texte = trim($data['texte']);
        if ($texte === '') {
            return response()->json(['ok' => false, 'message' => 'Message vide.'], 422);
        }

        $recents = PosteMessage::where('poste_id', $poste->id)
            ->where('sens', PosteMessage::SENS_CLIENT)
            ->where('created_at', '>', now()->subMinute())
            ->count();
        if ($recents >= 20) {
            return response()->json(['ok' => false, 'message' => 'Trop de messages envoyés. Patientez une minute.'], 429);
        }

        $m = PosteMessage::create([
            'poste_id' => $poste->id,
            'sens'     => PosteMessage::SENS_CLIENT,
            'contenu'  => $texte,
        ]);

        return response()->json(['ok' => true, 'message' => 'Message envoyé.', 'id' => $m->id]);
    }

    /** Commandes en attente. */
    public function commandes(Request $request): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) {
            return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);
        }

        $commandes = PosteCommande::where('poste_id', $poste->id)
            ->where('statut', 'en_attente')
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($commandes as $c) {
            $c->update(['statut' => 'recue', 'recue_at' => now()]);
        }

        return response()->json([
            'ok'        => true,
            'commandes' => $commandes->map(fn ($c) => [
                'id'      => $c->id,
                'type'    => $c->type,
                'payload' => $c->payload,
            ])->values(),
        ]);
    }

    public function confirmerCommande(Request $request, PosteCommande $commande): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null || $commande->poste_id !== $poste->id) {
            return response()->json(['ok' => false], 401);
        }

        $data = $request->validate([
            'succes'  => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $commande->update([
            'statut'      => $data['succes'] ? 'executee' : 'echouee',
            'resultat'    => $data['message'] ?? null,
            'executee_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // --- Helpers ---

    private function authentifier(Request $request): ?Poste
    {
        $token = $request->header('X-Agent-Token');
        if (!$token) {
            return null;
        }

        return Poste::where('agent_token', $token)->first();
    }

    /** Trace d'audit : action initiée par le client, déjà exécutée (jamais renvoyée à l'agent par le polling). */
    private function tracer(Poste $poste, string $type, string $resultat, array $payload = []): void
    {
        PosteCommande::create([
            'poste_id'    => $poste->id,
            'type'        => $type,
            'payload'     => $payload + ['origine' => 'client'],
            'statut'      => 'executee',
            'resultat'    => $resultat,
            'recue_at'    => now(),
            'executee_at' => now(),
        ]);
    }

    /** Instantané de session envoyé à l'agent : le serveur reste la référence du temps et du montant. */
    private function resumeSession(Session $session): array
    {
        $mode    = $session->mode_facturation ?: 'forfait';
        $ecoule  = (int) $session->secondesConsommees();
        $prevue  = (int) $session->duree_prevue;
        $restant = ($mode === 'forfait' && $prevue > 0) ? max(0, $prevue * 60 - $ecoule) : null;

        return [
            'id'               => $session->id,
            'mode'             => $mode,
            'date_heure_debut' => $session->date_heure_debut?->toIso8601String(),
            'date_heure_fin'   => $session->date_heure_fin_prevue?->toIso8601String(),
            'duree_prevue'     => $prevue,
            'montant_actuel'   => app(PosteSessionService::class)->montant($session),
            'etat'             => $session->etat,
            'ecoule_s'         => $ecoule,
            'restant_s'        => $restant,
        ];
    }
}