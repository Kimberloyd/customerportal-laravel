<?php

namespace App\Http\Controllers;

use App\Services\ReliabilityHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function ready(Request $request, ReliabilityHealth $health): JsonResponse
    {
        $status = $health->status();

        return response()->json([
            ...$status,
            'request_id' => $request->attributes->get('request_id'),
        ], $status['status'] === 'ok' ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
