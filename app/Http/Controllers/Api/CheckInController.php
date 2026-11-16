<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Support\GateDevice;
use App\Support\TicketCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $event = GateDevice::event($request);
        $request->validate(['code' => ['required', 'string', 'max:32']]);
        $code = TicketCode::normalize($request->string('code')->value());

        // One conditional UPDATE: two phones scanning the same ticket at the
        // same instant can't both let it in.
        $admitted = Registration::where('code', $code)
            ->where('event_id', $event->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => now()]);

        $registration = Registration::firstWhere('code', $code);

        if ($registration === null) {
            return response()->json(['message' => 'Ingresso não encontrado'], 404);
        }

        if ($registration->event_id !== $event->id) {
            return response()->json(['message' => 'Ingresso de outro evento'], 403);
        }

        $checkedInAt = $registration->checked_in_at?->toIso8601String();

        if ($admitted === 0) {
            return response()->json(['message' => 'Ingresso já usado', 'checked_in_at' => $checkedInAt], 409);
        }

        return response()->json(['name' => $registration->name, 'checked_in_at' => $checkedInAt]);
    }
}
