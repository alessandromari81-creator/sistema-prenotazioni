<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * I piani di abbonamento: tutti li vedono, solo gli amministratori li gestiscono.
 */
class SubscriptionPlanController extends Controller
{
    public function __construct(private readonly SubscriptionPlanService $plans)
    {
    }

    /**
     * Elenco dei piani (GET /api/subscription-plans), dal più breve al più lungo. Di default solo quelli attivi;
     * l'amministratore può chiedere anche gli altri con ?all=1.
     */
    public function index(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('all') && $request->user()->isAdmin();

        $plans = SubscriptionPlan::query()
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('duration_days')->orderBy('name')
            ->get();

        return response()->json(SubscriptionPlanResource::collection($plans)->resolve());
    }

    /**
     * Crea un piano (POST /api/subscription-plans, solo admin).
     */
    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $plan = SubscriptionPlan::create($request->validated());

        return (new SubscriptionPlanResource($plan))->response()->setStatusCode(201);
    }

    /**
     * Modifica un piano (PUT /api/subscription-plans/{id}, solo admin). Gli abbonamenti già assegnati non cambiano.
     */
    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): SubscriptionPlanResource
    {
        $subscriptionPlan->update($request->validated());

        return new SubscriptionPlanResource($subscriptionPlan);
    }

    /**
     * Elimina un piano (DELETE /api/subscription-plans/{id}, solo admin). Rifiutato se è già stato assegnato.
     */
    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $this->plans->delete($subscriptionPlan);

        return response()->json(null, 204);
    }
}
