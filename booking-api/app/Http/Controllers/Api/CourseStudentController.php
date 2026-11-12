<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * L'elenco degli iscritti a un corso.
 */
class CourseStudentController extends Controller
{
    /**
     * Gli studenti iscritti al corso (GET /api/courses/{course}/students). Li vedono gli amministratori e gli
     * istruttori di quel corso (CoursePolicy::viewStudents); per gli altri la risposta è 403.
     */
    public function index(Course $course): JsonResponse
    {
        Gate::authorize('viewStudents', $course);

        $enrollments = Enrollment::with('user:id,name,email')
            ->where('course_id', $course->id)
            ->where('status', Enrollment::ACTIVE)
            ->orderBy('created_at')
            ->get();

        return response()->json(EnrollmentResource::collection($enrollments)->resolve());
    }
}
