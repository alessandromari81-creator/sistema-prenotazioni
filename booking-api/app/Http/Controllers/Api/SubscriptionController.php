<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionService;

/**
 * Un singolo abbonamento, gestito dagli amministratori.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Annulla un abbonamento (DELETE /api/subscriptions/{id}, solo admin). Non lo cancella: salva quando è stato
     * annullato. Le iscrizioni già fatte restano valide. Risponde 422 se è già annullato.
     */
    public function destroy(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource($this->subscriptions->cancel($subscription));
    }
}
