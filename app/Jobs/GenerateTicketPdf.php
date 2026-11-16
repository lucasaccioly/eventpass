<?php

namespace App\Jobs;

use App\Enums\TicketStatus;
use App\Models\Registration;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateTicketPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public Registration $registration) {}

    public function handle(): void
    {
        $registration = $this->registration;
        $disk = Storage::disk('local');

        // A retried or duplicated job must not redo finished work.
        if ($registration->ticket_status === TicketStatus::Ready
            && $registration->ticket_path !== null
            && $disk->exists($registration->ticket_path)) {
            return;
        }

        $path = "tickets/{$registration->public_id}.pdf";
        $disk->put($path, $this->render($registration));

        $registration->update([
            'ticket_status' => TicketStatus::Ready,
            'ticket_path' => $path,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->registration->update(['ticket_status' => TicketStatus::Failed]);
    }

    private function render(Registration $registration): string
    {
        $qr = (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 8,
            'quietzoneSize' => 2,
        ])))->render($registration->code);

        return Pdf::loadView('pdf.ticket', [
            'registration' => $registration,
            'event' => $registration->event,
            'qr' => $qr,
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }
}
