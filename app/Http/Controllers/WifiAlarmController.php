<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WifiAlarmController extends Controller
{
    private const CLE_ENABLED = 'wifi_alarm_enabled';
    private const CLE_VOLUME  = 'wifi_alarm_volume';
    private const CLE_SOUND   = 'wifi_alarm_sound';
    private const CLE_DUREE   = 'wifi_alarm_duration';
    private const CLE_TAILLE  = 'wifi_alarm_max_size';   // stocké en Mo (1 à 10)

    private const DOSSIER = 'sounds';

    /**
     * Valeur stockée dans CLE_SOUND :
     *   ''                  → bip classique (synthétisé côté navigateur)
     *   'builtin:carillon'  → autre son intégré
     *   'mon-son-2026.mp3'  → fichier présent dans public/sounds/
     */
    private const PREFIXE_INTEGRE = 'builtin:';

    public const SONS_INTEGRES = ['bip-classique', 'carillon', 'urgent'];

    /** Extension réelle (déduite du contenu) → extension de stockage autorisée. */
    private const EXTENSIONS = [
        'mp3' => 'mp3', 'mpga' => 'mp3',
        'wav' => 'wav', 'wave' => 'wav',
        'ogg' => 'ogg', 'oga'  => 'ogg',
        'm4a' => 'm4a', 'mp4'  => 'm4a',
        'aac' => 'aac',
    ];

    // -----------------------------------------------------------------
    // Lecture des réglages (partagée avec WifiController)
    // -----------------------------------------------------------------

    /** Taille max d'un son en Mo. Convertit à la volée l'ancienne valeur en Ko, sans écrire en base. */
    public static function tailleMaxMo(): int
    {
        $v = (int) Parametre::get(self::CLE_TAILLE, '10');

        if ($v > 10) {
            $v = (int) round($v / 1024);
        }

        return max(1, min(10, $v));
    }

    public static function reglagesActuels(): array
    {
        $valeur  = (string) Parametre::get(self::CLE_SOUND, '');
        $integre = 'bip-classique';
        $fichier = null;

        if (str_starts_with($valeur, self::PREFIXE_INTEGRE)) {
            $id = substr($valeur, strlen(self::PREFIXE_INTEGRE));
            if (in_array($id, self::SONS_INTEGRES, true)) {
                $integre = $id;
            }
        } elseif ($valeur !== '') {
            $fichier = $valeur;
        }

        return [
            'enabled'        => Parametre::get(self::CLE_ENABLED, '1') === '1',
            'volume'         => max(0, min(100, (int) Parametre::get(self::CLE_VOLUME, '70'))),
            'duree_secondes' => max(0, min(600, (int) Parametre::get(self::CLE_DUREE, '0'))),
            'taille_max_mo'  => self::tailleMaxMo(),
            'son_nom'        => $fichier,
            'son_url'        => $fichier ? '/' . self::DOSSIER . '/' . rawurlencode($fichier) : null,
            'son_builtin'    => $integre,
        ];
    }

    // -----------------------------------------------------------------
    // Endpoints
    // -----------------------------------------------------------------

    public function index(): JsonResponse
    {
        return response()->json(self::reglagesActuels() + [
            'sons_uploades' => $this->listerSonsUploades(),
        ]);
    }

    public function reglages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled'        => ['required', 'boolean'],
            'volume'         => ['required', 'integer', 'min:0', 'max:100'],
            'duree_secondes' => ['required', 'integer', 'min:0', 'max:600'],
            'taille_max_mo'  => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        Parametre::set(self::CLE_ENABLED, $data['enabled'] ? '1' : '0');
        Parametre::set(self::CLE_VOLUME,  (string) $data['volume']);
        Parametre::set(self::CLE_DUREE,   (string) $data['duree_secondes']);
        Parametre::set(self::CLE_TAILLE,  (string) $data['taille_max_mo']);

        return response()->json(['ok' => true]);
    }

    public function uploader(Request $request): JsonResponse
    {
        $tailleMaxMo = self::tailleMaxMo();

        $request->validate([
            'son' => ['required', 'file', 'max:' . ($tailleMaxMo * 1024), 'mimes:mp3,wav,ogg,m4a,aac'],
        ], [
            'son.max'   => "Le fichier dépasse la taille maximale de {$tailleMaxMo} Mo.",
            'son.mimes' => 'Format non accepté. Utilisez MP3, WAV, OGG, M4A ou AAC.',
        ]);

        $fichier = $request->file('son');

        // L'extension est déduite du CONTENU du fichier (pas du nom envoyé par le
        // navigateur) et doit appartenir à la liste blanche : impossible de déposer
        // un fichier exécutable dans public/sounds/.
        $ext = self::EXTENSIONS[strtolower((string) $fichier->guessExtension())]
            ?? self::EXTENSIONS[strtolower((string) $fichier->getClientOriginalExtension())]
            ?? null;

        if ($ext === null) {
            return response()->json(['ok' => false, 'message' => 'Format audio non reconnu.'], 422);
        }

        $base = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base) ?: 'son';
        $base = mb_substr($base, 0, 60);
        $nom  = $base . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(4)) . '.' . $ext;

        if (!is_dir(public_path(self::DOSSIER))) {
            mkdir(public_path(self::DOSSIER), 0755, true);
        }

        $fichier->move(public_path(self::DOSSIER), $nom);

        return response()->json([
            'ok'  => true,
            'nom' => $nom,
            'url' => '/' . self::DOSSIER . '/' . rawurlencode($nom),
        ]);
    }

    /**
     * Choisit le son actif : un son intégré (bip-classique, carillon, urgent)
     * ou un fichier uploadé. '__default__' = bip classique.
     */
    public function choisirSon(Request $request): JsonResponse
    {
        $data = $request->validate(['nom' => ['required', 'string', 'max:255']]);
        $nom  = basename($data['nom']);

        if ($nom === '__default__') {
            $nom = 'bip-classique';
        }

        if (in_array($nom, self::SONS_INTEGRES, true)) {
            Parametre::set(self::CLE_SOUND, $nom === 'bip-classique' ? '' : self::PREFIXE_INTEGRE . $nom);
            return response()->json(['ok' => true]);
        }

        if (!$this->extensionAutorisee($nom) || !is_file(public_path(self::DOSSIER . '/' . $nom))) {
            return response()->json(['ok' => false, 'message' => 'Fichier introuvable.'], 404);
        }

        Parametre::set(self::CLE_SOUND, $nom);

        return response()->json(['ok' => true]);
    }

    public function supprimerSon(Request $request): JsonResponse
    {
        $data = $request->validate(['nom' => ['required', 'string', 'max:255']]);
        $nom  = basename($data['nom']);

        if (!$this->extensionAutorisee($nom)) {
            return response()->json(['ok' => false, 'message' => 'Fichier non autorisé.'], 422);
        }

        $chemin = public_path(self::DOSSIER . '/' . $nom);
        if (is_file($chemin)) {
            @unlink($chemin);
        }

        if (Parametre::get(self::CLE_SOUND) === $nom) {
            Parametre::set(self::CLE_SOUND, '');
        }

        return response()->json(['ok' => true]);
    }

    /** Réinitialise les réglages (ne touche PAS aux fichiers de public/sounds/). */
    public function reinitialiser(): JsonResponse
    {
        Parametre::set(self::CLE_ENABLED, '1');
        Parametre::set(self::CLE_VOLUME,  '70');
        Parametre::set(self::CLE_DUREE,   '0');
        Parametre::set(self::CLE_TAILLE,  '10');
        Parametre::set(self::CLE_SOUND,   '');

        return response()->json(['ok' => true]);
    }

    // -----------------------------------------------------------------

    private function extensionAutorisee(string $nom): bool
    {
        $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));

        return in_array($ext, array_unique(array_values(self::EXTENSIONS)), true);
    }

    private function listerSonsUploades(): array
    {
        $dossier = public_path(self::DOSSIER);
        if (!is_dir($dossier)) return [];

        $sons = [];
        foreach (scandir($dossier) ?: [] as $f) {
            if (!is_file($dossier . '/' . $f) || !$this->extensionAutorisee($f)) continue;

            $sons[] = [
                'nom' => $f,
                'url' => '/' . self::DOSSIER . '/' . rawurlencode($f),
            ];
        }

        usort($sons, fn ($a, $b) => strcmp($a['nom'], $b['nom']));

        return $sons;
    }
}