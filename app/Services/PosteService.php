<?php

namespace App\Services;

use App\Models\Poste;
use InvalidArgumentException;

class PosteService
{
    public function __construct(
        private AgentWindowsService $agentWindowsService
    ) {
    }

    public function verifierDisponibilite(Poste $poste): bool
    {
        return $poste->actif
            && $poste->etat === 'disponible';
    }

    public function testerAgent(Poste $poste): array
    {
        if (empty($poste->agent_url)) {
            throw new InvalidArgumentException(
                "Aucun agent Windows n'est configuré pour {$poste->nom_poste}."
            );
        }

        $resultat = $this->agentWindowsService->status($poste);

        if ($resultat['succes']) {
            $poste->update([
                'derniere_communication' => now(),
            ]);
        }

        return $resultat;
    }

    public function actualiserEtatAgent(Poste $poste): array
    {
        return $this->testerAgent($poste);
    }

    public function verrouillerPoste(Poste $poste): array
    {
        if (empty($poste->agent_url)) {
            throw new InvalidArgumentException(
                "Aucun agent Windows n'est configuré pour {$poste->nom_poste}."
            );
        }

        $resultat = $this->agentWindowsService->lockTest($poste);

        if ($resultat['succes']) {
            $poste->update([
                'derniere_communication' => now(),
            ]);
        }

        return $resultat;
    }

    public function deverrouillerPoste(Poste $poste): array
    {
        if (empty($poste->agent_url)) {
            throw new InvalidArgumentException(
                "Aucun agent Windows n'est configuré pour {$poste->nom_poste}."
            );
        }

        $resultat = $this->agentWindowsService->unlockTest($poste);

        if ($resultat['succes']) {
            $poste->update([
                'derniere_communication' => now(),
            ]);
        }

        return $resultat;
    }

    public function liberer(Poste $poste): Poste
    {
        $poste->update([
            'etat' => 'disponible',
        ]);

        return $poste->fresh();
    }
}