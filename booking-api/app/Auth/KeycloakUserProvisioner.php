<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Collega l'identità di Keycloak all'utente di Laravel.
 *
 * L'utente esiste in due posti: in Keycloak (credenziali, email, ruoli) e in Laravel (profilo e dati
 * dell'applicazione: corsi, abbonamenti, prenotazioni). Il legame è il claim "sub" del token, salvato
 * in users.keycloak_id. Il profilo Laravel nasce al primo accesso (provisioning "just in time").
 */
class KeycloakUserProvisioner
{
    /**
     * Restituisce l'utente Laravel che corrisponde ai claim del token, creandolo se è il primo accesso.
     * A ogni accesso allinea nome, email e ruolo con quanto dichiara Keycloak, che è la fonte di verità.
     *
     * @param  array<string, mixed>  $claims  i claim di un token già verificato
     *
     * @throws InvalidKeycloakToken se mancano i dati indispensabili o l'email è già di un altro utente non collegabile
     */
    public function resolve(array $claims): User
    {
        $subject = $claims['sub'] ?? null;
        $email = isset($claims['email']) ? strtolower((string) $claims['email']) : null;

        if (! $subject || ! $email) {
            throw new InvalidKeycloakToken('Il token non contiene "sub" ed "email".');
        }

        // keycloak_id è una colonna UUID: un valore diverso farebbe fallire la query (errore 500 invece di 401)
        if (! Str::isUuid($subject)) {
            throw new InvalidKeycloakToken('Il claim "sub" non è un UUID.');
        }

        $user = User::where('keycloak_id', $subject)->first()
            ?? $this->linkExistingUserByEmail($email, $claims)
            ?? new User;

        // Se l'utente ha cambiato email in Keycloak e quella nuova è di un altro profilo, non si può allineare
        if ($user->exists && User::whereRaw('lower(email) = ?', [$email])->where('id', '!=', $user->id)->exists()) {
            throw new InvalidKeycloakToken('Email già associata a un altro account.');
        }

        $user->forceFill([
            'keycloak_id' => $subject,
            'name' => $this->displayName($claims, $email),
            'email' => $email,
            'email_verified_at' => ($claims['email_verified'] ?? false) ? ($user->email_verified_at ?? now()) : null,
            'role' => $this->hasAdminRole($claims) ? 'admin' : 'user',
        ]);

        // Salva solo se qualcosa è cambiato: la maggior parte delle richieste non scrive nulla
        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }

    /**
     * Collega a Keycloak un utente Laravel già esistente con la stessa email (per esempio creato dai dati di prova).
     * Si fa solo se Keycloak garantisce che l'email è verificata: altrimenti chiunque potrebbe registrarsi con l'email
     * di un altro e impossessarsi del suo profilo.
     *
     * @param  array<string, mixed>  $claims
     */
    private function linkExistingUserByEmail(string $email, array $claims): ?User
    {
        $existing = User::whereRaw('lower(email) = ?', [$email])->first();

        if (! $existing) {
            return null;
        }

        // Già collegato a un'altra identità, oppure email non verificata: non si tocca
        if ($existing->keycloak_id !== null || ! ($claims['email_verified'] ?? false)) {
            throw new InvalidKeycloakToken('Email già associata a un altro account.');
        }

        return $existing;
    }

    /**
     * Il nome da mostrare: "name" del token, altrimenti nome e cognome, altrimenti la parte iniziale dell'email.
     *
     * @param  array<string, mixed>  $claims
     */
    private function displayName(array $claims, string $email): string
    {
        $name = trim((string) ($claims['name'] ?? ''));

        if ($name === '') {
            $name = trim(($claims['given_name'] ?? '').' '.($claims['family_name'] ?? ''));
        }

        return $name !== '' ? $name : strstr($email, '@', true);
    }

    /**
     * Dice se il token contiene il ruolo di amministratore tra i ruoli del realm.
     *
     * @param  array<string, mixed>  $claims
     */
    private function hasAdminRole(array $claims): bool
    {
        return in_array(
            config('keycloak.admin_role'),
            $claims['realm_access']['roles'] ?? [],
            true
        );
    }
}
