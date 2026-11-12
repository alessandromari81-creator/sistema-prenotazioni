<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\SubscriptionResource;
use App\Repositories\CourseRepository;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le informazioni personali dell'utente autenticato: i suoi corsi, quelli che insegna e il suo abbonamento.
 */
class MeController extends Controller
{
    public function __construct(
        private readonly CourseRepository $courses,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    /**
     * I corsi a cui l'utente è iscritto (GET /api/me/courses).
     */
    public function courses(Request $request): JsonResponse
    {
        return response()->json(CourseResource::collection($this->courses->enrolledBy($request->user()))->resolve());
    }

    /**
     * I corsi in cui l'utente è istruttore (GET /api/me/teaching). Vuoto se non insegna.
     */
    public function teaching(Request $request): JsonResponse
    {
        return response()->json(CourseResource::collection($this->courses->taughtBy($request->user()))->resolve());
    }

    /**
     * Il riepilogo dell'abbonamento (GET /api/me/subscription): quello attivo (oppure null), i corsi che l'utente sta
     * seguendo e quanti ne può ancora seguire.
     */
    public function subscription(Request $request): JsonResponse
    {
        $usage = $this->subscriptions->usage($request->user());

        return response()->json([
            'subscription' => $usage['subscription'] ? (new SubscriptionResource($usage['subscription']))->resolve() : null,
            'courses_used' => $usage['courses_used'],
            'courses_left' => $usage['courses_left'],
        ]);
    }
}
