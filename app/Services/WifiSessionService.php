<?php

namespace App\Services;

use App\Models\AppareilWifi;
use App\Models\Session;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WifiSessionService
{
    private const CLE_VERROU          = 'wifi_sync_verrou';
    private const CLE_DERNIERE_SYNC   = 'wifi_sync_derniere_tentative';
    private const CLE_ETAT_ROUTEUR    = 'wifi_sync_etat_routeur';
    private const PREFIXE_ABSENCE     = 'wifi_absence_session_';
    private const PREFIXE_MAC_SANS_FIL = 'wifi_mac_sans_fil_';
    private const PREFIXE_APPAREIL    = 'wifi_appareil_rafraichi_';

    /** Une absence n'est confirmée qu'après ce nombre de synchros sans le client... */
    private const ABSENCE_SYNCS_MIN = 2;
    /** ...ET après au moins ce délai (secondes) depuis la première absence. */
    private const ABSENCE_SECONDES_MIN = 3;

    private ?array $baux = null;
    private ?array $regTable = null;   // null = table Wi-Fi indisponible
    private ?array $wireless = null;

    public function __construct(private MikroTikService $mikroTik) {}

    // -----------------------------------------------------------------
    // Points d'entrée
    // -----------------------------------------------------------------

    /**
     * Synchronise seulement si la dernière tentative est assez ancienne.
     * Si le routeur est en panne, on réessaie moins souvent (évite de bloquer la page).
     */
    public function synchroniserSiNecessaire(int $ageMaxSecondes = 2): ?array
    {
        $etat = Cache::get(self::CLE_ETAT_ROUTEUR);
        if (is_array($etat) && empty($etat['ok'])) {
            $ageMaxSecondes = max($ageMaxSecondes, 10);
        }

        $derniere = Cache::get(self::CLE_DERNIERE_SYNC);
        if ($derniere && (now()->getTimestamp() - (int) $derniere) < $ageMaxSecondes) {
            return null;
        }

        return $this->synchroniser();
    }

    /**
     * Synchronisation protégée par un verrou unique (page Wi-Fi, page HotSpot,
     * cron et bouton manuel passent tous par ici).
     */
    public function synchroniser(): array
    {
        $verrou = Cache::lock(self::CLE_VERROU, 30);

        if (!$verrou->get()) {
            return ['ouvertes' => 0, 'maj' => 0, 'fermees' => 0, 'erreur' => null, 'ignoree' => true];
        }

        try {
            return $this->executerSynchro();
        } finally {
            $verrou->release();
        }
    }

    /** État de la dernière synchro (pour afficher un avertissement si MikroTik ne répond plus). */
    public function etatSynchro(): array
    {
        $e = Cache::get(self::CLE_ETAT_ROUTEUR);

        return [
            'ok'     => (bool) ($e['ok'] ?? true),
            'at'     => isset($e['at']) ? Carbon::createFromTimestamp((int) $e['at'])->toIso8601String() : null,
            'erreur' => $e['erreur'] ?? null,
        ];
    }

    // -----------------------------------------------------------------
    // Cœur de la synchro
    // -----------------------------------------------------------------

    private function executerSynchro(): array
    {
        $this->baux     = null;
        $this->regTable = null;
        $this->wireless = null;

        Cache::put(self::CLE_DERNIERE_SYNC, now()->getTimestamp(), 3600);

        try {
            $actives = $this->mikroTik->listHotspotActive();
        } catch (\Throwable $e) {
            // Routeur injoignable : on ne touche à AUCUNE session (pas de fausse déconnexion).
            Log::warning('Sync Wi-Fi : MikroTik injoignable', ['error' => $e->getMessage()]);
            Cache::put(self::CLE_ETAT_ROUTEUR, [
                'ok' => false, 'at' => now()->getTimestamp(), 'erreur' => $e->getMessage(),
            ], 3600);

            return ['ouvertes' => 0, 'maj' => 0, 'fermees' => 0, 'erreur' => $e->getMessage()];
        }

        Cache::put(self::CLE_ETAT_ROUTEUR, [
            'ok' => true, 'at' => now()->getTimestamp(), 'erreur' => null,
        ], 3600);

        $this->chargerPresenceSansFil();

        $ouvertes     = 0;
        $maj          = 0;
        $idsPresents  = [];

        foreach ($actives as $active) {
            $activeId = $active['.id'] ?? null;
            if (!$activeId) {
                continue;
            }

            $present = $this->estPresent($active);
            if ($present) {
                $idsPresents[] = $activeId;
            }

            try {
                $r = $this->traiterActive($active, $present);
                if ($r === 'ouverte') $ouvertes++;
                elseif ($r === 'maj') $maj++;
            } catch (\Throwable $e) {
                Log::warning('Sync Wi-Fi : entrée ignorée', [
                    'active_id' => $activeId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $fermees = $this->marquerSessionsDisparues($idsPresents);

        return ['ouvertes' => $ouvertes, 'maj' => $maj, 'fermees' => $fermees, 'erreur' => null];
    }

    /**
     * Charge la liste des téléphones réellement accrochés à l'antenne.
     * Mémorise aussi quelles MAC sont "sans fil" : seules celles-là pourront
     * être jugées absentes via cette table (les postes en câble ne sont jamais touchés).
     */
    private function chargerPresenceSansFil(): void
    {
        $this->regTable = null;

        try {
            $table = [];
            foreach ($this->mikroTik->listWirelessRegistrationTable() as $r) {
                $m = AppareilWifi::normaliserMac($r['mac-address'] ?? null);
                if (!$m) {
                    continue;
                }
                $table[$m] = $r;
                Cache::add(self::PREFIXE_MAC_SANS_FIL . str_replace(':', '', $m), 1, 604800);
            }
            $this->regTable = $table;
        } catch (\Throwable $e) {
            // Table indisponible (autre type de Wi-Fi, erreur...) : on ne conclut rien.
            $this->regTable = null;
        }
    }

    /**
     * Le client est-il vraiment là ?
     * Absent seulement si : table Wi-Fi lue avec succès + MAC déjà connue comme
     * "sans fil" + MAC absente de la table maintenant.
     * Dans tous les autres cas (doute) : on le considère présent.
     */
    private function estPresent(array $active): bool
    {
        if ($this->regTable === null) {
            return true;
        }

        $mac = AppareilWifi::normaliserMac($active['mac-address'] ?? null);
        if (!$mac) {
            return true;
        }

        if (isset($this->regTable[$mac])) {
            return true;
        }

        return !Cache::has(self::PREFIXE_MAC_SANS_FIL . str_replace(':', '', $mac));
    }

    private function traiterActive(array $active, bool $present): ?string
    {
        $activeId = $active['.id'];

        $session = Session::where('mikrotik_active_id', $activeId)
            ->whereIn('etat_wifi', [
                Session::WIFI_CONNECTE,
                Session::WIFI_DECONNECTE,
                Session::WIFI_EN_PAUSE,
            ])
            ->first();

        if ($session) {
            if (!$present) {
                // Entrée "fantôme" gardée par MikroTik : on n'y touche pas,
                // marquerSessionsDisparues() s'occupe de l'absence.
                return null;
            }

            $this->mettreAJour($session, $active);
            return 'maj';
        }

        if (!$present) {
            return null; // ne pas créer de session pour un fantôme
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

        // Le voucher passe "en cours" tout de suite : il disparaît de la liste "disponibles".
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
        // Le client est bien là : on efface tout compteur d'absence en cours.
        Cache::forget(self::PREFIXE_ABSENCE . $session->id);

        if ($session->etat_wifi === Session::WIFI_DECONNECTE) {
            $dureeHorsLigne = $session->deconnecte_at
                ? max(0, now()->getTimestamp() - $session->deconnecte_at->getTimestamp())
                : 0;

            $session->duree_hors_ligne_secondes = (int) $session->duree_hors_ligne_secondes + $dureeHorsLigne;
            $session->etat_wifi     = Session::WIFI_CONNECTE;
            $session->reconnecte_at = now();
            $session->deconnecte_at = null;

            // Reprendre l'alarme si elle était en pause
            if ($session->alarme_active && $session->alarme_secondes_restantes > 0) {
                $session->alarme_fin_prevue = now()->addSeconds($session->alarme_secondes_restantes);
                $session->alarme_secondes_restantes = null;
            }
        }

        $session->client_ip        = $active['address']     ?? $session->client_ip;
        $session->client_mac       = $active['mac-address'] ?? $session->client_mac;
        $session->derniere_sync_at = now();

        // Infos de l'appareil : rafraîchies au plus une fois par minute (évite DHCP + écriture à chaque synchro)
        $mac = AppareilWifi::normaliserMac($active['mac-address'] ?? null);
        $rafraichir = !$session->appareil_wifi_id
            || ($mac && Cache::add(self::PREFIXE_APPAREIL . str_replace(':', '', $mac), 1, 60));

        if ($rafraichir && ($app = $this->resoudreAppareil($active))) {
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

    /**
     * Sessions "connecté" dont le client n'est plus présent.
     * Une absence doit être confirmée (plusieurs synchros + délai minimal).
     * L'heure de déconnexion retenue est celle de la PREMIÈRE absence constatée.
     */
    private function marquerSessionsDisparues(array $idsPresents): int
    {
        $query = Session::where('type_session', 'wifi')
            ->where('etat_wifi', Session::WIFI_CONNECTE)
            ->whereNotNull('mikrotik_active_id');

        if (!empty($idsPresents)) {
            $query->whereNotIn('mikrotik_active_id', $idsPresents);
        }

        $n = 0;
        $maintenant = now()->getTimestamp();

        foreach ($query->get() as $s) {
            $cle = self::PREFIXE_ABSENCE . $s->id;
            $abs = Cache::get($cle);

            if (!is_array($abs)) {
                $abs = ['depuis' => $maintenant, 'n' => 0];
            }

            $abs['n'] = (int) $abs['n'] + 1;
            Cache::put($cle, $abs, 120);

            if ($abs['n'] < self::ABSENCE_SYNCS_MIN
                || ($maintenant - (int) $abs['depuis']) < self::ABSENCE_SECONDES_MIN) {
                continue; // pas encore confirmé
            }

            $moment = Carbon::createFromTimestamp((int) $abs['depuis']);

            $s->etat_wifi        = Session::WIFI_DECONNECTE;
            $s->deconnecte_at    = $moment;
            $s->derniere_sync_at = now();
            $s->duree_consommee  = (int) floor($s->secondesConsommees() / 60);

            // Alarme en pause : le temps restant est figé à l'instant du départ
            if ($s->alarme_active && $s->alarme_fin_prevue) {
                $restant = $s->alarme_fin_prevue->getTimestamp() - $moment->getTimestamp();
                if ($restant > 0) {
                    $s->alarme_secondes_restantes = $restant;
                    $s->alarme_fin_prevue = null;
                }
            }

            $s->save();
            Cache::forget($cle);
            $n++;
        }

        return $n;
    }

    // -----------------------------------------------------------------
    // Résolution SSID / appareil
    // -----------------------------------------------------------------

    private function resoudreSsid(array $active): ?string
    {
        $mac = AppareilWifi::normaliserMac($active['mac-address'] ?? null);

        if ($mac) {
            $reg = ($this->regTable ?? [])[$mac] ?? [];
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
            $derniere = $appareil->derniere_connexion;
            if (!$derniere || (now()->getTimestamp() - $derniere->getTimestamp()) > 60) {
                $appareil->toucherDerniereConnexion();
            }
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