<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nuova prenotazione: si indica la sala e l'inizio. La fine si ricava dalla durata
 * dello slot. Le regole che dipendono dal database (fascia oraria, slot occupato)
 * restano nel controller, dentro la transazione.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bookable_id' => ['required', 'integer', 'exists:bookables,id'],
            'starts_at' => ['required', 'date', 'after:now'],
        ];
    }
}
