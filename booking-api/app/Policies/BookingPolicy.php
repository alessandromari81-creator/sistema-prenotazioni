<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Regole di autorizzazione che riguardano una singola prenotazione.
 * Laravel la associa da solo al modello Booking (BookingPolicy ↔ Booking).
 *
 * Gate o Policy? Il Gate "admin" risponde a una domanda generale ("è un admin?")
 * e protegge intere aree dell'API. Una Policy risponde a "questo utente può fare
 * questa azione su QUESTO oggetto?", e per questo riceve anche il modello.
 */
class BookingPolicy
{
    /** Annullare: il proprietario della prenotazione o un admin. */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $booking->user_id === $user->id;
    }
}
