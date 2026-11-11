<?php

namespace App\Services;

use App\Models\Bookable;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le regole di business delle prenotazioni: creazione e annullamento.
 *
 * Il controller si occupa solo della parte HTTP (richiesta, permessi, risposta); qui sta tutto
 * quello che decide se una prenotazione è valida. Le violazioni delle regole sono ValidationException,
 * le stesse di prima: l'API risponde 422 con gli stessi messaggi.
 */
class BookingService
{
    /**
     * Crea una prenotazione per $user sulla risorsa indicata, a partire da $startsAt (un istante, in UTC).
     * La fine si ricava dalla durata dello slot. Tutto avviene in una transazione con lock sulla risorsa,
     * così due richieste simultanee non possono prenotare lo stesso slot.
     *
     * @throws ValidationException se la risorsa non è prenotabile, l'orario è fuori disponibilità,
     *                             non coincide con l'inizio di uno slot oppure lo slot è già occupato
     */
    public function create(User $user, int $bookableId, string $startsAt): Booking
    {
        $booking = DB::transaction(function () use ($user, $bookableId, $startsAt) {

            // Blocca la risorsa durante la creazione della prenotazione
            $bookable = Bookable::lockForUpdate()->findOrFail($bookableId);

            if (! $bookable->is_active) {
                throw ValidationException::withMessages([
                    'bookable_id' => ['Questa risorsa non è prenotabile.'],
                ]);
            }

            // Fuso orario della risorsa (es. Europe/Rome)
            $timezone = config('timezone.default');

            // L'istante ricevuto è in UTC
            $startUtc = Carbon::parse($startsAt)->utc();

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
                'user_id' => $user->id,
                'bookable_id' => $bookable->id,
                'starts_at' => $startUtc,
                'ends_at' => $endUtc,
                'status' => 'confirmed',
            ]);
        });

        // Invia la notifica dopo il commit
        $booking->load('bookable');
        $user->notify(new BookingConfirmed($booking));

        return $booking;
    }

    /**
     * Annulla una prenotazione (non la cancella: cambia lo stato in "cancelled").
     * Il permesso (proprietario o admin) lo controlla il controller con la Policy; qui restano le regole
     * di business: non si annulla due volte e un utente normale non annulla a meno di 2 ore dall'inizio.
     *
     * @throws ValidationException se è già annullata o mancano meno di 2 ore (utente non admin)
     */
    public function cancel(User $user, Booking $booking): Booking
    {
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

        return $booking;
    }
}
