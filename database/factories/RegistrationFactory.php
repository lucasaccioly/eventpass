<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Support\TicketCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'code' => TicketCode::generate(),
            'ticket_status' => TicketStatus::Pending,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_status' => TicketStatus::Ready,
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_status' => TicketStatus::Ready,
            'checked_in_at' => now()->subMinutes(fake()->numberBetween(1, 90)),
        ]);
    }
}
