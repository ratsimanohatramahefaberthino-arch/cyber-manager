<?php

namespace App\Services;

use App\Models\AppareilWifi;
use App\Models\Session;
use App\Models\Voucher;
use Illuminate\Support\Facades\Log;

/**
 * Synchronise les sessions HotSpot actives du MikroTik avec la base.
 *
 * Ne modifie JAMAIS le MikroTik. Lecture seule côté réseau.
 * Le MikroTik reste l'autorité pour savoir "qui est connecté".
 *
 * Cycle de vie d'un voucher piloté ici :
 *  - session ouverte  : disponible/utilise → en_cours
 *  - session fermée   : temporaire → utilise (ou expire si temps épuisé)
 *                       permanent  → disponible (réutilisable)
 */
class WifiSessionService
{
    /** @var array<string, array<string,string>>|null  MAC => bail DHCP (cache par sync) */
    private ?array $baux = null;

    /** @var array<string, array<string,string>>|null  MAC => host HotSpot (cache par sync) */
    private ?array $hotes = null;

    public function __construct(
        private MikroTikService $mikroTik,
    ) {
    }

    /**
     * Point d'entrée principal.
     *
     * @return array{ouvertes:int, maj:int, fermees:int, erreur:?string}
     */
    public function synchroniser(): array
    {
        // Les caches ne vivent que le temps d'une synchronisation
        $this->baux  = null;
        $this->hotes = null;

        try {
            $actives = $this->mikroTik->listHotspotActive();
        } catch (\Throwable $e) {
            Log::warning('Sync sessions Wi-Fi : MikroTik injoignable', [
                'error' => $e->getMessage(),
            ]);
            return ['ouvertes' => 0, 'maj' => 0, 'fermees' => 0, 'erreur' => $e->getMessage()];
        }

        $ouvertes = 0;
        $maj      = 0;

        foreach ($actives as $active) {
            try {
                $result = $this->traiterActive($active);
            } catch (\Throwable $e) {
                // Une entrée défectueuse ne doit pas bloquer les autres
                Log::warning('Sync sessions Wi-Fi : entrée ignorée', [
                    'active_id' => $active['.id'] ?? null,
                    'user'      => $active['user'] ?? null,
                    'error'     => $e->getMessage(),
                ]);
                continue;
            }

            if ($result === 'ouverte') {
                $ouvertes++;
            } elseif ($result === 'maj') {
                $maj++;
            }
        }

        // Fermer les sessions Wi-Fi qui ne sont plus actives côté MikroTik
        $fermees = $this->fermerSessionsDisparues($actives);

        return [
            'ouvertes' => $ouvertes,
            'maj'      => $maj,
            'fermees'  => $fermees,
            'erreur'   => null,
        ];
    }

    /**
     * Traite une session active MikroTik :
     *  - déjà en base (par mikrotik_active_id) → mise à jour
     *  - sinon → création
     *
     * @return 'ouverte'|'maj'|null
     */
    private function traiterActive(array $active): ?string
    {
        $activeId = $active['.id'] ?? null;
        if ($activeId === null) {
            return null;
        }

        $session = Session::where('mikrotik_active_id', $activeId)
            ->where('etat', 'en_cours')
            ->first();

        if ($session !== null) {
            $this->mettreAJour($session, $active);
            return 'maj';
        }

        $this->creer($active);
        return 'ouverte';
    }

    private function creer(array $active): Session
    {
        $username = $active['user'] ?? null;
        $voucher  = $username
            ? Voucher::where('username', $username)->first()
            : null;

        $appareil = $this->resoudreAppareil($active);
        $ssid     = $this->resoudreSsid($active);

        $uptimeSecondes = $this->parseUptime($active['uptime'] ?? '0s');
        $uptimeMinutes  = (int) floor($uptimeSecondes / 60);

        // Si on a un voucher, on prend ses valeurs ; sinon, valeurs neutres
        // (durée 0 = illimité, cas des vouchers permanents)
        $dureeTotale  = $voucher?->duree    ?? 0;
        $montantTotal = $voucher?->montant  ?? 0;

        $debut = now()->subSeconds($uptimeSecondes);
        $finPrevue = $dureeTotale > 0
            ? $debut->copy()->addMinutes($dureeTotale)
            : null;

        $session = Session::create(array_merge([
            'poste_id'              => null,
            'voucher_id'            => $voucher?->id,
            'type_session'          => 'wifi',
            'date_heure_debut'      => $debut,
            'date_heure_fin_prevue' => $finPrevue,
            'duree_prevue'          => $dureeTotale,
            'duree_consommee'       => $uptimeMinutes,
            'temps_restant'         => max(0, $dureeTotale - $uptimeMinutes),
            'duree_suspension'      => 0,
            'montant_initial'       => $montantTotal,
            'montant_recharge'      => 0,
            'montant_total'         => $montantTotal,
            'montant_consomme'      => 0,
            'montant_restant'       => $montantTotal,
            'montant_a_reverser'    => 0,
            'etat'                  => 'en_cours',
            'mikrotik_active_id'    => $active['.id'] ?? null,
            'hotspot_username'      => $username,
            'client_mac'            => $active['mac-address'] ?? null,
            'client_ip'             => $active['address']     ?? null,
            'ssid'                  => $ssid,
            'appareil_wifi_id'      => $appareil?->id,
            'derniere_sync_at'      => now(),
        ], $this->calculerVolumes($active)));

        $this->marquerVoucherEnCours($voucher, $appareil);

        return $session;
    }

