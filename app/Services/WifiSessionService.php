<?php

namespace App\Services;

use App\Models\AppareilWifi;
use App\Models\Session;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WifiSessionService
{
    private const CLE_VERROU          = 'wifi_sync_verrou';
    private const CLE_DERNIERE_SYNC   = 'wifi_sync_derniere_tentative';
    private const CLE_ETAT_ROUTEUR    = 'wifi_sync_etat_routeur';
    private const CLE_MACS_ACTIVES    = 'wifi_sync_macs_actives';
    /** Au-delà de cet âge (s), la liste des MAC présentes est jugée périmée. */
    private const MACS_ACTIVES_AGE_MAX = 20;
    private const PREFIXE_ABSENCE     = 'wifi_absence_session_';
    private const PREFIXE_MAC_SANS_FIL = 'wifi_mac_sans_fil_';
    private const PREFIXE_APPAREIL    = 'wifi_appareil_rafraichi_';
    private const PREFIXE_VOLUME_BASE = 'wifi_volume_base_session_';

    /**
     * Seuils de confirmation d'une absence : 1 synchro, 0 seconde.
     *
     * Pourquoi si bas : la fiabilité ne vient plus de l'attente mais de la
     * RELECTURE DE CONFIRMATION faite dans la même synchro (voir
     * marquerSessionsDisparues() / relireEtatActuel()) : avant de déclarer un
     * client absent, on relit /ip/hotspot/active ET la table d'enregistrement
     * Wi-Fi ~300 ms plus tard. Il faut que les DEUX lectures concordent.
     * Si la relecture échoue ou si la table Wi-Fi est indisponible, on ne
     * déconnecte rien. Le délai de détection devient celui de la cadence des
     * synchros (≈ 2 s) au lieu de 2 synchros + 3 s.
     *
     * (Les constantes restent là pour pouvoir durcir la règle sans toucher
     * au code : ex. ABSENCE_SYNCS_MIN = 2 si un réseau Wi-Fi instable pose
     * problème.)
     */
    private const ABSENCE_SYNCS_MIN = 1;
    private const ABSENCE_SECONDES_MIN = 0;

    /** Pause avant la relecture de confirmation (microsecondes) : 300 ms. */
    private const RELECTURE_PAUSE_US = 300000;

    /** États d'une session "vivante" (par opposition à terminée). */
    private const ETATS_OUVERTS = [
        Session::WIFI_CONNECTE,
        Session::WIFI_DECONNECTE,
        Session::WIFI_EN_PAUSE,
    ];

    /**
     * Une connexion MikroTik qui a commencé au plus tard N secondes après
     * l'arrêt d'une session terminée est considérée comme LA MÊME connexion
     * (le client n'a jamais été déconnecté). Au-delà : nouvelle connexion.
     */
    private const TOLERANCE_FIN_SECONDES = 5;

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

    /**
     * MAC (normalisées) actuellement présentes d'après la dernière synchro réussie.
     * Retourne null si l'information est inconnue ou périmée (routeur muet,
     * synchro trop ancienne) : l'appelant ne doit alors afficher AUCUN badge.
     *
     * @return string[]|null
     */
    public function macsEncoreActives(): ?array
    {
        $etat = Cache::get(self::CLE_ETAT_ROUTEUR);
        if (is_array($etat) && empty($etat['ok'])) {
            return null;
        }

        $d = Cache::get(self::CLE_MACS_ACTIVES);
        if (!is_array($d) || !isset($d['at'], $d['macs'])) {
            return null;
        }

        if ((now()->getTimestamp() - (int) $d['at']) > self::MACS_ACTIVES_AGE_MAX) {
            return null;
        }

        return (array) $d['macs'];
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

        // Un appareil physique = une seule connexion réelle : si MikroTik garde
        // plusieurs entrées pour la même MAC, on ne retient que la plus récente.
        $actives = $this->actifsCanoniques($actives);

        $ouvertes     = 0;
        $maj          = 0;
        $idsPresents  = [];
        $macsPresentes = [];   // MAC réellement présentes (pour le badge "Client encore connecté")

        foreach ($actives as $active) {
            $activeId = $active['.id'] ?? null;
            if (!$activeId) {
                continue;
            }

            $present = $this->estPresent($active);
            if ($present) {
                $idsPresents[] = $activeId;

                $macPresente = AppareilWifi::normaliserMac($active['mac-address'] ?? null);
                if ($macPresente) {
                    $macsPresentes[] = $macPresente;
                }
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

        Cache::put(self::CLE_MACS_ACTIVES, [
            'at'   => now()->getTimestamp(),
            'macs' => array_values(array_unique($macsPresentes)),
        ], 120);

        return ['ouvertes' => $ouvertes, 'maj' => $maj, 'fermees' => $fermees, 'erreur' => null];
    }

    /**
     * Ramène les entrées actives à UNE par appareil (MAC).
     * Si plusieurs entrées partagent la même MAC, la plus récente (plus petit
     * uptime) est la connexion réelle ; les autres sont des restes ignorés.
     * Les entrées sans MAC valide sont conservées telles quelles.
     */
    private function actifsCanoniques(array $actives): array
    {
        $resultat = [];
        $groupes  = [];

        foreach ($actives as $a) {
            $mac = AppareilWifi::normaliserMac($a['mac-address'] ?? null);
            if ($mac === null) {
                $resultat[] = $a;
                continue;
            }
            $groupes[$mac][] = $a;
        }

        foreach ($groupes as $mac => $liste) {
            if (count($liste) > 1) {
                usort($liste, fn ($x, $y) =>
                    $this->parseUptime($x['uptime'] ?? '0s') <=> $this->parseUptime($y['uptime'] ?? '0s'));

                Log::info('Sync Wi-Fi : plusieurs entrées MikroTik pour une même MAC, la plus récente est retenue', [
                    'mac'     => $mac,
                    'retenue' => $liste[0]['.id'] ?? null,
                    'ignores' => array_map(fn ($x) => $x['.id'] ?? null, array_slice($liste, 1)),
                ]);
            }
            $resultat[] = $liste[0];
        }

        return $resultat;
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

    // -----------------------------------------------------------------
    // Rattachement d'une entrée active à une session (anti-doublons)
    // -----------------------------------------------------------------

    private function traiterActive(array $active, bool $present): ?string
    {
        $activeId = $active['.id'];
        $mac      = AppareilWifi::normaliserMac($active['mac-address'] ?? null);
        $username = (($active['user'] ?? '') !== '') ? $active['user'] : null;

        // 1) Session OUVERTE qui correspond à cette connexion
        //    (même .id, ou même appareil + même voucher après un nouvel .id).
        $candidats = $this->sessionsOuvertesCorrespondantes($active, $activeId, $mac, $username);

        if ($candidats->isNotEmpty()) {
            $session  = $candidats->first();      // la plus ancienne = celle qu'on garde
            $doublons = $candidats->slice(1);

            if ($doublons->isNotEmpty()) {
                $this->fusionner($session, $doublons);
            }

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

        // 2) Aucune session ouverte. Cette connexion est-elle celle d'une session
        //    déjà TERMINÉE (figée) ? Alors le client n'a jamais été déconnecté :
        //    on ne crée rien, la ligne "Terminé" reste telle quelle.
        if ($this->connexionDejaTerminee($active, $activeId, $mac, $username)) {
            return null;
        }

        // 3) Vraie nouvelle connexion → nouvelle session.
        $this->creer($active);
        return 'ouverte';
    }

    /**
     * Sessions ouvertes qui représentent cette même connexion réelle,
     * de la plus ancienne à la plus récente.
     *
     * @return Collection<int, Session>
     */
    private function sessionsOuvertesCorrespondantes(array $active, string $activeId, ?string $mac, ?string $username): Collection
    {
        $limite = now()->subMinutes($this->fenetreRepriseMinutes());

        return $this->requeteParIdOuMac($activeId, $active, self::ETATS_OUVERTS)
            ->orderBy('date_heure_debut')
            ->orderBy('id')
            ->get()
            ->filter(function (Session $s) use ($activeId, $mac, $username, $limite) {
                if (!$this->correspond($s, $activeId, $mac, $username)) {
                    return false;
                }

                // Reprise par MAC (nouvel .id) : seulement si la déconnexion est récente.
                // Un retour plusieurs heures plus tard = nouvelle session.
                $memeId = (string) $s->mikrotik_active_id === (string) $activeId;
                if (!$memeId
                    && $s->etat_wifi === Session::WIFI_DECONNECTE
                    && $s->deconnecte_at
                    && $s->deconnecte_at->lt($limite)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * Une session TERMINÉE correspond-elle à la connexion en cours, c'est-à-dire
     * la connexion MikroTik a-t-elle commencé AVANT l'arrêt de cette session ?
     */
    private function connexionDejaTerminee(array $active, string $activeId, ?string $mac, ?string $username): bool
    {
        $debutConnexion = now()->getTimestamp() - $this->parseUptime($active['uptime'] ?? '0s');

        $terminees = $this->requeteParIdOuMac($activeId, $active, [Session::WIFI_TERMINE])
            ->whereNotNull('date_heure_fin_reelle')
            ->orderByDesc('date_heure_fin_reelle')
            ->limit(10)
            ->get();

        foreach ($terminees as $t) {
            if (!$this->correspond($t, $activeId, $mac, $username)) {
                continue;
            }

            if ($debutConnexion <= $t->date_heure_fin_reelle->getTimestamp() + self::TOLERANCE_FIN_SECONDES) {
                return true;
            }
        }

        return false;
    }

    /** Pré-filtre SQL : même .id actif OU même MAC (brute ou normalisée). */
    private function requeteParIdOuMac(string $activeId, array $active, array $etats)
    {
        $macBrute = $active['mac-address'] ?? null;
        $macNorm  = AppareilWifi::normaliserMac($macBrute);
        $macs     = array_values(array_unique(array_filter([$macBrute, $macNorm])));

        return Session::where('type_session', 'wifi')
            ->whereIn('etat_wifi', $etats)
            ->where(function ($q) use ($activeId, $macs) {
                $q->where('mikrotik_active_id', $activeId);
                if (!empty($macs)) {
                    $q->orWhereIn('client_mac', $macs);
                }
            });
    }

    /**
     * Critères combinés (aucun ne suffit seul) :
     *  - même .id actif : accepté seulement si la MAC est cohérente
     *    (protège contre la réutilisation d'un .id après redémarrage du routeur) ;
     *  - sinon : même MAC ET même voucher (username).
     */
    private function correspond(Session $s, string $activeId, ?string $mac, ?string $username): bool
    {
        $macSession = AppareilWifi::normaliserMac($s->client_mac);

        if ((string) $s->mikrotik_active_id === (string) $activeId) {
            return $mac === null || $macSession === null || $macSession === $mac;
        }

        if ($mac === null || $macSession !== $mac) {
            return false;
        }

        return (($s->hotspot_username ?: null) === $username);
    }

    private function fenetreRepriseMinutes(): int
    {
        return max(1, (int) config('mikrotik.wifi_reprise_minutes', 60));
    }

    /**
     * Fusionne des doublons opérationnels dans la session conservée.
     * On garde la plus ancienne (date de début d'origine) ; si elle n'a pas
     * d'alarme et qu'un doublon en a une, l'alarme est reprise.
     *
     * @param Collection<int, Session> $doublons
     */
    private function fusionner(Session $garde, Collection $doublons): void
    {
        foreach ($doublons as $d) {
            if (!$garde->alarme_active && $d->alarme_active) {
                foreach ([
                    'alarme_active', 'alarme_type', 'alarme_duree_minutes', 'alarme_montant',
                    'alarme_fin_prevue', 'alarme_declenchee_at', 'alarme_message',
                    'alarme_historique', 'alarme_secondes_restantes',
                ] as $champ) {
                    $garde->{$champ} = $d->{$champ};
                }
            }

            Log::info('Sync Wi-Fi : doublon de session fusionné', [
                'garde'    => $garde->id,
                'supprime' => $d->id,
                'mac'      => $d->client_mac,
                'voucher'  => $d->hotspot_username,
            ]);

            Cache::forget(self::PREFIXE_ABSENCE . $d->id);
            Cache::forget(self::PREFIXE_VOLUME_BASE . $d->id);
            $d->delete();
        }

        if ($garde->isDirty()) {
            $garde->save();
        }
    }

    // -----------------------------------------------------------------
    // Création / mise à jour
    // -----------------------------------------------------------------

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

        // Reconnexion avec un nouvel .id MikroTik : la session adopte la nouvelle
        // entrée et on mémorise le volume déjà cumulé (les compteurs MikroTik
        // repartent de zéro).
        $nouvelId = $active['.id'] ?? null;
        if ($nouvelId && (string) $session->mikrotik_active_id !== (string) $nouvelId) {
            Cache::put(self::PREFIXE_VOLUME_BASE . $session->id, [
                'entree' => (int) $session->volume_entree,
                'sortie' => (int) $session->volume_sortie,
            ], 604800);

            $session->mikrotik_active_id = $nouvelId;
        }

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
            $vol  = $this->calculerVolumes($active);
            $base = Cache::get(self::PREFIXE_VOLUME_BASE . $session->id);
            $baseEntree = is_array($base) ? (int) ($base['entree'] ?? 0) : 0;
            $baseSortie = is_array($base) ? (int) ($base['sortie'] ?? 0) : 0;

            $session->volume_entree = $vol['volume_entree'] + $baseEntree;
            $session->volume_sortie = $vol['volume_sortie'] + $baseSortie;
            $session->volume_total  = $session->volume_entree + $session->volume_sortie;
            $session->duree_consommee = (int) floor($session->secondesConsommees() / 60);
        }

        $session->save();
    }

    // -----------------------------------------------------------------
    // Détection des départs (avec relecture de confirmation)
    // -----------------------------------------------------------------

    /**
     * Sessions "connecté" dont le client n'est plus présent.
     *
     * Pour chaque candidat, une RELECTURE de confirmation (une seule pour tous,
     * faite seulement s'il y a au moins un candidat) vérifie que le client est
     * toujours absent. Si la relecture est impossible (MikroTik muet), on ne
     * déconnecte personne. L'heure de déconnexion retenue est celle de la
     * première absence constatée.
     */
    private function marquerSessionsDisparues(array $idsPresents): int
    {
        $query = Session::where('type_session', 'wifi')
            ->where('etat_wifi', Session::WIFI_CONNECTE)
            ->whereNotNull('mikrotik_active_id');

        if (!empty($idsPresents)) {
            $query->whereNotIn('mikrotik_active_id', $idsPresents);
        }

        $candidats = $query->get();

        if ($candidats->isEmpty()) {
            return 0;
        }

        // Relecture unique, partagée par tous les candidats.
        $relecture = $this->relireEtatActuel();

        if ($relecture === null) {
            return 0; // doute : aucune fausse déconnexion
        }

        $n = 0;
        $maintenant = now()->getTimestamp();

        foreach ($candidats as $s) {
            $cle = self::PREFIXE_ABSENCE . $s->id;

            // La seconde lecture le revoit : fausse alerte, on oublie.
            if ($this->sessionEncorePresente($s, $relecture)) {
                Cache::forget($cle);
                continue;
            }

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

    /**
     * Seconde lecture : liste active + table Wi-Fi, après une courte pause.
     * Retourne null si la liste active est illisible (on ne conclut rien).
     * Une table Wi-Fi illisible donne 'reg' = null : estPresent() répondra
     * alors "présent" (comportement prudent, inchangé).
     *
     * @return array{actives: array, reg: ?array}|null
     */
    private function relireEtatActuel(): ?array
    {
        usleep(self::RELECTURE_PAUSE_US);

        try {
            $actives = $this->mikroTik->listHotspotActive();
        } catch (\Throwable $e) {
            Log::warning('Sync Wi-Fi : relecture de confirmation impossible', ['error' => $e->getMessage()]);
            return null;
        }

        try {
            $reg = [];
            foreach ($this->mikroTik->listWirelessRegistrationTable() as $r) {
                $m = AppareilWifi::normaliserMac($r['mac-address'] ?? null);
                if ($m) {
                    $reg[$m] = $r;
                }
            }
        } catch (\Throwable $e) {
            $reg = null;
        }

        return ['actives' => $actives, 'reg' => $reg];
    }

    /**
     * La session est-elle toujours représentée, à la relecture, par une entrée
     * active ET présente sur l'antenne ? Même critère que l'anti-doublons (P2).
     */
    private function sessionEncorePresente(Session $s, array $relecture): bool
    {
        $ancienneTable  = $this->regTable;
        $this->regTable = $relecture['reg'];

        try {
            foreach ($relecture['actives'] as $a) {
                $id = (string) ($a['.id'] ?? '');
                if ($id === '') {
                    continue;
                }

                $mac      = AppareilWifi::normaliserMac($a['mac-address'] ?? null);
                $username = (($a['user'] ?? '') !== '') ? $a['user'] : null;

                if ($this->correspond($s, $id, $mac, $username) && $this->estPresent($a)) {
                    return true;
                }
            }
        } finally {
            $this->regTable = $ancienneTable;
        }

        return false;
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