<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

class PublicEventController extends Controller
{
    public function show(Event $event): Response
    {
        return Inertia::render('public/event', [
            'event' => $event->only(['name', 'slug', 'description', 'venue', 'starts_at', 'capacity']),
            'spots_left' => max(0, $event->capacity - $event->registrations()->count()),
        ]);
    }
}
