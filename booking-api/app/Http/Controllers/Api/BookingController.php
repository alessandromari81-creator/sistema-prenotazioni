<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    // Laravel crea da solo il BookingService e lo passa qui (iniezione delle dipendenze)
    public function __construct(
        private readonly BookingService $bookings,
        private readonly BookingRepository $repository,
    ) {
    }

    /**
     * Elenco delle prenotazioni: l'utente vede le proprie, l'admin tutte.
     * Supporta ricerca, filtri, ordinamento e paginazione.
     */
    public function index(IndexBookingRequest $request): JsonResponse
    {
        // La query (filtri, ricerca, ordinamento, paginazione) sta nel repository
        $bookings = $this->repository->paginateFor($request->user(), $request->validated());

        // through(): ogni prenotazione passa da BookingResource, il paginatore resta quello di Laravel
        return response()->json(
            $bookings->withQueryString()
                ->through(fn (Booking $b) => (new BookingResource($b))->resolve())
        );
    }

    /**
     * Crea una prenotazione. Il controller passa i dati già validati al servizio, che applica
     * le regole di business (fascia oraria, slot libero, lock) e invia la notifica.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $booking = $this->bookings->create($request->user(), $data['bookable_id'], $data['starts_at']);

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    /**
     * Annulla una prenotazione (non la cancella: cambia lo stato in "cancelled").
     * Il permesso lo controlla la Policy, le regole sull'annullamento stanno nel servizio.
     */
    public function destroy(Request $request, Booking $booking): BookingResource
    {
        // Solo il proprietario o un admin (BookingPolicy::cancel): altrimenti 403
        Gate::authorize('cancel', $booking);

        return new BookingResource($this->bookings->cancel($request->user(), $booking));
    }
}