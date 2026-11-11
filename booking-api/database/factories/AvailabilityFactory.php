<?php

namespace Database\Factories;

use App\Models\Availability;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Bookable;

/**
 * @extends Factory<Availability>
 */
class AvailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bookable_id' => Bookable::factory(),
            'day_of_week' => fake()->numberBetween(1, 7),
            'start_time' => '09:00',
            'end_time' => '13:00',
        ];
    }
}
