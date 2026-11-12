<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use Illuminate\Validation\ValidationException;

/**
 * Le regole sulla gestione dei piani di abbonamento.
 */
class SubscriptionPlanService
{
    /**
     * Elimina un piano. Se è già stato assegnato a qualcuno non si può, per non perdere lo storico degli abbonamenti:
     * va disattivato (non sarà più assegnabile ma resta leggibile).
     *
     * @throws ValidationException se il piano è stato assegnato ad almeno un utente
     */
    public function delete(SubscriptionPlan $plan): void
    {
        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages([
                'subscription_plan' => ['Il piano è stato assegnato a degli utenti: disattivalo invece di eliminarlo.'],
            ]);
        }

        $plan->delete();
    }
}
