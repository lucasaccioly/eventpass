<?php

use App\Models\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validEvent(array $overrides = []): array
{
    return [
        'name' => 'Meetup PHP Recife',
        'description' => 'Palestras e networking.',
        'venue' => 'Auditório Central',
        'starts_at' => now()->addWeek()->format('Y-m-d H:i'),
        'capacity' => 50,
        ...$overrides,
    ];
}

it('lists only my events', function () {
    $me = User::factory()->create();
    $mine = Event::factory()->for($me)->create();
    Event::factory()->create();

    $this->actingAs($me)->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('events/index')
            ->has('events', 1)
            ->where('events.0.id', $mine->id));
});

it('creates an event with a slug', function () {
    $me = User::factory()->create();

    $response = $this->actingAs($me)->post(route('events.store'), validEvent());

    $event = Event::sole();
    $response->assertRedirect(route('events.show', $event));
    expect($event->slug)->toMatch('/^meetup-php-recife-[a-z0-9]{5}$/')
        ->and($event->user_id)->toBe($me->id)
        ->and($event->capacity)->toBe(50);
});

it('keeps the slug when the name changes', function () {
    $event = Event::factory()->create();
    $slug = $event->slug;

    $this->actingAs($event->user)
        ->put(route('events.update', $event), validEvent(['name' => 'Outro nome']))
        ->assertRedirect(route('events.show', $event));

    expect($event->fresh()->slug)->toBe($slug)
        ->and($event->fresh()->name)->toBe('Outro nome');
});

it('validates capacity between 1 and 500', function (int $capacity) {
    $this->actingAs(User::factory()->create())
        ->post(route('events.store'), validEvent(['capacity' => $capacity]))
        ->assertSessionHasErrors('capacity');
})->with([0, 501]);

it('forbids other users', function () {
    $event = Event::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('events.show', $event))->assertForbidden();
    $this->get(route('events.edit', $event))->assertForbidden();
    $this->put(route('events.update', $event), validEvent())->assertForbidden();
    $this->delete(route('events.destroy', $event))->assertForbidden();
    expect($event->fresh())->not->toBeNull();
});

it('caps events per user at 20', function () {
    $me = User::factory()->create();
    Event::factory()->count(20)->for($me)->create();

    $this->actingAs($me)->post(route('events.store'), validEvent())
        ->assertSessionHasErrors(['name' => 'Limite de 20 eventos atingido.']);
    expect($me->events()->count())->toBe(20);
});

it('deletes my event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user)->delete(route('events.destroy', $event))
        ->assertRedirect(route('events.index'));
    expect(Event::count())->toBe(0);
});

it('redirects guests to login', function () {
    $this->get(route('events.index'))->assertRedirect(route('login'));
});
