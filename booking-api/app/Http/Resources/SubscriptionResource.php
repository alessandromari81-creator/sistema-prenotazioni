<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Come un abbonamento esce nelle risposte dell'API. Le date sono giorni (AAAA-MM-GG, estremi inclusi);
 * "is_active" dice se oggi è valido.
 *
 * @mixin \App\Models\Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * I campi dell'abbonamento.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'cancelled_at' => $this->cancelled_at,
            'is_active' => $this->isActive(),
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
            'created_at' => $this->created_at,
        ];
    }
}
