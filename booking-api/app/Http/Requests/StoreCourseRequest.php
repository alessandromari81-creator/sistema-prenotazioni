<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dati per creare un corso. Qui si controlla solo il formato; il permesso (solo admin) lo dà la rotta con can:admin.
 */
class StoreCourseRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['required', 'integer', 'between:1,200'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'between:15,240'],
            'bookable_id' => ['nullable', 'integer', 'exists:bookables,id'],
            'is_active' => ['boolean'],
        ];
    }
}
