<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dati che l'utente può modificare nel proprio profilo: solo quelli che appartengono all'applicazione.
 * Nome ed email non ci sono: vivono in Keycloak (fonte di verità) e a ogni richiesta Laravel li riallinea al token,
 * quindi un valore cambiato qui verrebbe sovrascritto. Anche se vengono inviati, non entrano in validated().
 */
class UpdateProfileRequest extends FormRequest
{
    /** L'accesso è già limitato dall'autenticazione Keycloak della rotta: ognuno modifica solo il proprio profilo. */
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
            // Facoltativo: un campo vuoto (null) cancella l'indirizzo
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
