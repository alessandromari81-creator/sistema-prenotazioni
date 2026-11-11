<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email inviata all'utente quando la sua prenotazione viene confermata.
 */
class BookingConfirmed extends Notification
{
    use Queueable;

    // La prenotazione viene passata al costruttore e resta disponibile come $this->booking
    public function __construct(public Booking $booking)
    {
    }

    // Canali di invio: solo email
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // Costruisce il contenuto dell'email
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Prenotazione confermata')
            ->greeting('Ciao ' . $notifiable->name . '!')
            ->line('La tua prenotazione per "' . $this->booking->bookable->name . '" è confermata.')
            ->line('Inizio: ' . $this->booking->starts_at->format('d/m/Y H:i'))
            ->line('Fine: ' . $this->booking->ends_at->format('d/m/Y H:i'));
    }
}