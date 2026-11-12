<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Il profilo applicativo di un utente.
 *
 * Credenziali, email verificata e ruoli stanno in Keycloak; qui restano i dati dell'applicazione
 * (indirizzo, prenotazioni, abbonamenti, corsi). Il legame con Keycloak è keycloak_id.
 * `role` ('user' o 'admin') è una copia del ruolo dichiarato dal token, aggiornata a ogni accesso
 * da KeycloakUserProvisioner: così Gate e Policy continuano a chiedere $user->isAdmin().
 */
#[Fillable(['name', 'email', 'address'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Conversioni automatiche dei campi quando si leggono dal database.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /** Le prenotazioni di sale (sale, slot) fatte dall'utente. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** I corsi a cui l'utente è iscritto (anche annullati: si filtra per stato dove serve). */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** I corsi in cui l'utente è istruttore. */
    public function taughtCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_instructor')->withTimestamps();
    }

    /** Gli abbonamenti dell'utente, passati e presenti. */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Indica se l'utente ha il ruolo di amministratore. Lo usano Gate e Policy. */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
