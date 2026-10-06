<?php

namespace App\Console\Commands;

use App\Services\MikroTikService;
use Illuminate\Console\Command;

class MikrotikTestCommand extends Command
{
    protected $signature = 'mikrotik:test
                            {--users : Lister aussi les utilisateurs HotSpot}
                            {--active : Lister aussi les sessions HotSpot actives}
                            {--profiles : Lister aussi les profils HotSpot}';

    protected $description = 'Teste la connexion à l\'API MikroTik RouterOS';

    public function handle(MikroTikService $service): int
    {
        $config = config('mikrotik');

        $this->info(sprintf(
            'Cible : %s:%d (env: %s, user: %s, ssl: %s)',
            $config['host'],
            $config['port'],
            $config['environment'],
            $config['user'],
            $config['ssl'] ? 'oui' : 'non'
        ));

        try {
            $info = $service->testConnection();
        } catch (\Throwable $e) {
            $this->error('Échec : ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('✓ Connexion et login OK');
        $this->newLine();

        $this->table(
            ['Champ', 'Valeur'],
            [
                ['Identity',     $info['identity']   ?? '-'],
                ['Version',      $info['version']    ?? '-'],
                ['Board',        $info['board_name'] ?? '-'],
                ['Uptime',       $info['uptime']     ?? '-'],
            ]
        );

        if ($this->option('users')) {
            $this->dumpList('Utilisateurs HotSpot', $service->listHotspotUsers(), 'name');
        }
        if ($this->option('active')) {
            $this->dumpList('Sessions HotSpot actives', $service->listHotspotActive(), 'user');
        }
        if ($this->option('profiles')) {
            $this->dumpList('Profils HotSpot', $service->listHotspotProfiles(), 'name');
        }

        return self::SUCCESS;
    }

    private function dumpList(string $title, array $rows, string $key): void
    {
        $this->newLine();
        $this->info("$title : " . count($rows));

        if (empty($rows)) {
            return;
        }

        foreach (array_slice($rows, 0, 15) as $row) {
            $this->line(' - ' . ($row[$key] ?? '?'));
        }

        if (count($rows) > 15) {
            $this->line(' ... (' . (count($rows) - 15) . ' de plus)');
        }
    }
}