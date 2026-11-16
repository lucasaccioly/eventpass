<?php

use App\Actions\CreateGateDevice;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

function deviceToken(Event $event, string $name = 'Portão A'): string
{
    return app(CreateGateDevice::class)->handle($event, $name);
}

function checkIn(string $token, string $code)
{
    return test()->withToken($token)->postJson('/api/checkins', ['code' => $code]);
}

it('creates a device token scoped to the event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user)
        ->post(route('devices.store', $event), ['name' => 'Portão A'])
        ->assertRedirect(route('events.show', $event))
        ->assertInertiaFlash('device_link');

    expect(session(SessionKey::FLASH_DATA)['device_link'])->toContain('/portaria#token=');
    expect(PersonalAccessToken::sole())
        ->name->toBe('Portão A')
        ->abilities->toBe(["checkin:{$event->id}"]);
});

it('lists only this event\'s devices on the dashboard', function () {
    $event = Event::factory()->create();
    $other = Event::factory()->for($event->user)->create();
    deviceToken($event, 'Portão A');
    deviceToken($other, 'Outro');

    $this->actingAs($event->user)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->has('devices', 1)
            ->where('devices.0.name', 'Portão A')
            ->missing('devices.0.token'));
});

it('caps devices at 10 per event', function () {
    $event = Event::factory()->create();
    foreach (range(1, 10) as $i) {
        deviceToken($event, "Portão {$i}");
    }

    $this->actingAs($event->user)
        ->post(route('devices.store', $event), ['name' => 'Portão 11'])
        ->assertSessionHasErrors(['name' => 'Limite de 10 dispositivos por evento.']);
});

it('opens the gate in a test device', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user)->post(route('devices.test', $event))
        ->assertRedirect(route('events.show', $event))
        ->assertInertiaFlash('device_link');
    $this->post(route('devices.test', $event)); // a second click replaces the first device

    expect(session(SessionKey::FLASH_DATA)['device_link'])->toContain('/portaria#token=');
    expect(PersonalAccessToken::sole()->name)->toBe('Este navegador');
});

it('describes the event to the gate', function () {
    $event = Event::factory()->create(['name' => 'Meetup PHP Recife']);

    $this->withToken(deviceToken($event))->getJson('/api/gate')
        ->assertOk()
        ->assertJsonPath('event.name', 'Meetup PHP Recife');
});

it('checks in once', function () {
    $registration = Registration::factory()->ready()->create(['name' => 'Ana Souza']);
    $token = deviceToken($registration->event);

    $first = checkIn($token, $registration->code)
        ->assertOk()
        ->assertJsonPath('name', 'Ana Souza');

    checkIn($token, $registration->code)
        ->assertStatus(409)
        ->assertJsonPath('message', 'Ingresso já usado')
        ->assertJsonPath('checked_in_at', $first->json('checked_in_at'));

    expect($registration->fresh()->checked_in_at)->not->toBeNull();
});

it('normalizes typed codes', function () {
    $registration = Registration::factory()->create();

    checkIn(deviceToken($registration->event), '  '.strtolower($registration->code).' ')->assertOk();
});

it('rejects another event\'s ticket', function () {
    $registration = Registration::factory()->create();
    $otherEvent = Event::factory()->for($registration->event->user)->create();

    checkIn(deviceToken($otherEvent), $registration->code)
        ->assertForbidden()
        ->assertJsonPath('message', 'Ingresso de outro evento');
    expect($registration->fresh()->checked_in_at)->toBeNull();
});

it('404s unknown codes', function () {
    checkIn(deviceToken(Event::factory()->create()), 'ZZZZZZZZZZ')
        ->assertNotFound()
        ->assertJsonPath('message', 'Ingresso não encontrado');
});

it('rejects missing and revoked tokens', function () {
    $event = Event::factory()->create();
    $token = deviceToken($event);
    $id = PersonalAccessToken::sole()->id;

    $this->postJson('/api/checkins', ['code' => 'X'])->assertUnauthorized();

    $this->actingAs($event->user)
        ->delete(route('devices.destroy', [$event, $id]))
        ->assertRedirect(route('events.show', $event));
    auth()->forgetGuards();

    checkIn($token, 'X')->assertUnauthorized();
});

it('revokes tokens when the event is deleted', function () {
    $event = Event::factory()->create();
    $token = deviceToken($event);

    $this->actingAs($event->user)->delete(route('events.destroy', $event));
    auth()->forgetGuards();

    expect(PersonalAccessToken::count())->toBe(0);
    $this->withToken($token)->getJson('/api/gate')->assertUnauthorized();
    checkIn($token, 'X')->assertUnauthorized();
});

it('forbids managing devices on others\' events', function () {
    $event = Event::factory()->create();
    $token = deviceToken($event);
    $this->actingAs(User::factory()->create());

    $this->post(route('devices.store', $event), ['name' => 'X'])->assertForbidden();
    $this->post(route('devices.test', $event))->assertForbidden();
    $this->delete(route('devices.destroy', [$event, PersonalAccessToken::sole()->id]))->assertForbidden();
    expect(PersonalAccessToken::count())->toBe(1);
});

it('throttles check-ins at 60 per minute per token', function () {
    $token = deviceToken(Event::factory()->create());

    foreach (range(1, 60) as $i) {
        checkIn($token, 'ZZZZZZZZZZ')->assertNotFound();
    }

    checkIn($token, 'ZZZZZZZZZZ')->assertTooManyRequests();
});

it('serves the gate page without auth', function () {
    $this->get(route('gate'))->assertInertia(fn (Assert $page) => $page->component('gate'));
});
