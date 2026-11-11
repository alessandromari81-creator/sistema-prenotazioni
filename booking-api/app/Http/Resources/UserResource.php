<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Come un utente appare nelle risposte dell'API.
 * Elenchiamo i campi uno per uno: se un domani si aggiunge una colonna sensibile
 * alla tabella users, non finisce nelle risposte per errore.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'address' => $this->address,
            'role' => $this->role,
        ];
    }
}
