<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Models\PosteCommande;
use App\Models\PosteMessage;
use App\Models\Session;
use App\Services\PosteSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PosteController extends Controller
{
    /** Définition des actions : label, couleur (clé), confirmation, ouvre une modale. */
    private const ACTIONS = [
        'demarrer'    => ['label' => 'Démarrer',     'couleur' => 'vert',  'modal' => true],
        'debloquer'   => ['label' => 'Débloquer',    'couleur' => 'bleu'],
        'fermer'      => ['label' => 'Fermer',       'couleur' => 'rouge', 'confirm' => 'Fermer la session ? Le montant sera calculé.'],
        'suspendre'   => ['label' => 'Suspendre',    'couleur' => 'violet'],
        'reprendre'   => ['label' => 'Reprendre',    'couleur' => 'vert'],
        'verrouiller' => ['label' => 'Verrouiller',  'couleur' => 'gris'],
        'recharger'   => ['label' => 'Recharger',    'couleur' => 'ambre', 'modal' => true],
        'desactiver'  => ['label' => 'Désactiver',   'couleur' => 'gris',  'confirm' => 'Désactiver ce poste ? La session en cours sera clôturée.'],
        'activer'     => ['label' => 'Réactiver',    'couleur' => 'vert'],
        'arreter'     => ['label' => 'Arrêter',      'couleur' => 'rouge', 'confirm' => "Éteindre le PC ? La session en cours sera clôturée."],
    ];

    public function __construct(
        private PosteSessionService $sessions
    ) {}

    // ------------------------------------------------------------------
    // Lecture
    // ------------------------------------------------------------------

    public function index()
    {
        return view('postes.index', ['postes' => $this->tousLesPostes()]);
    }

    /** Endpoint de polling (toutes les 3 s). */
    public function etat(): JsonResponse
    {
        return response()->json([
            'ok'     => true,
            'postes' => $this->tousLesPostes(),
        ]);
    }

    public function chat(Request $request, Poste $poste): JsonResponse
    {
        $apres = (int) $request->query('apres', 0);

        $messages = $poste->messages()
            ->where('id', '>', $apres)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'sens', 'contenu', 'created_at', 'lu_at']);

        // Le gérant consulte la discussion : les messages du client passent à « lu »
        $nonLus = $messages->where('sens', PosteMessage::SENS_CLIENT)->whereNull('lu_at')->pluck('id');
        if ($nonLus->isNotEmpty()) {
            PosteMessage::whereIn('id', $nonLus)->update(['lu_at' => now()]);
        }

        return response()->json([
            'ok'       => true,
            'messages' => $messages->map(fn ($m) => [
                'id'      => $m->id,
                'sens'    => $m->sens,
                'contenu' => $m->contenu,
                'heure'   => $m->created_at?->format('H:i'),
            ])->values(),
        ]);
    }

    // ------------------------------------------------------------------
    // Actions
    // ------------------------------------------------------------------

    public function demarrer(Request $request, Poste $poste)
    {
        $data = $request->validate([
            'type'          => ['required', 'in:forfait,postpaye'],
            'duree_minutes' => ['required_if:type,forfait', 'nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        if ($poste->etatAffiche() !== Poste::ETAT_DISPONIBLE) {
            return $this->echec($request, "Le poste {$poste->nom_poste} n'est pas disponible.");
        }

        $duree = $data['type'] === 'forfait' ? (int) $data['duree_minutes'] : null;

        $session = $this->sessions->creerSession($poste, $data['type'], $duree);
        $reseau  = $this->sessions->connecterReseau($poste);   // mode dégradé : n'échoue jamais

        PosteCommande::create([
            'poste_id' => $poste->id,
            'type'     => 'demarrer',
            'payload'  => [
                'session_id'    => $session->id,
                'duree_minutes' => $duree,
                'type'          => $data['type'],
                'montant'       => 0,
            ],
        ]);

        $poste->update(['etat' => Poste::ETAT_OCCUPE]);

        return $this->ok($request, "Poste {$poste->nom_poste} démarré." . $this->avertissementReseau($reseau));
    }

    public function debloquer(Request $request, Poste $poste)
    {
        if (!$poste->sessionActive()) {
            // Déblocage sans facturation : session de mode "libre" (montant 0)
            $this->sessions->creerSession($poste, 'libre', null);
        }

        $this->sessions->reprendre($poste);   // reprend si en pause, poste « utilisé »
        $reseau = $this->sessions->connecterReseau($poste);

        PosteCommande::create([
            'poste_id' => $poste->id,
            'type'     => 'debloquer',
            'payload'  => ['session_id' => $poste->sessionActive()?->id],
        ]);

        return $this->ok($request, "Poste {$poste->nom_poste} débloqué." . $this->avertissementReseau($reseau));
    }

    public function fermer(Request $request, Poste $poste)
    {
        $session = $this->terminer($poste, 'admin');
        if ($session === null) {
            return $this->echec($request, 'Aucune session active à fermer.');
        }

        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'verrouiller']);

        $montant = ($session->mode_facturation ?? 'forfait') === 'libre'
            ? ''
            : ' Montant : ' . number_format((int) $session->montant_total, 0, ',', ' ') . ' Ar.';

        return $this->ok($request, "Session de {$poste->nom_poste} fermée.{$montant}");
    }

    public function suspendre(Request $request, Poste $poste)
    {
        $ok = DB::transaction(function () use ($poste) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            return $p !== null && $this->sessions->suspendre($p);
        });

        if (!$ok) {
            return $this->echec($request, 'Aucune session active.');
        }

        $reseau = $this->sessions->deconnecterReseau($poste);   // la pause coupe Internet

        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'suspendre']);

        return $this->ok($request, "Session de {$poste->nom_poste} suspendue." . $this->avertissementReseau($reseau, 'coupé'));
    }

    public function reprendre(Request $request, Poste $poste)
    {
        if (!$this->sessions->reprendre($poste)) {
            return $this->echec($request, 'Aucune session à reprendre.');
        }

        $reseau = $this->sessions->connecterReseau($poste);   // Internet avait été coupé à la suspension

        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'reprendre']);

        return $this->ok($request, "Session de {$poste->nom_poste} reprise." . $this->avertissementReseau($reseau));
    }

    public function verrouiller(Request $request, Poste $poste)
    {
        if (!$poste->sessionActive()) {
            return $this->echec($request, 'Aucune session active à verrouiller.');
        }

        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'verrouiller']);
        $poste->update(['etat' => Poste::ETAT_VERROUILLE]); // le chrono continue

        return $this->ok($request, "Poste {$poste->nom_poste} verrouillé (le chrono continue).");
    }

    public function desactiver(Request $request, Poste $poste)
    {
        $this->terminer($poste, 'desactivation');
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'desactiver']);
        $poste->update(['etat' => Poste::ETAT_DESACTIVE]);

        return $this->ok($request, "Poste {$poste->nom_poste} désactivé.");
    }

    public function activer(Request $request, Poste $poste)
    {
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'verrouiller']);
        $poste->update(['etat' => Poste::ETAT_DISPONIBLE]);

        return $this->ok($request, "Poste {$poste->nom_poste} réactivé.");
    }

    public function arreter(Request $request, Poste $poste)
    {
        $this->terminer($poste, 'arret');
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'arreter']);
        $poste->update(['etat' => Poste::ETAT_DISPONIBLE]);

        return $this->ok($request, "Commande d'arrêt envoyée à {$poste->nom_poste}.");
    }

    public function recharger(Request $request, Poste $poste)
    {
        $data = $request->validate(['minutes' => ['required', 'integer', 'min:1', 'max:1440']]);

        // Sous verrou : évite une course avec l'expiration automatique du forfait
        $resultat = DB::transaction(function () use ($poste, $data) {
            $p       = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $session = $p?->sessionActive();

            if ($session === null) {
                return 'aucune';
            }
            if (($session->mode_facturation ?? 'forfait') !== 'forfait') {
                return 'non_forfait';
            }

            $nouvelleDuree = (int) $session->duree_prevue + $data['minutes'];
            $session->update([
                'date_heure_fin_prevue' => $session->date_heure_fin_prevue
                    ? $session->date_heure_fin_prevue->copy()->addMinutes($data['minutes'])
                    : now()->addMinutes($data['minutes']),
                'duree_prevue' => $nouvelleDuree,
            ]);

            return $nouvelleDuree;
        });

        if ($resultat === 'aucune') {
            return $this->echec($request, 'Aucune session active sur ce poste.');
        }
        if ($resultat === 'non_forfait') {
            return $this->echec($request, "Cette session n'est pas un forfait : rien à recharger.");
        }

        PosteCommande::create([
            'poste_id' => $poste->id,
            'type'     => 'recharger',
            'payload'  => ['minutes_ajoutees' => (int) $data['minutes'], 'duree_totale_min' => $resultat],
        ]);

        return $this->ok($request, "{$data['minutes']} min ajoutées à {$poste->nom_poste} (forfait : {$resultat} min).");
    }

    /** Chat admin → client. */
    public function envoyerMessage(Request $request, Poste $poste)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);

        PosteMessage::create([
            'poste_id' => $poste->id,
            'sens'     => PosteMessage::SENS_ADMIN,
            'contenu'  => $data['message'],
        ]);

        PosteCommande::create([
            'poste_id' => $poste->id,
            'type'     => 'message',
            'payload'  => ['message' => $data['message']],
        ]);

        return $this->ok($request, 'Message envoyé.');
    }

        /**
     * Identifiants HotSpot du poste (compte MikroTik utilisé pour ouvrir Internet).
     * Identifiant vide = dissociation. Mot de passe vide = on conserve l'ancien.
     * Le mot de passe n'est jamais renvoyé ni journalisé.
     */
    public function enregistrerHotspot(Request $request, Poste $poste)
    {
        $data = $request->validate([
            'hotspot_username' => ['nullable', 'string', 'min:3', 'max:64', 'regex:/^\S+$/',
                Rule::unique('postes', 'hotspot_username')->ignore($poste->id)],
            'hotspot_password' => ['nullable', 'string', 'min:4', 'max:64', 'regex:/^\S+$/'],
        ], [
            'hotspot_username.min'    => "L'identifiant doit contenir au moins 3 caractères.",
            'hotspot_username.max'    => "L'identifiant ne doit pas dépasser 64 caractères.",
            'hotspot_username.regex'  => "L'identifiant ne doit pas contenir d'espaces.",
            'hotspot_username.unique' => 'Ce compte HotSpot est déjà utilisé par un autre poste.',
            'hotspot_password.min'    => 'Le mot de passe doit contenir au moins 4 caractères.',
            'hotspot_password.max'    => 'Le mot de passe ne doit pas dépasser 64 caractères.',
            'hotspot_password.regex'  => 'Le mot de passe ne doit pas contenir d\'espaces.',
        ]);

        $username = trim((string) ($data['hotspot_username'] ?? ''));
        $password = trim((string) ($data['hotspot_password'] ?? ''));

        if ($username === '') {
            $poste->update(['hotspot_username' => null, 'hotspot_password' => null]);
            Log::info("Identifiants HotSpot du poste {$poste->nom_poste} supprimés.");

            return $this->ok($request, "Identifiants HotSpot de {$poste->nom_poste} supprimés : Internet ne sera plus activé pour ce poste.");
        }

        if ($password === '' && blank($poste->hotspot_password)) {
            return $this->echec($request, 'Le mot de passe est obligatoire pour un nouveau compte.');
        }

        $compteChange = $username !== $poste->hotspot_username;

        $poste->update([
            'hotspot_username' => $username,
            'hotspot_password' => $password !== '' ? $password : $poste->hotspot_password,
        ]);
        Log::info("Identifiants HotSpot du poste {$poste->nom_poste} mis à jour (compte « {$username} »).");

        $message = "Identifiants HotSpot de {$poste->nom_poste} enregistrés.";

        if ($this->sessions->compteHotspotExiste($username) === false) {
            $message .= " Attention : le compte « {$username} » est introuvable sur MikroTik — créez-le avant d'utiliser ce poste.";
        }

        // Session en cours avec un autre compte : on rebascule Internet sur le nouveau
        $session = $poste->sessionActive();
        if ($compteChange && $session !== null && $session->etat === 'en_cours') {
            $message .= $this->avertissementReseau($this->sessions->connecterReseau($poste));
        }

        return $this->ok($request, $message);
    }

    // ------------------------------------------------------------------
    // Données pour l'interface
    // ------------------------------------------------------------------

    private function tousLesPostes(): array
    {
        return Poste::orderBy('nom_poste')
            ->withCount(['messages as non_lus' => fn ($q) => $q
                ->where('sens', PosteMessage::SENS_CLIENT)
                ->whereNull('lu_at')])
            ->get()
            ->map(fn (Poste $p) => $this->donnees($p))
            ->values()
            ->all();
    }

    private function donnees(Poste $p): array
    {
        $etat = $p->etatAffiche();
        $s    = $p->sessionActive();

        $session = null;
        if ($s) {
            $mode    = $s->mode_facturation ?: 'forfait';
            $ecoule  = (int) $s->secondesConsommees();
            $prevue  = (int) $s->duree_prevue;
            $restant = ($mode === 'forfait' && $prevue > 0) ? max(0, $prevue * 60 - $ecoule) : null;

            $session = [
                'id'         => $s->id,
                'mode'       => $mode,
                'etat'       => $s->etat,
                'debut'      => $s->date_heure_debut?->format('H:i:s'),
                'ecoule_s'   => $ecoule,
                'restant_s'  => $restant,
                'duree_min'  => $prevue ?: null,
                'montant'    => $mode === 'libre' ? 0 : $this->sessions->montant($s, $ecoule),
            ];
        }

        return [
            'id'       => $p->id,
            'nom'      => $p->nom_poste,
            'etat'     => $etat,
            'ip'       => $p->adresse_ip,
            'mac'      => $p->adresse_mac,
            'version'  => $p->agent_version,
            'vu_il_y_a' => $p->dernier_heartbeat?->diffForHumans(),
            'session'  => $session,
            'non_lus'  => (int) ($p->non_lus ?? 0),
            'hotspot_username'  => $p->hotspot_username,
            'hotspot_configure' => filled($p->hotspot_username) && filled($p->hotspot_password),
            'actions'  => $this->actionsPour($etat, $s !== null, $session['mode'] ?? null),
        ];
    }

    /** Actions autorisées selon l'état — source unique pour le menu et les boutons. */
    private function actionsPour(string $etat, bool $aSession, ?string $modeSession = null): array
    {
        $cles = match ($etat) {
            Poste::ETAT_DISPONIBLE => ['demarrer', 'debloquer', 'desactiver', 'arreter'],
            Poste::ETAT_OCCUPE     => ['fermer', 'suspendre', 'verrouiller', 'recharger', 'desactiver', 'arreter'],
            Poste::ETAT_SUSPENDU   => ['reprendre', 'fermer', 'desactiver', 'arreter'],
            Poste::ETAT_VERROUILLE => $aSession
                ? ['debloquer', 'fermer', 'recharger', 'desactiver', 'arreter']
                : ['debloquer', 'desactiver', 'arreter'],
            // Poste injoignable avec une session ouverte : le gérant peut la clôturer à la main
            Poste::ETAT_HORS_LIGNE => $aSession ? ['fermer', 'desactiver'] : ['desactiver'],
            Poste::ETAT_DESACTIVE  => ['activer'],
            default                => [],
        };

        // Recharger n'a de sens que pour un forfait (durée limitée)
        if ($modeSession !== 'forfait') {
            $cles = array_values(array_diff($cles, ['recharger']));
        }

        return array_map(function (string $cle) use ($etat) {
            $def = self::ACTIONS[$cle];
            if ($cle === 'debloquer' && $etat === Poste::ETAT_VERROUILLE) {
                $def['label'] = 'Déverrouiller';
            }
            return ['cle' => $cle] + $def;
        }, $cles);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Clôture la session active (sous verrou) puis coupe Internet.
     * Si le poste est injoignable, la session est facturée jusqu'à son dernier signe de vie.
     */
    private function terminer(Poste $poste, string $motif): ?Session
    {
        $arret = $poste->estEnLigne() ? null : $poste->dernier_heartbeat;

        $session = DB::transaction(function () use ($poste, $motif, $arret) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            return $p !== null ? $this->sessions->cloturer($p, $motif, $arret) : null;
        });

        $this->sessions->deconnecterReseau($poste);   // mode dégradé : n'échoue jamais

        return $session;
    }

    /** Complément de message quand MikroTik n'a pas répondu (false = échec, null/true = rien à signaler). */
    private function avertissementReseau(?bool $reseau, string $verbe = 'activé'): string
    {
        return $reseau === false
            ? " Attention : Internet n'a pas pu être {$verbe} (MikroTik injoignable) — vérifiez le routeur."
            : '';
    }

    private function ok(Request $request, string $message)
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message])
            : back()->with('success', $message);
    }

    private function echec(Request $request, string $message)
    {
        return $request->expectsJson()
            ? response()->json(['ok' => false, 'message' => $message], 422)
            : back()->with('error', $message);
    }
}