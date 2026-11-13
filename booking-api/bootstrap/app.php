<?php

use App\Http\Middleware\AddAppVersionHeader;
use Illuminate\Auth\AuthenticationException;   // errore lanciato quando manca un token valido
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Gli utenti non autenticati non vengono mai rimandati a una pagina di login: l'API risponde 401
        // e il login lo fa il frontend con Keycloak.
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        // Ogni risposta dell'API dichiara la versione pubblicata, così il frontend si accorge di essere vecchio.
        // È globale (non nel gruppo "api") perché Laravel mette "auth" davanti agli altri middleware del gruppo:
        // un 401 partirebbe prima e resterebbe senza intestazione. Il middleware agisce solo sulle rotte "api/*".
        $middleware->append(AddAppVersionHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Per le rotte "api/*" un utente non autenticato riceve sempre un 401 in JSON,
        // anche se la richiesta non contiene l'header Accept: application/json
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Non autenticato.'], 401);
            }
        });

        // Troppe richieste (throttle): 429 in JSON pulito, con i secondi da attendere
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                $headers = $e->getHeaders();

                return response()->json([
                    'message' => 'Troppe richieste. Riprova tra qualche istante.',
                    'retry_after' => isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null,
                ], 429, $headers);
            }
        });
    })->create();