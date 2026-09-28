<?php

namespace App\Services;

use App\Models\Session;

class SessionExpirationService
{
    public function __construct(
        private SessionService $sessionService
    ) {
    }

    public function verifierSessions(): int
    {
        $sessions = Session::where('etat', 'en_cours')->get();

        $expirees = 0;

        foreach ($sessions as $session) {
            $etatAvant = $session->etat;

            $sessionMiseAJour = $this->sessionService
                ->mettreAJourConsommation($session);

            if (
                $etatAvant === 'en_cours'
                && $sessionMiseAJour->etat === 'expiree'
            ) {
                $expirees++;
            }
        }

        return $expirees;
    }
}