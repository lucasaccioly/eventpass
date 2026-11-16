<?php

use App\Actions\CreateGateDevice;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

function startDemo(string $ip = '10.0.0.1')
{
    $response = test()->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('demo.store'));
    auth()->logout();

    return $response;
}

it('creates a private sandbox per click', function () {
    startDemo('10.0.0.1')->assertRedirect(route('events.show', Event::sole()));
    startDemo('10.0.0.2');

    $users = User::where('is_demo', true)->get();
    expect($users)->toHaveCount(2);

    foreach ($users as $user) {
        $event = $user->events()->sole();
        expect($event->name)->toBe('Meetup PHP Recife')
            ->and($event->capacity)->toBe(60)
            ->and($event->registrations()->count())->toBe(40)
            ->and($event->registrations()->whereNotNull('checked_in_at')->count())->toBe(12)
            ->and($user->email)->toEndWith('@eventpass.test')
            ->and($user->demo_expires_at->isFuture())->toBeTrue();
    }
});

it('logs the visitor in', function () {
    $this->post(route('demo.store'));

    $this->assertAuthenticatedAs(User::sole());
});

it('prunes expired demo users and their files', function () {
    Storage::fake('local');
    startDemo();
    $event = Event::sole();
    app(CreateGateDevice::class)->handle($event, 'Portão A');
    $ticket = $event->registrations()->first();
    Storage::disk('local')->put("tickets/{$ticket->public_id}.pdf", 'pdf');

    $this->travel(25)->hours();
    $this->artisan('demo:prune')->expectsOutputToContain('Pruned 1')->assertSuccessful();

    expect(User::count())->toBe(0)
        ->and(Event::count())->toBe(0)
        ->and($ticket->newQuery()->count())->toBe(0)
        ->and(PersonalAccessToken::count())->toBe(0);
    Storage::disk('local')->assertMissing("tickets/{$ticket->public_id}.pdf");
});

it('keeps live demo users and real users', function () {
    $real = User::factory()->create();
    startDemo();

    $this->travel(23)->hours();
    $this->artisan('demo:prune')->expectsOutputToContain('Pruned 0');

    expect(User::count())->toBe(2)->and($real->fresh())->not->toBeNull();
});

it('caps live demo users', function () {
    config(['eventpass.max_demo_users' => 2]);

    startDemo('10.0.0.1');
    $oldest = User::sole();
    $this->travel(1)->minutes();
    startDemo('10.0.0.2');
    $this->travel(1)->minutes();
    startDemo('10.0.0.3');

    expect(User::where('is_demo', true)->count())->toBe(2)
        ->and($oldest->fresh())->toBeNull();
});

it('throttles demo creation to 5 per hour per IP', function () {
    foreach (range(1, 5) as $i) {
        startDemo()->assertRedirect();
    }

    startDemo()->assertTooManyRequests();
});

it('blocks demo email and password changes', function () {
    startDemo();
    $user = User::sole();
    $this->actingAs($user);

    $this->patch(route('profile.update'), ['name' => 'Outro', 'email' => 'novo@example.com'])->assertForbidden();
    $this->put(route('user-password.update'), [
        'current_password' => 'x', 'password' => 'new-password', 'password_confirmation' => 'new-password',
    ])->assertForbidden();
    expect($user->fresh()->email)->toEndWith('@eventpass.test');

    $this->delete(route('profile.destroy'))->assertRedirect('/');
    expect(User::count())->toBe(0);
});

it('lets demo users and allowed emails view Horizon', function () {
    config(['eventpass.horizon_emails' => ['admin@example.com']]);

    startDemo();
    $this->actingAs(User::where('is_demo', true)->sole())->get('/horizon')->assertOk();
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']))->get('/horizon')->assertOk();
    $this->actingAs(User::factory()->create())->get('/horizon')->assertForbidden();
});

it('schedules prune and snapshot', function () {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn ($event) => [trim(str($event->command)->afterLast('artisan'), " '\"") => $event->expression]);

    expect($events->get('demo:prune'))->toBe('0 * * * *')
        ->and($events->get('horizon:snapshot'))->toBe('*/5 * * * *');
});
