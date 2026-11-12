<?php

namespace App\Repositories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Le query di lettura sui corsi.
 *
 * Ogni corso esce già con gli istruttori, la sala, il numero di iscritti e due informazioni che dipendono da chi guarda:
 * se l'utente è iscritto e se è istruttore. Sono calcolate dal database in una sola query, senza una richiesta in più
 * per ogni corso (problema N+1).
 */
class CourseRepository
{
    /**
     * L'elenco dei corsi con ricerca, filtro per giorno, ordinamento e paginazione.
     *
     * @param  array{page?: int, per_page?: int, sort_by?: string, sort_direction?: string, search?: ?string, day_of_week?: int}  $filters  già validati da IndexCourseRequest
     * @param  bool  $includeInactive  se true compaiono anche i corsi disattivati (solo per gli amministratori)
     * @return LengthAwarePaginator<int, Course>
     */
    public function paginate(User $viewer, array $filters, bool $includeInactive = false): LengthAwarePaginator
    {
        $query = $this->baseQuery($viewer);

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        if (! empty($filters['search'])) {
            $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($filters['search']).'%']);
        }

        if (! empty($filters['day_of_week'])) {
            $query->where('day_of_week', $filters['day_of_week']);
        }

        $sortBy = $filters['sort_by'] ?? 'day_of_week';
        $direction = $filters['sort_direction'] ?? 'asc';

        // A parità di criterio si ordina per giorno, ora e nome, così l'ordine è sempre lo stesso
        $query->orderBy($sortBy, $direction)
            ->orderBy('day_of_week')->orderBy('start_time')->orderBy('name');

        return $query->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Un corso con tutti i dati, visto da $viewer.
     */
    public function findForViewer(User $viewer, int $courseId): Course
    {
        return $this->baseQuery($viewer)->findOrFail($courseId);
    }

    /**
     * I corsi a cui $viewer è iscritto (iscrizione valida), in ordine di giorno e ora.
     *
     * @return Collection<int, Course>
     */
    public function enrolledBy(User $viewer): Collection
    {
        return $this->baseQuery($viewer)
            ->whereHas('activeEnrollments', fn (Builder $q) => $q->where('user_id', $viewer->id))
            ->orderBy('day_of_week')->orderBy('start_time')
            ->get();
    }

    /**
     * I corsi in cui $viewer è istruttore, in ordine di giorno e ora.
     *
     * @return Collection<int, Course>
     */
    public function taughtBy(User $viewer): Collection
    {
        return $this->baseQuery($viewer)
            ->whereHas('instructors', fn (Builder $q) => $q->where('users.id', $viewer->id))
            ->orderBy('day_of_week')->orderBy('start_time')
            ->get();
    }

    /**
     * La query di partenza: i corsi con relazioni caricate e i campi calcolati per $viewer.
     *
     * @return Builder<Course>
     */
    private function baseQuery(User $viewer): Builder
    {
        return Course::query()
            ->with(['instructors:id,name', 'bookable'])
            ->withCount(['activeEnrollments as enrolled_count'])
            ->withExists([
                'activeEnrollments as is_enrolled' => fn (Builder $q) => $q->where('user_id', $viewer->id),
                'instructors as is_instructor' => fn (Builder $q) => $q->where('users.id', $viewer->id),
            ]);
    }
}
