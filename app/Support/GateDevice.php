<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class GateDevice
{
    /**
     * The event the request's gate token may check tickets in for.
     * A token without a checkin ability, or whose event is gone, is
     * treated like a revoked one: 401.
     */
    public static function event(Request $request): Event
    {
        $token = $request->user()?->currentAccessToken();

        $eventId = $token instanceof PersonalAccessToken
            ? collect($token->abilities)
                ->map(fn (string $ability): ?int => preg_match('/^checkin:(\d+)$/', $ability, $m) ? (int) $m[1] : null)
                ->filter()
                ->first()
            : null;

        $event = $eventId === null ? null : Event::whereKey($eventId)
            ->where('user_id', $token->tokenable_id)
            ->first();

        abort_if($event === null, 401, 'Dispositivo revogado ou inválido');

        return $event;
    }
}
