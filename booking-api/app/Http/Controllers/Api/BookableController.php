<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexBookableRequest;
use App\Http\Requests\SlotsRequest;
use App\Http\Requests\StoreBookableRequest;
use App\Http\Requests\UpdateBookableRequest;
use App\Http\Resources\BookableResource;
use App\Models\Bookable;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;   // tipo di risposta JSON restituita dalle API

class BookableController extends Controller
{
    public function __construct(private readonly SlotService $slots)
    {
    }

    /**
     * Restituisce l'elenco delle sale prenotabili con i propri orari di disponibilità.
     * Di default solo quelle attive; l'admin può chiedere anche le disattivate con ?all=1.
    */
    public function index(IndexBookableRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Query di base con disponibilità
        $query = Bookable::query()
            ->with('availabilities');

        // Sale disattivate visibili solo agli admin
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

        // through() trasforma ogni sala con BookableResource ma lascia intatto il
        // paginatore (current_page, total, ...) che il frontend già legge
        return response()->json(
            $query->paginate($perPage)
                ->through(fn (Bookable $b) => (new BookableResource($b))->resolve())
        );
    }

    /**
     * Crea una nuova sala prenotabile (la useranno solo gli admin).
     */
    public function store(StoreBookableRequest $request): JsonResponse
    {
        // I dati sono già validati da StoreBookableRequest (se no, 422 automatico).
        // Crea la sala con i soli campi validati (sono tutti in $fillable)
        $bookable = Bookable::create($request->validated());

        // 201 = sala creata; restituiamo la sala appena salvata
        return (new BookableResource($bookable))->response()->setStatusCode(201);
    }

    /**
     * Restituisce una singola sala prenotabile con i suoi orari di disponibilità.
     */
    public function show(Bookable $bookable): BookableResource
    {
        // Il modello è già stato trovato da Laravel in base all'id nell'indirizzo
        // (route model binding): se l'id non esiste risponde da solo con un 404.
        // load('availabilities') carica gli orari collegati a questa sala
        $bookable->load('availabilities');

        // Restituisce la sala in formato JSON
        return new BookableResource($bookable);
    }

    /**
     * Modifica una sala prenotabile esistente (la useranno solo gli admin).
     */
    public function update(UpdateBookableRequest $request, Bookable $bookable): BookableResource
    {
        // Aggiorna solo i campi arrivati e validati (UpdateBookableRequest usa 'sometimes':
        // si può modificare anche un solo campo alla volta), poi salva nel database
        $bookable->update($request->validated());

        // Restituisce la sala aggiornata (stato 200 di default)
        return new BookableResource($bookable);
    }

    /**
     * Elimina una sala prenotabile (la useranno solo gli admin).
     */
    public function destroy(Bookable $bookable): JsonResponse
    {
        // Cancella la sala dal database. Grazie al cascadeOnDelete delle migrazioni
        // vengono cancellate in automatico anche le sue disponibilità e le sue prenotazioni
        $bookable->delete();

        // 204 = operazione riuscita, nessun contenuto da restituire
        return response()->json(null, 204);
    }

    /**
     * Restituisce gli slot ancora liberi di una sala per un certo giorno.
     * Richiesta: GET /api/bookables/{id}/slots?date=2026-11-10
     */
    public function slots(SlotsRequest $request, Bookable $bookable): JsonResponse
    {
        // Il calcolo (fasce orarie meno prenotazioni e orari passati) sta nel servizio
        return response()->json(
            $this->slots->freeSlots($bookable, $request->validated()['date'])
        );
    }
}
