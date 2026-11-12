<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Titolo e file PDF di un materiale del corso. Il permesso (solo admin) lo dà la rotta con can:admin.
 */
class StoreCourseMaterialRequest extends FormRequest
{
    /** L'accesso è già limitato dalla rotta (autenticazione Keycloak e ruolo admin). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Le regole di validazione: solo PDF (si controlla il contenuto vero, non solo l'estensione), fino a 10 MB.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
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
            'title.required' => 'Scrivi un titolo per il materiale.',
            'file.required' => 'Scegli un file PDF da caricare.',
            'file.mimes' => 'Il file deve essere un PDF.',
            'file.max' => 'Il file non può superare i 10 MB.',
        ];
    }
}
