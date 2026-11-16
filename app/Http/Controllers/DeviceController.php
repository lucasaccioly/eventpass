<?php

namespace App\Http\Controllers;

use App\Actions\CreateGateDevice;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DeviceController extends Controller
{
    public function store(Request $request, Event $event, CreateGateDevice $create): RedirectResponse
    {
        Gate::authorize('update', $event);
        $validated = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $token = $create->handle($event, $validated['name']);

        // Shown once: only the hash is stored.
        Inertia::flash('device_link', route('gate').'#token='.rawurlencode($token));

        return to_route('events.show', $event);
    }

    public function destroy(Event $event, int $device): RedirectResponse
    {
        Gate::authorize('update', $event);

        $event->deviceTokens()->whereKey($device)->firstOrFail()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dispositivo revogado.']);

        return to_route('events.show', $event);
    }
}
