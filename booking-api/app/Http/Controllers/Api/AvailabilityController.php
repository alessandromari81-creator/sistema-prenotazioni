<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Models\Availability;
use App\Models\Bookable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;   // per restituire un errore 422 scritto da noi

class AvailabilityController extends Controller
{
    /**
     * Aggiunge una fascia oraria a una risorsa (solo admin).
     * Richiesta: POST /api/bookables/{id}/availabilities
     * con { day_of_week: 1-7, start_time: "09:00", end_time: "13:00" }
     */
    public function store(StoreAvailabilityRequest $request, Bookable $bookable): JsonResponse
    {
        // Formato dei dati già controllato da StoreAvailabilityRequest (giorno 1-7,
        // orari HH:MM, fine dopo l'inizio). Qui resta la regola sulle sovrapposizioni.
        $data = $request->validated();

        // Porta gli orari al formato HH:MM:SS usato dalla colonna "time" del database
        $start = $data['start_time'].':00';
        $end = $data['end_time'].':00';

        // Due fasce dello stesso giorno si sovrappongono se una inizia prima
        // che l'altra finisca e finisce dopo che l'altra è iniziata.
        // Fasce che si toccano (una finisce alle 12:00, l'altra inizia alle 12:00) sono ammesse
        $overlaps = $bookable->availabilities()
            ->where('day_of_week', $data['day_of_week'])
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_time' => 'La fascia si sovrappone a una già esistente in quel giorno.',
            ]);
        }

        // Crea la fascia collegata alla risorsa (bookable_id viene impostato in automatico)
        $availability = $bookable->availabilities()->create([
            'day_of_week' => $data['day_of_week'],
            'start_time' => $start,
            'end_time' => $end,
        ]);

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