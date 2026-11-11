<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;                    // il modello degli utenti, usato nella regola
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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

        // Definisce la regola "admin": riceve l'utente autenticato e restituisce
        // true se è un admin (usa il metodo isAdmin() che abbiamo scritto in User),
        // false altrimenti. Se restituisce false, Laravel risponde con un 403
        // Registrazione: limiti per IP e per email (evita di spammare una casella altrui)
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(5)->by('register:ip:'.$request->ip()),
            Limit::perHour(20)->by('register:ip-hour:'.$request->ip()),
            Limit::perHour(3)->by('register:email:'.strtolower((string) $request->input('email'))),
        ]);

        // Conferma: limita i tentativi di indovinare il token
        RateLimiter::for('register-confirm', fn (Request $request) => [
            Limit::perMinute(10)->by('confirm:ip:'.$request->ip()),
            Limit::perHour(10)->by('confirm:email:'.strtolower((string) $request->input('email'))),
        ]);

        // Login: contro il brute force, per IP+email
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login:ip:'.$request->ip()),
            Limit::perMinute(5)->by('login:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        Gate::define('admin', function (User $user): bool {
            return $user->isAdmin();
        });
    }
}
