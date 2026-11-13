<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aggiunge a ogni risposta dell'API l'intestazione "X-App-Version" con la versione pubblicata (config 'app.version').
 *
 * Serve al frontend per accorgersi di essere vecchio: se la versione con cui è stato costruito è diversa da questa,
 * dopo una nuova pubblicazione la pagina aperta nel browser va ricaricata. Non costa richieste in più: la versione
 * viaggia dentro le risposte che il frontend riceve comunque.
 */
class AddAppVersionHeader
{
    /**
     * Lascia passare la richiesta e, per le rotte "api/*", scrive la versione sulla risposta
     * (anche quando è un errore gestito da Laravel, come un 401 o un 422).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/*')) {
            $response->headers->set('X-App-Version', (string) config('app.version'));
        }

        return $response;
    }
}
