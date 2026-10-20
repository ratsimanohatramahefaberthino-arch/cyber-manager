<?php

namespace App\Http\Controllers;

use App\Models\AppareilWifi;
use App\Models\Session;
use App\Models\Tarif;
use App\Models\Voucher;
use App\Services\MikroTikService;
use App\Services\TarifService;
use App\Services\WifiMontantService;
use App\Services\WifiSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WifiController extends Controller
{
    /** Âge max (s) des données avant qu'une requête web déclenche elle-même une synchro. */
    private const SYNC_AGE_MAX = 3;

    private const MESSAGE_ACQUITTEE = 'acquittee';

    private const ETATS_AFFICHES = [
        Session::WIFI_CONNECTE,
        Session::WIFI_DECONNECTE,
        Session::WIFI_EN_PAUSE,
        Session::WIFI_TERMINE,
    ];

    public function __construct(
        private MikroTikService $mikroTik,
        private WifiSessionService $wifiSessions,
        private WifiMontantService $montant,
        private TarifService $tarifService,
    ) {}

    // =================================================================
    // Pages / état temps réel
    // =================================================================

    public function index()
    {
        $this->syncSilencieuse();

        return view('wifi.index', ['config' => $this->configFront()]);
    }

    public function etat(): JsonResponse
    {
        $this->syncSilencieuse();

        return response()->json(array_merge($this->charge(), [
            'vouchers_disponibles' => $this->vouchersDisponibles(),
            'vouchers_warning'     => $this->vouchersWarning(),
            'sync'                 => $this->wifiSessions->etatSynchro(),
        ]));
    }

    // =================================================================
    // Cycle de vie de la session
    // =================================================================

    public function arreter(Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return $this->erreur('Session non Wi-Fi.');
        }

        if ($session->estTermine()) {
            return $this->erreur('Déjà terminée.');
        }

        $final      = $this->montant->montantFinal($session);
        $maintenant = now();

        $maj = [
            'etat'                      => 'terminee',
            'etat_wifi'                 => Session::WIFI_TERMINE,
            'date_heure_fin_reelle'     => $maintenant,
            'montant_consomme'          => $final['final'],
            'montant_total'             => $final['final'],
            'montant_restant'           => 0,
            'motif_fin'                 => 'arret_manuel',
            'duree_consommee'           => $final['minutes_reelles'],
        ] + $this->champsAlarmeVides();

        if ($session->estDeconnecte() && $session->deconnecte_at) {
            $maj['duree_hors_ligne_secondes'] = (int) $session->duree_hors_ligne_secondes
                + max(0, $maintenant->getTimestamp() - $session->deconnecte_at->getTimestamp());
        } elseif ($session->estEnPause() && $session->pause_at) {
            $maj['duree_pause_secondes'] = (int) $session->duree_pause_secondes
                + max(0, $maintenant->getTimestamp() - $session->pause_at->getTimestamp());
        }

        $session->update($maj);

        return $this->reponse([
            'message' => 'Session arrêtée. Montant à encaisser : '
                . number_format($final['final'], 0, ',', ' ') . ' Ar. Le client reste connecté.',
            'final'   => $final,
        ]);
    }

    public function continuer(Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return $this->erreur('Session non Wi-Fi.');
        }

        if (!$session->estTermine()) {
            return $this->erreur('La session n\'est pas terminée.');
        }

        $maintenant = now();

        $ajoutHorsLigne = 0;
        if ($session->date_heure_fin_reelle) {
            $ajoutHorsLigne = max(0, $maintenant->getTimestamp() - $session->date_heure_fin_reelle->getTimestamp());
        }

        $session->update([
            'etat'                      => 'en_cours',
            'etat_wifi'                 => Session::WIFI_CONNECTE,
            'date_heure_fin_reelle'     => null,
            'motif_fin'                 => null,
            'duree_hors_ligne_secondes' => (int) $session->duree_hors_ligne_secondes + $ajoutHorsLigne,
            'montant_restant'           => (int) $session->montant_total,
        ] + $this->champsAlarmeVides());

        return $this->reponse(['message' => 'Session reprise : la durée et le montant reprennent.']);
    }

    public function supprimer(Request $request, Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return $this->erreur('Session non Wi-Fi.');
        }

        if ($session->voucher && $session->voucher->protege) {
            return $this->erreur('Cet identifiant est protégé. Supprimez-le depuis HotSpot.', 403, [
                'redirect' => route('hotspot.index', ['q' => $session->voucher->username]),
            ]);
        }

        $autres = $session->voucher ? $this->sessionsActivesDuMemeVoucher($session) : [];
        $force  = $request->boolean('force');

        if (!empty($autres) && !$force) {
            return $this->erreur('Ce voucher est encore utilisé par d\'autres connexions actives.', 409, [
                'besoin_force' => true,
                'autres'       => $autres,
            ]);
        }

        foreach ($autres as $info) {
            if (!empty($info['mac'])) {
                try { $this->mikroTik->disconnectHotspotActiveByMac($info['mac']); } catch (\Throwable $e) {}
            }
        }

        if ($session->client_mac) {
            try { $this->mikroTik->disconnectHotspotActiveByMac($session->client_mac); } catch (\Throwable $e) {}
        }

        if ($session->voucher && !$session->voucher->estReutilisable()) {
            try { $this->mikroTik->deleteHotspotUser($session->voucher->username); } catch (\Throwable $e) {}
            $session->voucher->delete();
        }

        $session->delete();

        return $this->reponse(['message' => 'Appareil et identifiant supprimés.']);
    }

    public function supprimerMasse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $sessions = Session::whereIn('id', $data['ids'])
            ->where('type_session', 'wifi')
            ->with('voucher')
            ->get();

        $supprimes = 0;
        $proteges  = 0;

        foreach ($sessions as $s) {
            if ($s->voucher && $s->voucher->protege) { $proteges++; continue; }

            if ($s->client_mac) {
                try { $this->mikroTik->disconnectHotspotActiveByMac($s->client_mac); } catch (\Throwable $e) {}
            }
            if ($s->voucher && !$s->voucher->estReutilisable()) {
                try { $this->mikroTik->deleteHotspotUser($s->voucher->username); } catch (\Throwable $e) {}
                $s->voucher->delete();
            }
            $s->delete();
            $supprimes++;
        }

        $msg = "{$supprimes} supprimé(s)";
        if ($proteges > 0) $msg .= ", {$proteges} protégé(s) ignoré(s)";

        return $this->reponse(['message' => $msg . '.']);
    }

    public function enregistrerRemarque(Request $request, Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return $this->erreur('Session non Wi-Fi.');
        }

        $data = $request->validate([
            'remarque' => ['nullable', 'string', 'max:1000'],
        ]);

        $texte = isset($data['remarque']) ? trim((string) $data['remarque']) : null;
        $session->update(['remarque' => $texte !== '' ? $texte : null]);

        return response()->json([
            'ok'       => true,
            'remarque' => $session->remarque,
            'message'  => 'Remarque enregistrée.',
        ]);
    }

    // =================================================================
    // Alarmes
    //
    // Source de vérité côté serveur :
    //   alarme_active = false                       → aucune alarme
    //   alarme_active + fin_prevue (ou secondes     → décompte (ou "en pause" si l'appareil
    //                restantes) dans le futur         est hors ligne : fin_prevue = null)
    //   alarme_active + temps écoulé                → SONNE
    //   alarme_active + alarme_message = acquittee  → SILENCIEUSE (son coupé, alarme conservée)
    //
    // Poser / prolonger remettent toujours alarme_message à null : on repart en décompte.
    // =================================================================

    public function definirAlarme(Request $request, Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return $this->erreur('Session non Wi-Fi.');
        }
        if ($session->estTermine()) {
            return $this->erreur('Session terminée : impossible de poser une alarme.');
        }

        $data = $request->validate([
            'type'   => ['required', 'in:duree,montant'],
            'valeur' => ['required', 'integer', 'min:1', 'max:1000000'],
            'depart' => ['nullable', 'in:debut,maintenant'],
        ]);

        $depart = $data['depart'] ?? 'debut';

        try {
            [$minutes, $montant] = $this->convertir($session, $data['type'], (int) $data['valeur']);
        } catch (InvalidArgumentException $e) {
            return $this->erreur($e->getMessage());
        }

        if ($minutes < 1) {
            return $this->erreur('Valeur trop faible : la durée obtenue est inférieure à 1 minute.');
        }

        $totalSec = $minutes * 60;
        $restant  = $depart === 'debut'
            ? $totalSec - $session->secondesConsommees()
            : $totalSec;

        if ($restant <= 0) {
            return $this->erreur("Cette limite est déjà dépassée. Choisissez une valeur plus grande ou « À partir de maintenant ».");
        }

        $connecte = $session->estConnecte();
        $fin      = $connecte ? now()->addSeconds($restant) : null;

        $session->update([
            'alarme_active'             => true,
            'alarme_type'               => $data['type'],
            'alarme_duree_minutes'      => $minutes,
            'alarme_montant'            => $montant,
            'alarme_total_secondes'     => $totalSec,
            'alarme_fin_prevue'         => $fin,
            'alarme_derniere_fin_at'    => $fin,
            'alarme_secondes_restantes' => $connecte ? null : $restant,
            'alarme_declenchee_at'      => null,
            'alarme_message'            => null,
            'alarme_historique'         => $this->historique($session, [
                'action'  => 'pose',
                'type'    => $data['type'],
                'valeur'  => (int) $data['valeur'],
                'minutes' => $minutes,
                'montant' => $montant,
                'depart'  => $depart,
            ]),
        ]);

        return $this->reponse([
            'message' => "Alarme activée : {$minutes} min."
                . ($connecte ? '' : ' Elle démarrera à la reconnexion de l\'appareil.'),
        ]);
    }

    public function prolongerAlarme(Request $request, Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi' || !$session->alarme_active) {
            return $this->erreur('Aucune alarme active.');
        }
        if ($session->estTermine()) {
            return $this->erreur('Session terminée.');
        }

        // "debut" n'existe plus pour la prolongation : trop ambigu pour l'opérateur.
        $data = $request->validate([
            'type'   => ['required', 'in:duree,montant'],
            'valeur' => ['required', 'integer', 'min:1', 'max:1000000'],
            'depart' => ['nullable', 'in:maintenant,apres'],
        ]);

        $mode = $data['depart'] ?? 'apres';

        try {
            [$minutesAjoutees, $montantAjoute] = $this->convertir($session, $data['type'], (int) $data['valeur']);
        } catch (InvalidArgumentException $e) {
            return $this->erreur($e->getMessage());
        }

        if ($minutesAjoutees < 1) {
            return $this->erreur('Valeur trop faible : la durée obtenue est inférieure à 1 minute.');
        }

        $maintenant = now();
        $ajoutSec   = $minutesAjoutees * 60;
        $restant    = $this->secondesRestantesAlarme($session, $maintenant);   // négatif = dépassement

        // "À la suite" n'a de sens que si l'alarme n'a pas encore expiré ;
        // sinon le nouveau délai démarre maintenant.
        if ($mode === 'apres' && $restant > 0) {
            $nouveauRestant = $restant + $ajoutSec;
            $nouveauTotal   = $this->totalAlarmeSecondes($session) + $ajoutSec;
        } else {
            $nouveauRestant = $ajoutSec;
            $nouveauTotal   = $ajoutSec;
        }

        $nouvelleDuree = max(1, (int) ceil($nouveauTotal / 60));
        $connecte      = $session->estConnecte();
        $fin           = $connecte ? $maintenant->copy()->addSeconds($nouveauRestant) : null;

        $tarif = $session->tarif ?: $this->tarifService->tarifWifi();
        $nouveauMontant = null;
        if ($session->alarme_type === 'montant') {
            $nouveauMontant = $tarif
                ? $this->tarifService->montantPourMinutes($nouvelleDuree, $tarif)
                : $session->alarme_montant;
        }

        $session->update([
            'alarme_duree_minutes'      => $nouvelleDuree,
            'alarme_montant'            => $nouveauMontant,
            'alarme_total_secondes'     => $nouveauTotal,
            'alarme_fin_prevue'         => $fin,
            'alarme_derniere_fin_at'    => $fin,
            'alarme_secondes_restantes' => $connecte ? null : $nouveauRestant,
            'alarme_declenchee_at'      => null,
            'alarme_message'            => null,       // ← retour au décompte
            'alarme_historique'         => $this->historique($session, [
                'action'           => 'prolongation',
                'mode'             => $mode,
                'type'             => $data['type'],
                'valeur'           => (int) $data['valeur'],
                'minutes_ajoutees' => $minutesAjoutees,
                'montant_ajoute'   => $montantAjoute,
                'nouvelle_fin'     => $fin?->toIso8601String(),
            ]),
        ]);

        return $this->reponse(['message' => "Alarme prolongée de {$minutesAjoutees} min."]);
    }

    public function annulerAlarme(Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return $this->erreur('Session non Wi-Fi.');
        }

        $session->update($this->champsAlarmeVides() + [
            'alarme_historique' => $this->historique($session, ['action' => 'annulation']),
        ]);

        return $this->reponse(['message' => 'Alarme annulée.']);
    }

    /** "Arrêter l'alarme" : coupe le son et ferme la fenêtre, mais l'alarme reste active (silencieuse). */
    public function acquitterAlarme(Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi' || !$session->alarme_active) {
            return $this->erreur('Aucune alarme active.');
        }

        $session->update([
            'alarme_declenchee_at' => $session->alarme_declenchee_at ?: now(),
            'alarme_message'       => self::MESSAGE_ACQUITTEE,
            'alarme_historique'    => $this->historique($session, ['action' => 'acquittement']),
        ]);

        return $this->reponse();
    }

    /**
     * Conservé pour compatibilité avec la route existante.
     * L'interface n'en a plus besoin (l'état "sonne" est déduit de l'heure de fin),
     * on se contente d'horodater le déclenchement si l'heure est bien passée.
     */
    public function marquerAlarmeDeclenchee(Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi' || !$session->alarme_active) {
            return $this->erreur('Aucune alarme active.');
        }

        if (!$session->alarme_declenchee_at
            && $session->alarme_fin_prevue
            && $session->alarme_fin_prevue->lte(now())) {
            $session->update(['alarme_declenchee_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    // =================================================================
    // Comptes HotSpot MikroTik (page secondaire)
    // =================================================================

    public function comptes()
    {
        try {
            $users  = $this->mikroTik->listHotspotUsers();
            $erreur = null;
        } catch (\Throwable $e) {
            $users  = [];
            $erreur = $e->getMessage();
        }

        $prefix = config('mikrotik.hotspot_user_prefix', 'cm-');

        $comptes = collect($users)->map(function ($u) use ($prefix) {
            $u['est_cyber_manager'] = str_starts_with($u['name'] ?? '', $prefix);
            return $u;
        })->sortBy([
            fn ($a, $b) => ($b['est_cyber_manager'] ?? false) <=> ($a['est_cyber_manager'] ?? false),
            fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''),
        ])->values();

        return view('wifi.comptes', compact('comptes', 'erreur'));
    }

    public function syncNow()
    {
        $r = $this->wifiSessions->synchroniser();

        if ($r['erreur']) {
            return back()->withErrors(['global' => 'MikroTik injoignable : ' . $r['erreur']]);
        }

        if (!empty($r['ignoree'])) {
            return back()->with('success', 'Une synchronisation est déjà en cours.');
        }

        return back()->with('success', "Sync : {$r['ouvertes']} ouverte(s), {$r['maj']} mise(s) à jour, {$r['fermees']} déconnectée(s).");
    }

    public function deconnecter(Request $request)
    {
        $mac = (string) $request->input('mac');
        if (!$mac) return back()->withErrors(['global' => 'MAC manquante.']);

        try {
            $ok = $this->mikroTik->disconnectHotspotActiveByMac($mac);
        } catch (\Throwable $e) {
            return back()->withErrors(['global' => $e->getMessage()]);
        }

        if (!$ok) return back()->withErrors(['global' => "Aucune session active avec la MAC {$mac}."]);
        return back()->with('success', 'Client déconnecté.');
    }

    public function toggleCompte(string $username)
    {
        try {
            $user = $this->mikroTik->findHotspotUser($username);
            if (!$user) return back()->withErrors(['global' => "Compte {$username} introuvable."]);

            $disabled = ($user['disabled'] ?? 'false') === 'true';
            $this->mikroTik->setHotspotUserDisabled($username, !$disabled);

            return back()->with('success', "Compte {$username} " . (!$disabled ? 'désactivé' : 'réactivé') . '.');
        } catch (\Throwable $e) {
            return back()->withErrors(['global' => $e->getMessage()]);
        }
    }

    public function supprimerCompte(string $username)
    {
        // Cohérence avec HotSpot : un identifiant protégé ne se supprime pas d'un clic.
        $voucher = Voucher::where('username', $username)->first();
        if ($voucher && $voucher->protege) {
            return back()->withErrors(['global' => "Le compte {$username} est protégé. Déprotégez-le depuis HotSpot d'abord."]);
        }

        try {
            $ok = $this->mikroTik->deleteHotspotUser($username);
            if (!$ok) return back()->withErrors(['global' => "Compte {$username} introuvable."]);
            return back()->with('success', "Compte {$username} supprimé.");
        } catch (\Throwable $e) {
            return back()->withErrors(['global' => $e->getMessage()]);
        }
    }

    // =================================================================
    // Helpers : réponses
    // =================================================================

    private function erreur(string $message, int $code = 422, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['ok' => false, 'message' => $message], $extra), $code);
    }

    /** Réponse standard des actions : message + tableau et stats à jour + heure serveur. */
    private function reponse(array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['ok' => true], $extra, $this->charge()));
    }

    private function syncSilencieuse(): void
    {
        try {
            $this->wifiSessions->synchroniserSiNecessaire(self::SYNC_AGE_MAX);
        } catch (\Throwable $e) {}
    }

    // =================================================================
    // Helpers : alarmes
    // =================================================================

    /** Valeurs qui remettent l'alarme à zéro. */
    private function champsAlarmeVides(): array
    {
        return [
            'alarme_active'             => false,
            'alarme_type'               => null,
            'alarme_duree_minutes'      => null,
            'alarme_montant'            => null,
            'alarme_total_secondes'     => null,
            'alarme_fin_prevue'         => null,
            'alarme_derniere_fin_at'    => null,
            'alarme_secondes_restantes' => null,
            'alarme_declenchee_at'      => null,
            'alarme_message'            => null,
        ];
    }

    /** @return array{0:int,1:?int} [minutes, montant] */
    private function convertir(Session $session, string $type, int $valeur): array
    {
        $tarif = $session->tarif ?: $this->tarifService->tarifWifi();

        if ($type === 'duree') {
            return [$valeur, null];
        }

        if (!$tarif) {
            throw new InvalidArgumentException('Aucun tarif Wi-Fi configuré : l\'alarme par montant est impossible.');
        }

        return [$this->tarifService->calculerDuree($valeur, $tarif), $valeur];
    }

    /** Secondes restantes avant la sonnerie. Négatif = déjà dépassé. */
    private function secondesRestantesAlarme(Session $s, $maintenant): int
    {
        if ($s->alarme_fin_prevue) {
            return $s->alarme_fin_prevue->getTimestamp() - $maintenant->getTimestamp();
        }

        if ($s->alarme_secondes_restantes !== null) {
            return (int) $s->alarme_secondes_restantes;
        }

        return 0;
    }

    private function totalAlarmeSecondes(Session $s): int
    {
        return (int) ($s->alarme_total_secondes ?: ((int) $s->alarme_duree_minutes * 60));
    }

    /** Ajoute une entrée à l'historique en bornant sa taille (50 dernières). */
    private function historique(Session $s, array $entree): array
    {
        $h   = $s->alarme_historique ?? [];
        $h[] = $entree + ['at' => now()->toIso8601String()];

        return array_slice($h, -50);
    }

    // =================================================================
    // Helpers : données du tableau
    // =================================================================

    private function sessionsActivesDuMemeVoucher(Session $session): array
    {
        if (!$session->voucher) return [];

        $username = $session->voucher->username;
        $macSelf  = AppareilWifi::normaliserMac($session->client_mac);
        $resultat = [];

        try {
            foreach ($this->mikroTik->listHotspotActive() as $a) {
                if (($a['user'] ?? null) !== $username) continue;

                $mac = AppareilWifi::normaliserMac($a['mac-address'] ?? null);
                if ($mac && $macSelf && $mac === $macSelf) continue;

                $resultat[] = ['mac' => $mac, 'appareil' => $a['mac-address'] ?? null];
            }
            return array_values($resultat);
        } catch (\Throwable $e) {
            $locales = Session::where('type_session', 'wifi')
                ->where('voucher_id', $session->voucher_id)
                ->where('id', '!=', $session->id)
                ->whereIn('etat_wifi', [Session::WIFI_CONNECTE, Session::WIFI_EN_PAUSE])
                ->get(['client_mac', 'appareil_wifi_id']);

            foreach ($locales as $s) {
                $mac = AppareilWifi::normaliserMac($s->client_mac);
                if ($mac && $macSelf && $mac === $macSelf) continue;
                $resultat[] = ['mac' => $mac, 'appareil' => null];
            }
            return array_values($resultat);
        }
    }

    private function configFront(): array
    {
        $charge = $this->charge();

        return [
            'lignesInitiales'     => $charge['lignes'],
            'statsInitiales'      => $charge['stats'],
            'serveurMs'           => $charge['serveur_ms'],
            'vouchersDisponibles' => $this->vouchersDisponibles(),
            'vouchersWarning'     => $this->vouchersWarning(),
            'alarme'              => WifiAlarmController::reglagesActuels(),
            'sonsParDefaut'       => $this->sonsParDefaut(),
            'urlEtat'             => route('wifi.etat'),
            'urlSession'          => url('/wifi/session'),
            'urlSupprimerMasse'   => route('wifi.session.supprimer-masse'),
            'urlReglagesGet'      => route('wifi.alarme.index'),
            'urlReglagesSave'     => route('wifi.alarme.reglages'),
            'urlUploaderSon'      => route('wifi.alarme.son'),
            'urlChoisirSon'       => route('wifi.alarme.son.choisir'),
            'urlSupprimerSon'     => route('wifi.alarme.son.supprimer'),
            'urlReinitialiser'    => route('wifi.alarme.reinitialiser'),
            'urlHotspot'          => route('hotspot.index'),
        ];
    }

    /** Une seule requête SQL (+ relations préchargées) alimente le tableau ET les KPI. */
    private function charge(): array
    {
        $sessions = Session::query()
            ->where('type_session', 'wifi')
            ->whereIn('etat_wifi', self::ETATS_AFFICHES)
            ->with(['appareilWifi', 'voucher', 'tarif'])
            ->orderByDesc('date_heure_debut')
            ->get();

        return [
            'lignes'     => $this->lignesSerialisees($sessions),
            'stats'      => $this->stats($sessions),
            'serveur_ms' => (int) floor(microtime(true) * 1000),
        ];
    }

    private function lignesSerialisees(Collection $sessions): array
    {
        $tarifDefaut = $this->tarifService->tarifWifi();   // 1 requête, pas une par ligne
        $macsActives = $this->wifiSessions->macsEncoreActives();

        $macsOuvertes = $sessions
            ->filter(fn ($s) => $s->etat_wifi !== Session::WIFI_TERMINE)
            ->map(fn ($s) => AppareilWifi::normaliserMac($s->client_mac))
            ->filter()
            ->unique()
            ->all();

        $dejaMarquees = [];
        $lignes = [];

        foreach ($sessions as $s) {
            $badge = false;

            if ($macsActives !== null && $s->etat_wifi === Session::WIFI_TERMINE) {
                $mac = AppareilWifi::normaliserMac($s->client_mac);

                if ($mac
                    && in_array($mac, $macsActives, true)
                    && !in_array($mac, $macsOuvertes, true)
                    && !isset($dejaMarquees[$mac])) {
                    $badge = true;
                    $dejaMarquees[$mac] = true;
                }
            }

            $lignes[] = $this->serialiser($s, $tarifDefaut, $badge);
        }

        return $lignes;
    }

    private function serialiser(Session $s, ?Tarif $tarifDefaut, bool $clientEncoreActif = false): array
    {
        $tarif   = $s->tarif ?: $tarifDefaut;
        $protege = (bool) ($s->voucher?->protege ?? false);

        $etatAppareil = match ($s->etat_wifi) {
            Session::WIFI_CONNECTE   => 'connecte',
            Session::WIFI_DECONNECTE => 'deconnecte',
            Session::WIFI_EN_PAUSE   => 'en_pause',
            Session::WIFI_TERMINE    => 'termine',
            default                  => 'inconnu',
        };

        return [
            'id'               => $s->id,
            'etat_wifi'        => $s->etat_wifi,
            'etat_appareil'    => $etatAppareil,
            'appareil'         => $s->appareilWifi?->libelle() ?? 'Appareil inconnu',
            'appareil_type'    => $s->appareilWifi?->type_appareil ?? 'inconnu',
            'mac'              => $s->client_mac,
            'ip'               => $s->client_ip,
            'ssid'             => $s->ssid,
            'voucher'          => $s->voucher ? [
                'username'  => $s->voucher->username,
                'protege'   => $protege,
                'permanent' => $s->voucher->estReutilisable(),
                'montant'   => (int) $s->voucher->montant,
            ] : null,
            'date_debut'       => $s->date_heure_debut?->toIso8601String(),
            'date_fin_reelle'  => $s->date_heure_fin_reelle?->toIso8601String(),
            'montant_final'    => ($s->etat_wifi === Session::WIFI_TERMINE && $s->motif_fin === 'arret_manuel')
                ? (int) $s->montant_total
                : null,
            'client_encore_actif' => $clientEncoreActif,
            'remarque'         => $s->remarque,
            'deconnecte_at'    => $s->deconnecte_at?->toIso8601String(),
            'pause_at'         => $s->pause_at?->toIso8601String(),
            'secondes_hors_ligne' => (int) $s->duree_hors_ligne_secondes,
            'secondes_pause'   => (int) $s->duree_pause_secondes,
            'volume_entree'    => (int) $s->volume_entree,
            'volume_sortie'    => (int) $s->volume_sortie,
            'volume_total'     => (int) $s->volume_total,
            'tarif'            => $tarif ? [
                'montant_par_minute' => (int) $tarif->montant_par_minute,
                'montant_minimum'    => (int) $tarif->montant_minimum,
                'arrondi_actif'      => (bool) $tarif->arrondi_actif,
                'unite_arrondi'      => (int) $tarif->unite_arrondi,
                'seuil_arrondi'      => (int) $tarif->seuil_arrondi,
            ] : null,
            'alarme'           => [
                'active'             => (bool) $s->alarme_active,
                'type'               => $s->alarme_type,
                'minutes'            => $s->alarme_duree_minutes,
                'montant'            => $s->alarme_montant,
                'total_secondes'     => $s->alarme_active ? $this->totalAlarmeSecondes($s) : null,
                'fin'                => $s->alarme_fin_prevue?->toIso8601String(),
                'secondes_restantes' => $s->alarme_secondes_restantes,
                'acquittee'          => $s->alarme_message === self::MESSAGE_ACQUITTEE,
            ],
        ];
    }

    private function stats(Collection $sessions): array
    {
        $parEtat = $sessions->countBy('etat_wifi');

        return [
            'connectes'    => (int) ($parEtat[Session::WIFI_CONNECTE] ?? 0),
            'deconnectes'  => (int) ($parEtat[Session::WIFI_DECONNECTE] ?? 0),
            'termines'     => (int) ($parEtat[Session::WIFI_TERMINE] ?? 0),
            'volume_total' => (int) $sessions->sum('volume_total'),
        ];
    }

    private function vouchersDisponibles(): array
    {
        $usernamesActifs = Session::where('type_session', 'wifi')
            ->where('etat_wifi', Session::WIFI_CONNECTE)
            ->whereNotNull('hotspot_username')
            ->pluck('hotspot_username')
            ->all();

        $query = Voucher::query()
            ->where('etat', 'disponible')
            ->where('type_voucher', Voucher::TYPE_TEMPORAIRE)
            ->where('source', 'cyber_manager');

        if (!empty($usernamesActifs)) {
            $query->whereNotIn('username', $usernamesActifs);
        }

        return $query->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn ($v) => [
                'id'       => $v->id,
                'username' => $v->username,
                'password' => $v->password,
            ])->values()->all();
    }

    private function vouchersWarning(): ?string
    {
        $n = Voucher::query()
            ->where('etat', 'disponible')
            ->where('type_voucher', Voucher::TYPE_TEMPORAIRE)
            ->where('source', 'cyber_manager')
            ->count();

        if ($n === 0) return 'Aucun identifiant disponible. Générez un lot depuis HotSpot.';
        if ($n < 5)   return "Plus que {$n} identifiant(s) disponible(s).";
        return null;
    }

    private function sonsParDefaut(): array
    {
        return [
            ['id' => 'bip-classique', 'nom' => 'Bip classique', 'desc' => '3 bips courts'],
            ['id' => 'carillon',      'nom' => 'Carillon',      'desc' => 'Mélodie douce'],
            ['id' => 'urgent',        'nom' => 'Urgent',        'desc' => 'Bips rapides'],
        ];
    }
}