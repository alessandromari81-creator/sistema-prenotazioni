<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmRegistrationRequest;
use App\Http\Requests\RegisterRequest;
use App\Jobs\SendRegistrationConfirmation;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationController extends Controller
{
    /**
     * Passo 1: il form invia tutti i dati. Li salviamo in pending_registrations
     * (password hashata) e mettiamo in coda la mail. La risposta è sempre la stessa,
     * così non si scopre quali email sono già iscritte.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $email = strtolower($data['email']);

        if (! $this->userExists($email)) {
            // Stessa email: vince l'ultima richiesta. token_hash torna a null, quindi
            // i link delle mail precedenti smettono di funzionare subito.
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

        return response()->json([
            'message' => 'Controlla la tua email: se l\'indirizzo è valido riceverai il link per confermare la registrazione.',
        ], 202);
    }

    /**
     * Passo 2: la pagina del frontend raggiunta dal link invia email e token.
     * Se il token è quello dell'ultima mail, crea l'utente (email già verificata).
     * È una POST e non un link diretto all'API: i programmi che aprono in anticipo
     * i link delle mail (antivirus, anteprime) non consumano il token.
     */
    public function confirm(ConfirmRegistrationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $email = strtolower($data['email']);

        $user = DB::transaction(function () use ($data, $email) {
            $row = PendingRegistration::lockForUpdate()->where('email', $email)->first();

            if (! $row
                || $row->isExpired()
                || ! $row->token_hash
                || ! hash_equals($row->token_hash, hash('sha256', $data['token']))) {
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

        if (! $user) {
            return response()->json([
                'message' => 'Link non valido, scaduto o sostituito da una richiesta più recente. Usa l\'ultima email ricevuta oppure registrati di nuovo.',
            ], 410);
        }

        return response()->json([
            'message' => 'Registrazione confermata. Ora puoi effettuare il login.',
        ], 201);
    }

    private function userExists(string $email): bool
    {
        return User::whereRaw('lower(email) = ?', [$email])->exists();
    }
}
