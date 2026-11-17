<?php

namespace App\Http\Controllers;

use App\Models\AgentVersion;
use App\Models\CyberSetting;
use App\Services\CodeInstallation;
use App\Services\ReseauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InstallationController extends Controller
{
    /** Dossier PRIVÉ : storage/app/private/installateur/ */
    private const DOSSIER = 'installateur';
    public const EXE     = 'CyberManagerInstall.exe';
    private const GARDIEN = 'gardien.zip';
    private const WEBVIEW = 'webview2.exe';

    private function chemin(string $f): string
    {
        return Storage::disk('local')->path(self::DOSSIER . '/' . $f);
    }

    private function versionInstall(): ?AgentVersion
    {
        return AgentVersion::generale() ?? AgentVersion::pilote() ?? AgentVersion::orderByDesc('id')->first();
    }

    /** « 84,2 Mo » / « 320 Ko », ou null si le fichier n'existe pas. */
    private function taille(string $chemin): ?string
    {
        if ($chemin === '' || !is_file($chemin)) {
            return null;
        }
        $o = filesize($chemin);
        if ($o === false) {
            return null;
        }

        return $o >= 1048576
            ? number_format($o / 1048576, 1, ',', ' ') . ' Mo'
            : number_format(max($o / 1024, 1), 0, ',', ' ') . ' Ko';
    }

    /** Code d'installation en clair, ou null s'il ne peut pas être lu. */
    private function codeInstallation(): ?string
    {
        try {
            return CodeInstallation::obtenir();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Page admin
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $port = (int) $request->getPort();
        $v    = $this->versionInstall();

        $exeOk     = is_file($this->chemin(self::EXE));
        $gardienOk = is_file($this->chemin(self::GARDIEN));
        $ecranOk   = $v && $v->fichierExiste();
        $webviewOk = is_file($this->chemin(self::WEBVIEW));
        $zipOk     = class_exists(\ZipArchive::class);

        $fichiers = [
            [
                'titre'          => 'Installateur Cyber Manager',
                'description'    => 'Le programme à lancer sur chaque nouveau poste. Il récupère et configure tout le reste tout seul.',
                'icone'          => 'download',
                'present'        => $exeOk,
                'facultatif'     => false,
                'telechargeable' => true,
                'detail'         => $this->taille($this->chemin(self::EXE)),
                'manquant'       => 'Absent du serveur. À publier par votre technicien (voir « Dépannage » plus bas).',
            ],
            [
                'titre'       => 'Gardien du poste',
                'description' => 'Garde le poste verrouillé et empêche les clients de modifier Windows. Installé automatiquement.',
                'icone'       => 'lock',
                'present'     => $gardienOk,
                'facultatif'  => false,
                'detail'      => $this->taille($this->chemin(self::GARDIEN)),
                'manquant'    => 'Absent du serveur. À publier par votre technicien (voir « Dépannage » plus bas).',
            ],
            [
                'titre'       => 'Écran de verrouillage',
                'description' => 'L\'écran plein écran montré aux clients, avec vos couleurs et vos tarifs. Installé automatiquement.',
                'icone'       => 'desktop',
                'present'     => $ecranOk,
                'facultatif'  => false,
                'detail'      => collect([
                    $v ? 'Version ' . $v->version : null,
                    $ecranOk ? $this->taille($v->chemin()) : null,
                ])->filter()->implode(' · ') ?: null,
                'manquant'    => 'Aucune version publiée.',
                'lien'        => route('parametres.versions.index'),
                'lien_texte'  => 'Ouvrir l\'onglet Versions',
            ],
            [
                'titre'       => 'WebView2 (hors ligne)',
                'description' => 'Sert uniquement aux postes sans connexion Internet. Vous pouvez l\'ignorer si vos postes sont connectés.',
                'icone'       => 'archive',
                'present'     => $webviewOk,
                'facultatif'  => true,
                'detail'      => $this->taille($this->chemin(self::WEBVIEW)),
                'manquant'    => 'Non fourni : sans importance si les postes ont Internet.',
            ],
        ];

        return view('parametres.installation', [
            'url'             => ReseauService::urlServeur($port),
            'ips'             => ReseauService::toutes(),
            'port'            => $port,
            'localhost'       => in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true),
            'fichiers'        => $fichiers,
            'nbManquants'     => collect($fichiers)->filter(fn ($f) => !$f['present'] && !$f['facultatif'])->count(),
            'zipOk'           => $zipOk,
            'peutTelecharger' => $exeOk && $zipOk,
            'nomExe'          => self::EXE,
            'code'            => $this->codeInstallation(),
        ]);
    }

    public function telecharger(Request $request)
    {
        $exe = $this->chemin(self::EXE);
        if (!is_file($exe)) {
            return back()->withErrors(['global' => 'L\'installateur n\'est pas encore sur le serveur. Demandez à votre technicien de le publier.']);
        }
        if (!class_exists(\ZipArchive::class)) {
            return back()->withErrors(['global' => 'L\'extension PHP « zip » est absente : activez extension=zip dans php.ini, puis redémarrez Apache.']);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cmi');
        $zip = new \ZipArchive();

        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            return back()->withErrors(['global' => 'Impossible de préparer le fichier à télécharger.']);
        }

        $zip->addFile($exe, self::EXE);
        $zip->addFromString('serveur.json', json_encode([
            'server_url' => ReseauService::urlServeur((int) $request->getPort()),
            'nom_cyber'  => CyberSetting::tous()['nom_cyber'] ?? 'Cyber Manager',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('LISEZMOI.txt',
            "1. Extrayez ce dossier (clic droit > Extraire tout) puis copiez-le sur le poste.\r\n"
            . "2. Gardez les deux fichiers ensemble dans le meme dossier.\r\n"
            . "3. Clic droit sur " . self::EXE . " > Executer en tant qu'administrateur.\r\n"
            . "4. Saisissez le code d'installation (page Parametres > Installation) puis cliquez sur Installer.\r\n");

        if (!$zip->close()) {
            @unlink($tmp);
            return back()->withErrors(['global' => 'Impossible de finaliser le fichier à télécharger.']);
        }

        return response()->download($tmp, 'CyberManagerInstallateur.zip')->deleteFileAfterSend(true);
    }

    // ------------------------------------------------------------------
    // Endpoints appelés par l'installateur (inchangés)
    // ------------------------------------------------------------------

    public function info(): JsonResponse
    {
        $v = $this->versionInstall();

        return response()->json([
            'cyber_manager' => true,
            'nom_cyber'     => CyberSetting::tous()['nom_cyber'] ?? 'Cyber Manager',
            'version_ecran' => $v && $v->fichierExiste() ? $v->version : null,
            'sha256_ecran'  => $v && $v->fichierExiste() ? strtolower($v->sha256) : null,
            'gardien'       => is_file($this->chemin(self::GARDIEN)),
            'webview2'      => is_file($this->chemin(self::WEBVIEW)),
        ]);
    }

    /** Le service normalise le code (tirets, espaces, majuscules). */
    private function codeValide(Request $request): bool
    {
        $code = $request->header('X-Install-Code');
        if (empty($code)) {
            return false;
        }

        return CodeInstallation::verifier($code);
    }

    private function servir(Request $request, string $chemin, string $nom)
    {
        if (!$this->codeValide($request)) {
            Log::warning('Installation : code invalide pour ' . $nom . ' (' . $request->ip() . ').');
            return response()->json(['ok' => false, 'message' => 'Code d\'installation invalide.'], 403);
        }
        if (!is_file($chemin)) {
            return response()->json(['ok' => false, 'message' => 'Fichier indisponible sur le serveur.'], 404);
        }

        return response()->download($chemin, $nom);
    }

    public function gardien(Request $request)
    {
        return $this->servir($request, $this->chemin(self::GARDIEN), 'gardien.zip');
    }

    public function ecran(Request $request)
    {
        $v = $this->versionInstall();

        return $this->servir($request, $v ? $v->chemin() : '', $v ? $v->fichier : 'ecran.zip');
    }

    public function webview2(Request $request)
    {
        return $this->servir($request, $this->chemin(self::WEBVIEW), 'webview2.exe');
    }
}