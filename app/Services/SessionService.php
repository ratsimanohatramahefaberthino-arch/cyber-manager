<?php

namespace App\Services;

use App\Models\Poste;
use App\Models\Recharge;
use App\Models\Restitution;
use App\Models\Session;

class SessionService
{
    public function __construct(
        private AgentWindowsService $agentWindowsService,
        private TarifService $tarifService
    ) {
    }

    public function calculerDuree(
        int $montant,
        ?\App\Models\Tarif $tarif = null
    ): int {
        return $this->tarifService->calculerDuree(
            $montant,
            $tarif
        );
    }

    public function creerSessionEthernet(
        Poste $poste,
        int $montant,
        ?string $description = null
    ): Session {
        if (!$poste->actif) {
            throw new \InvalidArgumentException(
                "Le poste {$poste->nom_poste} est désactivé."
            );
        }

        if ($poste->etat !== 'disponible') {
            throw new \InvalidArgumentException(
                "Le poste {$poste->nom_poste} n'est pas disponible."
            );
        }

        $tarif = $this->tarifService->tarifStandard();

$duree = $this->calculerDuree($montant, $tarif);

        $debut = now();
        $finPrevue = $debut->copy()->addMinutes($duree);

        $session = Session::create([
            'poste_id' => $poste->id,
            'tarif_id' => $tarif->id,
            'type_session' => 'ethernet',
            'date_heure_debut' => $debut,
            'date_heure_fin_prevue' => $finPrevue,
            'duree_prevue' => $duree,
            'duree_consommee' => 0,
            'temps_restant' => $duree,
            'duree_suspension' => 0,
            'montant_initial' => $montant,
            'montant_recharge' => 0,
            'montant_total' => $montant,
            'montant_consomme' => 0,
            'montant_restant' => $montant,
            'montant_a_reverser' => 0,
            'description' => $description,
            'etat' => 'en_cours',
            'volume_entree' => 0,
            'volume_sortie' => 0,
            'volume_total' => 0,
        ]);

        $poste->update([
            'etat' => 'en_utilisation',
        ]);

        $this->deverrouillerSiAgentDisponible($poste);

        return $session;
    }

    public function mettreAJourConsommation(Session $session): Session
    {
        if (!$session->date_heure_debut) {
            return $session;
        }

        if ($session->etat !== 'en_cours') {
            return $session;
        }

        $maintenant = now();

        if (
            $session->date_heure_fin_prevue &&
            $maintenant->greaterThanOrEqualTo($session->date_heure_fin_prevue)
        ) {
            $session->update([
                'date_heure_fin_reelle' => $maintenant,
                'duree_consommee' => $session->duree_prevue,
                'temps_restant' => 0,
                'montant_consomme' => $session->montant_total,
                'montant_restant' => 0,
                'montant_a_reverser' => 0,
                'etat' => 'expiree',
                'motif_fin' => 'expiration',
            ]);

            if ($session->poste) {
                $session->poste->update([
                    'etat' => 'disponible',
                ]);

                $this->verrouillerSiAgentDisponible($session->poste);
            }

            return $session->fresh();
        }

        $secondesEcoulees =
            $session->date_heure_debut->diffInSeconds($maintenant);

        $secondesActives =
            $secondesEcoulees - $session->duree_suspension;

        if ($secondesActives < 0) {
            $secondesActives = 0;
        }

        $minutesConsommees =
            (int) floor($secondesActives / 60);

        if ($minutesConsommees < 0) {
            $minutesConsommees = 0;
        }

        if ($minutesConsommees > $session->duree_prevue) {
            $minutesConsommees = $session->duree_prevue;
        }

        $tempsRestant =
            $session->duree_prevue - $minutesConsommees;

        $tarif = $session->tarif;

        $montantConsomme = $tarif
            ? $minutesConsommees * $tarif->montant_par_minute
            : $minutesConsommees * 20;

        if ($montantConsomme > $session->montant_total) {
            $montantConsomme = $session->montant_total;
        }

        $montantRestant =
            $session->montant_total - $montantConsomme;

        $session->update([
            'duree_consommee' => $minutesConsommees,
            'temps_restant' => $tempsRestant,
            'montant_consomme' => $montantConsomme,
            'montant_restant' => $montantRestant,
        ]);

        return $session->fresh();
    }