    private function mettreAJour(Session $session, array $active): void
    {
        $uptimeSecondes = $this->parseUptime($active['uptime'] ?? '0s');
        $uptimeMinutes  = (int) floor($uptimeSecondes / 60);

        $dureePrevue  = (int) $session->duree_prevue;
        $tempsRestant = max(0, $dureePrevue - $uptimeMinutes);

        $appareil = $this->resoudreAppareil($active);

        $donnees = [
            'duree_consommee'  => $uptimeMinutes,
            'temps_restant'    => $tempsRestant,
            'client_ip'        => $active['address']     ?? $session->client_ip,
            'client_mac'       => $active['mac-address'] ?? $session->client_mac,
            'derniere_sync_at' => now(),
        ];

        if ($appareil !== null) {
            $donnees['appareil_wifi_id'] = $appareil->id;
        }

        // Le SSID n'est résolu qu'une fois (évite des appels inutiles)
        if (empty($session->ssid)) {
            $ssid = $this->resoudreSsid($active);
            if ($ssid !== null) {
                $donnees['ssid'] = $ssid;
            }
        }

        $session->update(array_merge($donnees, $this->calculerVolumes($active)));

        // Auto-réparation : voucher d'une session déjà ouverte avant cette étape
        $this->marquerVoucherEnCours($session->voucher, $appareil);
    }

    /**
     * Ferme les sessions Wi-Fi en_cours dont le .id n'apparaît plus
     * dans la liste active du MikroTik (déconnexion ou expiration).
     */
    private function fermerSessionsDisparues(array $actives): int
    {
        $idsActifs = array_filter(array_map(
            fn($a) => $a['.id'] ?? null,
            $actives
        ));

        $sessions = Session::where('type_session', 'wifi')
            ->where('etat', 'en_cours')
            ->whereNotNull('mikrotik_active_id')
            ->whereNotIn('mikrotik_active_id', $idsActifs)
            ->get();

        $compteur = 0;

        foreach ($sessions as $session) {
            // Durée 0 = illimité (permanent, ou compte sans voucher) : jamais "expirée"
            $illimitee = (int) $session->duree_prevue <= 0;
            $expiree   = !$illimitee && (int) $session->temps_restant <= 0;

            $session->update([
                'date_heure_fin_reelle' => now(),
                'etat'                  => $expiree ? 'expiree' : 'terminee',
                'motif_fin'             => $expiree ? 'expiration' : 'deconnexion_client',
                'derniere_sync_at'      => now(),
            ]);

            $this->relacherVoucher($session, $expiree);

            $compteur++;
        }

        return $compteur;
    }

    // -----------------------------------------------------------------
    // Vouchers
    // -----------------------------------------------------------------

       // -----------------------------------------------------------------
    // Vouchers — la machine à états vit dans App\Models\Voucher
    // -----------------------------------------------------------------

    /**
     * Ouverture / poursuite d'une session : le voucher passe à "en_cours"
     * et l'appareil lui est rattaché (règles détaillées dans Voucher).
     * Sans effet si le voucher est désactivé, expiré ou annulé.
     */
    private function marquerVoucherEnCours(?Voucher $voucher, ?AppareilWifi $appareil): void
    {
        $voucher?->marquerEnCours($appareil);
    }

    /**
     * Fermeture d'une session :
     *  - réutilisable  → disponible
     *  - usage unique → utilise
     * Un identifiant désormais supprimé ou déjà changé d'état n'est pas touché.
     */
    private function relacherVoucher(Session $session, bool $expiree): void
    {
        if (!$session->voucher_id) {
            return;
        }

        $voucher = Voucher::find($session->voucher_id);

        if ($voucher === null || $voucher->etat !== 'en_cours') {
            return;
        }

        // Un autre appareil utilise encore ce même identifiant réutilisable
        $autreSession = Session::where('voucher_id', $voucher->id)
            ->where('etat', 'en_cours')
            ->where('id', '!=', $session->id)
            ->exists();

        if ($autreSession) {
            return;
        }

        $voucher->estReutilisable() ? $voucher->marquerDisponible() : $voucher->marquerUtilise();
    }

