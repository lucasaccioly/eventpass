<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\GateDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GateController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $event = GateDevice::event($request);

        return response()->json([
            'event' => [
                'name' => $event->name,
                'venue' => $event->venue,
                'starts_at' => $event->starts_at->toIso8601String(),
            ],
        ]);
    }
}
