<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

/**
 * Vouchers permanents DE TEST pour les appareils du personnel.
 *
 * Écrit UNIQUEMENT en base : rien n'est créé sur le MikroTik.
 * Idempotent (firstOrCreate sur le username).
 *
 * ⚠ Identifiants de test imposés par le cahier des charges :
 *   à remplacer par de vrais identifiants avant toute mise en production.
 */
class VoucherPermanentSeeder extends Seeder
{
    public function run(): void
    {
        $permanents = [
            ['username' => 'mahefa',      'password' => 'mahefa2026'],
            ['username' => 'maman&nolan', 'password' => 'mn2026'],
        ];

        foreach ($permanents as $p) {
            Voucher::firstOrCreate(
                ['username' => $p['username']],
                [
                    'password'            => $p['password'],
                    'code'                => $p['password'],
                    'montant'             => 0, // 0 = gratuit
                    'duree'               => 0, // 0 = illimité (pas de limit-uptime)
                    'etat'                => 'disponible',
                    'source'              => 'manuel',
                    'type_voucher'        => Voucher::TYPE_PERMANENT,
                    'date_heure_creation' => now(),
                    'description'         => 'Appareil personnel du personnel',
                ]
            );
        }

        $this->command?->info('Vouchers permanents de test créés (base uniquement).');
    }
}