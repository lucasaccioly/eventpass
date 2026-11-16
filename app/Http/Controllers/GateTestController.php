<?php

namespace App\Http\Controllers;

use App\Actions\CreateGateDevice;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** "Testar portaria": a device for this browser, so the gate can be tried without a phone. */
class GateTestController extends Controller
{
    public const DEVICE_NAME = 'Este navegador';

    public function __invoke(Event $event, CreateGateDevice $create): RedirectResponse
    {
        Gate::authorize('update', $event);

        // Replace the previous test device instead of piling up tokens toward the limit.
        $event->deviceTokens()->where('name', self::DEVICE_NAME)->delete();
        $token = $create->handle($event, self::DEVICE_NAME);

        Inertia::flash('device_link', route('gate').'#token='.rawurlencode($token));

        return to_route('events.show', $event);
    }
}
