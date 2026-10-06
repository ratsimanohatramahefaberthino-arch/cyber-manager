<?php

namespace App\Services;

use App\Models\Poste;
use App\Models\Session;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * Agrège les statistiques affichées sur le dashboard.
 *
 * Ne contient AUCUN SQL complexe en dehors de simples count().
 * Ne touche PAS aux Controllers.
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
            'mikrotik' => $this->getMikroTikStatus(),
            'postes'   => $this->getPosteStats(),
            'hotspot'  => $this->getHotspotStats(),
            'vouchers' => $this->getVoucherStats(),
            'sessions' => $this->getSessionStats(),
        ];
    }

    /**
     * Ping MikroTik en direct. Ne lance JAMAIS d'exception :
     * en cas d'échec, retourne connecte=false + message.
     */
    private function getMikroTikStatus(): array
    {
        try {
            $info    = $this->mikroTik->testConnection();
            $users   = $this->mikroTik->listHotspotUsers();
            $active  = $this->mikroTik->listHotspotActive();
            $profil  = $this->mikroTik->listHotspotProfiles();

            return [
                'connecte' => true,
                'identity' => $info['identity']   ?? '?',
                'version'  => $info['version']    ?? '?',
                'board'    => $info['board_name'] ?? '?',
                'uptime'   => $info['uptime']     ?? '?',
                'users'    => count($users),
                'active'   => count($active),
                'profiles' => count($profil),
                'erreur'   => null,
            ];
        } catch (\Throwable $e) {
            return [
                'connecte' => false,
                'identity' => null,
                'version'  => null,
                'board'    => null,
                'uptime'   => null,
                'users'    => 0,
                'active'   => 0,
                'profiles' => 0,
                'erreur'   => $e->getMessage(),
            ];
        }
    }

    private function getPosteStats(): array
    {
        return [
            'total'           => Poste::count(),
            'disponible'      => Poste::where('actif', true)
                                       ->where('etat', 'disponible')
                                       ->count(),
            'en_utilisation'  => Poste::where('etat', 'en_utilisation')->count(),
            'suspendu'        => Poste::where('etat', 'suspendu')->count(),
            'inactif'         => Poste::where('actif', false)->count(),
        ];
    }

    /**
     * Stats purement HotSpot (comptées depuis MikroTik).
     * Séparé de MikroTikStatus pour lisibilité du blade.
     */
    private function getHotspotStats(): array
    {
        try {
            $users = $this->mikroTik->listHotspotUsers();
            $cmUsers = $this->mikroTik->listCyberManagerHotspotUsers();

            return [
                'users_total'  => count($users),
                'users_cm'     => count($cmUsers),
                'users_autres' => count($users) - count($cmUsers),
            ];
        } catch (\Throwable) {
            return [
                'users_total'  => 0,
                'users_cm'     => 0,
                'users_autres' => 0,
            ];
        }
    }

    private function getVoucherStats(): array
    {
        return [
            'total'      => Voucher::count(),
            'disponible' => Voucher::where('etat', 'disponible')->count(),
            'utilise'    => Voucher::where('etat', 'utilise')->count(),
            'annule'     => Voucher::where('etat', 'annule')->count(),
            'non_sync'   => Voucher::whereNull('mikrotik_synced_at')
                                   ->where('etat', '!=', 'annule')
                                   ->count(),
        ];
    }

    private function getSessionStats(): array
    {
        return [
            'en_cours'   => Session::where('etat', 'en_cours')->count(),
            'suspendue'  => Session::where('etat', 'suspendue')->count(),
            'aujourdhui' => Session::whereDate('date_heure_debut', today())->count(),
        ];
    }
}