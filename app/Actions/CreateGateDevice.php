<?php

namespace App\Actions;

use App\Models\Event;
use Illuminate\Validation\ValidationException;

final class CreateGateDevice
{
    /**
     * Issue a Sanctum token that can only check tickets in for this event.
     *
     * @return string the plain-text token, shown to the organizer once
     *
     * @throws ValidationException at the per-event device limit
     */
    public function handle(Event $event, string $name): string
    {
        $max = (int) config('eventpass.max_devices_per_event');

        if ($event->deviceTokens()->count() >= $max) {
            throw ValidationException::withMessages(['name' => "Limite de {$max} dispositivos por evento."]);
        }

        return $event->user->createToken($name, [$event->checkinAbility()])->plainTextToken;
    }
}
