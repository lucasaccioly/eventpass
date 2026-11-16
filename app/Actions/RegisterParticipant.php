<?php

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Jobs\GenerateTicketPdf;
use App\Models\Event;
use App\Models\Registration;
use App\Support\TicketCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisterParticipant
{
    /**
     * @throws ValidationException when the event is full or the email is taken
     */
    public function handle(Event $event, string $name, string $email): Registration
    {
        $registration = DB::transaction(function () use ($event, $name, $email): Registration {
            // Row lock: two people racing for the last seat are serialized here.
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->registrations()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Este e-mail já está inscrito.']);
            }

            if ($locked->registrations()->count() >= $locked->capacity) {
                throw ValidationException::withMessages(['email' => 'Inscrições esgotadas.']);
            }

            return $this->insert($locked, $name, $email);
        });

        GenerateTicketPdf::dispatch($registration)->afterCommit();

        return $registration;
    }

    private function insert(Event $event, string $name, string $email, int $attempt = 1): Registration
    {
        try {
            return DB::transaction(fn () => $event->registrations()->create([
                'name' => $name,
                'email' => $email,
                'code' => TicketCode::generate(),
                'ticket_status' => TicketStatus::Pending,
            ]));
        } catch (UniqueConstraintViolationException $e) {
            // A code collision is ~1 in 10^15 per pair; retry a couple of times.
            if ($attempt >= 3) {
                throw $e;
            }

            return $this->insert($event, $name, $email, $attempt + 1);
        }
    }
}
