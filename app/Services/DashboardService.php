<?php

namespace App\Services;

use App\Models\Poste;
use App\Models\Session;
use App\Models\Voucher;
use Carbon\Carbon;

/**
 * Agrège les indicateurs essentiels du cybercafé pour le Dashboard.
 * Calculs légers et robustes, sans requêtes complexes, avec mode dégradé si le routeur est éteint.
 */
class DashboardService
{
    public function __construct(
        private MikroTikService $mikroTik,
    ) {
    }

    public function getStats(): array
    {
        return [
            'recette'  => $this->getRecetteJour(),
            'postes'   => $this->getPosteStats(),
            'wifi'     => $this->getWifiStats(),
            'vouchers' => $this->getVoucherStats(),
            'recentes' => $this->getDernieresSessions(),
            'mikrotik' => $this->getMikroTikStatus(),
        ];
    }

    /**
     * Chiffre d'affaires et encaissements du jour.
     */
    private function getRecetteJour(): array
    {
        $debutJour = Carbon::today();

        $sessionsJour = Session::query()
            ->where('date_heure_debut', '>=', $debutJour)
            ->whereIn('etat', ['terminee', 'en_cours', 'en_pause'])
            ->get();

        $totalPostes = (int) $sessionsJour->where('type_session', 'ethernet')->sum('montant_total');
        $totalWifi   = (int) $sessionsJour->where('type_session', 'wifi')->sum('montant_total');

        return [
            'total'       => $totalPostes + $totalWifi,
            'postes'      => $totalPostes,
            'wifi'        => $totalWifi,
            'nb_sessions' => $sessionsJour->count(),
        ];
    }

    /**
     * État des postes PC en direct.
     */
    private function getPosteStats(): array
    {
        $total = Poste::count();
        $occupes = Poste::where('actif', true)
            ->whereIn('etat', ['occupe', 'en_utilisation'])
            ->count();
        $disponibles = Poste::where('actif', true)
            ->where('etat', 'disponible')
            ->count();
        $suspendus = Poste::where('actif', true)
            ->where('etat', 'suspendu')
            ->count();
        $horsLigne = Poste::where('actif', true)
            ->where(function ($q) {
                $q->whereNull('dernier_heartbeat')
                  ->orWhere('dernier_heartbeat', '<', now()->subSeconds(Poste::DELAI_HORS_LIGNE));
            })
            ->count();

        $taux = $total > 0 ? round(($occupes / $total) * 100) : 0;

        return [
            'total'       => $total,
            'occupes'     => $occupes,
            'disponibles' => $disponibles,
            'suspendus'   => $suspendus,
            'hors_ligne'  => $horsLigne,
            'taux'        => $taux,
        ];
    }

    /**
     * Activité Wi-Fi en direct (clients actifs).
     */
    private function getWifiStats(): array
    {
        $sessionsEnCours = Session::where('type_session', 'wifi')
            ->whereIn('etat', ['en_cours', 'en_pause'])
            ->count();

        $actifsRouteur = 0;
        try {
            $actives = $this->mikroTik->listHotspotActive();
            $actifsRouteur = is_array($actives) ? count($actives) : 0;
        } catch (\Throwable) {
            $actifsRouteur = $sessionsEnCours;
        }

        return [
            'clients_actifs' => max($sessionsEnCours, $actifsRouteur),
        ];
    }

    /**
     * Vouchers et tickets d'accès Wi-Fi.
     */
    private function getVoucherStats(): array
    {
        return [
            'disponibles' => Voucher::where('etat', 'disponible')->count(),
            'total'       => Voucher::count(),
        ];
    }

    /**
     * Les 5 dernières sessions pour un aperçu rapide.
     */
    private function getDernieresSessions(): array
    {
        return Session::with(['poste'])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($s) {
                $nom = $s->type_session === 'ethernet'
                    ? ($s->poste?->nom_poste ?? 'Poste PC')
                    : ($s->client_mac ? 'Wi-Fi (' . substr($s->client_mac, -5) . ')' : 'Client Wi-Fi');

                return [
                    'id'          => $s->id,
                    'nom'         => $nom,
                    'type'        => $s->type_session,
                    'etat'        => $s->etat,
                    'montant'     => (int) ($s->montant_total ?? 0),
                    'duree_min'   => max(1, (int) round($s->secondesConsommees() / 60)),
                    'date_formatee' => $s->date_heure_debut?->locale('fr')->diffForHumans() ?? '—',
                ];
            })
            ->toArray();
    }

    /**
     * Statut de la liaison MikroTik.
     */
    private function getMikroTikStatus(): array
    {
        try {
            $info = $this->mikroTik->testConnection();

            return [
                'connecte' => true,
                'identity' => $info['identity'] ?? 'MikroTik',
                'version'  => $info['version'] ?? '',
                'board'    => $info['board_name'] ?? '',
                'erreur'   => null,
            ];
        } catch (\Throwable $e) {
            return [
                'connecte' => false,
                'identity' => null,
                'version'  => null,
                'board'    => null,
                'erreur'   => $e->getMessage(),
            ];
        }
    }
}