<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WifiAlarmController extends Controller
{
    private const CLE_ENABLED = 'wifi_alarm_enabled';
    private const CLE_VOLUME  = 'wifi_alarm_volume';
    private const CLE_SOUND   = 'wifi_alarm_sound';
    private const CLE_DUREE   = 'wifi_alarm_duration';
    private const CLE_TAILLE  = 'wifi_alarm_max_size';   // stocké en Mo (1 à 10)

    private const DOSSIER = 'sounds';

    /**
     * Retourne les réglages actuels.
     * Gère la migration automatique de l'ancienne valeur Ko → Mo.
     */
    public function index(): JsonResponse
    {
        $tailleBrute = (int) Parametre::get(self::CLE_TAILLE, '10');

        // Migration à la volée : si l'ancienne valeur était en Ko (ex. 10240),
        // on la convertit en Mo (10) et on la réenregistre.
        if ($tailleBrute > 10) {
            $tailleBrute = max(1, (int) round($tailleBrute / 1024));
            Parametre::set(self::CLE_TAILLE, (string) $tailleBrute);
        }

        return response()->json([
            'enabled'        => Parametre::get(self::CLE_ENABLED, '1') === '1',
            'volume'         => (int) Parametre::get(self::CLE_VOLUME, '70'),
            'duree_secondes' => (int) Parametre::get(self::CLE_DUREE, '0'),
            'taille_max_mo'  => $tailleBrute,
            'son_nom'        => Parametre::get(self::CLE_SOUND, '') ?: null,
            'son_url'        => ($s = Parametre::get(self::CLE_SOUND, '')) ? '/sounds/' . $s : null,
            'sons_uploades'  => $this->listerSonsUploades(),
        ]);
    }

    /**
     * Enregistre les réglages généraux (activation, volume, durée, taille max).
     */
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

    /**
     * Upload d'un fichier son personnalisé.
     */
    public function uploader(Request $request): JsonResponse
    {
        $tailleMaxMo = (int) Parametre::get(self::CLE_TAILLE, '10');

        // Sécurité : si la valeur stockée est encore l'ancienne (Ko), on borne à 10 Mo
        if ($tailleMaxMo > 10) {
            $tailleMaxMo = 10;
        }

        $tailleMaxKo = $tailleMaxMo * 1024;

        $request->validate([
            'son' => ['required', 'file', 'max:' . $tailleMaxKo, 'mimes:mp3,wav,ogg,m4a,aac'],
        ], [
            'son.max'   => "Le fichier dépasse la taille maximale de {$tailleMaxMo} Mo.",
            'son.mimes' => 'Format non accepté. Utilisez MP3, WAV, OGG, M4A ou AAC.',
        ]);

        $fichier = $request->file('son');
        $nomOriginal = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $nomOriginal = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nomOriginal) ?: 'son';
        $ext = strtolower($fichier->getClientOriginalExtension());
        $nom = $nomOriginal . '-' . now()->format('YmdHis') . '.' . $ext;

        if (!is_dir(public_path(self::DOSSIER))) {
            mkdir(public_path(self::DOSSIER), 0755, true);
        }

        $fichier->move(public_path(self::DOSSIER), $nom);

        return response()->json([
            'ok'  => true,
            'nom' => $nom,
            'url' => '/' . self::DOSSIER . '/' . $nom,
        ]);
    }

    /**
     * Choisit le son actif.
     * Cas spécial '__default__' → revient au bip par défaut (synthétisé côté client).
     */
    public function choisirSon(Request $request): JsonResponse
    {
        $data = $request->validate(['nom' => ['required', 'string', 'max:255']]);
        $nom = basename($data['nom']);

        // Retour au son par défaut : on vide le paramètre
        if ($nom === '__default__') {
            Parametre::set(self::CLE_SOUND, '');
            return response()->json(['ok' => true]);
        }

        if (!file_exists(public_path(self::DOSSIER . '/' . $nom))) {
            return response()->json(['ok' => false, 'message' => 'Fichier introuvable.'], 404);
        }

        Parametre::set(self::CLE_SOUND, $nom);

        return response()->json(['ok' => true]);
    }

    /**
     * Supprime un fichier son.
     */
    public function supprimerSon(Request $request): JsonResponse
    {
        $data = $request->validate(['nom' => ['required', 'string', 'max:255']]);
        $nom = basename($data['nom']);

        $chemin = public_path(self::DOSSIER . '/' . $nom);
        if (file_exists($chemin)) {
            @unlink($chemin);
        }

        // Si c'était le son actif, on retombe sur le son par défaut
        if (Parametre::get(self::CLE_SOUND) === $nom) {
            Parametre::set(self::CLE_SOUND, '');
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Réinitialise les réglages (ne touche PAS aux fichiers de public/sounds/).
     */
    public function reinitialiser(): JsonResponse
    {
        Parametre::set(self::CLE_ENABLED, '1');
        Parametre::set(self::CLE_VOLUME,  '70');
        Parametre::set(self::CLE_DUREE,   '0');
        Parametre::set(self::CLE_TAILLE,  '10');
        Parametre::set(self::CLE_SOUND,   '');

        return response()->json(['ok' => true]);
    }

    /**
     * Liste les fichiers audio présents dans public/sounds/.
     */
    private function listerSonsUploades(): array
    {
        $dossier = public_path(self::DOSSIER);
        if (!is_dir($dossier)) return [];

        $fichiers = scandir($dossier);
        $sons = [];
        foreach ($fichiers as $f) {
            if (in_array($f, ['.', '..', '.gitignore', '.gitkeep'])) continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['mp3','wav','ogg','m4a','aac'])) continue;
            $sons[] = [
                'nom' => $f,
                'url' => '/' . self::DOSSIER . '/' . $f,
            ];
        }
        return $sons;
    }
}