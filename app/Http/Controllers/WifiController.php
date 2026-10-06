<?php

namespace App\Http\Controllers;

use App\Services\MikroTikService;

class WifiController extends Controller
{
    public function __construct(
        private MikroTikService $mikroTik,
    ) {
    }

    public function index()
    {
        try {
            $active = $this->mikroTik->listHotspotActive();
            $hosts  = $this->mikroTik->listHotspotHosts();
            $leases = $this->mikroTik->listDhcpLeases();
            $erreur = null;
        } catch (\Throwable $e) {
            $active = [];
            $hosts  = [];
            $leases = [];
            $erreur = $e->getMessage();
        }

        return view('wifi.index', [
            'active' => $active,
            'hosts'  => $hosts,
            'leases' => $leases,
            'erreur' => $erreur,
        ]);
    }

    public function comptes()
    {
        try {
            $users = $this->mikroTik->listHotspotUsers();
            $erreur = null;
        } catch (\Throwable $e) {
            $users = [];
            $erreur = $e->getMessage();
        }

        $prefix = config('mikrotik.hotspot_user_prefix', 'cm-');

        $comptes = collect($users)->map(function ($u) use ($prefix) {
            $u['est_cyber_manager'] = str_starts_with($u['name'] ?? '', $prefix);
            return $u;
        });

        // Tri : Cyber Manager d'abord, puis alphabétique
        $comptes = $comptes->sortBy([
            fn($a, $b) => ($b['est_cyber_manager'] ?? false) <=> ($a['est_cyber_manager'] ?? false),
            fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''),
        ])->values();

        return view('wifi.comptes', [
            'comptes' => $comptes,
            'erreur'  => $erreur,
        ]);
    }

        /**
     * Force une synchronisation immédiate des sessions Wi-Fi.
     */
    public function syncNow(\App\Services\WifiSessionService $sync)
    {
        $result = $sync->synchroniser();

        if ($result['erreur']) {
            return back()->withErrors([
                'global' => 'MikroTik injoignable : ' . $result['erreur'],
            ]);
        }

        $msg = "Sync : {$result['ouvertes']} ouverte(s), " .
               "{$result['maj']} mise(s) à jour, " .
               "{$result['fermees']} fermée(s).";

        return back()->with('success', $msg);
    }

    /**
     * Déconnecte un client actif.
     */
    public function deconnecter(\Illuminate\Http\Request $request)
    {
        $mac = (string) $request->input('mac');

        if ($mac === '') {
            return back()->withErrors(['global' => 'MAC manquante.']);
        }

        try {
            $ok = $this->mikroTik->disconnectHotspotActiveByMac($mac);
        } catch (\Throwable $e) {
            return back()->withErrors(['global' => $e->getMessage()]);
        }

        if (!$ok) {
            return back()->withErrors([
                'global' => "Aucune session active avec la MAC {$mac}.",
            ]);
        }

        return back()->with(
            'success',
            'Client déconnecté. La session sera fermée à la prochaine sync.'
        );
    }

    /**
     * Désactive / réactive un compte HotSpot.
     */
    public function toggleCompte(string $username)
    {
        try {
            $user = $this->mikroTik->findHotspotUser($username);

            if ($user === null) {
                return back()->withErrors([
                    'global' => "Compte {$username} introuvable sur le MikroTik.",
                ]);
            }

            $estDesactive = ($user['disabled'] ?? 'false') === 'true';
            $nouveau = !$estDesactive;

            $this->mikroTik->setHotspotUserDisabled($username, $nouveau);

            return back()->with(
                'success',
                "Compte {$username} " . ($nouveau ? 'désactivé' : 'réactivé') . '.'
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['global' => $e->getMessage()]);
        }
    }

    /**
     * Supprime un compte HotSpot.
     */
    public function supprimerCompte(string $username)
    {
        try {
            $ok = $this->mikroTik->deleteHotspotUser($username);

            if (!$ok) {
                return back()->withErrors([
                    'global' => "Compte {$username} introuvable.",
                ]);
            }

            return back()->with('success', "Compte {$username} supprimé.");
        } catch (\Throwable $e) {
            return back()->withErrors(['global' => $e->getMessage()]);
        }
    }
}