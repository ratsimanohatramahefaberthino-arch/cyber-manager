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
    private const EXE     = 'CyberManagerInstall.exe';
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

    // ------------------------------------------------------------------
    // Page admin
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $port = (int) $request->getPort();
        $v    = $this->versionInstall();

        return view('parametres.installation', [
            'url'       => ReseauService::urlServeur($port),
            'ips'       => ReseauService::toutes(),
            'port'      => $port,
            'localhost' => in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true),
            'fichiers'  => [
                'Installateur (CyberManagerInstall.exe)' => is_file($this->chemin(self::EXE)),
                'Gardien (gardien.zip)'                  => is_file($this->chemin(self::GARDIEN)),
                'Écran' . ($v ? ' ' . $v->version : '') . ' (version publiée)' => $v && $v->fichierExiste(),
                'WebView2 hors ligne (webview2.exe), facultatif' => is_file($this->chemin(self::WEBVIEW)),
            ],
        ]);
    }

    public function telecharger(Request $request)
    {
        $exe = $this->chemin(self::EXE);
        if (!is_file($exe)) {
            return back()->withErrors(['L\'installateur n\'est pas encore sur le serveur. Lancez fabriquer.ps1 sur le PC du développeur.']);
        }
        if (!class_exists(\ZipArchive::class)) {
            return back()->withErrors(['L\'extension PHP « zip » est absente : activez extension=zip dans php.ini, puis redémarrez Apache.']);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cmi');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFile($exe, self::EXE);
        $zip->addFromString('serveur.json', json_encode([
            'server_url' => ReseauService::urlServeur((int) $request->getPort()),
            'nom_cyber'  => CyberSetting::tous()['nom_cyber'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('LISEZMOI.txt', "1. Copiez ces deux fichiers sur le poste.\r\n2. Clic droit sur CyberManagerInstall.exe > Exécuter en tant qu'administrateur.\r\n3. Saisissez le code d'installation (page Enregistrement) puis cliquez sur Installer.\r\n");
        $zip->close();

        return response()->download($tmp, 'CyberManagerInstallateur.zip')->deleteFileAfterSend(true);
    }

    // ------------------------------------------------------------------
    // Endpoints appelés par l'installateur
    // ------------------------------------------------------------------

    public function info(): JsonResponse
    {
        $v = $this->versionInstall();

        return response()->json([
            'cyber_manager' => true,
            'nom_cyber'     => CyberSetting::tous()['nom_cyber'],
            'version_ecran' => $v && $v->fichierExiste() ? $v->version : null,
            'sha256_ecran'  => $v && $v->fichierExiste() ? strtolower($v->sha256) : null,
            'gardien'       => is_file($this->chemin(self::GARDIEN)),
            'webview2'      => is_file($this->chemin(self::WEBVIEW)),
        ]);
    }

    /**
     * CORRECTION : Utilise le service CodeInstallation::verifier() qui normalise
     * automatiquement le code (supprime les tirets, espaces, met en majuscules).
     * Ainsi, que l'installateur envoie "4AFS-YC44" ou "4AFSYC44", la vérification
     * réussit si le code en base est le bon.
     */
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