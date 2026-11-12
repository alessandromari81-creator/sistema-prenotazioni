<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gli utenti dell'applicazione.
 *
 * Login, logout e registrazione non sono qui: li gestisce Keycloak. Laravel riceve solo il token.
 */
class UserController extends Controller
{
    /**
     * Restituisce il profilo dell'utente a cui appartiene il token (GET /api/user).
     * Il frontend lo chiama dopo il login per sapere nome, ruolo, indirizzo e se l'utente insegna in qualche corso.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->profile($request->user());
    }

    /**
     * Aggiorna i dati del proprio profilo che appartengono all'applicazione (PUT /api/user): per ora l'indirizzo.
     * Nome ed email non si cambiano qui: sono di Keycloak e verrebbero riallineati al token alla richiesta successiva.
     * Ognuno può modificare solo il proprio profilo: l'utente è sempre quello a cui appartiene il token.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->profile($user);
    }

    /**
     * La risposta con il profilo di $user, con l'informazione "insegna in almeno un corso".
     * Lo stato è 200 esplicito: al primo accesso il profilo è appena stato creato e una Resource risponderebbe 201.
     */
    private function profile(User $user): JsonResponse
    {
        $user->loadExists('taughtCourses as teaches_courses');

        return (new UserResource($user))->response()->setStatusCode(200);
    }

    /**
     * Elenco degli utenti con ricerca per nome o email e paginazione (GET /api/users, solo admin).
     * Serve all'amministratore per scegliere chi assegnare come istruttore o a chi dare un abbonamento.
     * Compaiono solo gli utenti che hanno già fatto almeno un accesso: i profili nascono al primo login.
     */
    public function index(IndexUserRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $users = User::query()
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $term = '%'.mb_strtolower($filters['search']).'%';

                $query->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(email) like ?', [$term]));
            })
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 10);

        return response()->json(
            $users->withQueryString()->through(fn (User $u) => (new UserResource($u))->resolve())
        );
    }
}
