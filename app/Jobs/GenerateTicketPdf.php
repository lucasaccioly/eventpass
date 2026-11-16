<?php

namespace App\Jobs;

use App\Models\Registration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTicketPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public Registration $registration) {}

    public function handle(): void
    {
        // Rendered in Task 4.
    }
}
