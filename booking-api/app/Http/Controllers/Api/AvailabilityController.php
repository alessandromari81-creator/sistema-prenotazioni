<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Models\Availability;
use App\Models\Bookable;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availabilities)
    {
    }

    /**
     * Aggiunge una fascia oraria a una risorsa (solo admin).
     * Richiesta: POST /api/bookables/{id}/availabilities
     * con { day_of_week: 1-7, start_time: "09:00", end_time: "13:00" }
     * Il formato è controllato da StoreAvailabilityRequest, la regola sulle sovrapposizioni dal servizio.
     */
    public function store(StoreAvailabilityRequest $request, Bookable $bookable): JsonResponse
    {
        $availability = $this->availabilities->add($bookable, $request->validated());

        // 201 = creata; restituiamo la fascia appena salvata
        return (new AvailabilityResource($availability))->response()->setStatusCode(201);
    }

    /**
     * Elimina una fascia oraria (solo admin).
     * Le prenotazioni già fatte in quella fascia restano: da quel momento
     * semplicemente non si potranno più prenotare nuovi slot al suo interno.
     */
    public function destroy(Availability $availability): JsonResponse
    {
        $availability->delete();

        // 204 = riuscito, nessun contenuto da restituire
        return response()->json(null, 204);
    }
}
