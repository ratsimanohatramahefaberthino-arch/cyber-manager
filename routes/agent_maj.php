<?php

use App\Http\Controllers\Api\AgentMiseAJourController;
use App\Http\Controllers\Api\AgentRestrictionsController;
use App\Http\Controllers\Api\DecouverteController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\ParcController;
use App\Http\Controllers\RestrictionsController;
use App\Http\Controllers\VersionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/agent')->group(function () {
    Route::get('/mise-a-jour',                 [AgentMiseAJourController::class, 'visee']);
    Route::get('/paquet/{version}',            [AgentMiseAJourController::class, 'paquet']);
    Route::post('/mise-a-jour/resultat',       [AgentMiseAJourController::class, 'resultat']);

    // Restrictions Windows : profil + maintenance du poste (jeton du poste requis)
    Route::get('/restrictions',                [AgentRestrictionsController::class, 'show']);

    // Découverte du serveur par les postes : signature + identifiant du serveur (public, sans secret)
    Route::get('/decouverte',                  [DecouverteController::class, 'show'])->middleware('throttle:60,1');

    // Installateur (avant le premier jeton) : info publique, fichiers protégés par le code d'installation
    Route::middleware('throttle:60,1')->prefix('installation')->group(function () {
        Route::get('/info',     [InstallationController::class, 'info']);
        Route::get('/gardien',  [InstallationController::class, 'gardien']);
        Route::get('/ecran',    [InstallationController::class, 'ecran']);
        Route::get('/webview2', [InstallationController::class, 'webview2']);
    });
});

// Administration : Paramètres > Installation, Versions et Parc
Route::middleware('auth')->prefix('parametres')->group(function () {
    Route::get('/installation',                   [InstallationController::class, 'index'])->name('parametres.installation.index');
    Route::get('/installation/telecharger',       [InstallationController::class, 'telecharger'])->name('parametres.installation.telecharger');

    Route::get('/versions',                       [VersionsController::class, 'index'])->name('parametres.versions.index');
    Route::post('/versions',                      [VersionsController::class, 'televerser'])->name('parametres.versions.televerser');
    Route::post('/versions/{version}/pilote',     [VersionsController::class, 'pilote'])->name('parametres.versions.pilote');
    Route::post('/versions/{version}/general',    [VersionsController::class, 'general'])->name('parametres.versions.general');
    Route::post('/versions/{version}/depublier',  [VersionsController::class, 'depublier'])->name('parametres.versions.depublier');
    Route::delete('/versions/{version}',          [VersionsController::class, 'supprimer'])->name('parametres.versions.supprimer');

    Route::get('/parc',                           [ParcController::class, 'index'])->name('parametres.parc.index');
    Route::post('/parc/restrictions',             [RestrictionsController::class, 'enregistrer'])->name('parametres.parc.restrictions');
    Route::post('/parc/{poste}/renommer',         [ParcController::class, 'renommer'])->name('parametres.parc.renommer');
    Route::post('/parc/{poste}/maintenance',      [ParcController::class, 'maintenance'])->name('parametres.parc.maintenance');
});