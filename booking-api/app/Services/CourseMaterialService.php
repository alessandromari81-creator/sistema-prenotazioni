<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Le regole sui materiali PDF dei corsi: salvataggio e cancellazione dei file nel disco privato.
 */
class CourseMaterialService
{
    /**
     * Salva il PDF nel disco privato (cartella course-materials, nome casuale scelto da Laravel) e registra il materiale.
     * Il titolo è quello scritto dall'admin: il nome del file dell'utente non viene mai usato.
     */
    public function add(Course $course, string $title, UploadedFile $file): CourseMaterial
    {
        $path = $file->store('course-materials', 'local');

        return $course->materials()->create([
            'title' => $title,
            'file_path' => $path,
            'size_bytes' => $file->getSize(),
        ]);
    }

    /** Elimina il materiale: prima il file dal disco, poi la riga dal database. */
    public function delete(CourseMaterial $material): void
    {
        Storage::disk('local')->delete($material->file_path);
        $material->delete();
    }

    /** Cancella dal disco i file di tutti i materiali del corso (le righe spariscono con il corso, per la chiave esterna). */
    public function deleteFilesOf(Course $course): void
    {
        foreach ($course->materials as $material) {
            Storage::disk('local')->delete($material->file_path);
        }
    }
}
