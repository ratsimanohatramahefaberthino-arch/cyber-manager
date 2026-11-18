<?php

use App\Http\Controllers\Api\AgentDesinstallationController;
use Illuminate\Support\Facades\Route;

// Désinstallation d'un poste par le poste lui-même (jeton X-Agent-Token).
// Chargé depuis routes/api.php : l'URL finale est POST /api/agent/desinstaller.
Route::post('/agent/desinstaller', [AgentDesinstallationController::class, 'desinstaller']);