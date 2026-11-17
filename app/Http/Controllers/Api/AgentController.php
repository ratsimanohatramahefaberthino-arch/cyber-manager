<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poste;
use App\Models\PosteCommande;
use App\Models\PosteMessage;
use App\Models\Session;
use App\Services\CodeInstallation;
use App\Services\PosteSessionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\CyberSetting;

class AgentController extends Controller
{
    private const REGEX_MAC = '/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/';

    public function handshake(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $data = $request->validate([
            'identifiant_unique' => ['required', 'string', 'max:64'],
            'nom_poste'          => ['required', 'string', 'max:100'],
            'adresse_ip'         => ['nullable', 'ip'],
            'adresse_mac'        => ['nullable', 'string', 'regex:' . self::REGEX_MAC],
            'agent_version'      => ['nullable', 'string', 'max:32'],
            'code_installation'  => ['nullable', 'string', 'max:32'],
        ]);

        $mac   = isset($data['adresse_mac']) ? strtoupper(str_replace('-', ':', $data['adresse_mac'])) : null;
        $ipVue = $this->ipVue($request);

        $poste = Poste::where('identifiant_unique', $data['identifiant_unique'])->first();

        $retourApresPanne = false;
        $ancienSigne      = null;

        if ($poste === null) {
            if (!CodeInstallation::verifier($data['code_installation'] ?? null)) {
                Log::warning("Enregistrement refusé depuis {$ipVue} : code d'installation absent ou invalide.");
                return response()->json([
                    'ok'      => false,
                    'code'    => 'code_installation_invalide',
                    'message' => "Code d'installation absent ou invalide. Demandez-le au gérant (page « Enregistrement » de l'interface web).",
                ], 403);
            }

            try {
                $poste = Poste::create([
                    'nom_poste'              => Poste::nomPourNouveauPoste($data['nom_poste']),
                    'identifiant_unique'     => $data['identifiant_unique'],
                    'adresse_ip'             => $data['adresse_ip'] ?? null,
                    'derniere_ip_vue'        => $ipVue,
                    'adresse_mac'            => $mac,
                    'agent_version'          => $data['agent_version'] ?? null,
                    'agent_token'            => Str::random(64),
                    'etat'                   => Poste::ETAT_DESACTIVE,
                    'actif'                  => true,
                    'dernier_heartbeat'      => now(),
                    'derniere_communication' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return response()->json([
                    'ok'      => false,
                    'code'    => 'enregistrement_simultane',
                    'message' => 'Enregistrement simultané détecté, nouvel essai en cours.',
                ], 409);
            }

            Log::info("Nouveau poste enregistré : « {$poste->nom_poste} » (demandé : « {$data['nom_poste']} »).");
            $nouveau = true;
        } else {
            if ($poste->aConflitIdentite() && $mac !== null && $poste->adresse_mac !== null
                && $mac !== strtoupper($poste->adresse_mac)) {
                Log::warning("Poste « {$poste->nom_poste} » : un ordinateur dupliqué (MAC différente) reçoit l'ordre de changer d'identité.");
                return response()->json([
                    'ok'                   => false,
                    'code'                 => 'identifiant_a_regenerer',
                    'regenerer_identifiant' => true,
                    'message'              => "Cet ordinateur est un doublon d'un autre poste : il va recevoir une nouvelle identité.",
                ], 409);
            }

            $retourApresPanne = !$poste->estEnLigne();
            $ancienSigne      = $poste->dernier_heartbeat;

            $maj = [
                'adresse_ip'             => $data['adresse_ip'] ?? $poste->adresse_ip,
                'derniere_ip_vue'        => $ipVue,
                'adresse_mac'            => $mac ?? $poste->adresse_mac,
                'agent_version'          => $data['agent_version'] ?? $poste->agent_version,
                'agent_token'            => $poste->agent_token ?: Str::random(64),
                'dernier_heartbeat'      => now(),
                'derniere_communication' => now(),
                'etat'                   => $poste->etat === Poste::ETAT_HORS_LIGNE
                    ? ($poste->sessionActive() ? Poste::ETAT_OCCUPE : Poste::ETAT_DISPONIBLE)
                    : $poste->etat,
            ];

            if ($this->conflitProbable($poste, $ipVue)) {
                $maj['conflit_identite_at'] = now();
                Log::warning("Poste « {$poste->nom_poste} » : deux ordinateurs semblent utiliser la même identité (PC cloné ?).");
            }

            $poste->update($maj);
            $nouveau = false;
        }

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
            'branding'             => CyberSetting::brandingAgent(),
            'message'              => $nouveau ? 'Poste enregistré.' : 'Poste reconnu.',
            'delai_hors_ligne_s'   => Poste::DELAI_HORS_LIGNE,
            'delai_grace_agent_s'  => Poste::DELAI_GRACE_AGENT,
        ]);
    }

