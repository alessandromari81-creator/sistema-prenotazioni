<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Bookable;
use App\Models\User;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'user_id' => User::factory(),
            'bookable_id' => Bookable::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+30 minutes'),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
