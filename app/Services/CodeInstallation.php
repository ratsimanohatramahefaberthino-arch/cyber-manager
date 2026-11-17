<?php

namespace App\Services;

use App\Models\CyberSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class CodeInstallation
{
    private const CLE = 'code_installation';
    private const CACHE_KEY = 'code_installation_brut';

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LONGUEUR = 8;

    public static function obtenir(): string
    {
        return self::formater(self::obtenirBrut());
    }

    public static function regenerer(): string
    {
        $brut = self::generer();
        CyberSetting::definir(self::CLE, $brut);
        Cache::put(self::CACHE_KEY, $brut, 60);
        Log::info("Code d'installation régénéré.");

        return self::formater($brut);
    }

    public static function verifier(?string $saisi): bool
    {
        $saisi = self::normaliser((string) $saisi);
        if (strlen($saisi) !== self::LONGUEUR) {
            return false;
        }

        return hash_equals(self::obtenirBrut(), $saisi);
    }

    private static function obtenirBrut(): string
    {
        $cache = Cache::get(self::CACHE_KEY);
        if (is_string($cache) && strlen($cache) === self::LONGUEUR) {
            return $cache;
        }

        $brut = self::normaliser((string) CyberSetting::query()->where('cle', self::CLE)->value('valeur'));

        if (strlen($brut) !== self::LONGUEUR) {
            $brut = self::generer();
            CyberSetting::definir(self::CLE, $brut);
            Log::info("Code d'installation généré automatiquement (première utilisation).");
        }

        Cache::put(self::CACHE_KEY, $brut, 60);

        return $brut;
    }

    private static function generer(): string
    {
        $code = '';
        $max  = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LONGUEUR; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    private static function normaliser(string $texte): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $texte));
    }

    private static function formater(string $brut): string
    {
        return substr($brut, 0, 4) . '-' . substr($brut, 4);
    }
}