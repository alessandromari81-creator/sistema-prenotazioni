<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Le query di lettura sulle prenotazioni.
 *
 * Esiste solo per l'elenco con filtri, ricerca, ordinamento e paginazione: è la query più complessa del
 * progetto. Per il resto Eloquent basta da solo (Booking::create, ->update), e un repository sopra ogni
 * modello ripeterebbe soltanto i suoi metodi.
 */
class BookingRepository
{
    /**
     * Prenotazioni visibili a $user: le proprie, oppure tutte se è admin.
     *
     * @param  array{page?: int, per_page?: int, sort_by?: string, sort_direction?: string, search?: ?string, status?: ?string}  $filters
     *                                                                                                                                  Parametri già validati da IndexBookingRequest
     * @return LengthAwarePaginator<int, Booking> con sala e utente già caricati (niente query N+1)
     */
    public function paginateFor(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 10;
        $sortBy = $filters['sort_by'] ?? 'starts_at';
        $sortDirection = $filters['sort_direction'] ?? 'asc';

        $query = Booking::with(['bookable', 'user']);

        // L'utente normale vede esclusivamente le proprie prenotazioni.
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        // Ricerca per nome della sala e, per l'admin, nome dell'utente.
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

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }
}
