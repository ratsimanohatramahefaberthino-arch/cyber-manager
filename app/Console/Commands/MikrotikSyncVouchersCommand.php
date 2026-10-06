<?php

namespace App\Console\Commands;

use App\Services\VoucherService;
use Illuminate\Console\Command;

class MikrotikSyncVouchersCommand extends Command
{
    protected $signature = 'mikrotik:sync-vouchers {--limite=50 : Nombre max de vouchers à traiter}';

    protected $description = 'Synchronise vers MikroTik les vouchers en attente (mikrotik_synced_at NULL)';

    public function handle(VoucherService $service): int
    {
        $limite = (int) $this->option('limite');

        $this->info("Recherche des vouchers non synchronisés (limite : {$limite})...");

        $result = $service->synchroniserVouchersEnAttente($limite);

        $this->newLine();
        $this->info("✓ Synchronisés : {$result['ok']}");
        if ($result['ko'] > 0) {
            $this->warn("✗ Échecs : {$result['ko']}");
        }
        $this->line("Restants non synchronisés : {$result['restants']}");

        return $result['ko'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}