<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosteController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TarifController;

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

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
