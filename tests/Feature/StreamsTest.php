<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('ends the ticket stream once ready', function () {
    $registration = Registration::factory()->ready()->create();

    $content = $this->get(route('tickets.stream', $registration->public_id))
        ->assertOk()
        ->assertHeader('content-type', 'text/event-stream; charset=UTF-8')
        ->streamedContent();

    expect($content)->toContain("event: status\n")
        ->toContain('"status":"ready"')
        ->toMatch('#"pdf_url":"[^"]+/t/'.$registration->public_id.'/pdf"#');
});

it('sends pending once and closes when the window ends', function () {
    $registration = Registration::factory()->create();

    $content = $this->get(route('tickets.stream', $registration->public_id))->streamedContent();

    expect(substr_count($content, '"status":"pending"'))->toBe(1)
        ->and($content)->toContain('"pdf_url":null');
});

it('streams stats to the owner', function () {
    $event = Event::factory()->create(['capacity' => 40]);
    Registration::factory()->for($event)->create();
    Registration::factory()->for($event)->checkedIn()->create(['name' => 'Bia Lima']);

    $content = $this->actingAs($event->user)
        ->get(route('events.stream', $event))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain("event: stats\n")
        ->toContain('"registered":2,"checked_in":1,"capacity":40')
        ->toContain('"name":"Bia Lima"')
        ->and(substr_count($content, 'event: stats'))->toBe(1); // only changes are re-sent
});

it('forbids stats to others and guests', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('events.stream', $event))->assertForbidden();
    auth()->logout();
    $this->get(route('events.stream', $event))->assertRedirect(route('login'));
});

it('sends stats on show', function () {
    $event = Event::factory()->create();
    Registration::factory()->for($event)->checkedIn()->create(['name' => 'Caio Dias', 'checked_in_at' => now()->subMinute()]);
    Registration::factory()->for($event)->checkedIn()->create(['name' => 'Ana Reis', 'checked_in_at' => now()]);

    $this->actingAs($event->user)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.registered', 2)
            ->where('stats.checked_in', 2)
            ->where('stats.latest.0.name', 'Ana Reis')
            ->where('stats.latest.1.name', 'Caio Dias'));
});

it('keeps only the 10 latest check-ins', function () {
    $event = Event::factory()->create();
    Registration::factory()->for($event)->checkedIn()->count(12)->create();

    expect($event->stats()['latest'])->toHaveCount(10);
});
