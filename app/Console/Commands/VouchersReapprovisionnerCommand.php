<?php

namespace App\Console\Commands;

use App\Services\VoucherReplenishmentService;
use Illuminate\Console\Command;

class VouchersReapprovisionnerCommand extends Command
{
    protected $signature   = 'vouchers:reapprovisionner {--simuler} {--force : Ignore le mode manuel}';
    protected $description = 'Réapprovisionne le pool d’identifiants HotSpot disponibles';

    public function handle(VoucherReplenishmentService $pool): int
    {
        $r = $pool->verifierEtReapprovisionner((bool) $this->option('simuler'), (bool) $this->option('force'));

        $this->line(sprintf('Pool : %d disponible(s) · seuil %d · taille %d', $r['disponibles'], $r['seuil'], $r['taille']));

        match ($r['statut']) {
            'genere' => $this->info($r['message']),
            'erreur' => $this->error($r['message']),
            'ignore' => $this->warn($r['message']),
            default  => $this->line($r['message']),
        };

        return $r['statut'] === 'erreur' ? self::FAILURE : self::SUCCESS;
    }
}