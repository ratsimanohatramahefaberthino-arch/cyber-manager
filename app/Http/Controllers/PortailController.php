<?php

namespace App\Http\Controllers;

use App\Models\CyberSetting;
use App\Models\PortailParametre;
use App\Models\Session;
use App\Services\MikroTikService;
use App\Services\MikroTikStatusService;
use App\Services\PortailConfig;
use App\Services\ReseauService;
use App\Services\TarifService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class PortailController extends Controller
{
    private const MESSAGES = [
        'required'     => 'Le champ « :attribute » est obligatoire.',
        'regex'        => 'Le champ « :attribute » n\'a pas un format valide.',
        'in'           => 'Le choix « :attribute » n\'est pas valide.',
        'integer'      => '« :attribute » doit être un nombre entier.',
        'between.numeric' => '« :attribute » doit être compris entre :min et :max.',
        'max.string'   => '« :attribute » est trop long (:max caractères au plus).',
        'max.file'     => 'Le logo est trop lourd (2 Mo au plus).',
        'image'        => 'Le logo doit être une image.',
        'mimes'        => 'Le logo doit être un fichier PNG, JPG ou WebP.',
        'uploaded'     => 'Le logo n\'a pas pu être envoyé (fichier trop lourd pour le serveur ?).',
        'ipv4'         => '« :attribute » doit ressembler à 192.168.1.10.',
    ];

    private const ATTRIBUTS = [
        'titre' => 'Titre', 'titre_taille' => 'Taille du titre', 'message' => 'Message de bienvenue',
        'police' => 'Police', 'fond_type' => 'Type de fond', 'bouton_rayon' => 'Arrondi du bouton',
        'footer_texte' => 'Texte sous le bouton', 'annonce_texte' => 'Texte de l\'annonce', 'logo' => 'Logo',
        'titre_couleur' => 'Couleur du titre', 'message_couleur' => 'Couleur du message',
        'fond_couleur_1' => 'Fond principal', 'fond_couleur_2' => 'Halo du fond',
        'carte_couleur' => 'Fond de la carte', 'carte_texte' => 'Texte de la carte',
        'bouton_fond' => 'Fond du bouton', 'bouton_texte' => 'Texte du bouton', 'accent_couleur' => 'Couleur d\'accent',
        'annonce_fond' => 'Fond de la bannière', 'annonce_texte_color' => 'Texte de la bannière',
        'portail_domaine' => 'Nom de la page', 'portail_ip_serveur' => 'Adresse de ce PC',
        'mikrotik_host' => 'Adresse du routeur', 'mikrotik_port' => 'Port du routeur',
        'mikrotik_user' => 'Utilisateur du routeur', 'mikrotik_password' => 'Mot de passe du routeur',
    ];

    public function __construct(
        private MikroTikService $mikroTik,
        private TarifService $tarifService,
        private MikroTikStatusService $statutRouteur,
    ) {}

    public static function parametresParDefaut(): array
    {
        return PortailConfig::defauts();
    }

    public function adminIndex(Request $request)
    {
        $config  = PortailConfig::charger();
        $port    = (int) ($request->getPort() ?: 80);
        $ip      = $this->ipServeur($this->mikroTik);
        $domaine = CyberSetting::reseau('portail_domaine', PortailConfig::DOMAINE_DEFAUT);
        $tarif   = $this->tarifService->tarifWifi();
        $suffixe = ($port !== 80 ? ':' . $port : '') . '/portail';

        return view('portail.admin', [
            'config'      => $config,
            'polices'     => PortailConfig::POLICES,
            'logoUrl'     => $config['logo'] ? Storage::url($config['logo']) : '',
            'port'        => $port,
            'domaine'     => $domaine,
            'ipServeur'   => $ip,
            'ipManuelle'  => CyberSetting::reseau('portail_ip_serveur'),
            'urlIp'       => $ip ? 'http://' . $ip . $suffixe : null,
            'urlNom'      => $domaine ? 'http://' . $domaine . $suffixe : null,
            'tarifTexte'  => $tarif ? "{$tarif->montant_par_minute} Ar/min · minimum {$tarif->montant_minimum} Ar" : null,
            'routeur'     => $this->statutRouteur->obtenir(),
            'routeurConfig' => [
                'host' => CyberSetting::reseau('mikrotik_host'),
                'port' => CyberSetting::reseau('mikrotik_port'),
                'user' => CyberSetting::reseau('mikrotik_user'),
                'mot_de_passe_defini' => filled(CyberSetting::reseau('mikrotik_password')),
            ],
            'sync'        => $this->etatSynchro($ip),
        ]);
    }

    public function adminSave(Request $request)
    {
        return $this->traiter($request, $request->boolean('diffuser'), false);
    }

    public function configurerRouteur(Request $request)
    {
        return $this->traiter($request, true, true);
    }

    public function adminResetDefaults()
    {
        $defauts = PortailConfig::defauts();
        unset($defauts['logo']);
        PortailConfig::enregistrer($defauts);

        return redirect()->route('portail.admin')
            ->with('onglet', 'design')
            ->with('success', 'Textes et couleurs remis comme à l\'origine. Le logo est conservé.');
    }

    private function traiter(Request $request, bool $diffuser, bool $redemarrer)
    {
        $d = $request->validate($this->regles(), self::MESSAGES, self::ATTRIBUTS);

        $valeurs = [];
        foreach (PortailConfig::CHAMPS as $cle) {
            $valeurs[$cle] = $d[$cle] ?? ($cle === 'annonce_active' ? '0' : '');
        }

        $ancienLogo  = PortailParametre::get('logo');
        $nouveauLogo = $ancienLogo;
        if ($request->hasFile('logo')) {
            $nouveauLogo = $request->file('logo')->store('portail', 'public');
        } elseif ($request->boolean('supprimer_logo')) {
            $nouveauLogo = null;
        }
        $valeurs['logo'] = $nouveauLogo;

        PortailConfig::enregistrer($valeurs);

        if ($ancienLogo && $ancienLogo !== $nouveauLogo) {
            Storage::disk('public')->delete($ancienLogo);
        }

        if (filled($d['portail_domaine'] ?? null)) {
            CyberSetting::setReseau('portail_domaine', strtolower(trim($d['portail_domaine'])));
        }
        CyberSetting::setReseau('portail_ip_serveur', filled($d['portail_ip_serveur'] ?? null) ? $d['portail_ip_serveur'] : null);

        $routeurModifie = false;
        if ($request->boolean('efface_routeur')) {
            foreach (['mikrotik_host', 'mikrotik_port', 'mikrotik_user', 'mikrotik_password'] as $k) {
                CyberSetting::setReseau($k, null);
            }
            $routeurModifie = true;
        } else {
            foreach (['mikrotik_host', 'mikrotik_port', 'mikrotik_user', 'mikrotik_password'] as $k) {
                if (filled($d[$k] ?? null)) {
                    CyberSetting::setReseau($k, (string) $d[$k]);
                    $routeurModifie = true;
                }
            }
        }
        if ($routeurModifie) {
            Cache::forget('mikrotik.statut');
        }

        $retour = redirect()->route('portail.admin')->with('onglet', $d['onglet'] ?? 'general');

        if (!$diffuser) {
            return $retour->with('success', 'Réglages enregistrés. Vos clients voient déjà les nouveaux textes et couleurs.');
        }

        $r = $this->synchroniserRouteur($request, $redemarrer);

        return $r['ok']
            ? $retour->with('success', 'Réglages enregistrés. ' . $r['message'])
            : $retour->with('success', 'Réglages enregistrés : vos clients voient déjà les nouveaux textes et couleurs.')
                     ->with('error', $r['message']);
    }

    private function regles(): array
    {
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        $ipUtilisable = function (string $attribut, mixed $valeur, \Closure $echec) {
            if (!$this->ipUtilisable((string) $valeur)) {
                $echec('L\'adresse saisie ne peut pas être utilisée (pas de 127.x.x.x ni de 169.254.x.x).');
            }
        };

        return [
            'titre'               => 'required|string|max:120',
            'titre_taille'        => 'required|integer|between:18,36',
            'message'             => 'required|string|max:300',
            'police'              => ['required', Rule::in(array_keys(PortailConfig::POLICES))],
            'fond_type'           => 'required|in:gradient,solid',
            'bouton_rayon'        => 'required|integer|between:0,99',
            'titre_couleur'       => $hex,
            'message_couleur'     => $hex,
            'fond_couleur_1'      => $hex,
            'fond_couleur_2'      => $hex,
            'carte_couleur'       => $hex,
            'carte_texte'         => $hex,
            'bouton_fond'         => $hex,
            'bouton_texte'        => $hex,
            'accent_couleur'      => $hex,
            'annonce_fond'        => $hex,
            'annonce_texte_color' => $hex,
            'annonce_active'      => 'nullable|in:0,1',
            'annonce_texte'       => 'nullable|string|max:150',
            'footer_texte'        => 'nullable|string|max:200',
            'logo'                => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',

            'portail_domaine'     => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i'],
            'portail_ip_serveur'  => ['nullable', 'ipv4', $ipUtilisable],
            'mikrotik_host'       => ['nullable', 'ipv4'],
            'mikrotik_port'       => 'nullable|integer|between:1,65535',
            'mikrotik_user'       => 'nullable|string|max:64',
            'mikrotik_password'   => 'nullable|string|max:128',
            'efface_routeur'      => 'nullable|boolean',
            'onglet'              => 'nullable|in:general,design,reseau',
        ];
    }

    private function synchroniserRouteur(Request $request, bool $redemarrer): array
    {
        $mt = new MikroTikService();

        if (!$mt->estConfigure()) {
            return $this->noter(false, null, 'Aucun routeur n\'est configuré (onglet Réseau, « Accès au routeur »).');
        }

        $ip = $this->ipServeur($mt);
        if ($ip === null) {
            return $this->noter(false, null, 'L\'adresse de ce PC sur le réseau n\'a pas été trouvée. Saisissez-la dans l\'onglet Réseau (« Forcer une adresse »).');
        }

        $port    = (string) ($request->getPort() ?: 80);
        $domaine = CyberSetting::reseau('portail_domaine', PortailConfig::DOMAINE_DEFAUT);
        $url     = 'http://' . $ip . ($port !== '80' ? ':' . $port : '') . '/portail';

        try {
            $mt->nettoyerReglesObsolètesPortail();
            $mt->configurerWalledGarden($ip, $port);
            $mt->verifierEtAjusterDhcpDns();

            if ($domaine) {
                $mt->configurerDnsLocal($domaine, $ip);
            }

            if (!$mt->installerRedirectionHotspot($url)) {
                throw new RuntimeException('Le fichier de redirection n\'a pas pu être écrit sur le routeur.');
            }

            if ($redemarrer) {
                $mt->redemarrerHotspotEtPurger();
            } else {
                $mt->viderCacheDns();
            }
        } catch (\Throwable $e) {
            Log::warning('Portail : synchronisation du routeur échouée', ['error' => $e->getMessage()]);

            return $this->noter(false, $ip, $this->messageRouteur($e));
        }

        $message = "Routeur synchronisé : les clients sont redirigés vers {$url}"
            . ($domaine ? " (nom « {$domaine} » enregistré dans le routeur)." : '.')
            . ($redemarrer ? ' Le HotSpot a été redémarré.' : '');

        return $this->noter(true, $ip, $message);
    }

    private function noter(bool $ok, ?string $ip, string $message): array
    {
        CyberSetting::setReseau('portail_sync_at', now()->toIso8601String());
        CyberSetting::setReseau('portail_sync_ok', $ok ? '1' : '0');
        CyberSetting::setReseau('portail_sync_message', $ok ? null : mb_substr($message, 0, 250));
        if ($ok && $ip) {
            CyberSetting::setReseau('portail_ip_synchronisee', $ip);
        }

        return ['ok' => $ok, 'message' => $message];
    }

    private function messageRouteur(\Throwable $e): string
    {
        $brut = $e->getMessage();

        return match (true) {
            (bool) preg_match('/impossible|timeout|ferm|refused/i', $brut)
                => 'Le routeur ne répond pas. Vérifiez qu\'il est allumé, branché, et que son adresse est correcte (onglet Réseau).',
            (bool) preg_match('/cannot log in|invalid user|login/i', $brut)
                => 'Le routeur refuse l\'utilisateur ou le mot de passe (onglet Réseau, « Accès au routeur »).',
            default => 'Le routeur a refusé une commande : ' . mb_substr($brut, 0, 160),
        };
    }

    private function etatSynchro(?string $ipActuelle): array
    {
        $date = null;
        try {
            $at   = CyberSetting::reseau('portail_sync_at');
            $date = $at ? Carbon::parse($at) : null;
        } catch (\Throwable) {
        }

        $ipSync = CyberSetting::reseau('portail_ip_synchronisee');

        return [
            'jamais'          => $date === null,
            'ok'              => CyberSetting::reseau('portail_sync_ok') === '1',
            'il_y_a'          => $date?->locale('fr')->diffForHumans(),
            'message'         => CyberSetting::reseau('portail_sync_message'),
            'ip_synchronisee' => $ipSync,
            'ip_changee'      => $ipSync && $ipActuelle && $ipSync !== $ipActuelle,
        ];
    }

    private function ipServeur(MikroTikService $mt): ?string
    {
        $manuelle = CyberSetting::reseau('portail_ip_serveur');
        if ($manuelle && $this->ipUtilisable($manuelle)) {
            return $manuelle;
        }

        try {
            if (function_exists('socket_create')) {
                $ip = $mt->detecterIpLocaleVersRouteur();
                if ($ip && $this->ipUtilisable($ip)) {
                    return $ip;
                }
            }
        } catch (\Throwable) {
        }

        $ip = ReseauService::ipPrincipale();

        return $ip && $this->ipUtilisable($ip) ? $ip : null;
    }

    private function ipUtilisable(?string $ip): bool
    {
        return $ip
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && !str_starts_with($ip, '127.')
            && !str_starts_with($ip, '169.254.')
            && $ip !== '0.0.0.0';
    }

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
            default => 'Connexion refusée. Vérifiez vos identifiants.',
        };
    }

    private function lienLoginAutorise(string $url): bool
    {
        $p = parse_url($url);
        if (!$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host'])) {
            return false;
        }

        $hote = $p['host'];

        if (filter_var($hote, FILTER_VALIDATE_IP)) {
            return !filter_var($hote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return !str_contains($hote, '.') || (bool) preg_match('/\.(local|lan)$/i', $hote);
    }

    public function login(Request $request)
    {
        $hote     = CyberSetting::reseau('mikrotik_host');
        $loginUrl = $hote ? "http://{$hote}/login" : url('/portail');
        $lien     = $request->query('link-login-only');

        if (is_string($lien) && $this->lienLoginAutorise($lien)) {
            $loginUrl = $lien;
        }

        $mac = $this->macValide($request->query('mac'));
        $dst = url(route('portail.dashboard', array_filter(['mac' => $mac]), false));

        $tarif  = $this->tarifService->tarifWifi();
        $config = PortailConfig::charger();

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
        $mac    = $this->macValide($request->query('mac'));
        $tarif  = $this->tarifService->tarifWifi();
        $config = PortailConfig::charger();

        return response()->view('portail.dashboard', [
            'mac'           => $mac,
            'mikrotik_ip'   => CyberSetting::reseau('mikrotik_host'),
            'config'        => $config,
            'logo'          => $config['logo'] ? Storage::url($config['logo']) : null,
            'tarif_minute'  => $tarif?->montant_par_minute ?? 20,
            'tarif_minimum' => $tarif?->montant_minimum ?? 300,
        ])->header('Cache-Control', 'no-store, max-age=0');
    }

    public function apiStats(Request $request): JsonResponse
    {
        $macNorm = $this->macValide($request->query('mac'));
        $ip = $request->ip();

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

        $tarif = $session?->tarif ?: $this->tarifService->tarifWifi();
        $parMin = max(1, (int) ($tarif->montant_par_minute ?? 20));
        $minimum = (int) ($tarif->montant_minimum ?? 300);

        if (!$session) {
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

        $estTermine = $session->estTermine();
        $estConnecte = $session->estConnecte();
        $estEnPause = $session->estEnPause() || $session->estDeconnecte();

        $secondes = $session->secondesConsommees();
        $minutes = intdiv($secondes, 60);

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