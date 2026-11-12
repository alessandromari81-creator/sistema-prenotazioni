<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexCourseRequest;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Repositories\CourseRepository;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * I corsi della palestra: tutti gli utenti autenticati li consultano, solo gli amministratori li gestiscono.
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courses,
        private readonly CourseRepository $repository,
    ) {
    }

    /**
     * Elenco dei corsi con ricerca, filtro per giorno, ordinamento e paginazione (GET /api/courses).
     * Di default solo i corsi attivi; l'amministratore può chiedere anche gli altri con ?all=1.
     */
    public function index(IndexCourseRequest $request): JsonResponse
    {
        $viewer = $request->user();
        $includeInactive = $request->boolean('all') && $viewer->isAdmin();

        $courses = $this->repository->paginate($viewer, $request->validated(), $includeInactive);

        // through(): ogni corso passa da CourseResource, il paginatore resta quello di Laravel
        return response()->json(
            $courses->withQueryString()->through(fn (Course $c) => (new CourseResource($c))->resolve())
        );
    }

    /**
     * Dettaglio di un corso (GET /api/courses/{id}). Un corso disattivato lo vedono solo gli amministratori:
     * per gli altri è come se non esistesse (404).
     */
    public function show(Request $request, Course $course): CourseResource
    {
        $viewer = $request->user();

        abort_if(! $course->is_active && ! $viewer->isAdmin(), 404);

        return new CourseResource($this->repository->findForViewer($viewer, $course->id));
    }

    /**
     * Crea un corso (POST /api/courses, solo admin).
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = $this->courses->create($request->validated());

        return (new CourseResource($this->repository->findForViewer($request->user(), $course->id)))
            ->response()->setStatusCode(201);
    }

    /**
     * Modifica un corso (PUT /api/courses/{id}, solo admin). Si può inviare anche un solo campo.
     */
    public function update(UpdateCourseRequest $request, Course $course): CourseResource
    {
        $this->courses->update($course, $request->validated());

        return new CourseResource($this->repository->findForViewer($request->user(), $course->id));
    }

    /**
     * Elimina un corso (DELETE /api/courses/{id}, solo admin). Rifiutato se ha iscritti attivi.
     */
    public function destroy(Course $course): JsonResponse
    {
        $this->courses->delete($course);

        return response()->json(null, 204);
    }
}
