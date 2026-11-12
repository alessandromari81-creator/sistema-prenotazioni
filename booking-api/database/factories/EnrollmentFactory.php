<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Genera iscrizioni finte. Di partenza: valida, senza un abbonamento collegato.
 *
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * I valori di partenza di un'iscrizione finta (si possono sovrascrivere nei test).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'subscription_id' => null,
            'status' => Enrollment::ACTIVE,
        ];
    }

    /** Un'iscrizione annullata. */
    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Enrollment::CANCELLED]);
    }
}
