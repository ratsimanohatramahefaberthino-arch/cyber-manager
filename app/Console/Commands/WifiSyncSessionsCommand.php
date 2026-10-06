<?php

namespace App\Console\Commands;

use App\Services\WifiSessionService;
use Illuminate\Console\Command;

class WifiSyncSessionsCommand extends Command
{
    protected $signature   = 'wifi:sync-sessions';
    protected $description = 'Synchronise les sessions Wi-Fi depuis le HotSpot MikroTik';

    public function handle(WifiSessionService $service): int
    {
        $result = $service->synchroniser();

        if ($result['erreur']) {
            $this->error('MikroTik injoignable : ' . $result['erreur']);
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Sessions ouvertes : %d · mises à jour : %d · fermées : %d',
            $result['ouvertes'],
            $result['maj'],
            $result['fermees']
        ));

        return self::SUCCESS;
    }
}