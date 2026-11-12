<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Genera abbonamenti finti. Di partenza: attivo da oggi per 30 giorni (estremi inclusi).
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * I valori di partenza di un abbonamento finto (si possono sovrascrivere nei test).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(29)->toDateString(),
            'cancelled_at' => null,
        ];
    }

    /** Un abbonamento già scaduto (finito ieri). */
    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subDays(30)->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);
    }

    /** Un abbonamento annullato dall'amministratore. */
    public function cancelled(): static
    {
        return $this->state(fn () => ['cancelled_at' => now()]);
    }
}
