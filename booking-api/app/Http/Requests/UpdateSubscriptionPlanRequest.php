<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dati per modificare un piano di abbonamento: "sometimes" permette di inviare anche un solo campo.
 * Gli abbonamenti già assegnati non cambiano: hanno già le loro date.
 */
class UpdateSubscriptionPlanRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['sometimes', 'required', 'integer', 'between:1,1095'],
            'max_courses' => ['sometimes', 'required', 'integer', 'between:1,50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
