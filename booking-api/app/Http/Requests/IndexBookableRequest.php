<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Parametri di ricerca, ordinamento e paginazione dell'elenco risorse. */
class IndexBookableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'accesso è già limitato da auth:sanctum
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['sometimes', 'in:name,created_at,slot_minutes'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'all' => ['sometimes', 'boolean'],
        ];
    }
}
