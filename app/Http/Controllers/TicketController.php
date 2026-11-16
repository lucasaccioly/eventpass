<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function show(Registration $registration): Response
    {
        $event = $registration->event;

        return Inertia::render('public/ticket', [
            'ticket' => [
                'public_id' => $registration->public_id,
                'name' => $registration->name,
                'code' => $registration->code,
                'status' => $registration->ticket_status->value,
                'event' => $event->only(['name', 'slug', 'venue', 'starts_at']),
            ],
        ]);
    }
}
