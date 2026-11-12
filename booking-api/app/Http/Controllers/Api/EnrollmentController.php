<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Repositories\CourseRepository;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'iscrizione dell'utente autenticato a un corso: "la mia iscrizione" a quel corso.
 */
class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly CourseRepository $courses,
    ) {
    }

    /**
     * Iscrive l'utente al corso (POST /api/courses/{course}/enrollment). Le regole (abbonamento, posti, istruttore...)
     * stanno nel servizio e, se non sono rispettate, la risposta è 422. Restituisce l'iscrizione con il corso aggiornato.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        $enrollment = $this->enrollments->enroll($request->user(), $course);
        $enrollment->setRelation('course', $this->courses->findForViewer($request->user(), $course->id));

        return (new EnrollmentResource($enrollment))->response()->setStatusCode(201);
    }

    /**
     * Annulla l'iscrizione dell'utente al corso (DELETE /api/courses/{course}/enrollment).
     */
    public function destroy(Request $request, Course $course): JsonResponse
    {
        $enrollment = $this->enrollments->cancel($request->user(), $course);
        $enrollment->setRelation('course', $this->courses->findForViewer($request->user(), $course->id));

        return (new EnrollmentResource($enrollment))->response()->setStatusCode(200);
    }
}