    // -----------------------------------------------------------------
    // Appareil, SSID, volumes
    // -----------------------------------------------------------------

    /**
     * Cherche ou crée l'appareil Wi-Fi à partir de la MAC du client
     * et met à jour sa dernière connexion.
     */
    private function resoudreAppareil(array $active): ?AppareilWifi
    {
        $mac = AppareilWifi::normaliserMac($active['mac-address'] ?? null);

        if ($mac === null) {
            return null;
        }

        $bail = $this->bailPourMac($mac);

        $appareil = AppareilWifi::trouverOuCreerParMac($mac, [
            'adresse_ip' => $active['address'] ?? ($bail['address'] ?? null),
            'host_name'  => $bail['host-name'] ?? null,
        ]);

        // À la création, les dates sont déjà posées
        if (!$appareil->wasRecentlyCreated) {
            $appareil->toucherDerniereConnexion();
        }

        return $appareil;
    }

    /**
     * SSID = table de correspondance (config) appliquée au serveur HotSpot.
     * Le serveur vient de active['server'] ou, à défaut, de /ip/hotspot/host.
     */
    private function resoudreSsid(array $active): ?string
    {
        $serveur = $active['server'] ?? null;

        if ($serveur === null || $serveur === '') {
            $mac     = AppareilWifi::normaliserMac($active['mac-address'] ?? null);
            $serveur = $mac !== null ? ($this->hotePourMac($mac)['server'] ?? null) : null;
        }

        if ($serveur === null || $serveur === '') {
            $defaut = config('mikrotik.ssid_default');
            return ($defaut !== null && $defaut !== '') ? mb_substr($defaut, 0, 64) : null;
        }

        $table = config('mikrotik.ssid_map', []);

        return mb_substr($table[$serveur] ?? $serveur, 0, 64);
    }

    /**
     * Volumes vus DEPUIS LE CLIENT :
     *  - volume_entree = données reçues par le client (download)
     *                  = "bytes-out" RouterOS (le routeur les envoie au client)
     *  - volume_sortie = données envoyées par le client (upload)
     *                  = "bytes-in" RouterOS (le routeur les reçoit du client)
     *
     * @return array{volume_entree:int, volume_sortie:int, volume_total:int}
     */
    private function calculerVolumes(array $active): array
    {
        $bytesIn  = (int) ($active['bytes-in']  ?? 0);
        $bytesOut = (int) ($active['bytes-out'] ?? 0);

        return [
            'volume_entree' => $bytesOut,
            'volume_sortie' => $bytesIn,
            'volume_total'  => $bytesIn + $bytesOut,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function bailPourMac(string $mac): array
    {
        if ($this->baux === null) {
            $this->baux = [];

            try {
                foreach ($this->mikroTik->listDhcpLeases() as $bail) {
                    $m = AppareilWifi::normaliserMac($bail['mac-address'] ?? null);
                    if ($m !== null) {
                        $this->baux[$m] = $bail;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Sync Wi-Fi : lecture des baux DHCP impossible', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->baux[$mac] ?? [];
    }

    /**
     * @return array<string,string>
     */
    private function hotePourMac(string $mac): array
    {
        if ($this->hotes === null) {
            $this->hotes = [];

            try {
                foreach ($this->mikroTik->listHotspotHosts() as $hote) {
                    $m = AppareilWifi::normaliserMac($hote['mac-address'] ?? null);
                    if ($m !== null) {
                        $this->hotes[$m] = $hote;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Sync Wi-Fi : lecture des hosts HotSpot impossible', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->hotes[$mac] ?? [];
    }

    /**
     * Convertit un uptime RouterOS ("1w2d3h4m5s", "1h20m15s", "12s")
     * en secondes. Les semaines ("w") existent en RouterOS 6 et 7.
     */
    private function parseUptime(string $uptime): int
    {
        $total = 0;

        if (preg_match('/(\d+)w/', $uptime, $m)) { $total += (int)$m[1] * 604800; }
        if (preg_match('/(\d+)d/', $uptime, $m)) { $total += (int)$m[1] * 86400; }
        if (preg_match('/(\d+)h/', $uptime, $m)) { $total += (int)$m[1] * 3600; }
        if (preg_match('/(\d+)m(?!s)/', $uptime, $m)) { $total += (int)$m[1] * 60; }
        if (preg_match('/(\d+)s/', $uptime, $m)) { $total += (int)$m[1]; }

        return $total;
    }
}