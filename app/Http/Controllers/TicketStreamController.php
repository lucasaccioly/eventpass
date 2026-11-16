<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Registration;
use App\Support\PollingStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketStreamController extends Controller
{
    public function __invoke(Registration $registration): StreamedResponse
    {
        return PollingStream::response(
            'status',
            function () use ($registration): array {
                $registration->refresh();

                return [
                    'status' => $registration->ticket_status->value,
                    'pdf_url' => $registration->ticket_status === TicketStatus::Ready
                        ? route('tickets.pdf', $registration)
                        : null,
                ];
            },
            fn (array $data): bool => $data['status'] !== TicketStatus::Pending->value,
        );
    }
}
