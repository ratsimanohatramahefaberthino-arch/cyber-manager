<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poste;
use App\Services\RestrictionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRestrictionsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $token = $request->header('X-Agent-Token');
        $poste = $token ? Poste::where('agent_token', $token)->first() : null;
        if ($poste === null) {
            return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);
        }

        return response()->json(['ok' => true] + RestrictionsService::pourPoste($poste));
    }
}