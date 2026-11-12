<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Genera corsi finti per test e dati di prova. Di partenza: attivo, 12 posti, un'ora, senza sala.
 *
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * I valori di partenza di un corso finto (si possono sovrascrivere nei test).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'capacity' => 12,
            'day_of_week' => fake()->numberBetween(1, 7),
            'start_time' => '18:00',
            'duration_minutes' => 60,
            'bookable_id' => null,
            'is_active' => true,
        ];
    }

    /** Un corso disattivato: non accetta nuove iscrizioni. */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
