<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('events/index', [
            'events' => $request->user()->events()->withCount('registrations')->orderBy('starts_at')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('events/form', ['event' => null]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = $request->user()->events()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento criado.']);

        return to_route('events.show', $event);
    }

    public function show(Event $event): Response
    {
        Gate::authorize('view', $event);

        return Inertia::render('events/show', [
            'event' => $event,
            'stats' => $event->stats(),
            'devices' => $event->deviceTokens()->latest()->get(['id', 'name', 'created_at', 'last_used_at']),
        ]);
    }

    public function edit(Event $event): Response
    {
        Gate::authorize('update', $event);

        return Inertia::render('events/form', ['event' => $event]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento atualizado.']);

        return to_route('events.show', $event);
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento excluído.']);

        return to_route('events.index');
    }
}
