<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Bookable */
class BookableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slot_minutes' => $this->slot_minutes,
            'is_active' => $this->is_active,
            // whenLoaded: il campo compare solo se la relazione è stata caricata con with()/load(),
            // così non si scatenano query nascoste (problema N+1) durante la serializzazione
            'availabilities' => AvailabilityResource::collection($this->whenLoaded('availabilities')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
