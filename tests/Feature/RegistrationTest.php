<?php

use App\Enums\TicketStatus;
use App\Jobs\GenerateTicketPdf;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Queue::fake());

function register(Event $event, string $email = 'ana@example.com', string $name = 'Ana Souza')
{
    return test()->post(route('registrations.store', $event->slug), compact('name', 'email'));
}

it('shows the public page without auth', function () {
    $event = Event::factory()->create(['capacity' => 10]);
    Registration::factory()->for($event)->count(3)->create();

    $this->get(route('public.events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/event')
            ->where('event.name', $event->name)
            ->where('spots_left', 7)
            ->missing('event.user_id'));
});

it('registers and queues the ticket', function () {
    $event = Event::factory()->create();

    $response = register($event);

    $registration = Registration::sole();
    $response->assertRedirect(route('tickets.show', $registration->public_id));
    expect($registration->ticket_status)->toBe(TicketStatus::Pending)
        ->and($registration->name)->toBe('Ana Souza')
        ->and($registration->code)->toMatch('/^[2-9A-HJ-NP-Z]{10}$/');
    Queue::assertPushed(GenerateTicketPdf::class, fn ($job) => $job->registration->is($registration));
});

it('normalizes email for duplicates', function () {
    $event = Event::factory()->create();
    register($event, 'ana@x.com');

    register($event, ' Ana@X.com ')
        ->assertSessionHasErrors(['email' => 'Este e-mail já está inscrito.']);

    expect(Registration::sole()->email)->toBe('ana@x.com');
});

it('allows the same email on another event', function () {
    register(Event::factory()->create(), 'ana@x.com');
    register(Event::factory()->create(), 'ana@x.com')->assertSessionHasNoErrors();

    expect(Registration::count())->toBe(2);
});

it('closes at exact capacity', function () {
    $event = Event::factory()->create(['capacity' => 1]);

    register($event, 'a@x.com')->assertSessionHasNoErrors();
    register($event, 'b@x.com')->assertSessionHasErrors(['email' => 'Inscrições esgotadas.']);

    expect(Registration::count())->toBe(1);
});

it('validates name and email', function () {
    $event = Event::factory()->create();

    $this->post(route('registrations.store', $event->slug), ['name' => '', 'email' => 'nope'])
        ->assertSessionHasErrors(['name', 'email']);
});

it('throttles registrations to 10 per minute per IP', function () {
    $event = Event::factory()->create();

    foreach (range(1, 10) as $i) {
        register($event, "p{$i}@x.com")->assertRedirect();
    }

    register($event, 'p11@x.com')->assertTooManyRequests();
});

it('shows the ticket page by public id', function () {
    $registration = Registration::factory()->create();

    $this->get(route('tickets.show', $registration->public_id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/ticket')
            ->where('ticket.name', $registration->name)
            ->where('ticket.status', 'pending')
            ->where('ticket.event.name', $registration->event->name)
            ->missing('ticket.email'));
});

it('404s unknown tickets', function () {
    $this->get('/t/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
});

it('rejects capacity below current registrations', function () {
    $event = Event::factory()->create(['capacity' => 5]);
    Registration::factory()->for($event)->count(3)->create();

    $this->actingAs($event->user)
        ->put(route('events.update', $event), [
            'name' => $event->name,
            'venue' => $event->venue,
            'starts_at' => $event->starts_at->format('Y-m-d H:i'),
            'capacity' => 2,
        ])
        ->assertSessionHasErrors(['capacity' => 'Já existem 3 inscritos; as vagas não podem ser menos que isso.']);

    expect($event->fresh()->capacity)->toBe(5);
});

it('counts registrations on the organizer list', function () {
    $event = Event::factory()->create();
    Registration::factory()->for($event)->count(2)->create();

    $this->actingAs($event->user)->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page->where('events.0.registrations_count', 2));
});
