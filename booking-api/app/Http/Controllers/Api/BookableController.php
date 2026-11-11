<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexBookableRequest;
use App\Http\Requests\SlotsRequest;
use App\Http\Requests\StoreBookableRequest;
use App\Http\Requests\UpdateBookableRequest;
use App\Http\Resources\BookableResource;
use App\Models\Bookable;
use Illuminate\Http\JsonResponse;   // tipo di risposta JSON restituita dalle API
use Illuminate\Support\Carbon;   // gestione delle date

class BookableController extends Controller
{
    /**
     * Restituisce l'elenco delle risorse prenotabili con i propri orari di disponibilità.
     * Di default solo quelle attive; l'admin può chiedere anche le disattivate con ?all=1.
    */
    public function index(IndexBookableRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Query di base con disponibilità
        $query = Bookable::query()
            ->with('availabilities');

        // Risorse disattivate visibili solo agli admin
        $includeInactive = $request->boolean('all')
            && $request->user()?->can('admin');

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        // Ricerca per nome
        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%' . $validated['search'] . '%');
        }

        // Ordinamento
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        $query->orderBy($sortBy, $sortDirection);

        // Paginazione
        $perPage = $validated['per_page'] ?? 10;

        // through() trasforma ogni risorsa con BookableResource ma lascia intatto il
        // paginatore (current_page, total, ...) che il frontend già legge
        return response()->json(
            $query->paginate($perPage)
                ->through(fn (Bookable $b) => (new BookableResource($b))->resolve())
        );
    }

    /**
     * Crea una nuova risorsa prenotabile (la useranno solo gli admin).
     */
    public function store(StoreBookableRequest $request): JsonResponse
    {
        // I dati sono già validati da StoreBookableRequest (se no, 422 automatico).
        // Crea la risorsa con i soli campi validati (sono tutti in $fillable)
        $bookable = Bookable::create($request->validated());

        // 201 = risorsa creata; restituiamo la risorsa appena salvata
        return (new BookableResource($bookable))->response()->setStatusCode(201);
    }

    /**
     * Restituisce una singola risorsa prenotabile con i suoi orari di disponibilità.
     */
    public function show(Bookable $bookable): BookableResource
    {
        // Il modello è già stato trovato da Laravel in base all'id nell'indirizzo
        // (route model binding): se l'id non esiste risponde da solo con un 404.
        // load('availabilities') carica gli orari collegati a questa risorsa
        $bookable->load('availabilities');

        // Restituisce la risorsa in formato JSON
        return new BookableResource($bookable);
    }

    /**
     * Modifica una risorsa prenotabile esistente (la useranno solo gli admin).
     */
    public function update(UpdateBookableRequest $request, Bookable $bookable): BookableResource
    {
        // Aggiorna solo i campi arrivati e validati (UpdateBookableRequest usa 'sometimes':
        // si può modificare anche un solo campo alla volta), poi salva nel database
        $bookable->update($request->validated());

        // Restituisce la risorsa aggiornata (stato 200 di default)
        return new BookableResource($bookable);
    }

    /**
     * Elimina una risorsa prenotabile (la useranno solo gli admin).
     */
    public function destroy(Bookable $bookable): JsonResponse
    {
        // Cancella la risorsa dal database. Grazie al cascadeOnDelete delle migrazioni
        // vengono cancellate in automatico anche le sue disponibilità e le sue prenotazioni
        $bookable->delete();

        // 204 = operazione riuscita, nessun contenuto da restituire
        return response()->json(null, 204);
    }

    /**
     * Restituisce gli slot ancora liberi di una risorsa per un certo giorno.
     * Richiesta: GET /api/bookables/{id}/slots?date=2026-11-10
     */
    public function slots(SlotsRequest $request, Bookable $bookable): JsonResponse
    {
        $data = $request->validated();

        // Le disponibilità sono espresse nell'orario di Roma
        $timezoneUser = config('timezone.default');

        $date = Carbon::createFromFormat(
            '!Y-m-d',
            $data['date'],
            $timezoneUser
        );

        // Fasce orarie della risorsa
        $windows = $bookable->availabilities()
            ->where('day_of_week', $date->dayOfWeekIso)
            ->orderBy('start_time')
            ->get();

        // Limiti della giornata convertiti in UTC per interrogare le prenotazioni
        $dayStartUtc = $date->copy()->startOfDay()->utc();
        $dayEndUtc = $date->copy()->endOfDay()->utc();

        $booked = $bookable->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->get();

        $slots = [];

        foreach ($windows as $window) {
            $cursor = $date->copy()->setTimeFromTimeString($window->start_time);
            $windowEnd = $date->copy()->setTimeFromTimeString($window->end_time);

            while ($cursor->copy()->addMinutes($bookable->slot_minutes) <= $windowEnd) {
                $slotEnd = $cursor->copy()->addMinutes($bookable->slot_minutes);

                $taken = $booked->contains(
                    fn ($b) => $b->starts_at < $slotEnd->copy()->utc()
                        && $b->ends_at > $cursor->copy()->utc()
                );

                if (! $taken && $cursor->isFuture()) {
                    $slots[] = [
                        'starts_at' => $cursor->copy()->utc()->toIso8601String(),
                        'ends_at' => $slotEnd->copy()->utc()->toIso8601String(),
                    ];
                }

                $cursor = $slotEnd;
            }
        }

        return response()->json($slots);
    }
}
