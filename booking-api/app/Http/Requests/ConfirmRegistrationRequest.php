<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Dati inviati dalla pagina raggiunta con il link nella mail (passo 2). */
class ConfirmRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rotta pubblica: a proteggerla sono il token e il throttle
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
        ];
    }
}
