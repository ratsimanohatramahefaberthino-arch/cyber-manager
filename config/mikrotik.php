<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuration MikroTik
    |--------------------------------------------------------------------------
    |
    | Toutes les valeurs sont externalisées dans .env.
    | Aucune adresse IP, aucun identifiant ne doit être codé en dur
    | dans le code applicatif (règle 3 du contexte maître).
    |
    */

    'host'     => env('MIKROTIK_HOST', '128.0.1.1'),
    'port'     => (int) env('MIKROTIK_PORT', 8728),
    'user'     => env('MIKROTIK_USER', 'admin'),
    'password' => env('MIKROTIK_PASSWORD', ''),
    'ssl'      => filter_var(env('MIKROTIK_SSL', false), FILTER_VALIDATE_BOOLEAN),
    'timeout'  => (float) env('MIKROTIK_TIMEOUT', 5.0),

    // Profil HotSpot par défaut pour les utilisateurs créés par Cyber Manager.
    // À adapter selon les profils réels du routeur (default, LIBRE, ...).
    'hotspot_profile'     => env('MIKROTIK_HOTSPOT_PROFILE', 'default'),

    // Préfixe des utilisateurs créés par Cyber Manager.
    // Permet de les distinguer des users créés manuellement / Mikhmon.
    'hotspot_user_prefix' => env('MIKROTIK_USER_PREFIX', 'cm-'),

    // 'test' ou 'production' — purement informatif pour l'UI/l'audit.
    // Ne JAMAIS s'en servir pour changer le comportement du code.
    'environment' => env('MIKROTIK_ENV', 'test'),

    /*
    |--------------------------------------------------------------------------
    | SSID Wi-Fi
    |--------------------------------------------------------------------------
    |
    | Le champ "server" renvoyé par /ip/hotspot/active est le nom du serveur
    | HotSpot, pas le SSID. On le traduit ici.
    |
    | MIKROTIK_SSID_MAP     = "hotspot1=NomDuSSID,hotspot2=AutreSSID"
    | MIKROTIK_SSID_DEFAULT = SSID utilisé si le serveur est inconnu/absent
    |
    */
    'ssid_default' => env('MIKROTIK_SSID_DEFAULT'),

    'ssid_map' => collect(explode(',', (string) env('MIKROTIK_SSID_MAP', '')))
        ->filter(fn ($paire) => str_contains($paire, '='))
        ->mapWithKeys(function ($paire) {
            [$serveur, $ssid] = explode('=', $paire, 2);
            return [trim($serveur) => trim($ssid)];
        })
        ->all(),

    /*
    |--------------------------------------------------------------------------
    | Pool automatique de vouchers TEMPORAIRES
    |--------------------------------------------------------------------------
    |
    | Toutes les 5 minutes, si le nombre de temporaires "disponibles" est
    | inférieur au seuil, un lot est généré pour remonter à la taille cible
    | (taille - disponibles). Les permanents ne sont jamais concernés.
    |
    | MONTANT / DUREE : caractéristiques des vouchers générés. Si l'un des
    | deux vaut 0, les valeurs du dernier lot créé sont reprises.
    |
    */
    'voucher_pool_seuil'  => (int) env('VOUCHER_POOL_SEUIL', 10),
    'voucher_pool_taille' => (int) env('VOUCHER_POOL_TAILLE', 40),


];