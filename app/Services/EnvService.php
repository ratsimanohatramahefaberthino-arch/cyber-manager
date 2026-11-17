<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Lecture / bascule du mode production depuis l'interface web (sans terminal). */
final class EnvService
{
    private const PRODUCTION = ['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'LOG_LEVEL' => 'warning'];
    private const TEST       = ['APP_ENV' => 'local',      'APP_DEBUG' => 'true',  'LOG_LEVEL' => 'debug'];

    public function chemin(): string
    {
        return base_path('.env');
    }

    public function diagnostic(): array
    {
        $fichier = $this->chemin();
        $cx      = (string) config('database.default');
        $c       = (array) config("database.connections.{$cx}", []);

        $bind = null;
        try {
            $bind = DB::selectOne("SHOW VARIABLES LIKE 'bind_address'")->Value ?? null;
        } catch (\Throwable) {
        }

        return [
            'env'           => (string) config('app.env'),
            'debug'         => (bool) config('app.debug'),
            'production'    => config('app.env') === 'production' && !config('app.debug'),
            'fichier_ok'    => is_file($fichier),
            'modifiable'    => is_file($fichier) && is_writable($fichier),
            'bd_utilisateur' => (string) ($c['username'] ?? ''),
            'bd_root_sans_mdp' => ($c['username'] ?? '') === 'root' && (string) ($c['password'] ?? '') === '',
            'bd_ecoute'     => $bind,
        ];
    }

    public function basculer(bool $production): void
    {
        $this->ecrire($production ? self::PRODUCTION : self::TEST);
    }

    private function ecrire(array $valeurs): void
    {
        $f = $this->chemin();
        if (!is_file($f) || !is_writable($f)) {
            throw new RuntimeException('Le fichier de configuration (.env) est introuvable ou protégé en écriture.');
        }

        $contenu = (string) file_get_contents($f);

        // Sauvegarde horodatée (hors dossier public), on garde les 5 dernières
        $dossier = storage_path('app/sauvegardes-env');
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
        @copy($f, $dossier . '/env-' . date('Ymd-His') . '.bak');
        $anciennes = glob($dossier . '/env-*.bak') ?: [];
        sort($anciennes);
        foreach (array_slice($anciennes, 0, max(0, count($anciennes) - 5)) as $vieille) {
            @unlink($vieille);
        }

        foreach ($valeurs as $cle => $valeur) {
            $motif = '/^' . preg_quote($cle, '/') . '=[^\r\n]*/m';
            $ligne = $cle . '=' . $valeur;
            if (preg_match($motif, $contenu)) {
                $contenu = preg_replace_callback($motif, fn () => $ligne, $contenu, 1);
            } else {
                $contenu = rtrim($contenu) . PHP_EOL . $ligne . PHP_EOL;
            }
        }

        if (file_put_contents($f, $contenu, LOCK_EX) === false) {
            throw new RuntimeException("Impossible d'écrire le fichier de configuration.");
        }

        Artisan::call('config:clear');
    }
}