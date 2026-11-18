<?php

use App\Http\Controllers\Api\AgentMiseAJourController;
use App\Http\Controllers\Api\AgentRestrictionsController;
use App\Http\Controllers\Api\DecouverteController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\ParcController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\RestrictionsController;
use App\Http\Controllers\VersionsController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/agent')->group(function () {
    Route::get('/mise-a-jour',                 [AgentMiseAJourController::class, 'visee']);
    Route::get('/paquet/{version}',            [AgentMiseAJourController::class, 'paquet']);
    Route::post('/mise-a-jour/resultat',       [AgentMiseAJourController::class, 'resultat']);

    Route::get('/restrictions',                [AgentRestrictionsController::class, 'show']);

    Route::get('/decouverte',                  [DecouverteController::class, 'show'])->middleware('throttle:60,1');

    Route::middleware('throttle:60,1')->prefix('installation')->group(function () {
        Route::get('/info',     [InstallationController::class, 'info']);
        Route::get('/gardien',  [InstallationController::class, 'gardien']);
        Route::get('/ecran',    [InstallationController::class, 'ecran']);
        Route::get('/webview2', [InstallationController::class, 'webview2']);
    });
});

Route::middleware(['auth', RoleMiddleware::class . ':admin'])->prefix('parametres')->group(function () {
    Route::get('/installation',                         [InstallationController::class, 'index'])->name('parametres.installation.index');
    Route::get('/installation/telecharger',             [InstallationController::class, 'telecharger'])->name('parametres.installation.telecharger');
    Route::get('/installation/fichier/{cle}',           [InstallationController::class, 'fichier'])->whereIn('cle', ['gardien', 'ecran', 'webview2'])->name('parametres.installation.fichier');
    Route::get('/installation/postes',                  [InstallationController::class, 'postes'])->name('parametres.installation.postes');
    Route::post('/installation/postes/{poste}/renommer', [InstallationController::class, 'renommer'])->name('parametres.installation.renommer');

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

    // Personnel
    Route::get('/personnel',                      [PersonnelController::class, 'index'])->name('parametres.personnel.index');
    Route::post('/personnel',                     [PersonnelController::class, 'store'])->name('parametres.personnel.store');
    Route::patch('/personnel/{utilisateur}',      [PersonnelController::class, 'update'])->name('parametres.personnel.update');
    Route::delete('/personnel/{utilisateur}',     [PersonnelController::class, 'destroy'])->name('parametres.personnel.destroy');
    Route::post('/personnel/{utilisateur}/reinitialiser', [PersonnelController::class, 'reinitialiserMotDePasse'])->name('parametres.personnel.reinitialiser');
    Route::post('/personnel/{utilisateur}/deconnecter-partout', [PersonnelController::class, 'deconnecterPartout'])->name('parametres.personnel.deconnecter-partout');
    Route::delete('/personnel/{utilisateur}/avatar', [PersonnelController::class, 'supprimerAvatar'])->name('parametres.personnel.avatar.supprimer');
});