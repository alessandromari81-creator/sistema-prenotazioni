<?php

namespace Database\Factories;

use App\Models\Bookable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookable>
 */
class BookableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
{
    return [
        'name' => fake()->unique()->words(2, true),
        'description' => fake()->sentence(),
        'slot_minutes' => 30,
        'is_active' => true,
    ];
}
}
