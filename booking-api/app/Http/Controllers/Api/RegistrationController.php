<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmRegistrationRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    /**
     * Passo 1: il form invia tutti i dati. Il servizio li salva in pending_registrations
     * (password hashata) e mette in coda la mail. La risposta è sempre la stessa,
     * così non si scopre quali email sono già iscritte.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $this->registration->register($request->validated());

        return response()->json([
            'message' => 'Controlla la tua email: se l\'indirizzo è valido riceverai il link per confermare la registrazione.',
        ], 202);
    }

    /**
     * Passo 2: la pagina del frontend raggiunta dal link invia email e token.
     * Se il token è quello dell'ultima mail, il servizio crea l'utente (email già verificata).
     * È una POST e non un link diretto all'API: i programmi che aprono in anticipo
     * i link delle mail (antivirus, anteprime) non consumano il token.
     */
    public function confirm(ConfirmRegistrationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $this->registration->confirm($data['email'], $data['token']);

        if (! $user) {
            return response()->json([
                'message' => 'Link non valido, scaduto o sostituito da una richiesta più recente. Usa l\'ultima email ricevuta oppure registrati di nuovo.',
            ], 410);
        }

        return response()->json([
            'message' => 'Registrazione confermata. Ora puoi effettuare il login.',
        ], 201);
    }
}
