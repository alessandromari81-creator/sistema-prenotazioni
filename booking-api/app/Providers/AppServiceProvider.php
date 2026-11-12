<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Auth\InvalidKeycloakToken;
use App\Auth\KeycloakTokenVerifier;
use App\Auth\KeycloakUserProvisioner;
use App\Models\User;                    // il modello degli utenti, usato nella regola
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;    // per definire le regole di autorizzazione

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

   /**
     * Eseguito all'avvio dell'applicazione: qui registriamo le regole di autorizzazione.
     */
    public function boot(): void
    {
        // Le Resource non avvolgono la risposta in {"data": ...}: il frontend Angular
        // si aspetta già la forma attuale (oggetto singolo, o paginatore di Laravel)
        JsonResource::withoutWrapping();

        // Guardia "keycloak": autentica la richiesta dal JWT nell'header Authorization.
        // Se il token manca o non è valido restituisce null e Laravel risponde 401.
        // Se è valido restituisce il profilo Laravel dell'utente (creato al primo accesso).
        Auth::viaRequest('keycloak', function (Request $request) {
            $token = $request->bearerToken();

            if (! $token) {
                return null;
            }

            try {
                $claims = app(KeycloakTokenVerifier::class)->verify($token);

                return app(KeycloakUserProvisioner::class)->resolve($claims);
            } catch (InvalidKeycloakToken) {
                return null;
            }
        });

        // Limite generale dell'API: 120 richieste al minuto per utente (per IP se non autenticato).
        // Protegge da abusi e da script impazziti; il 429 ha il formato definito in bootstrap/app.php
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Definisce la regola "admin": riceve l'utente autenticato e restituisce
        // true se è un admin (usa il metodo isAdmin() di User), false altrimenti.
        // Se restituisce false, Laravel risponde con un 403
        Gate::define('admin', function (User $user): bool {
            return $user->isAdmin();
        });
    }
}
