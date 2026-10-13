<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use App\Models\Session;
use App\Models\Voucher;
use App\Services\MikroTikService;
use App\Services\TarifService;
use App\Services\WifiMontantService;
use App\Services\WifiSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WifiController extends Controller
{
    public function __construct(
        private MikroTikService $mikroTik,
        private WifiSessionService $wifiSessions,
        private WifiMontantService $montant,
        private TarifService $tarifService,
    ) {}

    // -----------------------------------------------------------------
    // Page principale
    // -----------------------------------------------------------------

    public function index()
    {
        $this->syncSilencieuse();
        return view('wifi.index', ['config' => $this->configFront()]);
    }

    public function etat(): JsonResponse
    {
        $this->syncSilencieuse();

        return response()->json([
            'lignes' => $this->lignesSerialisees(),
            'stats'  => $this->stats(),
            'vouchers_disponibles' => $this->vouchersDisponibles(),
            'vouchers_warning'     => $this->vouchersWarning(),
            'sync'                 => $this->wifiSessions->etatSynchro(),
        ]);
    }

    // -----------------------------------------------------------------
    // Arrêter / Supprimer
    // -----------------------------------------------------------------

    /**
     * "Arrêter" : FIGE la durée et le montant (arrondi appliqué).
     * Le client RESTE connecté au Wi-Fi : aucune déconnexion MikroTik ici.
     * La déconnexion réelle se fait uniquement via "Supprimer".
     */
    public function arreter(Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return response()->json(['ok' => false, 'message' => 'Session non Wi-Fi.'], 422);
        }

        if ($session->estTermine()) {
            return response()->json(['ok' => false, 'message' => 'Déjà terminée.'], 422);
        }

        // Calculé AVANT la mise à jour : la session n'est pas encore figée.
        $final      = $this->montant->montantFinal($session);
        $maintenant = now();

        $maj = [
            'etat'                      => 'terminee',
            'etat_wifi'                 => Session::WIFI_TERMINE,
            'date_heure_fin_reelle'     => $maintenant,
            'montant_consomme'          => $final['final'],
            'montant_total'             => $final['final'],
            'montant_restant'           => 0,
            'alarme_active'             => false,
            'alarme_fin_prevue'         => null,
            'alarme_secondes_restantes' => null,
            'motif_fin'                 => 'arret_manuel',
            'duree_consommee'           => $final['minutes_reelles'],
        ];

        // Intègre l'éventuelle période hors ligne / pause en cours, pour que
        // (fin_reelle - début - hors_ligne - pause) donne exactement la durée figée.
        if ($session->estDeconnecte() && $session->deconnecte_at) {
            $maj['duree_hors_ligne_secondes'] = (int) $session->duree_hors_ligne_secondes
                + max(0, $maintenant->getTimestamp() - $session->deconnecte_at->getTimestamp());
        } elseif ($session->estEnPause() && $session->pause_at) {
            $maj['duree_pause_secondes'] = (int) $session->duree_pause_secondes
                + max(0, $maintenant->getTimestamp() - $session->pause_at->getTimestamp());
        }

        $session->update($maj);

        return response()->json([
            'ok'      => true,
            'message' => "Session arrêtée. Montant à encaisser : "
                . number_format($final['final'], 0, ',', ' ') . " Ar. Le client reste connecté.",
            'final'   => $final,
            'lignes'  => $this->lignesSerialisees(),
            'stats'   => $this->stats(),
        ]);
    }

    /**
     * Suppression définitive : déconnecte, efface le voucher du MikroTik,
     * supprime la session. Autorisé même si connecté (déconnexion auto).
     */
    public function supprimer(Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return response()->json(['ok' => false, 'message' => 'Session non Wi-Fi.'], 422);
        }

        // Voucher protégé → redirection HotSpot (sécurité)
        if ($session->voucher && $session->voucher->protege) {
            return response()->json([
                'ok'       => false,
                'redirect' => route('hotspot.index', ['q' => $session->voucher->username]),
                'message'  => "Cet identifiant est protégé. Supprimez-le depuis HotSpot.",
            ], 403);
        }

        // Déconnecter le client du MikroTik si encore actif
        if ($session->client_mac) {
            try {
                $this->mikroTik->disconnectHotspotActiveByMac($session->client_mac);
            } catch (\Throwable $e) {}
        }

        // Supprimer le voucher temporaire associé
        if ($session->voucher && !$session->voucher->estReutilisable()) {
            try {
                $this->mikroTik->deleteHotspotUser($session->voucher->username);
            } catch (\Throwable $e) {}
            $session->voucher->delete();
        }

        $session->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Appareil et identifiant supprimés.',
            'lignes'  => $this->lignesSerialisees(),
            'stats'   => $this->stats(),
        ]);
    }

    /** Suppression en masse. */
    public function supprimerMasse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $sessions = Session::whereIn('id', $data['ids'])
            ->where('type_session', 'wifi')
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

        return response()->json([
            'ok'      => true,
            'message' => $msg . '.',
            'lignes'  => $this->lignesSerialisees(),
            'stats'   => $this->stats(),
        ]);
    }

    // -----------------------------------------------------------------
    // Alarme — poser, prolonger, annuler, acquitter
    // -----------------------------------------------------------------

    public function definirAlarme(Request $request, Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi') {
            return response()->json(['ok' => false, 'message' => 'Session non Wi-Fi.'], 422);
        }

        $data = $request->validate([
            'type'    => ['required', 'in:duree,montant'],
            'valeur'  => ['required', 'integer', 'min:1', 'max:1000000'],
            'depart'  => ['required', 'in:debut,maintenant'],
        ]);

        $secondesDeja = $session->secondesConsommees();

        if ($data['type'] === 'duree') {
            $minutes = (int) $data['valeur'];
            $montant = null;
        } else {
            $montant = (int) $data['valeur'];
            $tarif = $session->tarif ?: $this->tarifService->tarifWifi();
            $minutes = $tarif
                ? $this->tarifService->calculerDuree($montant, $tarif)
                : (int) floor($montant / 20);
        }

        $secondesTotal = $minutes * 60;
        if ($data['depart'] === 'debut') {
            if ($secondesDeja >= $secondesTotal) {
                return response()->json([
                    'ok' => false,
                    'message' => "Cette limite est déjà dépassée. Choisissez une valeur plus grande ou 'À partir de maintenant'.",
                ], 422);
            }
            $secondesRestantes = $secondesTotal - $secondesDeja;
        } else {
            $secondesRestantes = $secondesTotal;
        }

        $session->update([
            'alarme_active'        => true,
            'alarme_type'          => $data['type'],
            'alarme_duree_minutes' => $minutes,
            'alarme_montant'       => $montant,
            'alarme_fin_prevue'    => now()->addSeconds($secondesRestantes),
            'alarme_declenchee_at' => null,
            'alarme_message'       => null,
            'alarme_historique'    => array_merge($session->alarme_historique ?? [], [[
                'action' => 'pose',
                'type'   => $data['type'],
                'valeur' => (int) $data['valeur'],
                'minutes'=> $minutes,
                'montant'=> $montant,
                'depart' => $data['depart'],
                'at'     => now()->toIso8601String(),
            ]]),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => "Alarme activée : {$minutes} min.",
            'lignes'  => $this->lignesSerialisees(),
            'stats'   => $this->stats(),
        ]);
    }

    public function prolongerAlarme(Request $request, Session $session): JsonResponse
    {
        if ($session->type_session !== 'wifi' || !$session->alarme_active) {
            return response()->json(['ok' => false, 'message' => 'Aucune alarme active.'], 422);
        }

        $data = $request->validate([
            'type'   => ['required', 'in:duree,montant'],
            'valeur' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $tarif = $session->tarif ?: $this->tarifService->tarifWifi();

        if ($data['type'] === 'duree') {
            $minutesAjoutees = (int) $data['valeur'];
            $montantAjoute = $tarif ? $minutesAjoutees * $tarif->montant_par_minute : 0;
        } else {
            $montantAjoute = (int) $data['valeur'];
            $minutesAjoutees = $tarif
                ? $this->tarifService->calculerDuree($montantAjoute, $tarif)
                : (int) floor($montantAjoute / 20);
        }

        $nouvelleDuree = (int) $session->alarme_duree_minutes + $minutesAjoutees;
        $nouveauMontant = $session->alarme_type === 'montant'
            ? (int) $session->alarme_montant + ($data['type'] === 'montant' ? $montantAjoute : 0)
            : null;

        $nouvelleFin = now()->addSeconds(
            max(0, $nouvelleDuree * 60 - $session->secondesConsommees())
        );

        $session->update([
            'alarme_duree_minutes' => $nouvelleDuree,
            'alarme_montant'       => $nouveauMontant,
            'alarme_fin_prevue'    => $nouvelleFin,
            'alarme_declenchee_at' => null,
            'alarme_message'       => null,
            'alarme_historique'    => array_merge($session->alarme_historique ?? [], [[
                'action'           => 'prolongation',
                'type'             => $data['type'],
                'valeur'           => (int) $data['valeur'],
                'minutes_ajoutees' => $minutesAjoutees,
                'at'               => now()->toIso8601String(),
            ]]),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => "Alarme prolongée de {$minutesAjoutees} min.",
            'lignes'  => $this->lignesSerialisees(),
            'stats'   => $this->stats(),
        ]);
    }

    public function annulerAlarme(Session $session): JsonResponse
    {
        $session->update([
            'alarme_active'        => false,
            'alarme_type'          => null,
            'alarme_duree_minutes' => null,
            'alarme_montant'       => null,
            'alarme_fin_prevue'    => null,
            'alarme_declenchee_at' => null,
            'alarme_message'       => null,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Alarme annulée.',
            'lignes'  => $this->lignesSerialisees(),
        ]);
    }

    public function acquitterAlarme(Session $session): JsonResponse
    {
        $session->update([
            'alarme_declenchee_at' => $session->alarme_declenchee_at ?: now(),
            'alarme_message'       => 'acquittee',
        ]);

        return response()->json(['ok' => true]);
    }

    // -----------------------------------------------------------------
    // Comptes HotSpot
    // -----------------------------------------------------------------

    public function comptes()
    {
        try {
            $users = $this->mikroTik->listHotspotUsers();
            $erreur = null;
        } catch (\Throwable $e) {
            $users = [];
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
        try {
            $ok = $this->mikroTik->deleteHotspotUser($username);
            if (!$ok) return back()->withErrors(['global' => "Compte {$username} introuvable."]);
            return back()->with('success', "Compte {$username} supprimé.");
        } catch (\Throwable $e) {
            return back()->withErrors(['global' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Synchro opportuniste : seulement si la dernière a plus de 2 s.
     * Le verrou et la protection anti-chevauchement sont dans le service.
     */
    private function syncSilencieuse(): void
    {
        try {
            $this->wifiSessions->synchroniserSiNecessaire(2);
        } catch (\Throwable $e) {}
    }

    private function configFront(): array
    {
        return [
            'lignesInitiales'     => $this->lignesSerialisees(),
            'statsInitiales'      => $this->stats(),
            'vouchersDisponibles' => $this->vouchersDisponibles(),
            'vouchersWarning'     => $this->vouchersWarning(),
            'alarme'              => $this->alarmeSettings(),
            'sonsParDefaut'       => $this->sonsParDefaut(),
            'urlEtat'             => route('wifi.etat'),
            'urlSession'          => url('/wifi/session'),
            'urlSupprimerMasse'   => route('wifi.session.supprimer-masse'),
            'urlReglagesGet'      => route('wifi.alarme.index'),      // ← GET
            'urlReglagesSave'     => route('wifi.alarme.reglages'),   // ← POST
            'urlUploaderSon'      => route('wifi.alarme.son'),
            'urlChoisirSon'       => route('wifi.alarme.son.choisir'),
            'urlSupprimerSon'     => route('wifi.alarme.son.supprimer'),
            'urlReinitialiser'    => route('wifi.alarme.reinitialiser'),
            'urlHotspot'          => route('hotspot.index'),
            'csrf'                => csrf_token(),
        ];
    }

    private function lignesSerialisees(): array
    {
        $sessions = Session::query()
            ->where('type_session', 'wifi')
            ->whereIn('etat_wifi', [
                Session::WIFI_CONNECTE,
                Session::WIFI_DECONNECTE,
                Session::WIFI_EN_PAUSE,
                Session::WIFI_TERMINE,
            ])
            ->with(['appareilWifi', 'voucher', 'tarif'])
            ->orderByDesc('date_heure_debut')
            ->get();

        return $sessions->map(fn ($s) => $this->serialiser($s))->values()->all();
    }

    private function serialiser(Session $s): array
    {
        $tarif = $s->tarif ?: $this->tarifService->tarifWifi();
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
                'username' => $s->voucher->username,
                'protege'  => $protege,
                'permanent'=> $s->voucher->estReutilisable(),
                'montant'  => (int) $s->voucher->montant,
            ] : null,
            'date_debut'       => $s->date_heure_debut?->toIso8601String(),
            'date_fin_reelle'  => $s->date_heure_fin_reelle?->toIso8601String(),
            'montant_final'    => ($s->etat_wifi === Session::WIFI_TERMINE && $s->motif_fin === 'arret_manuel')
                ? (int) $s->montant_total
                : null,
            'deconnecte_at'    => $s->deconnecte_at?->toIso8601String(),
            'pause_at'         => $s->pause_at?->toIso8601String(),
            'secondes_hors_ligne' => (int) $s->duree_hors_ligne_secondes,
            'secondes_pause'   => (int) $s->duree_pause_secondes,
            'volume_entree'    => (int) $s->volume_entree,
            'volume_sortie'    => (int) $s->volume_sortie,
            'volume_total'     => (int) $s->volume_total,
            'tarif'            => $tarif ? [
                'montant_par_minute' => $tarif->montant_par_minute,
                'montant_minimum'    => $tarif->montant_minimum,
                'arrondi_actif'      => (bool) $tarif->arrondi_actif,
                'unite_arrondi'      => $tarif->unite_arrondi,
                'seuil_arrondi'      => $tarif->seuil_arrondi,
            ] : null,
            'alarme' => [
                'active'      => (bool) $s->alarme_active,
                'type'        => $s->alarme_type,
                'minutes'     => $s->alarme_duree_minutes,
                'montant'     => $s->alarme_montant,
                'fin'         => $s->alarme_fin_prevue?->toIso8601String(),
                'declenchee'  => (bool) $s->alarme_declenchee_at,
                'acquittee'   => ($s->alarme_message === 'acquittee'),
            ],
        ];
    }

    private function stats(): array
    {
        $sessions = Session::where('type_session', 'wifi')
            ->whereIn('etat_wifi', [
                Session::WIFI_CONNECTE,
                Session::WIFI_DECONNECTE,
                Session::WIFI_EN_PAUSE,
                Session::WIFI_TERMINE,
            ])->get();

        return [
            'connectes'    => $sessions->where('etat_wifi', Session::WIFI_CONNECTE)->count(),
            'deconnectes'  => $sessions->where('etat_wifi', Session::WIFI_DECONNECTE)->count(),
            'termines'     => $sessions->where('etat_wifi', Session::WIFI_TERMINE)->count(),
            'volume_total' => (int) $sessions->sum('volume_total'),
        ];
    }

    /**
     * Vouchers disponibles = temporaires, état "disponible", sans session
     * active en cours. On EXCLUT explicitement tout voucher dont une session
     * Wi-Fi est actuellement "en_cours".
     */
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
            ->map(fn($v) => [
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

        if ($n === 0) return "Aucun identifiant disponible. Générez un lot depuis HotSpot.";
        if ($n < 5)   return "Plus que {$n} identifiant(s) disponible(s).";
        return null;
    }

    private function alarmeSettings(): array
    {
        $son = Parametre::get('wifi_alarm_sound', '');

        return [
            'enabled'        => Parametre::get('wifi_alarm_enabled', '1') === '1',
            'volume'         => (int) Parametre::get('wifi_alarm_volume', '70'),
            'duree_secondes' => (int) Parametre::get('wifi_alarm_duration', '0'),
            'taille_max_mo'  => (int) Parametre::get('wifi_alarm_max_size', '10'),
            'son_nom'        => $son ?: null,
            'son_url'        => $son ? '/sounds/' . $son : null,
        ];
    }

    private function sonsParDefaut(): array
    {
        return [
            ['id' => 'bip-classique', 'nom' => 'Bip classique', 'desc' => '3 bips courts', 'defaut' => true],
            ['id' => 'carillon',      'nom' => 'Carillon',      'desc' => 'Mélodie douce'],
            ['id' => 'urgent',        'nom' => 'Urgent',        'desc' => 'Bips rapides'],
        ];
    }
}