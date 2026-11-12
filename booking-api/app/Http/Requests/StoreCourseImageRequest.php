<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Il file immagine di un corso. Si controlla il formato e la dimensione; il permesso (solo admin) lo dà la rotta.
 */
class StoreCourseImageRequest extends FormRequest
{
    /** L'accesso è già limitato dalla rotta (autenticazione Keycloak e ruolo admin). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Le regole di validazione: JPEG, PNG o WebP fino a 2 MB (niente SVG: può contenere script).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * I messaggi in italiano.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Scegli un\'immagine da caricare.',
            'image.mimes' => 'L\'immagine deve essere un file JPEG, PNG o WebP.',
            'image.max' => 'L\'immagine non può superare i 2 MB.',
        ];
    }
}
