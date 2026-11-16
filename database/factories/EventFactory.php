<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Meetup', 'Workshop', 'Encontro', 'Conferência']).' '.fake()->city(),
            'description' => fake()->sentence(),
            'venue' => fake()->streetAddress(),
            'starts_at' => now()->addDays(fake()->numberBetween(1, 60))->setTime(19, 0),
            'capacity' => 100,
        ];
    }
}
