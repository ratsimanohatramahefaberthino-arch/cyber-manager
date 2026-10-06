<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Statut de connexion MikroTik pour la barre supérieure.
 * Le résultat (succès OU échec) est mis en cache 20 s : un routeur
 * injoignable ne ralentit donc pas chaque requête.
 */
class MikroTikStatusService
{
    private const CLE = 'mikrotik.statut';
    private const TTL = 20;

    public function __construct(
        private MikroTikService $mikroTik,
    ) {
    }

    /**
     * @return array{connecte:bool, identity:?string, version:?string, erreur:?string, verifie_a:string}
     */
    public function obtenir(): array
    {
        return Cache::remember(self::CLE, self::TTL, function () {
            try {
                $info = $this->mikroTik->testConnection();

                return [
                    'connecte'  => true,
                    'identity'  => $info['identity'],
                    'version'   => $info['version'],
                    'erreur'    => null,
                    'verifie_a' => now()->toIso8601String(),
                ];
            } catch (\Throwable $e) {
                return [
                    'connecte'  => false,
                    'identity'  => null,
                    'version'   => null,
                    'erreur'    => mb_substr($e->getMessage(), 0, 200),
                    'verifie_a' => now()->toIso8601String(),
                ];
            }
        });
    }
}