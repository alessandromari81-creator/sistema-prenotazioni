<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\User;
use App\Repositories\CourseRepository;
use App\Services\CourseService;
use Illuminate\Http\Request;

/**
 * L'assegnazione degli istruttori ai corsi (solo amministratori).
 */
class CourseInstructorController extends Controller
{
    public function __construct(
        private readonly CourseService $courses,
        private readonly CourseRepository $repository,
    ) {
    }

    /**
     * Assegna un utente come istruttore del corso (PUT /api/courses/{course}/instructors/{user}).
     * Rifiutato (422) se l'utente è iscritto a quel corso come studente. Ripeterla non ha effetto.
     */
    public function store(Request $request, Course $course, User $user): CourseResource
    {
        $this->courses->addInstructor($course, $user);

        return new CourseResource($this->repository->findForViewer($request->user(), $course->id));
    }

    /**
     * Toglie un utente dagli istruttori del corso (DELETE /api/courses/{course}/instructors/{user}).
     */
    public function destroy(Request $request, Course $course, User $user): CourseResource
    {
        $this->courses->removeInstructor($course, $user);

        return new CourseResource($this->repository->findForViewer($request->user(), $course->id));
    }
}
