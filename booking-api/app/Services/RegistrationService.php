<?php

namespace App\Services;

use App\Jobs\SendRegistrationConfirmation;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * La registrazione in due passi: richiesta (con mail) e conferma (nasce l'utente).
 *
 * Il controller sceglie la risposta HTTP; qui sta la logica. Il servizio non dice mai se un'email è già
 * iscritta: lo decide il controller rispondendo sempre allo stesso modo.
 */
class RegistrationService
{
    /**
     * Passo 1: salva i dati in pending_registrations (password hashata) e mette in coda la mail.
     * Se l'email appartiene già a un utente non fa nulla, in silenzio.
     *
     * Stessa email più volte: vince l'ultima richiesta. token_hash torna a null, quindi
     * i link delle mail precedenti smettono di funzionare subito.
     *
     * @param  array{name: string, email: string, password: string, address: string}  $data  dati già validati
     */
    public function register(array $data): void
    {
        $email = strtolower($data['email']);

        if ($this->userExists($email)) {
            return;
        }

        $pending = PendingRegistration::updateOrCreate(
            ['email' => $email],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'address' => $data['address'],
                'token_hash' => null,
                'expires_at' => now()->addHours(PendingRegistration::TTL_HOURS),
            ],
        );

        SendRegistrationConfirmation::dispatch($pending->id);
    }

    /**
     * Passo 2: se il token è quello dell'ultima mail e non è scaduto, crea l'utente (email già verificata)
     * e cancella la registrazione in attesa.
     *
     * @return User|null l'utente creato, oppure null se il link non è valido, è scaduto o è stato sostituito
     */
    public function confirm(string $email, string $token): ?User
    {
        $email = strtolower($email);

        return DB::transaction(function () use ($token, $email) {
            $row = PendingRegistration::lockForUpdate()->where('email', $email)->first();

            if (! $row
                || $row->isExpired()
                || ! $row->token_hash
                || ! hash_equals($row->token_hash, hash('sha256', $token))) {
                return null;
            }

            // Può essere nato un utente con la stessa email nel frattempo
            if ($this->userExists($email)) {
                $row->delete();

                return null;
            }

            // La password è già hashata: il cast 'hashed' del modello non la rielabora
            $user = new User([
                'name' => $row->name,
                'email' => $email,
                'password' => $row->password,
                'address' => $row->address,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $row->delete();

            return $user;
        });
    }

    private function userExists(string $email): bool
    {
        return User::whereRaw('lower(email) = ?', [$email])->exists();
    }
}
