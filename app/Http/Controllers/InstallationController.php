<?php

namespace App\Http\Controllers;

use App\Models\AgentVersion;
use App\Models\CyberSetting;
use App\Models\Poste;
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

    /** Chemin tel qu'on le verrait dans l'Explorateur Windows. */
    private function cheminAffiche(string $chemin): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $chemin);
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

        $absent = 'Absent du serveur. Copiez le fichier exactement à l\'endroit indiqué ci-dessous (ou demandez à votre technicien).';

        $fichiers = [
            [
                'cle'            => 'installateur',
                'titre'          => 'Installateur Cyber Manager',
                'description'    => 'Le programme à lancer sur chaque nouveau poste. Il récupère et configure tout le reste tout seul.',
                'usage'          => 'Télécharge un dossier .zip contenant l\'installateur et l\'adresse de ce serveur, prêt à copier sur une clé USB.',
                'icone'          => 'download',
                'present'        => $exeOk,
                'facultatif'     => false,
                'telechargeable' => $exeOk && $zipOk,
                'url'            => route('parametres.installation.telecharger'),
                'detail'         => $this->taille($this->chemin(self::EXE)),
                'manquant'       => $absent,
                'chemin'         => $this->cheminAffiche($this->chemin(self::EXE)),
            ],
            [
                'cle'            => 'gardien',
                'titre'          => 'Gardien du poste',
                'description'    => 'Garde le poste verrouillé et empêche les clients de modifier Windows. Installé automatiquement.',
                'usage'          => 'Télécharge gardien.zip tel quel : utile pour réparer un poste à la main.',
                'icone'          => 'lock',
                'present'        => $gardienOk,
                'facultatif'     => false,
                'telechargeable' => $gardienOk,
                'url'            => route('parametres.installation.fichier', 'gardien'),
                'detail'         => $this->taille($this->chemin(self::GARDIEN)),
                'manquant'       => $absent,
                'chemin'         => $this->cheminAffiche($this->chemin(self::GARDIEN)),
            ],
            [
                'cle'            => 'ecran',
                'titre'          => 'Écran de verrouillage',
                'description'    => 'L\'écran plein écran montré aux clients, avec vos couleurs et vos tarifs. Installé automatiquement.',
                'usage'          => 'Télécharge le paquet .zip de la version de l\'écran actuellement proposée aux nouveaux postes.',
                'icone'          => 'desktop',
                'present'        => $ecranOk,
                'facultatif'     => false,
                'telechargeable' => $ecranOk,
                'url'            => route('parametres.installation.fichier', 'ecran'),
                'detail'         => collect([
                    $v ? 'Version ' . $v->version : null,
                    $ecranOk ? $this->taille($v->chemin()) : null,
                ])->filter()->implode(' · ') ?: null,
                'manquant'       => 'Aucune version publiée. Envoyez-en une depuis l\'onglet Versions (aucun chemin à connaître).',
                'chemin'         => null,
                'lien'           => route('parametres.versions.index'),
                'lien_texte'     => 'Ouvrir l\'onglet Versions',
            ],
            [
                'cle'            => 'webview2',
                'titre'          => 'WebView2 (hors ligne)',
                'description'    => 'Sert uniquement aux postes sans connexion Internet. Vous pouvez l\'ignorer si vos postes sont connectés.',
                'usage'          => 'Télécharge webview2.exe : à lancer à la main sur un poste qui n\'a pas Internet.',
                'icone'          => 'archive',
                'present'        => $webviewOk,
                'facultatif'     => true,
                'telechargeable' => $webviewOk,
                'url'            => route('parametres.installation.fichier', 'webview2'),
                'detail'         => $this->taille($this->chemin(self::WEBVIEW)),
                'manquant'       => 'Non fourni : sans importance si les postes ont Internet. Sinon, copiez-le à l\'endroit indiqué ci-dessous.',
                'chemin'         => $this->cheminAffiche($this->chemin(self::WEBVIEW)),
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
            'assistant'       => [
                'maxId'        => (int) Poste::max('id'),
                'urlPostes'    => route('parametres.installation.postes'),
                'urlRenommer'  => route('parametres.installation.renommer', ['poste' => '__ID__']),
            ],
        ]);
    }

    public function telecharger(Request $request)
    {
        $exe = $this->chemin(self::EXE);
        if (!is_file($exe)) {
            return back()->withErrors(['global' => 'L\'installateur est absent du serveur. Copiez ' . self::EXE . ' ici : ' . $this->cheminAffiche($exe)]);
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

    /** Téléchargement direct (admin) du gardien, de l'écran ou de WebView2. */
    public function fichier(string $cle)
    {
        if ($cle === 'ecran') {
            $v = $this->versionInstall();
            if (!$v || !$v->fichierExiste()) {
                return back()->withErrors(['global' => 'Aucune version de l\'écran n\'est publiée. Envoyez-en une depuis l\'onglet Versions.']);
            }

            return response()->download($v->chemin(), $v->fichier);
        }

        [$chemin, $nom] = match ($cle) {
            'gardien'  => [$this->chemin(self::GARDIEN), 'gardien.zip'],
            'webview2' => [$this->chemin(self::WEBVIEW), 'webview2.exe'],
            default    => abort(404),
        };

        if (!is_file($chemin)) {
            return back()->withErrors(['global' => "« {$nom} » est absent du serveur. Copiez-le exactement ici : " . $this->cheminAffiche($chemin)]);
        }

        return response()->download($chemin, $nom);
    }

    /** Interrogé par l'assistant (étape « Attendre l'enregistrement »). */
    public function postes(): JsonResponse
    {
        $liste = Poste::orderByDesc('id')->limit(10)->get()->map(fn (Poste $p) => [
            'id'       => $p->id,
            'nom'      => $p->nom_poste,
            'ip'       => $p->adresse_ip,
            'en_ligne' => $p->estEnLigne(),
        ])->values();

        return response()->json(['max_id' => (int) Poste::max('id'), 'postes' => $liste]);
    }

    /** Renommage depuis l'assistant (réponse JSON, la page ne se recharge pas). */
    public function renommer(Request $request, Poste $poste): JsonResponse
    {
        $nom = Poste::nettoyerNom((string) $request->input('nom'));

        if ($nom === '') {
            return response()->json(['ok' => false, 'message' => 'Le nom est obligatoire.'], 422);
        }
        if (Poste::nomExiste($nom, $poste->id)) {
            return response()->json(['ok' => false, 'message' => "Le nom « {$nom} » est déjà pris par un autre poste."], 422);
        }

        $poste->update(['nom_poste' => $nom]);

        return response()->json(['ok' => true, 'nom' => $nom]);
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