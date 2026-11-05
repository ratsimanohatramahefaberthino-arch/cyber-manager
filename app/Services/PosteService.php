<?php

namespace App\Services;

use App\Models\Poste;
use App\Models\Session;
use App\Models\Tarif;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class PosteService
{
    // ... (tes méthodes existantes testerAgent, verrouillerPoste, etc.)

    public function creerSession(Poste $poste, int $dureeMinutes, string $type): Session
    {
        $tarif = Tarif::where('cible', 'ethernet')->orWhere('cible', 'unifie')->first();

        return Session::create([
            'poste_id' => $poste->id,
            'type_session' => 'ethernet',
            'date_heure_debut' => now(),
            'date_heure_fin_prevue' => now()->addMinutes($dureeMinutes),
            'duree_prevue' => $dureeMinutes,
            'etat' => 'en_cours',
            'montant_initial' => 0,
            'montant_total' => 0,
            'tarif_id' => $tarif?->id,
            'description' => "Session {$type} de {$dureeMinutes} minutes.",
        ]);
    }

    /**
     * Envoie une commande à l'agent C#.
     */
    private function envoyerCommande(Poste $poste, string $commande): array
    {
        if (empty($poste->agent_url)) {
            throw new \Exception("Aucun agent configuré pour ce poste.");
        }

        try {
            $response = Http::withHeaders([
                'X-Agent-Token' => config('mikrotik.agent_token', 'CyberManagerAgent2026_Secret'),
                'Content-Type' => 'application/json'
            ])->timeout(5)->post($poste->agent_url . '/api/commande', [
                'commande' => $commande
            ]);

            if ($response->successful()) {
                return ['succes' => true, 'message' => $response->json('message')];
            }

            return ['succes' => false, 'message' => 'Réponse invalide de l\'agent.'];
        } catch (\Throwable $e) {
            return ['succes' => false, 'message' => $e->getMessage()];
        }
    }

    public function verrouillerPoste(Poste $poste): array
    {
        return $this->envoyerCommande($poste, 'LOCK');
    }

    public function deverrouillerPoste(Poste $poste): array
    {
        return $this->envoyerCommande($poste, 'UNLOCK');
    }

    public function pausePoste(Poste $poste): array
    {
        // Pour l'instant, on utilise LOCK comme pause. On pourra ajouter PAUSE plus tard.
        return $this->envoyerCommande($poste, 'LOCK');
    }

    public function arreterPoste(Poste $poste): array
    {
        return $this->envoyerCommande($poste, 'LOCK');
    }
}