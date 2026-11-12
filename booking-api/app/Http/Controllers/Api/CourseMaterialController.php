<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseMaterialRequest;
use App\Http\Resources\CourseMaterialResource;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Services\CourseMaterialService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * I materiali PDF di un corso. Li vedono solo gli amministratori, gli istruttori del corso e gli iscritti
 * (CoursePolicy::viewMaterials): il file non ha mai un indirizzo pubblico, passa sempre da qui, con il token.
 * Li carica e li elimina solo l'amministratore.
 */
class CourseMaterialController extends Controller
{
    public function __construct(private readonly CourseMaterialService $materials)
    {
    }

    /** L'elenco dei materiali del corso (GET /api/courses/{course}/materials). 403 per chi non può vederli. */
    public function index(Course $course): JsonResponse
    {
        Gate::authorize('viewMaterials', $course);

        return response()->json(
            CourseMaterialResource::collection($course->materials()->orderBy('created_at')->get())->resolve()
        );
    }

    /**
     * Il file PDF (GET /api/courses/{course}/materials/{material}). Stesso permesso dell'elenco. Se il materiale
     * non appartiene a quel corso la risposta è 404 (il collegamento lo fa Laravel con le rotte "scoped").
     */
    public function show(Course $course, CourseMaterial $material): StreamedResponse
    {
        Gate::authorize('viewMaterials', $course);

        /** @var FilesystemAdapter $disk il disco "local": ha response(), che il tipo generico del facade non dichiara */
        $disk = Storage::disk('local');

        abort_unless($disk->exists($material->file_path), 404);

        return $disk->response($material->file_path, Str::slug($material->title).'.pdf', [
            'Content-Type' => 'application/pdf',
            // Mai in una cache condivisa: il permesso di vederlo può cambiare (iscrizione annullata)
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Carica un materiale (POST /api/courses/{course}/materials, solo admin, multipart: "title" e "file"). */
    public function store(StoreCourseMaterialRequest $request, Course $course): JsonResponse
    {
        $material = $this->materials->add($course, $request->validated('title'), $request->file('file'));

        return (new CourseMaterialResource($material))->response()->setStatusCode(201);
    }

    /**
     * Elimina un materiale (DELETE /api/courses/{course}/materials/{material}, solo admin). 204 senza corpo.
     * $course non si usa nel corpo, ma serve nella firma: è il parametro che fa verificare a Laravel (scopeBindings)
     * che il materiale appartenga a quel corso, altrimenti 404.
     */
    public function destroy(Course $course, CourseMaterial $material): Response
    {
        $this->materials->delete($material);

        return response()->noContent();
    }
}
