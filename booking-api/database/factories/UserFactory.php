<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Genera utenti finti per test e dati di prova. Hanno già un keycloak_id, come se avessero fatto il primo accesso.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * I valori di partenza di un utente finto (si possono sovrascrivere nei test).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'keycloak_id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role' => 'user',
        ];
    }

    /** Un utente amministratore. */
    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    /** Un utente la cui email non è verificata. */
    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
