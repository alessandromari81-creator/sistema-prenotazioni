<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Login: verifica le credenziali e apre la sessione. */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        // Verifica le credenziali
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Credenziali non valide.'],
            ]);
        }

        // Recupera l'utente autenticato
        $user = Auth::user();

        // Controlla che abbia verificato l'indirizzo email
        if (! $user->hasVerifiedEmail()) {
            Auth::logout();

            return response()->json([
                'message' => 'Devi verificare la tua email prima di accedere.',
            ], 403);
        }

        // Rigenera la sessione
        $request->session()->regenerate();

        return new UserResource($user);
    }

    /** Logout: chiude la sessione e invalida i cookie. */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();           // toglie l'utente dalla sessione
        $request->session()->invalidate();      // distrugge la sessione
        $request->session()->regenerateToken(); // nuovo token CSRF

        return response()->noContent();         // risposta 204, senza corpo
    }

    /** Restituisce l'utente autenticato (Angular lo usa all'avvio). */
    public function me(Request $request)
    {
        return new UserResource($request->user());
    }
}