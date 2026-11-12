<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Come un piano di abbonamento esce nelle risposte dell'API.
 *
 * @mixin \App\Models\SubscriptionPlan
 */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * I campi del piano.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_days' => $this->duration_days,
            'max_courses' => $this->max_courses,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
