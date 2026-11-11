<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filtri, ricerca, ordinamento e paginazione dell'elenco prenotazioni. */
class IndexBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // chi vede cosa lo decide il controller (utente: solo le sue; admin: tutte)
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:5,10,15,20,50'],
            'sort_by' => ['sometimes', 'in:starts_at,created_at'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:confirmed,cancelled'],
        ];
    }
}
