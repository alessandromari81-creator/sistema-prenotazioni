<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le regole sulla gestione dei corsi: creazione, modifica, eliminazione e assegnazione degli istruttori.
 */
class CourseService
{
    /**
     * Crea un corso con i dati già validati (nome, posti, giorno, ora, durata, sala, stato).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Course
    {
        return Course::create($data);
    }

    /**
     * Modifica un corso. I posti non possono scendere sotto il numero degli iscritti attuali.
     *
     * @param  array<string, mixed>  $data  solo i campi da cambiare
     *
     * @throws ValidationException se i nuovi posti sono meno degli iscritti
     */
    public function update(Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data) {
            // Blocca il corso: nessuna iscrizione può inserirsi tra il controllo dei posti e il salvataggio
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();

            if (isset($data['capacity'])) {
                $enrolled = $course->activeEnrollments()->count();

                if ($data['capacity'] < $enrolled) {
                    throw ValidationException::withMessages([
                        'capacity' => ["I posti non possono essere meno degli iscritti attuali ({$enrolled})."],
                    ]);
                }
            }

            $course->update($data);

            return $course;
        });
    }

    /**
     * Elimina un corso. Se ha iscritti attivi non si può: va prima disattivato e svuotato, per non cancellare
     * di sorpresa l'iscrizione di qualcuno. Con la cancellazione spariscono anche le assegnazioni degli istruttori.
     *
     * @throws ValidationException se ci sono iscritti attivi
     */
    public function delete(Course $course): void
    {
        $enrolled = $course->activeEnrollments()->count();

        if ($enrolled > 0) {
            throw ValidationException::withMessages([
                'course' => ["Il corso ha {$enrolled} iscritti: disattivalo invece di eliminarlo."],
            ]);
        }

        $course->delete();
    }

    /**
     * Assegna $user come istruttore di $course. Se lo è già non cambia nulla.
     * Regola: chi è iscritto come studente a un corso non può esserne anche istruttore.
     * (Un istruttore può invece essere studente di un altro corso.)
     *
     * @throws ValidationException se l'utente è iscritto come studente a questo corso
     */
    public function addInstructor(Course $course, User $user): void
    {
        DB::transaction(function () use ($course, $user) {
            // Stesso ordine di blocco dell'iscrizione (prima l'utente, poi il corso): un'iscrizione e un'assegnazione
            // contemporanee non possono produrre uno studente-istruttore dello stesso corso
            User::whereKey($user->id)->lockForUpdate()->first();
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();

            $enrolled = Enrollment::where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->where('status', Enrollment::ACTIVE)
                ->exists();

            if ($enrolled) {
                throw ValidationException::withMessages([
                    'user_id' => ['Questo utente è iscritto al corso come studente: non può esserne anche istruttore.'],
                ]);
            }

            $course->instructors()->syncWithoutDetaching([$user->id]);
        });
    }

    /**
     * Toglie $user dagli istruttori di $course. Se non lo era non cambia nulla.
     */
    public function removeInstructor(Course $course, User $user): void
    {
        $course->instructors()->detach($user->id);
    }
}
