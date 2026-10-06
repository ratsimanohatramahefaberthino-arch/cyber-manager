<?php

namespace App\Http\Controllers;

use App\Services\MikroTikStatusService;
use Illuminate\Http\JsonResponse;

class MikroTikStatusController extends Controller
{
    public function show(MikroTikStatusService $statut): JsonResponse
    {
        return response()->json(
            $statut->obtenir() + ['environnement' => config('mikrotik.environment')]
        );
    }
}