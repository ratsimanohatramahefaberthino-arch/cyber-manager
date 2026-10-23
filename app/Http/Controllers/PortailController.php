<?php

namespace App\Http\Controllers;

use App\Models\PortailParametre;
use App\Services\MikroTikService;
use App\Services\TarifService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortailController extends Controller
{
    public function __construct(
        private MikroTikService $mikroTik,
        private TarifService $tarifService
    ) {}

    // =================================================================
    // CÔTÉ ADMIN
    // =================================================================

    public function adminIndex()
    {
        $tarifWifi = $this->tarifService->tarifWifi();

        return view('portail.admin', [
            'logo' => PortailParametre::get('logo'),
            'titre' => PortailParametre::get('titre', 'Bienvenue au Cybercafé'),
            'message' => PortailParametre::get('message', 'Veuillez entrer vos identifiants pour vous connecter.'),
            'tarifWifi' => $tarifWifi,
        ]);
    }

    public function adminSave(Request $request)
    {
        $request->validate([
            'titre' => 'required|string|max:255',
            'message' => 'required|string',
            'logo' => 'nullable|image|max:2048', // max 2Mo
        ]);

        PortailParametre::set('titre', $request->titre);
        PortailParametre::set('message', $request->message);

        if ($request->hasFile('logo')) {
            $ancienLogo = PortailParametre::get('logo');
            if ($ancienLogo) Storage::disk('public')->delete($ancienLogo);

            $path = $request->file('logo')->store('portail', 'public');
            PortailParametre::set('logo', $path);
        }

        return back()->with('success', 'Réglages du portail enregistrés.');
    }

    public function configurerRouteur()
    {
        $ipLocale = $this->trouverIpLocale();
        $port = request()->getPort();

        if ($ipLocale === '127.0.0.1' || str_starts_with($ipLocale, '169.254')) {
            return back()->with('error', "L'IP détectée ({$ipLocale}) est invalide. Allez dans votre fichier .env et ajoutez SERVER_IP=128.0.1.214 (votre vraie IP).");
        }

        try {
            $this->mikroTik->configurerWalledGarden($ipLocale, (string)$port);
            $this->mikroTik->configurerDnsLocal('inside.net', $ipLocale);
            $this->mikroTik->configurerDnsNameHotspot('inside.net');

            return back()->with('success', "Routeur configuré ! Portail accessible sur http://inside.net:{$port}/portail");
            
        } catch (\Throwable $e) {
            return back()->with('error', "Erreur : " . $e->getMessage());
        }
    }

    private function trouverIpLocale(): string
    {
        if (env('SERVER_IP')) {
            return env('SERVER_IP');
        }

        $ipRequete = request()->server('SERVER_ADDR');
        if ($ipRequete && $ipRequete !== '127.0.0.1' && $ipRequete !== '::1') {
            return $ipRequete;
        }

        $ips = gethostbynamel(gethostname());
        if ($ips) {
            foreach ($ips as $ip) {
                if (!str_starts_with($ip, '127.') && !str_starts_with($ip, '169.254.')) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    // =================================================================
    // CÔTÉ CLIENT (Code optimisé par Claude)
    // =================================================================

    /** Valide et normalise une MAC (évite toute injection dans le JS / les filtres RouterOS). */
    private function macValide(?string $mac): ?string
    {
        if (!$mac) {
            return null;
        }
        $mac = strtoupper(str_replace('-', ':', trim($mac)));

        return preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac) ? $mac : null;
    }

    /** Messages MikroTik (anglais) → messages clairs pour le client. */
    private function traduireErreur(?string $brut): ?string
    {
        if (!$brut) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/invalid (username|password)/i', $brut)  => 'Identifiant ou mot de passe incorrect.',
            (bool) preg_match('/(uptime|time) limit/i', $brut)          => 'Le temps de ce compte est épuisé.',
            (bool) preg_match('/(bytes|data|traffic) limit/i', $brut)   => 'Le volume de données de ce compte est épuisé.',
            (bool) preg_match('/already|simultaneous|active/i', $brut)  => 'Ce compte est déjà utilisé sur un autre appareil.',
            (bool) preg_match('/disabled/i', $brut)                     => 'Ce compte est désactivé. Adressez-vous à la caisse.',
            default => 'Connexion refusée. Vérifiez vos informations ou demandez de l\'aide à la caisse.',
        };
    }

    public function login(Request $request)
    {
        $hote = config('mikrotik.host');

        $loginUrl = "http://{$hote}/login";
        $lien = $request->query('link-login-only');
        if ($lien && ($p = parse_url($lien)) && in_array($p['host'] ?? '', [$hote, 'inside.net'], true)
            && in_array($p['scheme'] ?? '', ['http', 'https'], true)) {
            $loginUrl = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . ($p['path'] ?? '/login');
        }

        $mac = $this->macValide($request->query('mac'));
        $dst = url(route('portail.dashboard', array_filter(['mac' => $mac]), false));

        $tarif = $this->tarifService->tarifWifi();
        $logo  = PortailParametre::get('logo');

        return response()->view('portail.login', [
            'logo'           => $logo ? Storage::url($logo) : null,
            'titre'          => PortailParametre::get('titre', 'Bienvenue'),
            'message'        => PortailParametre::get('message'),
            'login_url'      => $loginUrl,
            'dst'            => $dst,
            'erreur'         => $this->traduireErreur($request->query('error')),
            'tarif_minute'   => $tarif?->montant_par_minute,
            'tarif_minimum'  => $tarif?->montant_minimum,
        ])->header('Cache-Control', 'no-store, max-age=0');
    }

    public function dashboard(Request $request)
    {
        $tarif = $this->tarifService->tarifWifi();
        $logo  = PortailParametre::get('logo');

        return response()->view('portail.dashboard', [
            'mac'          => $this->macValide($request->query('mac')),
            'mikrotik_ip'  => config('mikrotik.host'),
            'logo'         => $logo ? Storage::url($logo) : null,
            'titre'        => PortailParametre::get('titre'),
            'tarif_minute' => $tarif?->montant_par_minute ?? 20,
        ])->header('Cache-Control', 'no-store, max-age=0');
    }

    public function apiStats(Request $request)
    {
        $mac    = $this->macValide($request->query('mac'));
        $filtre = $mac ? ['mac-address' => $mac] : ['address' => $request->ip()];

        try {
            $session = $this->mikroTik->queryFiltered('/ip/hotspot/active/print', $filtre)[0] ?? null;

            if (!$session) {
                return response()->json(['connected' => false]);
            }

            $secondes = $this->parseUptime($session['uptime'] ?? '0s');
            $minutes  = intdiv($secondes, 60);

            $tarif   = $this->tarifService->tarifWifi();
            $parMin  = max(1, (int) ($tarif->montant_par_minute ?? 20));
            $minimum = (int) ($tarif->montant_minimum ?? 300);

            $montant = $tarif
                ? $this->tarifService->montantPourMinutes($minutes, $tarif)
                : max($minutes * $parMin, $minimum);

            return response()->json([
                'connected'        => true,
                'uptime'           => $secondes,
                'bytes_down'       => (int) ($session['bytes-out'] ?? 0), // Download
                'bytes_up'         => (int) ($session['bytes-in'] ?? 0),  // Upload
                'montant'          => $montant,
                'minimum_applique' => $minutes * $parMin < $minimum,
                'tarif_minute'     => $parMin,
                'tarif_minimum'    => $minimum,
            ])->header('Cache-Control', 'no-store');

        } catch (\Throwable $e) {
            return response()->json(['error' => 'Routeur injoignable'], 503);
        }
    }

    private function parseUptime(string $uptime): int
    {
        $t = 0;
        if (preg_match('/(\d+)w/', $uptime, $m)) $t += (int) $m[1] * 604800;
        if (preg_match('/(\d+)d/', $uptime, $m)) $t += (int) $m[1] * 86400;
        if (preg_match('/(\d+)h/', $uptime, $m)) $t += (int) $m[1] * 3600;
        if (preg_match('/(\d+)m(?!s)/', $uptime, $m)) $t += (int) $m[1] * 60;
        if (preg_match('/(\d+)s/', $uptime, $m)) $t += (int) $m[1];
        return $t;
    }
}