<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CyberSetting extends Model
{
    protected $table = 'cyber_settings';
    protected $fillable = ['cle', 'valeur'];

    public const DEFAUTS = [
        'nom_cyber'          => 'Cyber Manager',
        'slogan'             => 'Gestion de cybercafé',
        'nom_admin'          => '',
        'couleur_principale' => '#7c3aed',
        'logo_path'          => null,
    ];

    public static function tous(): array
    {
        try {
            $lignes = static::query()->pluck('valeur', 'cle')->all();
        } catch (\Throwable) {
            $lignes = [];
        }

        $res = self::DEFAUTS;
        foreach ($lignes as $cle => $val) {
            if (array_key_exists($cle, $res)) {
                $res[$cle] = $val ?? '';
            }
        }

        if (trim((string) $res['nom_cyber']) === '') {
            $res['nom_cyber'] = self::DEFAUTS['nom_cyber'];
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $res['couleur_principale'])) {
            $res['couleur_principale'] = self::DEFAUTS['couleur_principale'];
        }
        if ($res['logo_path'] === '') {
            $res['logo_path'] = null;
        }

        return $res;
    }

    public static function definir(string $cle, ?string $valeur): void
    {
        static::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }

    public static function logoChemin(): ?string
    {
        $p = self::tous()['logo_path'];
        if (!$p || !Storage::disk('public')->exists($p)) {
            return null;
        }
        return Storage::disk('public')->path($p);
    }

    public static function logoExiste(): bool
    {
        return self::logoChemin() !== null;
    }

    public static function logoVersion(): string
    {
        $chemin = self::logoChemin();
        if ($chemin === null) {
            return '';
        }
        return substr(md5($chemin . '|' . @filemtime($chemin) . '|' . @filesize($chemin)), 0, 12);
    }

    public static function brandingAgent(): array
    {
        $t = self::tous();
        $version = self::logoVersion();

        return [
            'nom_cyber'    => $t['nom_cyber'],
            'slogan'       => (string) $t['slogan'],
            'couleur'      => $t['couleur_principale'],
            'avec_logo'    => $version !== '',
            'logo_version' => $version,
        ];
    }

    // -----------------------------------------------------------------
    // NOUVELLES MÉTHODES RÉSEAU
    // -----------------------------------------------------------------

    public static function reseau(string $cle, ?string $defaut = null): ?string
    {
        $valeur = static::query()->where('cle', $cle)->value('valeur');
        if ($valeur !== null && $valeur !== '') {
            return $valeur;
        }
        return $defaut;
    }

    public static function setReseau(string $cle, ?string $valeur): void
    {
        static::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }
}