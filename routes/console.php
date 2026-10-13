<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ferme les sessions Ethernet arrivées à expiration
Schedule::command('sessions:expire')->everyMinute();

// Synchronise les sessions Wi-Fi depuis le MikroTik, toutes les 5 secondes,
// même si aucune page n'est ouverte. Le verrou est dans WifiSessionService.
Schedule::command('wifi:sync-sessions')
    ->everyFiveSeconds()
    ->withoutOverlapping(2);

// Expire les vouchers dépassés et réapprovisionne le pool de temporaires
Schedule::command('vouchers:reapprovisionner')
    ->everyFiveMinutes()
    ->withoutOverlapping();