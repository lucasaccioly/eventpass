<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Jobs\GenerateTicketPdf;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketPdfController extends Controller
{
    public function __invoke(Registration $registration): StreamedResponse|RedirectResponse
    {
        $disk = Storage::disk('local');

        if ($registration->ticket_status !== TicketStatus::Ready) {
            return to_route('tickets.show', $registration);
        }

        // The container's disk is wiped on every restart: regenerate instead of failing.
        if ($registration->ticket_path === null || ! $disk->exists($registration->ticket_path)) {
            $registration->update(['ticket_status' => TicketStatus::Pending, 'ticket_path' => null]);
            GenerateTicketPdf::dispatch($registration);

            return to_route('tickets.show', $registration);
        }

        return $disk->download($registration->ticket_path, "ingresso-{$registration->code}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
