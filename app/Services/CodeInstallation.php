<?php

namespace App\Services;

use App\Models\CyberSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Code d'installation : il faut le connaître pour enregistrer un NOUVEAU poste.
 * Il empêche un appareil quelconque du réseau (par exemple un client en Wi-Fi) de s'enregistrer.
 * Les postes déjà enregistrés ne sont pas concernés quand on le régénère.
 *
 * Stocké dans la table cyber_settings (clé « code_installation »), sans toucher à CyberSetting.
 */
final class CodeInstallation
{
    private const CLE = 'code_installation';
    private const CACHE_KEY = 'code_installation_brut';

    // Sans caractères ambigus (pas de 0/O, 1/I)
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LONGUEUR = 8;

    /** Code à afficher (XXXX-XXXX). Il est créé automatiquement s'il n'existe pas encore. */
    public static function obtenir(): string
    {
        return self::formater(self::obtenirBrut());
    }

    /** Remplace le code par un nouveau. Les postes déjà enregistrés continuent de fonctionner. */
    public static function regenerer(): string
    {
        $brut = self::generer();
        CyberSetting::definir(self::CLE, $brut);
        Cache::forget(self::CACHE_KEY);
        Log::info("Code d'installation régénéré. Nouveau code : " . self::formater($brut));

        return self::formater($brut);
    }

    /** Le code saisi est-il le bon ? (majuscules, tirets et espaces ignorés) */
    public static function verifier(?string $saisi): bool
    {
        $saisi = self::normaliser((string) $saisi);
        if (strlen($saisi) !== self::LONGUEUR) {
            return false;
        }

        $attendu = self::obtenirBrut();

        // Log de débogage (visible dans storage/logs/laravel.log)
        Log::debug("Vérification code d'installation : saisi='" . $saisi . "', attendu='" . $attendu . "'");

        return hash_equals($attendu, $saisi);
    }

    // ------------------------------------------------------------------

    private static function obtenirBrut(): string
    {
        // On essaie de lire depuis le cache d'abord
        $brut = Cache::remember(self::CACHE_KEY, 60, function () {
            try {
                $valeur = CyberSetting::query()->where('cle', self::CLE)->value('valeur');
                return self::normaliser((string) $valeur);
            } catch (\Throwable $e) {
                Log::error("Impossible de lire le code d'installation : " . $e->getMessage());
                return '';
            }
        });

        // Si le code est vide ou invalide, on en génère un nouveau
        if (strlen($brut) !== self::LONGUEUR) {
            $brut = self::generer();
            CyberSetting::definir(self::CLE, $brut);
            Cache::put(self::CACHE_KEY, $brut, 60);
            Log::info("Nouveau code d'installation généré automatiquement : " . self::formater($brut));
        }

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