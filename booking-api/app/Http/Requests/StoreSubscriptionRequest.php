<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dati per assegnare un abbonamento a un utente: il piano e, se serve, il giorno di inizio (da oggi in poi).
 * La sovrapposizione con altri abbonamenti la controlla il servizio, perché dipende dal database.
 */
class StoreSubscriptionRequest extends FormRequest
{
    /** L'accesso è già limitato dalla rotta (autenticazione Keycloak e ruolo admin). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Le regole di validazione.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'starts_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
