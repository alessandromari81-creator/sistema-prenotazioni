<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Come uno studente appare a chi può vedere gli iscritti di un corso (amministratori e istruttori del corso):
 * identificativo, nome ed email.
 *
 * @mixin \App\Models\User
 */
class StudentResource extends JsonResource
{
    /**
     * I campi di uno studente nell'elenco iscritti.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
