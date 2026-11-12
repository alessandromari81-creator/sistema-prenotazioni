<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Come un istruttore appare agli altri utenti: solo identificativo e nome (niente email né dati personali).
 *
 * @mixin \App\Models\User
 */
class InstructorResource extends JsonResource
{
    /**
     * I campi pubblici di un istruttore.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
