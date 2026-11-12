<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseImageRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Repositories\CourseRepository;
use App\Services\CourseService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * L'immagine di un corso: la legge chiunque (è pubblica, perché un tag <img> non può mandare il token),
 * la carica e la toglie solo l'amministratore.
 */
class CourseImageController extends Controller
{
    public function __construct(
        private readonly CourseService $courses,
        private readonly CourseRepository $repository,
    ) {
    }

    /**
     * Restituisce il file dell'immagine (GET /api/courses/{id}/image, senza autenticazione).
     * 404 se il corso non ha immagine. Il browser la tiene in cache: il frontend cambia l'indirizzo (?v=) a ogni modifica.
     */
    public function show(Course $course): StreamedResponse
    {
        /** @var FilesystemAdapter $disk il disco "local": ha response(), che il tipo generico del facade non dichiara */
        $disk = Storage::disk('local');

        abort_if(! $course->image_path || ! $disk->exists($course->image_path), 404);

        return $disk->response($course->image_path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Carica o sostituisce l'immagine (POST /api/courses/{id}/image, solo admin, multipart con il campo "image").
     * Si usa POST e non PUT perché PHP non legge i file di una richiesta PUT. Risponde con il corso aggiornato.
     */
    public function store(StoreCourseImageRequest $request, Course $course): CourseResource
    {
        $this->courses->setImage($course, $request->file('image'));

        return new CourseResource($this->repository->findForViewer($request->user(), $course->id));
    }

    /** Toglie l'immagine (DELETE /api/courses/{id}/image, solo admin). Risponde 204 senza corpo. */
    public function destroy(Course $course): Response
    {
        $this->courses->removeImage($course);

        return response()->noContent();
    }
}
