<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Giorno per cui calcolare gli slot liberi: ?date=AAAA-MM-GG, da oggi in poi. */
class SlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
