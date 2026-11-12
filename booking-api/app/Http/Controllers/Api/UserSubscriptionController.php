<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Gli abbonamenti di un utente, gestiti dagli amministratori.
 */
class UserSubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Gli abbonamenti di un utente, dal più recente (GET /api/users/{user}/subscriptions).
     */
    public function index(User $user): JsonResponse
    {
        $subscriptions = $user->subscriptions()->with('plan')->orderByDesc('starts_on')->get();

        return response()->json(SubscriptionResource::collection($subscriptions)->resolve());
    }

    /**
     * Assegna un piano a un utente (POST /api/users/{user}/subscriptions). Rifiutato (422) se il piano è disattivato
     * o se il periodo si sovrappone a un altro abbonamento dell'utente.
     */
    public function store(StoreSubscriptionRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $subscription = $this->subscriptions->assign(
            $user,
            SubscriptionPlan::findOrFail($data['subscription_plan_id']),
            isset($data['starts_on']) ? Carbon::parse($data['starts_on']) : null
        );

        return (new SubscriptionResource($subscription))->response()->setStatusCode(201);
    }
}
