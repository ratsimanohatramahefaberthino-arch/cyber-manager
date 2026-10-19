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
use App\Http\Controllers\WifiAlarmController;

/*
|--------------------------------------------------------------------------
| Routes publiques
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Routes protégées (auth)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // -----------------------------------------------------------------
    // Dashboard
    // -----------------------------------------------------------------
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // -----------------------------------------------------------------
    // Tarifs — règles tarifaires (Ethernet / Wi-Fi)
    // -----------------------------------------------------------------
    Route::get('/tarifs', [TarifController::class, 'index'])
        ->name('tarifs.index');

    // Routes fixes AVANT les routes avec {tarif}
    Route::post('/tarifs/passer-separe', [TarifController::class, 'passerSepare'])
        ->name('tarifs.passer-separe');

    Route::post('/tarifs/passer-unifie', [TarifController::class, 'passerUnifie'])
        ->name('tarifs.passer-unifie');

    Route::post('/tarifs/reinitialiser', [TarifController::class, 'reinitialiser'])
        ->name('tarifs.reinitialiser');

    Route::post('/tarifs/simuler', [TarifController::class, 'simuler'])
        ->name('tarifs.simuler');

    Route::patch('/tarifs/{tarif}', [TarifController::class, 'update'])
        ->name('tarifs.update');

    Route::post('/tarifs/{tarif}/raccourcis', [TarifController::class, 'storeRaccourci'])
        ->name('tarifs.raccourcis.store');

    Route::delete('/tarifs/{tarif}/raccourcis/{raccourci}', [TarifController::class, 'destroyRaccourci'])
        ->name('tarifs.raccourcis.destroy');

    // -----------------------------------------------------------------
    // Sessions
    // -----------------------------------------------------------------
    Route::get('/sessions', [SessionController::class, 'index'])
        ->name('sessions.index');

    Route::post('/sessions', [SessionController::class, 'store'])
        ->name('sessions.store');

    // -----------------------------------------------------------------
    // Postes Ethernet
    // -----------------------------------------------------------------
    Route::get('/postes', [PosteController::class, 'index'])
        ->name('postes.index');

    Route::post('/postes/{poste}/tester-agent', [PosteController::class, 'testerAgent'])
        ->name('postes.tester-agent');

    Route::post('/postes/{poste}/verrouiller', [PosteController::class, 'verrouiller'])
        ->name('postes.verrouiller');

    Route::post('/postes/{poste}/deverrouiller', [PosteController::class, 'deverrouiller'])
        ->name('postes.deverrouiller');

    // -----------------------------------------------------------------
    // Profil utilisateur
    // -----------------------------------------------------------------
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // -----------------------------------------------------------------
    // MikroTik — statut
    // -----------------------------------------------------------------
    Route::get('/mikrotik/statut', [MikroTikStatusController::class, 'show'])
        ->name('mikrotik.statut');

    // -----------------------------------------------------------------
    // HotSpot (vouchers)
    // -----------------------------------------------------------------
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

    Route::get('/hotspot/imprimer-disponibles', [HotspotController::class, 'imprimerDisponibles'])
        ->name('hotspot.imprimer.disponibles');

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

    // -----------------------------------------------------------------
    // Wi-Fi
    // -----------------------------------------------------------------
    Route::get('/wifi', [WifiController::class, 'index'])->name('wifi.index');
    Route::get('/wifi/etat', [WifiController::class, 'etat'])->name('wifi.etat');

    Route::get('/wifi/comptes', [WifiController::class, 'comptes'])->name('wifi.comptes');
    Route::post('/wifi/sync', [WifiController::class, 'syncNow'])->name('wifi.sync');
    Route::post('/wifi/deconnecter', [WifiController::class, 'deconnecter'])->name('wifi.deconnecter');
    Route::post('/wifi/comptes/{username}/toggle', [WifiController::class, 'toggleCompte'])->name('wifi.comptes.toggle');
    Route::delete('/wifi/comptes/{username}', [WifiController::class, 'supprimerCompte'])->name('wifi.comptes.supprimer');

    // Actions Wi-Fi session (jamais de "démarrer" : c'est MikroTik qui détecte les connexions)
    Route::post('/wifi/session/{session}/arreter',  [WifiController::class, 'arreter'])->name('wifi.session.arreter');
    Route::post('/wifi/session/{session}/continuer', [WifiController::class, 'continuer'])->name('wifi.session.continuer');
    Route::delete('/wifi/session/{session}',         [WifiController::class, 'supprimer'])->name('wifi.session.supprimer');
    Route::post('/wifi/session/supprimer-masse',     [WifiController::class, 'supprimerMasse'])->name('wifi.session.supprimer-masse');
    Route::post('/wifi/session/{session}/remarque', [WifiController::class, 'enregistrerRemarque'])->name('wifi.session.remarque');

    // Alarmes par appareil
    Route::post('/wifi/session/{session}/alarme',            [WifiController::class, 'definirAlarme'])->name('wifi.session.alarme');
    Route::post('/wifi/session/{session}/alarme/prolonger',  [WifiController::class, 'prolongerAlarme'])->name('wifi.session.alarme.prolonger');
    Route::post('/wifi/session/{session}/alarme/annuler',    [WifiController::class, 'annulerAlarme'])->name('wifi.session.alarme.annuler');
    Route::post('/wifi/session/{session}/alarme/acquitter',  [WifiController::class, 'acquitterAlarme'])->name('wifi.session.alarme.acquitter');

    // Réglages alarme
    Route::get('/wifi/alarme',              [WifiAlarmController::class, 'index'])->name('wifi.alarme.index');
    Route::post('/wifi/alarme/reglages',    [WifiAlarmController::class, 'reglages'])->name('wifi.alarme.reglages');
    Route::post('/wifi/alarme/son',         [WifiAlarmController::class, 'uploader'])->name('wifi.alarme.son');
    Route::post('/wifi/alarme/son/choisir', [WifiAlarmController::class, 'choisirSon'])->name('wifi.alarme.son.choisir');
    Route::delete('/wifi/alarme/son',       [WifiAlarmController::class, 'supprimerSon'])->name('wifi.alarme.son.supprimer');
    Route::post('/wifi/alarme/reinitialiser', [WifiAlarmController::class, 'reinitialiser'])->name('wifi.alarme.reinitialiser');
});

/*
|--------------------------------------------------------------------------
| Authentification (Breeze)
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';