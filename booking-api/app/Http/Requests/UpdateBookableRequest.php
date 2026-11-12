<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Modifica di una sala: "sometimes" permette di inviare anche un solo campo. */
class UpdateBookableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'slot_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
