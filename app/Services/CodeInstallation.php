<?php

namespace App\Services;

use App\Models\CyberSetting;

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

        return self::formater($brut);
    }

    /** Le code saisi est-il le bon ? (majuscules, tirets et espaces ignorés) */
    public static function verifier(?string $saisi): bool
    {
        $saisi = self::normaliser((string) $saisi);
        if (strlen($saisi) !== self::LONGUEUR) {
            return false;
        }

        return hash_equals(self::obtenirBrut(), $saisi);
    }

    // ------------------------------------------------------------------

    private static function obtenirBrut(): string
    {
        $brut = '';
        try {
            $brut = self::normaliser((string) CyberSetting::query()->where('cle', self::CLE)->value('valeur'));
        } catch (\Throwable) {
            // table indisponible : on génère quand même un code
        }

        if (strlen($brut) !== self::LONGUEUR) {
            $brut = self::generer();
            CyberSetting::definir(self::CLE, $brut);
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