    public function arreterSession(
        Session $session,
        string $motifFin = 'arret_client'
    ): Session {
        if ($session->etat !== 'en_cours') {
            throw new \InvalidArgumentException(
                "La session n'est pas en cours."
            );
        }

        $session = $this->mettreAJourConsommation($session);

        if ($session->etat !== 'en_cours') {
            return $session;
        }

        $montantAReverser = $session->montant_restant;

        $session->update([
            'date_heure_fin_reelle' => now(),
            'duree_consommee' => $session->duree_consommee,
            'temps_restant' => $session->temps_restant,
            'montant_consomme' => $session->montant_consomme,
            'montant_restant' => $session->montant_restant,
            'montant_a_reverser' => $montantAReverser,
            'etat' => 'terminee',
            'motif_fin' => $motifFin,
        ]);

        if ($montantAReverser > 0) {
            Restitution::create([
                'session_id' => $session->id,
                'date_heure' => now(),
                'montant' => $montantAReverser,
                'motif' => $motifFin,
                'description' =>
                    'Restitution du montant restant après arrêt de la session.',
            ]);
        }

        if ($session->poste) {
            $session->poste->update([
                'etat' => 'disponible',
            ]);

            $this->verrouillerSiAgentDisponible($session->poste);
        }

        return $session->fresh();
    }

    public function rechargerSession(
        Session $session,
        int $montant,
        ?string $description = null
    ): Session {
        if ($session->etat !== 'en_cours') {
            throw new \InvalidArgumentException(
                "La session n'est pas en cours."
            );
        }

        if ($montant <= 0) {
            throw new \InvalidArgumentException(
                "Le montant de recharge doit être supérieur à 0."
            );
        }

        $session = $this->mettreAJourConsommation($session);

        if ($session->etat !== 'en_cours') {
            throw new \InvalidArgumentException(
                "La session a expiré avant la recharge."
            );
        }

        $dureeAjoutee = $this->calculerDuree(
            $montant,
            $session->tarif
        );

        $session->update([
            'montant_recharge' =>
                $session->montant_recharge + $montant,

            'montant_total' =>
                $session->montant_total + $montant,

            'duree_prevue' =>
                $session->duree_prevue + $dureeAjoutee,

            'temps_restant' =>
                $session->temps_restant + $dureeAjoutee,

            'montant_restant' =>
                $session->montant_restant + $montant,

            'date_heure_fin_prevue' =>
                $session->date_heure_fin_prevue
                    ->copy()
                    ->addMinutes($dureeAjoutee),
        ]);

        Recharge::create([
            'session_id' => $session->id,
            'date_heure' => now(),
            'montant' => $montant,
            'duree_ajoutee' => $dureeAjoutee,
            'description' => $description,
        ]);

        return $session->fresh();
    }

    public function suspendreSession(Session $session): Session
    {
        if ($session->etat !== 'en_cours') {
            throw new \InvalidArgumentException(
                "La session n'est pas en cours."
            );
        }

        $session = $this->mettreAJourConsommation($session);

        if ($session->etat !== 'en_cours') {
            return $session;
        }

        $maintenant = now();

        $session->update([
            'date_heure_suspension' => $maintenant,
            'etat' => 'suspendue',
        ]);

        if ($session->poste) {
            $session->poste->update([
                'etat' => 'suspendu',
            ]);

            $this->verrouillerSiAgentDisponible($session->poste);
        }

        return $session->fresh();
    }

    public function reprendreSession(Session $session): Session
    {
        if ($session->etat !== 'suspendue') {
            throw new \InvalidArgumentException(
                "La session n'est pas suspendue."
            );
        }

        if (!$session->date_heure_suspension) {
            throw new \InvalidArgumentException(
                "La date de suspension est introuvable."
            );
        }

        $maintenant = now();

        $dureeSuspensionActuelle =
            $session->date_heure_suspension
                ->diffInSeconds($maintenant);

        $dureeSuspensionTotale =
            $session->duree_suspension
            + $dureeSuspensionActuelle;

        $nouvelleFinPrevue =
            $maintenant->copy()
                ->addSeconds($session->temps_restant * 60);

        $session->update([
            'date_heure_reprise' => $maintenant,
            'date_heure_fin_prevue' => $nouvelleFinPrevue,
            'duree_suspension' => $dureeSuspensionTotale,
            'etat' => 'en_cours',
        ]);

        if ($session->poste) {
            $session->poste->update([
                'etat' => 'en_utilisation',
            ]);

            $this->deverrouillerSiAgentDisponible($session->poste);
        }

        return $session->fresh();
    }

    private function verrouillerSiAgentDisponible(Poste $poste): void
    {
        if (empty($poste->agent_url)) {
            return;
        }

        $this->agentWindowsService->lockTest($poste);
    }

    private function deverrouillerSiAgentDisponible(Poste $poste): void
    {
        if (empty($poste->agent_url)) {
            return;
        }

        $this->agentWindowsService->unlockTest($poste);
    }
}