<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Creazione di una sala prenotabile. Il permesso (solo admin) lo dà la rotta: can:admin. */
class StoreBookableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'slot_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'is_active' => ['boolean'],
        ];
    }
}
