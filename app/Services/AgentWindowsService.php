<?php

namespace App\Services;

use App\Models\Poste;
use Illuminate\Support\Facades\Http;

class AgentWindowsService
{
    /**
     * Envoie une commande à l'agent Windows d'un poste.
     */
    public function envoyerCommande(
        string $adresse,
        string $commande,
        array $parametres = []
    ): array {
        $token = config('services.agent_windows.token');

        if (empty($token)) {
            return [
                'succes' => false,
                'message' => 'Le token de l’agent Windows n’est pas configuré.',
            ];
        }

        try {
            $reponse = Http::timeout(5)
                ->withHeaders([
                    'X-Agent-Token' => $token,
                ])
                ->post($adresse . '/api/commande', [
                    'commande' => $commande,
                    'parametres' => (object) $parametres,
                ]);

            if ($reponse->failed()) {
                return [
                    'succes' => false,
                    'message' => 'Impossible de communiquer avec l’agent Windows.',
                    'code_http' => $reponse->status(),
                    'details' => $reponse->body(),
                ];
            }

            return [
                'succes' => true,
                'reponse' => $reponse->json(),
            ];
        } catch (\Throwable $exception) {
            return [
                'succes' => false,
                'message' => 'Erreur de communication avec l’agent Windows.',
                'details' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Vérifie que l'agent Windows répond.
     */
    public function ping(Poste $poste): array
    {
        return $this->envoyerCommande(
            $this->adresseAgent($poste),
            'PING'
        );
    }

    /**
     * Récupère l'état de l'agent Windows.
     */
    public function status(Poste $poste): array
    {
        return $this->envoyerCommande(
            $this->adresseAgent($poste),
            'STATUS'
        );
    }

    /**
     * Teste le verrouillage du poste.
     * Pour le moment, l'agent effectue uniquement une simulation.
     */
    public function lockTest(Poste $poste): array
    {
        return $this->envoyerCommande(
            $this->adresseAgent($poste),
            'LOCK_TEST'
        );
    }

    /**
     * Teste le déverrouillage du poste.
     * Pour le moment, l'agent effectue uniquement une simulation.
     */
    public function unlockTest(Poste $poste): array
    {
        return $this->envoyerCommande(
            $this->adresseAgent($poste),
            'UNLOCK_TEST'
        );
    }

    /**
     * Récupère et vérifie l'adresse de l'agent.
     */
    private function adresseAgent(Poste $poste): string
    {
        if (empty($poste->agent_url)) {
            throw new \InvalidArgumentException(
                "Aucune adresse d'agent Windows n'est configurée pour {$poste->nom_poste}."
            );
        }

        return rtrim($poste->agent_url, '/');
    }
}