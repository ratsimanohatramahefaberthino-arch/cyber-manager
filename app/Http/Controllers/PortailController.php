<?php

namespace App\Http\Controllers;

use App\Models\AppareilWifi;
use App\Models\PortailParametre;
use App\Models\Session;
use App\Services\MikroTikService;
use App\Services\TarifService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PortailController extends Controller
{
    public function __construct(
        private MikroTikService $mikroTik,
        private TarifService $tarifService
    ) {}

    // =================================================================
    // VALEURS PAR DÉFAUT DU THÈME DU PORTAIL
    // =================================================================

    public static function parametresParDefaut(): array
    {
        return [
            'titre'               => 'Bienvenue au Cybercafé',
            'titre_taille'        => '26',
            'titre_couleur'       => '#ffffff',
            'message'             => 'Veuillez vous connecter avec vos identifiants pour accéder à Internet.',
            'message_couleur'     => '#b9d3d4',
            'police'              => 'Inter, system-ui, sans-serif',
            'fond_type'           => 'gradient', // gradient ou solid
            'fond_couleur_1'      => '#0b1f24',
            'fond_couleur_2'      => '#1b5c63',
            'carte_couleur'       => '#f4f7f6',
            'carte_texte'         => '#10262b',
            'bouton_fond'         => '#ffb020',
            'bouton_texte'        => '#2b1c00',
            'bouton_rayon'        => '16', // px
            'accent_couleur'      => '#3dd6d0',
            'annonce_active'      => '0',
            'annonce_texte'       => '🎉 Profitez d\'une connexion haut débit stable et sécurisée !',
            'annonce_fond'        => '#ffb020',
            'annonce_texte_color' => '#2b1c00',
            'footer_texte'        => 'Demandez votre ticket d\'accès ou votre recharge à la caisse.',
            'logo'                => null,
        ];
    }

    private function getConfigurationPortail(): array
    {
        $defauts = self::parametresParDefaut();
        $config = [];

        foreach ($defauts as $cle => $valeurDefaut) {
            $config[$cle] = PortailParametre::get($cle, $valeurDefaut);
        }

        return $config;
    }

    // =================================================================
    // CÔTÉ ADMINISTRATION
    // =================================================================

    public function adminIndex()
    {
        $tarifWifi = $this->tarifService->tarifWifi();
        $config = $this->getConfigurationPortail();

        return view('portail.admin', [
            'config'    => $config,
            'tarifWifi' => $tarifWifi,
        ]);
    }

    public function adminSave(Request $request)
    {
        $request->validate([
            'titre'               => 'required|string|max:255',
            'titre_taille'        => 'required|integer|min:16|max:48',
            'titre_couleur'       => 'required|string|max:20',
            'message'             => 'required|string|max:1000',
            'message_couleur'     => 'required|string|max:20',
            'police'              => 'required|string|max:100',
            'fond_type'           => 'required|in:gradient,solid',
            'fond_couleur_1'      => 'required|string|max:20',
            'fond_couleur_2'      => 'required|string|max:20',
            'carte_couleur'       => 'required|string|max:20',
            'carte_texte'         => 'required|string|max:20',
            'bouton_fond'         => 'required|string|max:20',
            'bouton_texte'        => 'required|string|max:20',
            'bouton_rayon'        => 'required|integer|min:0|max:40',
            'accent_couleur'      => 'required|string|max:20',
            'annonce_active'      => 'nullable|in:0,1',
            'annonce_texte'       => 'nullable|string|max:255',
            'annonce_fond'        => 'nullable|string|max:20',
            'annonce_texte_color' => 'nullable|string|max:20',
            'footer_texte'        => 'nullable|string|max:255',
            'logo'                => 'nullable|image|max:2048',
        ]);

        $champs = [
            'titre', 'titre_taille', 'titre_couleur', 'message', 'message_couleur',
            'police', 'fond_type', 'fond_couleur_1', 'fond_couleur_2', 'carte_couleur',
            'carte_texte', 'bouton_fond', 'bouton_texte', 'bouton_rayon', 'accent_couleur',
            'annonce_active', 'annonce_texte', 'annonce_fond', 'annonce_texte_color', 'footer_texte'
        ];

        foreach ($champs as $champ) {
            PortailParametre::set($champ, $request->input($champ, self::parametresParDefaut()[$champ] ?? ''));
        }

        // Gestion du logo
        if ($request->hasFile('logo')) {
            $ancienLogo = PortailParametre::get('logo');
            if ($ancienLogo && Storage::disk('public')->exists($ancienLogo)) {
                Storage::disk('public')->delete($ancienLogo);
            }
            $path = $request->file('logo')->store('portail', 'public');
            PortailParametre::set('logo', $path);
        } elseif ($request->boolean('supprimer_logo')) {
            $ancienLogo = PortailParametre::get('logo');
            if ($ancienLogo && Storage::disk('public')->exists($ancienLogo)) {
                Storage::disk('public')->delete($ancienLogo);
            }
            PortailParametre::set('logo', null);
        }

        return back()->with('success', 'Paramètres et design du portail enregistrés avec succès.');
    }

    public function adminResetDefaults()
    {
        $defauts = self::parametresParDefaut();
        foreach ($defauts as $cle => $valeur) {
            if ($cle !== 'logo') {
                PortailParametre::set($cle, $valeur);
            }
        }
        return back()->with('success', 'Les styles par défaut ont été restaurés.');
    }

    public function configurerRouteur()
    {
        $ipLocale = $this->trouverIpLocale();
        $port = request()->getPort();

        if ($ipLocale === '127.0.0.1' || str_starts_with($ipLocale, '169.254')) {
            return back()->with('error', "L'IP détectée ({$ipLocale}) est locale ou invalide. Renseignez SERVER_IP dans votre fichier .env.");
        }

        try {
            $this->mikroTik->configurerWalledGarden($ipLocale, (string) $port);
            $this->mikroTik->configurerDnsLocal('inside.net', $ipLocale);
            $this->mikroTik->configurerDnsNameHotspot('inside.net');

            return back()->with('success', "Routeur MikroTik configuré ! Portail accessible sur http://inside.net:{$port}/portail");
        } catch (\Throwable $e) {
            return back()->with('error', "Erreur MikroTik : " . $e->getMessage());
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
    // CÔTÉ CLIENT (PORTAIL CAPTIF)
    // =================================================================

    private function macValide(?string $mac): ?string
    {
        if (!$mac) return null;
        $mac = strtoupper(str_replace('-', ':', trim($mac)));
        return preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac) ? $mac : null;
    }

    private function traduireErreur(?string $brut): ?string
    {
        if (!$brut) return null;

        return match (true) {
            (bool) preg_match('/invalid (username|password)/i', $brut) => 'Identifiant ou mot de passe incorrect.',
            (bool) preg_match('/(uptime|time) limit/i', $brut)         => 'Le temps accordé à ce ticket est épuisé.',
            (bool) preg_match('/(bytes|data|traffic) limit/i', $brut)  => 'Le quota de données de ce ticket est épuisé.',
            (bool) preg_match('/already|simultaneous|active/i', $brut) => 'Ce compte est déjà utilisé sur un autre appareil.',
            (bool) preg_match('/disabled/i', $brut)                    => 'Ce compte est désactivé. Veuillez voir la caisse.',
            default => 'Connexion refusée. Vérifiez vos identifiants ou contactez le comptoir.',
        };
    }

    public function login(Request $request)
    {
        $hote = config('mikrotik.host');
        $loginUrl = "http://{$hote}/login";
        $lien = $request->query('link-login-only');

        if ($lien && ($p = parse_url($lien)) && in_array($p['host'] ?? '', [$hote, 'inside.net'], true)) {
            $loginUrl = ($p['scheme'] ?? 'http') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . ($p['path'] ?? '/login');
        }

        $mac = $this->macValide($request->query('mac'));
        $dst = url(route('portail.dashboard', array_filter(['mac' => $mac]), false));

        $tarif  = $this->tarifService->tarifWifi();
        $config = $this->getConfigurationPortail();

        return response()->view('portail.login', [
            'config'        => $config,
            'logo'          => $config['logo'] ? Storage::url($config['logo']) : null,
            'login_url'     => $loginUrl,
            'dst'           => $dst,
            'erreur'        => $this->traduireErreur($request->query('error')),
            'tarif_minute'  => $tarif?->montant_par_minute,
            'tarif_minimum' => $tarif?->montant_minimum,
        ])->header('Cache-Control', 'no-store, max-age=0');
    }

    public function dashboard(Request $request)
    {
        $mac = $this->macValide($request->query('mac'));
        $tarif = $this->tarifService->tarifWifi();
        $config = $this->getConfigurationPortail();

        return response()->view('portail.dashboard', [
            'mac'          => $mac,
            'mikrotik_ip'  => config('mikrotik.host'),
            'config'       => $config,
            'logo'         => $config['logo'] ? Storage::url($config['logo']) : null,
            'tarif_minute' => $tarif?->montant_par_minute ?? 20,
            'tarif_minimum'=> $tarif?->montant_minimum ?? 300,
        ])->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * API Stats appelée en polling par le dashboard client.
     * Résout les bugs de chrono et de calcul de tarif en temps réel.
     */
    public function apiStats(Request $request): JsonResponse
    {
        $macNorm = $this->macValide($request->query('mac'));
        $ip = $request->ip();

        // 1. Recherche de la session correspondante dans cyber_sessions
        $session = Session::query()
            ->where('type_session', 'wifi')
            ->where(function ($q) use ($macNorm, $ip) {
                if ($macNorm) {
                    $q->where('client_mac', $macNorm);
                } else {
                    $q->where('client_ip', $ip);
                }
            })
            ->orderByDesc('id')
            ->first();

        // Tarif Wi-Fi courant
        $tarif = $session?->tarif ?: $this->tarifService->tarifWifi();
        $parMin = max(1, (int) ($tarif->montant_par_minute ?? 20));
        $minimum = (int) ($tarif->montant_minimum ?? 300);

        // Si aucune session n'est encore enregistrée en base locale
        if (!$session) {
            // Tentative de secours : lecture directe MikroTik
            try {
                $filtre = $macNorm ? ['mac-address' => $macNorm] : ['address' => $ip];
                $active = Cache::remember("mt_active_{$macNorm}_{$ip}", 2, function () use ($filtre) {
                    return $this->mikroTik->queryFiltered('/ip/hotspot/active/print', $filtre)[0] ?? null;
                });

                if (!$active) {
                    return response()->json(['connected' => false, 'terminee' => false])->header('Cache-Control', 'no-store');
                }

                $sec = $this->parseUptime($active['uptime'] ?? '0s');
                $min = intdiv($sec, 60);
                $montant = $tarif ? $this->tarifService->montantPourMinutes($min, $tarif) : max($min * $parMin, $minimum);

                return response()->json([
                    'connected'        => true,
                    'en_pause'         => false,
                    'terminee'         => false,
                    'uptime'           => $sec,
                    'bytes_down'       => (int) ($active['bytes-out'] ?? 0),
                    'bytes_up'         => (int) ($active['bytes-in'] ?? 0),
                    'montant'          => $montant,
                    'minimum_applique' => ($min * $parMin) < $minimum,
                    'tarif_minute'     => $parMin,
                    'tarif_minimum'    => $minimum,
                ])->header('Cache-Control', 'no-store');
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Routeur injoignable'], 503)->header('Cache-Control', 'no-store');
            }
        }

        // 2. Traitement d'une session connue
        $estTermine = $session->estTermine();
        $estConnecte = $session->estConnecte();
        $estEnPause = $session->estEnPause() || $session->estDeconnecte();

        // Calcul des secondes consommées effectives (décompte figé si déconnecté/pause/terminé)
        $secondes = $session->secondesConsommees();
        $minutes = intdiv($secondes, 60);

        // Calcul du montant selon les règles tarifaires
        $montant = $tarif
            ? $this->tarifService->montantPourMinutes($minutes, $tarif)
            : max($minutes * $parMin, $minimum);

        return response()->json([
            'connected'        => $estConnecte,
            'en_pause'         => $estEnPause,
            'terminee'         => $estTermine,
            'uptime'           => $secondes,
            'bytes_down'       => (int) $session->volume_entree,
            'bytes_up'         => (int) $session->volume_sortie,
            'montant'          => $montant,
            'minimum_applique' => $minimum > 0 && ($minutes * $parMin) < $minimum,
            'tarif_minute'     => $parMin,
            'tarif_minimum'    => $minimum,
        ])->header('Cache-Control', 'no-store');
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