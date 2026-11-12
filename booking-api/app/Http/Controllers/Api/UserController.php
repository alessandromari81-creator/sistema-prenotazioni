<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexUserRequest;
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
     * Il frontend lo chiama dopo il login per sapere nome, ruolo e se l'utente insegna in qualche corso.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadExists('taughtCourses as teaches_courses');

        // Stato 200 esplicito: al primo accesso il profilo è appena stato creato e una Resource risponderebbe 201
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
