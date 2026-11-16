<?php

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Models\User;
use App\Support\TicketCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** A throwaway organizer account with one populated event, one per visitor. */
final class CreateDemoSandbox
{
    private const FIRST_NAMES = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Felipe', 'Gabriela', 'Henrique', 'Isabela', 'João',
        'Larissa', 'Marcos', 'Natália', 'Otávio', 'Paula', 'Rafael', 'Sofia', 'Tiago', 'Vanessa', 'Yuri'];

    private const LAST_NAMES = ['Almeida', 'Barbosa', 'Cardoso', 'Dias', 'Ferreira', 'Gomes', 'Lima',
        'Moreira', 'Nunes', 'Oliveira', 'Pereira', 'Rocha', 'Santos', 'Teixeira'];

    public function handle(): User
    {
        $this->makeRoom();

        return DB::transaction(function (): User {
            $user = new User;
            $user->forceFill([
                'name' => 'Visitante',
                'email' => 'demo-'.Str::lower((string) Str::ulid()).'@eventpass.test',
                'password' => Str::random(40),
                'email_verified_at' => now(),
                'is_demo' => true,
                'demo_expires_at' => now()->addHours(config('eventpass.demo_ttl_hours')),
            ])->save();

            $event = $user->events()->create([
                'name' => 'Meetup PHP Recife',
                'description' => 'Palestras curtas sobre Laravel, filas e testes, seguidas de networking.',
                'venue' => 'Auditório Central',
                'starts_at' => now()->addDays(7)->setTime(19, 0),
                'capacity' => 60,
            ]);

            // PDFs are not generated up front: the download falls back to generating on demand.
            $event->registrations()->createMany(collect(range(0, 39))->map(function (int $i): array {
                $name = self::FIRST_NAMES[$i % 20].' '.self::LAST_NAMES[($i * 3) % 14];

                return [
                    'name' => $name,
                    'email' => Str::slug($name, '.').".{$i}@example.com",
                    'code' => TicketCode::generate(),
                    'ticket_status' => TicketStatus::Ready,
                    'checked_in_at' => $i < 12 ? now()->subMinutes(5 * ($i + 1)) : null,
                ];
            })->all());

            return $user;
        });
    }

    /** Drop expired sandboxes, then the oldest live ones if the cap is reached. */
    private function makeRoom(): void
    {
        (new User)->pruneAll();

        $excess = User::where('is_demo', true)->count() - config('eventpass.max_demo_users') + 1;

        if ($excess > 0) {
            User::where('is_demo', true)->orderBy('created_at')->orderBy('id')
                ->limit($excess)->get()
                ->each(fn (User $user) => $user->delete());
        }
    }
}
