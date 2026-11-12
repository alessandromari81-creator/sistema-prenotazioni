<?php

namespace App\Services;

use App\Models\Bookable;
use Illuminate\Support\Carbon;

/**
 * Calcola gli slot ancora liberi di una sala in un certo giorno.
 */
class SlotService
{
    /**
     * Slot liberi di $bookable per il giorno $date (formato AAAA-MM-GG, nel fuso orario della sala).
     * Le fasce orarie sono nell'orario di Roma; gli istanti restituiti sono in UTC (ISO 8601).
     * Gli slot già prenotati e quelli nel passato non compaiono.
     *
     * @return list<array{starts_at: string, ends_at: string}>
     */
    public function freeSlots(Bookable $bookable, string $date): array
    {
        // Le disponibilità sono espresse nell'orario di Roma
        $timezoneUser = config('timezone.default');

        $day = Carbon::createFromFormat('!Y-m-d', $date, $timezoneUser);

        // Fasce orarie della sala
        $windows = $bookable->availabilities()
            ->where('day_of_week', $day->dayOfWeekIso)
            ->orderBy('start_time')
            ->get();

        // Limiti della giornata convertiti in UTC per interrogare le prenotazioni
        $dayStartUtc = $day->copy()->startOfDay()->utc();
        $dayEndUtc = $day->copy()->endOfDay()->utc();

        $booked = $bookable->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->get();

        $slots = [];

        foreach ($windows as $window) {
            $cursor = $day->copy()->setTimeFromTimeString($window->start_time);
            $windowEnd = $day->copy()->setTimeFromTimeString($window->end_time);

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

        return $slots;
    }
}
