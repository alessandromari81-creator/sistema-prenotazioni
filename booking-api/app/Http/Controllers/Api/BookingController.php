<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Bookable;
use App\Models\Booking;
use App\Notifications\BookingConfirmed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * Elenco delle prenotazioni: l'utente vede le proprie, l'admin tutte.
     * Supporta ricerca, filtri, ordinamento e paginazione.
     */
    public function index(IndexBookingRequest $request): JsonResponse
    {
        $user = $request->user();

        $filters = $request->validated();

        $perPage = $filters['per_page'] ?? 10;
        $sortBy = $filters['sort_by'] ?? 'starts_at';
        $sortDirection = $filters['sort_direction'] ?? 'asc';

        $query = Booking::with(['bookable', 'user']);

        // L'utente normale vede esclusivamente le proprie prenotazioni.
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        // Ricerca per nome della risorsa e, per l'admin, nome dell'utente.
        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search, $user) {
                $q->whereHas('bookable', function ($b) use ($search) {
                    $b->where('name', 'LIKE', "%{$search}%");
                });

                if ($user->isAdmin()) {
                    $q->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'LIKE', "%{$search}%");
                    });
                }
            });
        }

        // Filtro per stato.
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Ordinamento.
        $query->orderBy($sortBy, $sortDirection);

        // through(): ogni prenotazione passa da BookingResource, il paginatore resta quello di Laravel
        return response()->json(
            $query->paginate($perPage)->withQueryString()
                ->through(fn (Booking $b) => (new BookingResource($b))->resolve())
        );
    }

    /**
     * Crea una prenotazione. Si indica la risorsa e l'inizio: la fine si ricava
     * dalla durata dello slot. Tutto avviene in una transazione con lock sulla
     * risorsa, così due richieste simultanee non possono prenotare lo stesso slot.
     */
   
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $booking = DB::transaction(function () use ($request, $data) {

            // Blocca la risorsa durante la creazione della prenotazione
            $bookable = Bookable::lockForUpdate()->findOrFail($data['bookable_id']);

            if (! $bookable->is_active) {
                throw ValidationException::withMessages([
                    'bookable_id' => ['Questa risorsa non è prenotabile.'],
                ]);
            }

            // Fuso orario della risorsa (es. Europe/Rome)
            $timezone = config('timezone.default');

            // L'istante ricevuto è in UTC
            $startUtc = Carbon::parse($data['starts_at'])->utc();

            // Convertiamo in ora locale per controllare la disponibilità
            $start = $startUtc->copy()->setTimezone($timezone);
            $end = $start->copy()->addMinutes($bookable->slot_minutes);

            // Verifica che lo slot rientri nella disponibilità
            $window = $bookable->availabilities()
                ->where('day_of_week', $start->dayOfWeekIso)
                ->where('start_time', '<=', $start->format('H:i:s'))
                ->where('end_time', '>=', $end->format('H:i:s'))
                ->first();

            if (! $window) {
                throw ValidationException::withMessages([
                    'starts_at' => ['Orario fuori dalla disponibilità della risorsa.'],
                ]);
            }

            // Verifica che l'orario coincida con l'inizio di uno slot
            $windowStart = $start->copy()->setTimeFromTimeString($window->start_time);
            $minutes = (int) abs($windowStart->diffInMinutes($start));

            if ($minutes % $bookable->slot_minutes !== 0) {
                throw ValidationException::withMessages([
                    'starts_at' => ['L\'orario non coincide con l\'inizio di uno slot.'],
                ]);
            }

            // Calcola anche la fine dello slot in UTC
            $endUtc = $startUtc->copy()->addMinutes($bookable->slot_minutes);

            // Controlla che non esista già una prenotazione sovrapposta
            $overlap = $bookable->bookings()
                ->where('status', '!=', 'cancelled')
                ->where('starts_at', '<', $endUtc)
                ->where('ends_at', '>', $startUtc)
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'starts_at' => ['Lo slot è già stato prenotato.'],
                ]);
            }

            // Salva gli istanti in UTC
            return Booking::create([
                'user_id' => $request->user()->id,
                'bookable_id' => $bookable->id,
                'starts_at' => $startUtc,
                'ends_at' => $endUtc,
                'status' => 'confirmed',
            ]);
        });

        // Invia la notifica dopo il commit
        $booking->load('bookable');
        $request->user()->notify(new BookingConfirmed($booking));

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }


    /**
     * Annulla una prenotazione (non la cancella: cambia lo stato in "cancelled").
     * Possono farlo il proprietario e l'admin; l'utente solo fino a 2 ore prima.
     */
    public function destroy(Request $request, Booking $booking): BookingResource
    {
        $user = $request->user();

        // Solo il proprietario o un admin (BookingPolicy::cancel): altrimenti 403
        Gate::authorize('cancel', $booking);

        // Già annullata: niente da fare
        if ($booking->status === 'cancelled') {
            throw ValidationException::withMessages([
                'booking' => ['La prenotazione è già annullata.'],
            ]);
        }

        // Un utente normale non può annullare a meno di 2 ore dall'inizio
        if (! $user->isAdmin() && $booking->starts_at->copy()->subHours(2)->isPast()) {
            throw ValidationException::withMessages([
                'booking' => ['Si può annullare fino a 2 ore prima dell\'inizio.'],
            ]);
        }

        $booking->update(['status' => 'cancelled']);

        return new BookingResource($booking);
    }
}