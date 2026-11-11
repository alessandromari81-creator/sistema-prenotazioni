<?php

namespace App\Services;

use App\Models\Availability;
use App\Models\Bookable;
use Illuminate\Validation\ValidationException;

/**
 * Le regole sulle fasce orarie settimanali di una risorsa.
 */
class AvailabilityService
{
    /**
     * Aggiunge una fascia a $bookable. Il formato dei dati (giorno 1-7, orari HH:MM, fine dopo l'inizio)
     * è già controllato da StoreAvailabilityRequest; qui resta la regola di business sulle sovrapposizioni.
     *
     * @param  array{day_of_week: int, start_time: string, end_time: string}  $data  orari in formato HH:MM
     *
     * @throws ValidationException se la fascia si sovrappone a una già esistente nello stesso giorno
     */
    public function add(Bookable $bookable, array $data): Availability
    {
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
        return $bookable->availabilities()->create([
            'day_of_week' => $data['day_of_week'],
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
}
