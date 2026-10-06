<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ferme les sessions Ethernet arrivées à expiration
Schedule::command('sessions:expire')->everyMinute();

// Synchronise les sessions Wi-Fi depuis le MikroTik
Schedule::command('wifi:sync-sessions')->everyMinute();

// Expire les vouchers dépassés et réapprovisionne le pool de temporaires
Schedule::command('vouchers:reapprovisionner')
    ->everyFiveMinutes()
    ->withoutOverlapping();