<?php

namespace App\Console\Commands;

use App\Services\SessionExpirationService;
use Illuminate\Console\Command;

class ExpireSessions extends Command
{
    protected $signature = 'sessions:expire';

    protected $description = 'Vérifie et expire les sessions arrivées à leur terme';

    public function handle(
        SessionExpirationService $sessionExpirationService
    ): int {
        $expirees = $sessionExpirationService->verifierSessions();

        $this->info("Sessions expirées : {$expirees}");

        return self::SUCCESS;
    }
}