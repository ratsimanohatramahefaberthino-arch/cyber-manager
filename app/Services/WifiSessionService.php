<?php

namespace App\Services;

use App\Models\AppareilWifi;
use App\Models\Session;
use App\Models\Voucher;
use Illuminate\Support\Facades\Log;

class WifiSessionService
{
    private ?array $baux = null;
    private ?array $hotes = null;
    private ?array $regTable = null;
    private ?array $wireless = null;

    public function __construct(private MikroTikService $mikroTik) {}

    public function synchroniser(): array
    {
        $this->baux     = null;
        $this->hotes    = null;
        $this->regTable = null;
        $this->wireless = null;

        try {
            $actives = $this->mikroTik->listHotspotActive();
        } catch (\Throwable $e) {
            Log::warning('Sync Wi-Fi : MikroTik injoignable', ['error' => $e->getMessage()]);
            return ['ouvertes' => 0, 'maj' => 0, 'fermees' => 0, 'erreur' => $e->getMessage()];
        }

        $ouvertes = 0;
        $maj      = 0;

        foreach ($actives as $active) {
            try {
                $r = $this->traiterActive($active);
                if ($r === 'ouverte') $ouvertes++;
                elseif ($r === 'maj') $maj++;
            } catch (\Throwable $e) {
                Log::warning('Sync Wi-Fi : entrée ignorée', [
                    'active_id' => $active['.id'] ?? null,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $fermees = $this->marquerSessionsDisparues($actives);

        return ['ouvertes' => $ouvertes, 'maj' => $maj, 'fermees' => $fermees, 'erreur' => null];
    }

    private function traiterActive(array $active): ?string
    {
        $activeId = $active['.id'] ?? null;
        if (!$activeId) return null;

        $session = Session::where('mikrotik_active_id', $activeId)
            ->whereIn('etat_wifi', [
                Session::WIFI_CONNECTE,
                Session::WIFI_DECONNECTE,
                Session::WIFI_EN_PAUSE,
            ])
            ->first();

        if ($session) {
            $this->mettreAJour($session, $active);
            return 'maj';
        }

        $this->creer($active);
        return 'ouverte';
    }

    private function creer(array $active): Session
    {
        $username = $active['user'] ?? null;
        $voucher  = $username ? Voucher::where('username', $username)->first() : null;

        $appareil = $this->resoudreAppareil($active);
        $ssid     = $this->resoudreSsid($active);

        $uptimeSecondes = $this->parseUptime($active['uptime'] ?? '0s');
        $debut = now()->subSeconds($uptimeSecondes);

        $montantTotal = $voucher?->montant ?? 0;

        $session = Session::create(array_merge([
            'poste_id'              => null,
            'voucher_id'            => $voucher?->id,
            'type_session'          => 'wifi',
            'date_heure_debut'      => $debut,
            'date_heure_fin_prevue' => null,
            'duree_prevue'          => 0,
            'duree_consommee'       => 0,
            'temps_restant'         => 0,
            'duree_suspension'      => 0,
            'montant_initial'       => $montantTotal,
            'montant_recharge'      => 0,
            'montant_total'         => $montantTotal,
            'montant_consomme'      => 0,
            'montant_restant'       => $montantTotal,
            'montant_a_reverser'    => 0,
            'etat'                  => 'en_cours',
            'etat_wifi'             => Session::WIFI_CONNECTE,
            'mikrotik_active_id'    => $active['.id'] ?? null,
            'hotspot_username'      => $username,
            'client_mac'            => $active['mac-address'] ?? null,
            'client_ip'             => $active['address']     ?? null,
            'ssid'                  => $ssid,
            'appareil_wifi_id'      => $appareil?->id,
            'derniere_sync_at'      => now(),
        ], $this->calculerVolumes($active)));

        // ⚡ FIX CLÉ : marquer le voucher comme "en cours" pour qu'il
        // disparaisse IMMÉDIATEMENT de la liste des identifiants disponibles.
        if ($voucher) {
            try {
                $voucher->marquerEnCours($appareil);
            } catch (\Throwable $e) {
                Log::warning('Impossible de marquer le voucher en cours', [
                    'voucher_id' => $voucher->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $session;
    }

    private function mettreAJour(Session $session, array $active): void
    {
        if ($session->etat_wifi === Session::WIFI_DECONNECTE) {
            $dureeHorsLigne = $session->deconnecte_at
                ? $session->deconnecte_at->diffInSeconds(now())
                : 0;

            $session->duree_hors_ligne_secondes = (int) $session->duree_hors_ligne_secondes + $dureeHorsLigne;
            $session->etat_wifi     = Session::WIFI_CONNECTE;
            $session->reconnecte_at = now();
            $session->deconnecte_at = null;

            // NOUVEAU : Reprendre l'alarme si elle était en pause
            if ($session->alarme_active && $session->alarme_secondes_restantes > 0) {
                $session->alarme_fin_prevue = now()->addSeconds($session->alarme_secondes_restantes);
                $session->alarme_secondes_restantes = null;
            }
        }

        $session->client_ip        = $active['address']     ?? $session->client_ip;
        $session->client_mac       = $active['mac-address'] ?? $session->client_mac;
        $session->derniere_sync_at = now();

        if ($app = $this->resoudreAppareil($active)) {
            $session->appareil_wifi_id = $app->id;
        }

        if (empty($session->ssid)) {
            $session->ssid = $this->resoudreSsid($active);
        }

        if ($session->etat_wifi === Session::WIFI_CONNECTE) {
            $vol = $this->calculerVolumes($active);
            $session->volume_entree = $vol['volume_entree'];
            $session->volume_sortie = $vol['volume_sortie'];
            $session->volume_total  = $vol['volume_total'];
            $session->duree_consommee = (int) floor($session->secondesConsommees() / 60);
        }

        $session->save();
    }

        private function marquerSessionsDisparues(array $actives): int
    {
        $idsActifs = array_filter(array_map(fn($a) => $a['.id'] ?? null, $actives));

        $sessions = Session::where('type_session', 'wifi')
            ->where('etat_wifi', Session::WIFI_CONNECTE)
            ->whereNotNull('mikrotik_active_id')
            ->whereNotIn('mikrotik_active_id', $idsActifs)
            ->get();

        $n = 0;
        foreach ($sessions as $s) {
            $s->etat_wifi     = Session::WIFI_DECONNECTE;
            $s->deconnecte_at = now();
            $s->derniere_sync_at = now();
            $s->duree_consommee = (int) floor($s->secondesConsommees() / 60);

            // NOUVEAU : Mettre l'alarme en pause
            if ($s->alarme_active && $s->alarme_fin_prevue) {
                $restant = $s->alarme_fin_prevue->getTimestamp() - now()->getTimestamp();
                if ($restant > 0) {
                    $s->alarme_secondes_restantes = $restant;
                    $s->alarme_fin_prevue = null; // Empêche le déclenchement hors ligne
                }
            }

            $s->save();
            $n++;
        }

        return $n;
    }

    private function resoudreSsid(array $active): ?string
    {
        $mac = AppareilWifi::normaliserMac($active['mac-address'] ?? null);

        if ($mac) {
            $reg = $this->registrationPourMac($mac);
            $iface = $reg['interface'] ?? null;

            if ($iface) {
                $wifi = $this->wirelessPourInterface($iface);
                if (!empty($wifi['ssid'])) {
                    return mb_substr($wifi['ssid'], 0, 64);
                }
            }
        }

        $serveur = $active['server'] ?? null;
        if ($serveur) {
            $table = config('mikrotik.ssid_map', []);
            if (isset($table[$serveur])) {
                return mb_substr($table[$serveur], 0, 64);
            }
        }

        $defaut = config('mikrotik.ssid_default');
        return $defaut ? mb_substr($defaut, 0, 64) : null;
    }

    private function registrationPourMac(string $mac): array
    {
        if ($this->regTable === null) {
            $this->regTable = [];
            try {
                foreach ($this->mikroTik->listWirelessRegistrationTable() as $r) {
                    $m = AppareilWifi::normaliserMac($r['mac-address'] ?? null);
                    if ($m) $this->regTable[$m] = $r;
                }
            } catch (\Throwable $e) {}
        }
        return $this->regTable[$mac] ?? [];
    }

    private function wirelessPourInterface(string $iface): array
    {
        if ($this->wireless === null) {
            $this->wireless = [];
            try {
                foreach ($this->mikroTik->listWirelessInterfaces() as $w) {
                    $name = $w['name'] ?? null;
                    if ($name) $this->wireless[$name] = $w;
                }
            } catch (\Throwable $e) {}
        }
        return $this->wireless[$iface] ?? [];
    }

    private function resoudreAppareil(array $active): ?AppareilWifi
    {
        $mac = AppareilWifi::normaliserMac($active['mac-address'] ?? null);
        if (!$mac) return null;

        $bail = $this->bailPourMac($mac);

        $appareil = AppareilWifi::trouverOuCreerParMac($mac, [
            'adresse_ip' => $active['address'] ?? ($bail['address'] ?? null),
            'host_name'  => $bail['host-name'] ?? null,
        ]);

        if (!$appareil->wasRecentlyCreated) {
            $appareil->toucherDerniereConnexion();
        }

        return $appareil;
    }

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

    private function bailPourMac(string $mac): array
    {
        if ($this->baux === null) {
            $this->baux = [];
            try {
                foreach ($this->mikroTik->listDhcpLeases() as $bail) {
                    $m = AppareilWifi::normaliserMac($bail['mac-address'] ?? null);
                    if ($m) $this->baux[$m] = $bail;
                }
            } catch (\Throwable $e) {}
        }
        return $this->baux[$mac] ?? [];
    }

    private function parseUptime(string $uptime): int
    {
        $t = 0;
        if (preg_match('/(\d+)w/', $uptime, $m)) $t += (int)$m[1] * 604800;
        if (preg_match('/(\d+)d/', $uptime, $m)) $t += (int)$m[1] * 86400;
        if (preg_match('/(\d+)h/', $uptime, $m)) $t += (int)$m[1] * 3600;
        if (preg_match('/(\d+)m(?!s)/', $uptime, $m)) $t += (int)$m[1] * 60;
        if (preg_match('/(\d+)s/', $uptime, $m)) $t += (int)$m[1];
        return $t;
    }
}