    public function heartbeat(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        $data = $request->validate(['adresse_ip' => ['nullable', 'ip']]);

        $etaitEnLigne = $poste->estEnLigne();
        $ancienSigne  = $poste->dernier_heartbeat;

        $ipVue   = $this->ipVue($request);
        $conflit = $this->conflitProbable($poste, $ipVue);

        $maj = ['dernier_heartbeat' => now(), 'derniere_communication' => now(), 'derniere_ip_vue' => $ipVue];
        if (!empty($data['adresse_ip'])) $maj['adresse_ip'] = $data['adresse_ip'];

        if ($conflit) {
            if ($poste->conflit_identite_at === null || $poste->conflit_identite_at->lt(now()->subMinute())) {
                Log::warning("Poste « {$poste->nom_poste} » : deux ordinateurs semblent utiliser la même identité (PC cloné ?).");
            }
            $maj['conflit_identite_at'] = now();
        } elseif ($poste->conflit_identite_at !== null
            && $poste->conflit_identite_at->lt(now()->subMinutes(Poste::DUREE_CONFLIT_MINUTES))) {
            $maj['conflit_identite_at'] = null;
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

        app(\App\Services\AccesInternetService::class)->reconcilierTous();

        $etat    = $poste->etatAffiche();
        $session = $poste->sessionActive();

        return response()->json([
            'ok'                   => true,
            'nom_poste'            => $poste->nom_poste,
            'etat'                 => $etat,
            'doit_etre_verrouille' => $this->doitEtreVerrouille($etat),
            'session'              => $session ? $this->resumeSession($session) : null,
            'branding'             => CyberSetting::brandingAgent(),
            'delai_hors_ligne_s'   => Poste::DELAI_HORS_LIGNE,
            'delai_grace_agent_s'  => Poste::DELAI_GRACE_AGENT,
        ]);
    }

    public function activer(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        [$code, $corps, $posteFinal] = DB::transaction(function () use ($poste, $sessions) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $p->update(['dernier_heartbeat' => now(), 'derniere_communication' => now()]);

            switch ($p->etatAffiche()) {
                case Poste::ETAT_DESACTIVE: return [403, ['ok' => false, 'message' => 'Poste désactivé, contactez le gérant.'], null];
                case Poste::ETAT_SUSPENDU: return [409, ['ok' => false, 'message' => "Votre session est en pause."], null];
                case Poste::ETAT_VERROUILLE: return [409, ['ok' => false, 'message' => "Ce poste est verrouillé."], null];
                case Poste::ETAT_OCCUPE:
                    $s = $p->sessionActive();
                    return [200, ['ok' => true, 'message' => 'Session déjà active.', 'session' => $s ? $this->resumeSession($s) : null], $s ? $p : null];
            }

            if ($p->etatAffiche() !== Poste::ETAT_DISPONIBLE) {
                return [409, ['ok' => false, 'message' => "Ce poste n'est pas disponible."], null];
            }

            $session = $sessions->creerSession($p, 'postpaye', null);
            PosteMessage::where('poste_id', $p->id)->delete();
            $this->tracer($p, 'demarrer', 'Activation depuis le poste client', ['session_id' => $session->id, 'type' => 'postpaye']);
            $p->update(['etat' => Poste::ETAT_OCCUPE]);

            return [200, ['ok' => true, 'message' => 'Poste activé.', 'session' => $this->resumeSession($session)], $p];
        });

        if ($posteFinal !== null) {
            $reseau = app(\App\Services\AccesInternetService::class)->autoriser($posteFinal);
            if ($reseau === false) $corps['avertissement'] = "Connexion Internet momentanément indisponible.";
        }

        return response()->json($corps, $code);
    }

