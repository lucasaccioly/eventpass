<?php

use App\Enums\TicketStatus;
use App\Jobs\GenerateTicketPdf;
use App\Models\Registration;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('renders the pdf and marks ready', function () {
    $registration = Registration::factory()->create();

    (new GenerateTicketPdf($registration))->handle();

    $registration->refresh();
    expect($registration->ticket_status)->toBe(TicketStatus::Ready)
        ->and($registration->ticket_path)->toBe("tickets/{$registration->public_id}.pdf");
    Storage::disk('local')->assertExists($registration->ticket_path);
    expect(Storage::disk('local')->get($registration->ticket_path))->toStartWith('%PDF');
});

it('is idempotent', function () {
    $registration = Registration::factory()->ready()->create([
        'ticket_path' => 'tickets/existing.pdf',
    ]);
    Storage::disk('local')->put('tickets/existing.pdf', 'original');

    (new GenerateTicketPdf($registration))->handle();

    expect(Storage::disk('local')->get('tickets/existing.pdf'))->toBe('original');
    Storage::disk('local')->assertMissing("tickets/{$registration->public_id}.pdf");
});

it('marks failed', function () {
    $registration = Registration::factory()->create();

    (new GenerateTicketPdf($registration))->failed(new RuntimeException('boom'));

    expect($registration->refresh()->ticket_status)->toBe(TicketStatus::Failed);
});

it('retries 3 times with a 5 second backoff', function () {
    $job = new GenerateTicketPdf(Registration::factory()->create());

    expect($job->tries)->toBe(3)->and($job->backoff)->toBe(5);
});

it('downloads a ready ticket', function () {
    $registration = Registration::factory()->create();
    (new GenerateTicketPdf($registration))->handle();

    $response = $this->get(route('tickets.pdf', $registration->public_id));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain("ingresso-{$registration->code}.pdf");
});

it('requeues when the file is gone', function () {
    Queue::fake();
    $registration = Registration::factory()->ready()->create([
        'ticket_path' => 'tickets/wiped.pdf',
    ]);

    $this->get(route('tickets.pdf', $registration->public_id))
        ->assertRedirect(route('tickets.show', $registration->public_id));

    expect($registration->refresh()->ticket_status)->toBe(TicketStatus::Pending);
    Queue::assertPushed(GenerateTicketPdf::class);
});

it('redirects pending downloads to the ticket page', function () {
    Queue::fake();
    $registration = Registration::factory()->create();

    $this->get(route('tickets.pdf', $registration->public_id))
        ->assertRedirect(route('tickets.show', $registration->public_id));

    Queue::assertNothingPushed();
});

it('re-queues a failed ticket when its page is opened', function () {
    Queue::fake();
    $registration = Registration::factory()->create(['ticket_status' => TicketStatus::Failed]);

    $this->get(route('tickets.show', $registration))->assertOk();

    expect($registration->refresh()->ticket_status)->toBe(TicketStatus::Pending);
    Queue::assertPushed(GenerateTicketPdf::class, 1);
});

it('re-queues a ticket stuck in pending (lost job)', function () {
    Queue::fake();
    $registration = Registration::factory()->create();
    $this->travel(3)->minutes();

    $this->get(route('tickets.show', $registration))->assertOk();

    Queue::assertPushed(GenerateTicketPdf::class, 1);
    expect($registration->refresh()->updated_at->isToday())->toBeTrue()
        ->and($registration->updated_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('leaves a freshly pending ticket alone', function () {
    Queue::fake();
    $registration = Registration::factory()->create();

    $this->get(route('tickets.show', $registration))->assertOk();

    Queue::assertNothingPushed();
});
