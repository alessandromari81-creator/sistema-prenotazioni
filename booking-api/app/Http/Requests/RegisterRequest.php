<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dati del form di registrazione (passo 1).
 * Il controller usa solo $request->validated(): niente campi non previsti.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rotta pubblica
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Niente "unique:users": risponderebbe 422 e rivelerebbe chi è già iscritto.
            // L'esistenza dell'utente si controlla nel controller, con risposta uniforme.
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ];
    }
}