    public function suspendreSession(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        $ok = DB::transaction(function () use ($poste, $sessions) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $p->update(['dernier_heartbeat' => now(), 'derniere_communication' => now()]);
            $session = $p->sessionActive();
            if ($session === null || !$sessions->suspendre($p)) return false;
            $this->tracer($p, 'suspendre', 'Suspension depuis le widget client', ['session_id' => $session->id]);
            return true;
        });

        if (!$ok) return response()->json(['ok' => false, 'message' => 'Aucune session active à suspendre.'], 409);

        $corps = ['ok' => true, 'message' => 'Session suspendue.'];
        if (app(\App\Services\AccesInternetService::class)->couper($poste) === false) $corps['avertissement'] = "Coupure Internet non confirmée.";
        return response()->json($corps);
    }

    public function reprendreSession(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        $ok = DB::transaction(function () use ($poste, $sessions) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            if ($p === null || !$sessions->reprendre($p)) return false;
            $this->tracer($p, 'reprendre', 'Reprise depuis l\'écran de verrouillage client');
            return true;
        });

        if (!$ok) return response()->json(['ok' => false, 'message' => 'Aucune session à reprendre.'], 409);

        $corps = ['ok' => true, 'message' => 'Session reprise.'];
        if (app(\App\Services\AccesInternetService::class)->autoriser($poste) === false) $corps['avertissement'] = "Connexion Internet non confirmée.";
        return response()->json($corps);
    }

    private function doitEtreVerrouille(string $etat): bool
    {
        return in_array($etat, [Poste::ETAT_DISPONIBLE, Poste::ETAT_VERROUILLE, Poste::ETAT_DESACTIVE, Poste::ETAT_SUSPENDU], true);
    }

    public function fermerSession(Request $request, PosteSessionService $sessions): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        $resultat = DB::transaction(function () use ($poste, $sessions) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $p->update(['dernier_heartbeat' => now(), 'derniere_communication' => now()]);

            $session = $sessions->cloturer($p, 'client');
            if ($session === null) return null;

            $p->update(['etat' => Poste::ETAT_DESACTIVE]);
            PosteCommande::create(['poste_id' => $p->id, 'type' => 'verrouiller']);

            $this->tracer($p, 'fermer', 'Fermeture depuis le widget client', ['session_id' => $session->id]);
            return [$p, (int) $session->montant_total];
        });

        if ($resultat === null) return response()->json(['ok' => false, 'message' => 'Aucune session active à fermer.'], 409);

        [$posteFinal, $montant] = $resultat;

        $corps = ['ok' => true, 'message' => 'Session terminée.', 'montant' => $montant];
        if (app(\App\Services\AccesInternetService::class)->couper($posteFinal) === false) $corps['avertissement'] = "Coupure Internet non confirmée.";
        return response()->json($corps);
    }

    public function message(Request $request): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        $data  = $request->validate(['texte' => ['required', 'string', 'max:500']]);
        $texte = trim($data['texte']);
        if ($texte === '') return response()->json(['ok' => false, 'message' => 'Message vide.'], 422);

        $m = PosteMessage::create(['poste_id' => $poste->id, 'sens' => PosteMessage::SENS_CLIENT, 'contenu' => $texte]);
        return response()->json(['ok' => true, 'message' => 'Message envoyé.', 'id' => $m->id]);
    }

    public function commandes(Request $request): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        $commandes = PosteCommande::where('poste_id', $poste->id)->where('statut', 'en_attente')->orderBy('id')->limit(10)->get();
        foreach ($commandes as $c) $c->update(['statut' => 'recue', 'recue_at' => now()]);

        return response()->json([
            'ok'        => true,
            'commandes' => $commandes->map(fn ($c) => ['id' => $c->id, 'type' => $c->type, 'payload' => $c->payload])->values(),
        ]);
    }

    public function confirmerCommande(Request $request, PosteCommande $commande): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null || $commande->poste_id !== $poste->id) return response()->json(['ok' => false], 401);

        $data = $request->validate(['succes' => ['required', 'boolean'], 'message' => ['nullable', 'string', 'max:500']]);
        $commande->update(['statut' => $data['succes'] ? 'executee' : 'echouee', 'resultat' => $data['message'] ?? null, 'executee_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function logo()
    {
        $chemin = CyberSetting::logoChemin();
        if ($chemin === null) {
            return response()->json(['ok' => false, 'message' => 'Aucun logo.'], 404);
        }
        return response()->file($chemin, ['Cache-Control' => 'no-cache']);
    }

    private function authentifier(Request $request): ?Poste
    {
        $token = $request->header('X-Agent-Token');
        if (!$token) return null;
        return Poste::where('agent_token', $token)->first();
    }

    private function ipVue(Request $request): ?string
    {
        $ip = $request->ip();
        if ($ip === null) return null;

        if (str_starts_with($ip, '::ffff:')) $ip = substr($ip, 7);
        return $ip === '::1' ? '127.0.0.1' : $ip;
    }

    private function conflitProbable(Poste $poste, ?string $ipVue): bool
    {
        return $ipVue !== null
            && $poste->derniere_ip_vue !== null
            && $poste->derniere_ip_vue !== $ipVue
            && $poste->dernier_heartbeat !== null
            && $poste->dernier_heartbeat->gt(now()->subSeconds(Poste::DELAI_CONFLIT));
    }

    private function tracer(Poste $poste, string $type, string $resultat, array $payload = []): void
    {
        PosteCommande::create([
            'poste_id' => $poste->id, 'type' => $type, 'payload' => $payload + ['origine' => 'client'],
            'statut' => 'executee', 'resultat' => $resultat, 'recue_at' => now(), 'executee_at' => now(),
        ]);
    }

    private function resumeSession(Session $session): array
    {
        $mode    = $session->mode_facturation ?: 'forfait';
        $ecoule  = (int) $session->secondesConsommees();
        $prevue  = (int) $session->duree_prevue;
        $restant = ($mode === 'forfait' && $prevue > 0) ? max(0, $prevue * 60 - $ecoule) : null;

        $forfaitTotal = 0;
        if ($mode === 'forfait') {
            $tarif = app(\App\Services\TarifService::class)->tarifStandard();
            $forfaitTotal = app(\App\Services\TarifService::class)->montantPourMinutes($prevue, $tarif);
        }

        $tarifInfo = ['par_minute' => 20, 'minimum' => 300, 'arrondi_actif' => false, 'unite' => 0, 'seuil' => 0];
        try {
            $t = $session->tarif ?: app(\App\Services\TarifService::class)->tarifStandard();
            $tarifInfo = [
                'par_minute'    => (int) $t->montant_par_minute,
                'minimum'       => (int) $t->montant_minimum,
                'arrondi_actif' => (bool) $t->arrondi_actif,
                'unite'         => (int) $t->unite_arrondi,
                'seuil'         => (int) $t->seuil_arrondi,
            ];
        } catch (\Throwable) {}

        return [
            'id'               => $session->id,
            'mode'             => $mode,
            'date_heure_debut' => $session->date_heure_debut?->toIso8601String(),
            'date_heure_fin'   => $session->date_heure_fin_prevue?->toIso8601String(),
            'duree_prevue'     => $prevue,
            'forfait_total'    => $forfaitTotal,
            'montant_actuel'   => app(PosteSessionService::class)->montant($session),
            'etat'             => $session->etat,
            'ecoule_s'         => $ecoule,
            'restant_s'        => $restant,
            'tarif'            => $tarifInfo,
        ];
    }
}