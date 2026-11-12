<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Parametri di ricerca, filtro, ordinamento e paginazione dell'elenco corsi.
 */
class IndexCourseRequest extends FormRequest
{
    /** L'accesso è già limitato dall'autenticazione Keycloak della rotta. */
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'sort_by' => ['sometimes', 'in:name,day_of_week,start_time'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'day_of_week' => ['sometimes', 'nullable', 'integer', 'between:1,7'],
            'all' => ['sometimes', 'boolean'],
        ];
    }
}
