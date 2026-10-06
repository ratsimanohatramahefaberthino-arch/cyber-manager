<?php

use App\Http\Controllers\HotspotController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosteController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TarifController;
use App\Http\Controllers\WifiController;
use App\Http\Controllers\MikroTikStatusController;

Route::get('/tarifs', [TarifController::class, 'index'])
    ->middleware('auth')
    ->name('tarifs.index');

Route::post('/tarifs', [TarifController::class, 'store'])
    ->middleware('auth')
    ->name('tarifs.store');

Route::post('/tarifs/{tarif}/activer', [TarifController::class, 'activer'])
    ->middleware('auth')
    ->name('tarifs.activer');

Route::delete('/tarifs/{tarif}', [TarifController::class, 'destroy'])
    ->middleware('auth')
    ->name('tarifs.destroy');

Route::get('/sessions', [SessionController::class, 'index'])
    ->middleware('auth')
    ->name('sessions.index');

Route::post('/sessions', [SessionController::class, 'store'])
    ->middleware('auth')
    ->name('sessions.store');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::get('/postes', [PosteController::class, 'index'])
    ->middleware('auth')
    ->name('postes.index');

Route::post('/postes/{poste}/tester-agent', [PosteController::class, 'testerAgent'])
    ->middleware('auth')
    ->name('postes.tester-agent');

Route::post('/postes/{poste}/verrouiller', [PosteController::class, 'verrouiller'])
    ->middleware('auth')
    ->name('postes.verrouiller');

Route::post('/postes/{poste}/deverrouiller', [PosteController::class, 'deverrouiller'])
    ->middleware('auth')
    ->name('postes.deverrouiller');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/mikrotik/statut', [MikroTikStatusController::class, 'show'])
        ->name('mikrotik.statut');

    Route::get('/hotspot', [HotspotController::class, 'index'])
        ->name('hotspot.index');

    Route::get('/hotspot/etat', [HotspotController::class, 'etat'])
        ->name('hotspot.etat');

    Route::get('/hotspot/options-mikrotik', [HotspotController::class, 'optionsMikrotik'])
        ->name('hotspot.options-mikrotik');

    Route::post('/hotspot/generer', [HotspotController::class, 'generer'])
        ->name('hotspot.generer');

    Route::post('/hotspot/ajouter', [HotspotController::class, 'ajouter'])
        ->name('hotspot.ajouter');

    Route::get('/hotspot/imprimer/{lot}', [HotspotController::class, 'imprimerLot'])
        ->name('hotspot.imprimer.lot');

    Route::post('/hotspot/{voucher}/proteger', [HotspotController::class, 'proteger'])
        ->name('hotspot.proteger');

    Route::post('/hotspot/appareils/{appareil}/renommer', [HotspotController::class, 'renommerAppareil'])
        ->name('hotspot.appareils.renommer');

    Route::post('/hotspot/synchroniser-tout', [HotspotController::class, 'synchroniserTout'])
        ->name('hotspot.synchroniser-tout');

    Route::post('/hotspot/supprimer-masse', [HotspotController::class, 'supprimerMasse'])
        ->name('hotspot.supprimer-masse');

    Route::post('/hotspot/pool-mode', [HotspotController::class, 'poolMode'])
        ->name('hotspot.pool-mode');
});

Route::middleware('auth')->group(function () {
    Route::get('/wifi', [WifiController::class, 'index'])
        ->name('wifi.index');

    Route::get('/wifi/comptes', [WifiController::class, 'comptes'])
    ->name('wifi.comptes');

    Route::post('/wifi/sync', [WifiController::class, 'syncNow'])
    ->name('wifi.sync');

    Route::post('/wifi/deconnecter', [WifiController::class, 'deconnecter'])
        ->name('wifi.deconnecter');

    Route::post('/wifi/comptes/{username}/toggle', [WifiController::class, 'toggleCompte'])
        ->name('wifi.comptes.toggle');

    Route::delete('/wifi/comptes/{username}', [WifiController::class, 'supprimerCompte'])
        ->name('wifi.comptes.supprimer');
});

require __DIR__.'/auth.php';