<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Jobs\GenerateTicketPdf;
use App\Models\Registration;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /** A pending ticket untouched this long has lost its job (Redis restart, killed worker). */
    private const STUCK_AFTER_SECONDS = 120;

    public function show(Registration $registration): Response
    {
        $stuck = $registration->ticket_status === TicketStatus::Pending
            && $registration->updated_at?->lt(now()->subSeconds(self::STUCK_AFTER_SECONDS));

        if ($registration->ticket_status === TicketStatus::Failed || $stuck) {
            $registration->ticket_status = TicketStatus::Pending;
            $registration->touch();
            GenerateTicketPdf::dispatch($registration);
        }

        $event = $registration->event;

        return Inertia::render('public/ticket', [
            'ticket' => [
                'public_id' => $registration->public_id,
                'name' => $registration->name,
                'code' => $registration->code,
                'status' => $registration->ticket_status->value,
                'pdf_url' => $registration->ticket_status === TicketStatus::Ready
                    ? route('tickets.pdf', $registration)
                    : null,
                'event' => $event->only(['name', 'slug', 'venue', 'starts_at']),
            ],
        ]);
    }
}
