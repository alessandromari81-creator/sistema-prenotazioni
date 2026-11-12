<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le regole delle iscrizioni ai corsi: chi può iscriversi, a quali condizioni e come si annulla.
 */
class EnrollmentService
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Iscrive $user a $course. Tutto avviene in una transazione che blocca prima l'utente e poi il corso (sempre in
     * questo ordine, per evitare blocchi incrociati): due richieste contemporanee non possono superare né i posti del
     * corso né il limite di corsi dell'abbonamento.
     *
     * Regole, nell'ordine in cui vengono controllate:
     *  1. il corso deve essere attivo;
     *  2. chi insegna in un corso non può iscriversi come studente allo stesso corso;
     *  3. non ci si può iscrivere due volte;
     *  4. serve un abbonamento attivo;
     *  5. non si supera il numero di corsi del piano;
     *  6. il corso deve avere posti liberi.
     * Un'iscrizione annullata in precedenza viene riattivata.
     *
     * @throws ValidationException se una regola non è rispettata
     */
    public function enroll(User $user, Course $course): Enrollment
    {
        return DB::transaction(function () use ($user, $course) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();

            if (! $course->is_active) {
                $this->refuse('course', 'Il corso non è attivo.');
            }

            if ($course->isTaughtBy($user)) {
                $this->refuse('course', 'Sei istruttore di questo corso: non puoi iscriverti come studente.');
            }

            if ($this->activeEnrollmentOf($user, $course) !== null) {
                $this->refuse('course', 'Sei già iscritto a questo corso.');
            }

            $subscription = $this->subscriptions->activeFor($user);

            if ($subscription === null) {
                $this->refuse('subscription', 'Per iscriverti serve un abbonamento attivo.');
            }

            $max = $subscription->plan->max_courses;

            if ($this->activeEnrollmentsCountOf($user) >= $max) {
                $this->refuse('subscription', "Hai raggiunto il limite di corsi del tuo abbonamento ({$max}).");
            }

            if ($course->activeEnrollments()->count() >= $course->capacity) {
                $this->refuse('course', 'Il corso è al completo.');
            }

            // Una sola riga per coppia corso-utente: se c'era un'iscrizione annullata la si riattiva
            return Enrollment::updateOrCreate(
                ['course_id' => $course->id, 'user_id' => $user->id],
                ['status' => Enrollment::ACTIVE, 'subscription_id' => $subscription->id]
            );
        });
    }

    /**
     * Annulla l'iscrizione di $user a $course (la riga resta, con stato "cancelled"). Il posto torna libero.
     *
     * @throws ValidationException se l'utente non è iscritto al corso
     */
    public function cancel(User $user, Course $course): Enrollment
    {
        $enrollment = $this->activeEnrollmentOf($user, $course);

        if ($enrollment === null) {
            $this->refuse('course', 'Non sei iscritto a questo corso.');
        }

        $enrollment->update(['status' => Enrollment::CANCELLED]);

        return $enrollment;
    }

    /** L'iscrizione valida di $user a $course, oppure null. */
    private function activeEnrollmentOf(User $user, Course $course): ?Enrollment
    {
        return Enrollment::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->where('status', Enrollment::ACTIVE)
            ->first();
    }

    /** Quante iscrizioni valide ha $user, in tutti i corsi. */
    private function activeEnrollmentsCountOf(User $user): int
    {
        return Enrollment::where('user_id', $user->id)->where('status', Enrollment::ACTIVE)->count();
    }

    /**
     * Rifiuta l'operazione con un errore di validazione (risposta 422) su un campo e con un messaggio leggibile.
     *
     * @throws ValidationException sempre
     */
    private function refuse(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
