<?php

namespace App\Services;

use App\Models\Poste;
use App\Models\Session;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Logique partagée entre l'interface admin, l'API agent et la maintenance planifiée :
 * création / suspension / reprise / clôture de session et connexion du poste au réseau (MikroTik).
 *
 * POLITIQUE « poste injoignable pendant une session » :
 *  - le temps d'injoignabilité n'est jamais facturé (facturation jusqu'au dernier heartbeat) ;
 *  - la session reste ouverte pendant la tolérance (config postes.hors_ligne_tolerance_minutes) ;
 *    si le poste revient, la panne est déduite (duree_hors_ligne_secondes) et la session reprend ;
 *  - au-delà de la tolérance, clôture automatique (motif « hors_ligne ») + coupure MikroTik.
 *
 * MODE DÉGRADÉ : aucune méthode réseau ne lève d'exception ; une panne MikroTik est loggée
 * (jamais de mot de passe ni de token dans les logs) et la session s'ouvre / se ferme quand même.
 */
class PosteSessionService
{
    public function __construct(
        private MikroTikService $mikroTik,
        private TarifService $tarifs
    ) {}

    // ------------------------------------------------------------------
    // Cycle de vie de la session (base de données)
    // ------------------------------------------------------------------

    public function creerSession(Poste $poste, string $mode, ?int $duree): Session
    {
        try {
            $tarifId = $this->tarifs->tarifStandard()->id;
        } catch (\Throwable) {
            $tarifId = null;
        }

        $session = Session::create([
            'poste_id'              => $poste->id,
            'type_session'          => 'ethernet',
            'date_heure_debut'      => now(),
            'date_heure_fin_prevue' => $duree ? now()->addMinutes($duree) : null,
            'duree_prevue'          => $duree ?? 0,
            'etat'                  => 'en_cours',
            'tarif_id'              => $tarifId,
            'montant_initial'       => 0,
            'montant_total'         => 0,
            'description'           => "Session {$mode}" . ($duree ? " de {$duree} minutes." : '.'),
        ]);

        $session->mode_facturation = $mode;
        $session->save();

        return $session;
    }

    /**
     * Montant (Ar) de la session. Le mode « libre » n'est jamais facturé.
     *
     * @param int|null $secondes Durée facturable à utiliser ; null = durée consommée à l'instant T
     */
    public function montant(Session $session, ?int $secondes = null): int
    {
        if (($session->mode_facturation ?? 'forfait') === 'libre') {
            return 0;
        }

        try {
            $tarif   = $session->tarif ?: $this->tarifs->tarifStandard();
            $minutes = intdiv($secondes ?? $session->secondesConsommees(), 60);

            return $this->tarifs->montantPourMinutes($minutes, $tarif);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Suspend la session active : chrono figé, poste « suspendu ».
     * Base de données uniquement (à appeler dans une transaction) ; la coupure réseau
     * se fait ensuite via deconnecterReseau().
     *
     * @return bool false si le poste n'a aucune session active
     */
    public function suspendre(Poste $poste): bool
    {
        $session = $poste->sessionActive();
        if ($session === null) {
            return false;
        }

        if ($session->etat === 'en_cours') {
            $session->update(['etat' => 'en_pause', 'date_heure_suspension' => now()]);
        }
        $poste->update(['etat' => Poste::ETAT_SUSPENDU]);

        return true;
    }

    /**
     * Reprend la session active (si elle était en pause) et passe le poste « utilisé ».
     * Base de données uniquement ; la reconnexion réseau se fait ensuite via connecterReseau().
     *
     * @return bool false si le poste n'a aucune session active
     */
    public function reprendre(Poste $poste): bool
    {
        $session = $poste->sessionActive();
        if ($session === null) {
            return false;
        }

        if ($session->etat === 'en_pause') {
            $pause = $session->date_heure_suspension
                ? max(0, now()->getTimestamp() - $session->date_heure_suspension->getTimestamp())
                : 0;

            $session->update([
                'etat'                  => 'en_cours',
                'date_heure_reprise'    => now(),
                'duree_suspension'      => (int) $session->duree_suspension + $pause,
                'date_heure_suspension' => null,
            ]);
        }

        $poste->update(['etat' => Poste::ETAT_OCCUPE]);

        return true;
    }

    /**
     * Clôture la session active : calcule et fige le montant, poste « disponible ».
     * Base de données uniquement (à appeler dans une transaction) ; la coupure réseau
     * se fait ensuite via deconnecterReseau().
     *
     * @param CarbonInterface|null $arretA Instant (passé) où la consommation s'est réellement arrêtée
     *                                     (expiration d'un forfait, poste injoignable). Ignoré si la session est en pause.
     */
    public function cloturer(Poste $poste, string $motif = 'client', ?CarbonInterface $arretA = null): ?Session
    {
        $session = $poste->sessionActive();
        if ($session === null) {
            return null;
        }

        $brut     = $session->secondesConsommees();   // avant le changement d'état
        $secondes = $brut;
        $fin      = now();

        if ($arretA !== null && $session->etat === 'en_cours' && $session->date_heure_debut !== null) {
            $arret = $arretA->copy();
            if ($arret->lt($session->date_heure_debut)) {
                $arret = $session->date_heure_debut->copy();
            }
            if ($arret->lt($fin)) {
                // Le chrono comptait en continu : on retire le temps écoulé depuis l'arrêt réel
                $secondes = max(0, $brut - ($fin->getTimestamp() - $arret->getTimestamp()));
                $fin      = $arret;
            }
        }

        $montant = $this->montant($session, $secondes);

        $session->update([
            'etat'                  => 'terminee',
            'date_heure_fin_reelle' => $fin,
            'motif_fin'             => $motif,
            'duree_consommee'       => intdiv($secondes, 60),
            'montant_consomme'      => $montant,
            'montant_total'         => $montant,
        ]);

        $poste->update(['etat' => Poste::ETAT_DISPONIBLE]);

        return $session;
    }

    // ------------------------------------------------------------------
    // Expiration des forfaits
    // ------------------------------------------------------------------

    /** Vrai si la session est un forfait en cours dont le temps est épuisé. */
    public function sessionExpiree(Session $session): bool
    {
        return $session->etat === 'en_cours'
            && ($session->mode_facturation ?? 'forfait') === 'forfait'
            && (int) $session->duree_prevue > 0
            && $session->secondesConsommees() >= (int) $session->duree_prevue * 60;
    }

    /**
     * Clôture la session du poste si son forfait est épuisé, puis coupe Internet.
     * Idempotent et sûr en concurrence (verrou de ligne) : appelable depuis le heartbeat
     * ET depuis la maintenance planifiée.
     */
    public function expirerSiNecessaire(Poste $poste): ?Session
    {
        $apercu = $poste->sessionActive();
        if ($apercu === null || !$this->sessionExpiree($apercu)) {
            return null;
        }

        $session = DB::transaction(function () use ($poste) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $s = $p?->sessionActive();
            if ($s === null || !$this->sessionExpiree($s)) {
                return null;
            }

            // Instant exact où le forfait a été épuisé (en tenant compte des temps non facturés)
            $deductions = (int) $s->duree_suspension + (int) $s->duree_hors_ligne_secondes + (int) $s->duree_pause_secondes;
            $arret      = $s->date_heure_debut->copy()->addSeconds((int) $s->duree_prevue * 60 + $deductions);

            return $this->cloturer($p, 'expiration', $arret->isFuture() ? null : $arret);
        });

        if ($session === null) {
            return null;
        }

        Log::info("Forfait expiré : session #{$session->id} du poste {$poste->nom_poste} clôturée ({$session->montant_total} Ar).");
        $this->deconnecterReseau($poste);

        return $session;
    }

    // ------------------------------------------------------------------
    // Poste injoignable / retour en ligne
    // ------------------------------------------------------------------

    /**
     * Le poste redonne signe de vie après une panne : le temps d'injoignabilité n'est pas facturé.
     *
     * @return int Secondes déduites
     */
    public function deduireTempsHorsLigne(Poste $poste, ?CarbonInterface $dernierSigne): int
    {
        if ($dernierSigne === null) {
            return 0;
        }

        return DB::transaction(function () use ($poste, $dernierSigne) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $s = $p?->sessionActive();
            if ($s === null || $s->etat !== 'en_cours') {
                return 0;
            }

            // La panne ne peut pas avoir commencé avant le début ou la dernière reprise de la session
            $debutPanne = max(
                $dernierSigne->getTimestamp(),
                $s->date_heure_debut?->getTimestamp() ?? 0,
                $s->date_heure_reprise?->getTimestamp() ?? 0
            );
            $panne = now()->getTimestamp() - $debutPanne;
            if ($panne <= Poste::DELAI_HORS_LIGNE) {
                return 0;
            }

            $s->update(['duree_hors_ligne_secondes' => (int) $s->duree_hors_ligne_secondes + $panne]);
            Log::info("Poste {$poste->nom_poste} de retour après {$panne} s : temps de panne déduit de la session #{$s->id}.");

            return $panne;
        });
    }

    /**
     * Retour en ligne d'un poste (redémarrage, coupure) : déduit la panne, applique une éventuelle
     * expiration, puis rétablit Internet si la session est toujours en cours.
     */
    public function traiterRetourEnLigne(Poste $poste, ?CarbonInterface $dernierSigne): void
    {
        $this->deduireTempsHorsLigne($poste, $dernierSigne);

        if ($this->expirerSiNecessaire($poste) !== null) {
            return;
        }

        $session = $poste->sessionActive();
        if ($session !== null && $session->etat === 'en_cours') {
            $this->connecterReseau($poste);
        }
    }

    /**
     * Clôture une session « en cours » dont le poste est injoignable depuis plus longtemps que la tolérance.
     * Facturée jusqu'au dernier signe de vie ; Internet coupé.
     */
    public function cloturerSiInjoignable(Poste $poste, int $toleranceMinutes): ?Session
    {
        $session = DB::transaction(function () use ($poste, $toleranceMinutes) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $s = $p?->sessionActive();
            if ($s === null || $s->etat !== 'en_cours') {
                return null;
            }

            $dernier = $p->dernier_heartbeat;
            if ($dernier !== null && $dernier->gt(now()->subMinutes($toleranceMinutes))) {
                return null;   // revenu entre-temps ou tolérance pas encore écoulée
            }

            $arret = ($dernier === null || $dernier->lt($s->date_heure_debut)) ? $s->date_heure_debut : $dernier;

            return $this->cloturer($p, 'hors_ligne', $arret);
        });

        if ($session === null) {
            return null;
        }

        Log::warning("Poste {$poste->nom_poste} injoignable depuis plus de {$toleranceMinutes} min : session #{$session->id} clôturée automatiquement ({$session->montant_total} Ar, facturée jusqu'au dernier signe de vie).");
        $this->deconnecterReseau($poste);

        return $session;
    }



    // ------------------------------------------------------------------
    // Réseau (MikroTik) — mode dégradé : ne lève jamais d'exception
    // ------------------------------------------------------------------

    /**
     * Le compte HotSpot existe-t-il sur MikroTik ?
     *
     * @return bool|null true/false = vérifié, null = impossible à vérifier (MikroTik désactivé ou injoignable)
     */
    public function compteHotspotExiste(string $username): ?bool
    {
        if (!$this->mikrotikActif()) {
            return null;
        }

        try {
            return $this->mikroTik->findHotspotUser($username) !== null;
        } catch (\Throwable $e) {
            Log::warning('MikroTik : vérification du compte HotSpot impossible (' . $e->getMessage() . ').');
            return null;
        }
    }

    public function mikrotikActif(): bool
    {
        return (bool) config('mikrotik.enabled', true);
    }

    /**
     * Connecte le poste à Internet via MikroTik.
     *
     * @return bool|null true = connecté, false = échec (mode dégradé), null = non applicable
     *                   (MikroTik désactivé, identifiants HotSpot ou MAC absents)
     */
    public function connecterReseau(Poste $poste): ?bool
    {
        if (!$this->mikrotikActif()) {
            Log::info("MikroTik désactivé (MIKROTIK_ENABLED=false) : connexion Internet de {$poste->nom_poste} ignorée.");
            return null;
        }
        if (!$poste->hotspot_username || !$poste->adresse_mac) {
            Log::warning("Poste {$poste->nom_poste} : identifiants HotSpot ou adresse MAC absents, Internet non activé.");
            return null;
        }

        try {
            $this->mikroTik->connecterPosteHotspot(
                $poste->hotspot_username,
                $poste->hotspot_password ?? '',
                $poste->adresse_mac,
                $poste->adresse_ip
            );
            Log::info("MikroTik : poste {$poste->nom_poste} connecté à Internet.");
            return true;
        } catch (\Throwable $e) {
            Log::warning("MikroTik : connexion de {$poste->nom_poste} échouée (mode dégradé, la session continue) : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Coupe l'accès Internet du poste.
     *
     * @return bool|null true = traité, false = échec (mode dégradé), null = non applicable
     */
    public function deconnecterReseau(Poste $poste): ?bool
    {
        if (!$this->mikrotikActif()) {
            Log::info("MikroTik désactivé (MIKROTIK_ENABLED=false) : coupure Internet de {$poste->nom_poste} ignorée.");
            return null;
        }
        if (!$poste->adresse_mac) {
            Log::info("Poste {$poste->nom_poste} : MAC absente, coupure réseau ignorée.");
            return null;
        }

        try {
            $coupe = $this->mikroTik->deconnecterPosteHotspot($poste->adresse_mac, $poste->hotspot_username);
            Log::info($coupe
                ? "MikroTik : Internet coupé pour {$poste->nom_poste}."
                : "MikroTik : aucune session HotSpot active pour {$poste->nom_poste} (déjà coupé).");
            return true;
        } catch (\Throwable $e) {
            Log::warning("MikroTik : coupure Internet de {$poste->nom_poste} échouée (mode dégradé, la session est tout de même terminée) : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Aligne MikroTik sur l'état réel des sessions (auto-réparation) :
     *  - poste avec session « en cours » mais sans session HotSpot → reconnecté ;
     *  - poste sans session « en cours » mais encore connecté → coupé (YouTube résiduel).
     * Rattrape un MikroTik tombé pendant une fermeture, ou redémarré en pleine session.
     *
     * @return array{connectes:int, coupes:int, erreurs:int}
     */
    public function reconcilierReseau(): array
    {
        $bilan = ['connectes' => 0, 'coupes' => 0, 'erreurs' => 0];

        if (!$this->mikrotikActif()) {
            return $bilan;
        }

        try {
            $actives = $this->mikroTik->listHotspotActive();
        } catch (\Throwable $e) {
            Log::warning('MikroTik injoignable : réconciliation réseau ignorée (' . $e->getMessage() . ').');
            $bilan['erreurs']++;
            return $bilan;
        }

        $macsActives = [];
        foreach ($actives as $ligne) {
            $mac = strtoupper((string) ($ligne['mac-address'] ?? ''));
            if ($mac !== '') {
                $macsActives[$mac] = true;
            }
        }

        $cache = Cache::store('file');

        foreach (Poste::whereNotNull('adresse_mac')->get() as $poste) {
            $session          = $poste->etat === Poste::ETAT_DESACTIVE ? null : $poste->sessionActive();
            $doitEtreConnecte = $session !== null && $session->etat === 'en_cours';
            $estConnecte      = isset($macsActives[strtoupper($poste->adresse_mac)]);

            if ($doitEtreConnecte && !$estConnecte) {
                if (!$poste->estEnLigne() || !$poste->hotspot_username) {
                    continue;   // PC éteint ou poste non configuré : rien à reconnecter
                }
                $cle = "reseau:reessai:{$poste->id}";
                if ($cache->has($cle)) {
                    continue;   // évite de répéter un échec toutes les 30 s
                }
                $res = $this->connecterReseau($poste);
                if ($res === true) {
                    $bilan['connectes']++;
                } elseif ($res === false) {
                    $bilan['erreurs']++;
                    $cache->put($cle, true, 120);
                }
            } elseif (!$doitEtreConnecte && $estConnecte) {
                $res = $this->deconnecterReseau($poste);
                if ($res === true) {
                    $bilan['coupes']++;
                } elseif ($res === false) {
                    $bilan['erreurs']++;
                }
            }
        }

        return $bilan;
    }
}