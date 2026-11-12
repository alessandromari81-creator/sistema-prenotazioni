<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Genera piani di abbonamento finti. Di partenza: attivo, 30 giorni, due corsi contemporanei.
 *
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * I valori di partenza di un piano finto (si possono sovrascrivere nei test).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'duration_days' => 30,
            'max_courses' => 2,
            'is_active' => true,
        ];
    }

    /** Un piano disattivato: non si può più assegnare. */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
