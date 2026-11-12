<?php

namespace Tests\Feature\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Le iscrizioni ai corsi: abbonamento, limite di corsi, posti, regola dell'istruttore, annullamento.
 */
class EnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentService $service;

    private Course $course;

    private User $student;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EnrollmentService::class);
        $this->course = Course::factory()->create(['capacity' => 2]);
        $this->student = User::factory()->create();

        // Abbonamento attivo che permette fino a 2 corsi
        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->student->id,
            'subscription_plan_id' => SubscriptionPlan::factory()->create(['max_courses' => 2])->id,
        ]);
    }

    /** Controlla che $action lanci una ValidationException con quel messaggio su quel campo. */
    private function assertInvalid(callable $action, string $field, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertSame($message, $e->errors()[$field][0] ?? null);

            return;
        }

        $this->fail('Doveva lanciare una ValidationException.');
    }

    // ---------- enroll: il caso normale ----------

    public function test_a_student_with_an_active_subscription_can_enroll(): void
    {
        $enrollment = $this->service->enroll($this->student, $this->course);

        $this->assertSame(Enrollment::ACTIVE, $enrollment->status);
        $this->assertSame($this->subscription->id, $enrollment->subscription_id);
        $this->assertDatabaseHas('enrollments', ['course_id' => $this->course->id, 'user_id' => $this->student->id, 'status' => 'active']);
    }

    // ---------- enroll: la regola dell'istruttore ----------

    public function test_an_instructor_cannot_enroll_in_the_course_they_teach(): void
    {
        $this->course->instructors()->attach($this->student->id);

        $this->assertInvalid(
            fn () => $this->service->enroll($this->student, $this->course),
            'course',
            'Sei istruttore di questo corso: non puoi iscriverti come studente.'
        );
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_an_instructor_can_enroll_in_a_course_taught_by_someone_else(): void
    {
        $other = Course::factory()->create();
        $other->instructors()->attach($this->student->id);   // insegna in "other"

        $enrollment = $this->service->enroll($this->student, $this->course);   // ma è studente di "course"

        $this->assertSame(Enrollment::ACTIVE, $enrollment->status);
    }

    // ---------- enroll: le altre regole ----------

    public function test_an_inactive_course_does_not_accept_enrollments(): void
    {
        $course = Course::factory()->inactive()->create();

        $this->assertInvalid(fn () => $this->service->enroll($this->student, $course), 'course', 'Il corso non è attivo.');
    }

    public function test_enrolling_twice_is_refused(): void
    {
        $this->service->enroll($this->student, $this->course);

        $this->assertInvalid(fn () => $this->service->enroll($this->student, $this->course), 'course', 'Sei già iscritto a questo corso.');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_an_active_subscription_is_required(): void
    {
        $user = User::factory()->create();   // nessun abbonamento

        $this->assertInvalid(fn () => $this->service->enroll($user, $this->course), 'subscription', 'Per iscriverti serve un abbonamento attivo.');
    }

    public function test_an_expired_subscription_is_not_enough(): void
    {
        $this->subscription->update(['starts_on' => now()->subDays(30), 'ends_on' => now()->subDay()]);

        $this->assertInvalid(fn () => $this->service->enroll($this->student, $this->course), 'subscription', 'Per iscriverti serve un abbonamento attivo.');
    }

    public function test_a_cancelled_subscription_is_not_enough(): void
    {
        $this->subscription->update(['cancelled_at' => now()]);

        $this->assertInvalid(fn () => $this->service->enroll($this->student, $this->course), 'subscription', 'Per iscriverti serve un abbonamento attivo.');
    }

    public function test_a_subscription_that_has_not_started_yet_is_not_enough(): void
    {
        $this->subscription->update(['starts_on' => now()->addDays(5), 'ends_on' => now()->addDays(35)]);

        $this->assertInvalid(fn () => $this->service->enroll($this->student, $this->course), 'subscription', 'Per iscriverti serve un abbonamento attivo.');
    }

    public function test_the_courses_limit_of_the_plan_is_respected(): void
    {
        $this->service->enroll($this->student, Course::factory()->create());
        $this->service->enroll($this->student, Course::factory()->create());

        // Il piano permette 2 corsi: il terzo è rifiutato
        $this->assertInvalid(
            fn () => $this->service->enroll($this->student, $this->course),
            'subscription',
            'Hai raggiunto il limite di corsi del tuo abbonamento (2).'
        );
    }

    public function test_a_full_course_does_not_accept_enrollments(): void
    {
        foreach (User::factory()->count(2)->create() as $other) {
            Enrollment::factory()->create(['course_id' => $this->course->id, 'user_id' => $other->id]);
        }

        $this->assertInvalid(fn () => $this->service->enroll($this->student, $this->course), 'course', 'Il corso è al completo.');
    }

    public function test_cancelled_enrollments_do_not_take_a_seat(): void
    {
        Enrollment::factory()->cancelled()->create(['course_id' => $this->course->id]);
        Enrollment::factory()->cancelled()->create(['course_id' => $this->course->id]);

        $enrollment = $this->service->enroll($this->student, $this->course);

        $this->assertSame(Enrollment::ACTIVE, $enrollment->status);
    }

    // ---------- cancel e riattivazione ----------

    public function test_cancel_frees_the_seat(): void
    {
        $this->service->enroll($this->student, $this->course);

        $cancelled = $this->service->cancel($this->student, $this->course);

        $this->assertSame(Enrollment::CANCELLED, $cancelled->status);
        $this->assertSame(0, $this->course->activeEnrollments()->count());
    }

    public function test_cancelling_without_being_enrolled_is_refused(): void
    {
        $this->assertInvalid(fn () => $this->service->cancel($this->student, $this->course), 'course', 'Non sei iscritto a questo corso.');
    }

    public function test_cancelling_twice_is_refused(): void
    {
        $this->service->enroll($this->student, $this->course);
        $this->service->cancel($this->student, $this->course);

        $this->assertInvalid(fn () => $this->service->cancel($this->student, $this->course), 'course', 'Non sei iscritto a questo corso.');
    }

    public function test_enrolling_again_after_cancelling_reactivates_the_same_row(): void
    {
        $first = $this->service->enroll($this->student, $this->course);
        $this->service->cancel($this->student, $this->course);

        $again = $this->service->enroll($this->student, $this->course);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(Enrollment::ACTIVE, $again->status);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_cancelled_enrollments_do_not_count_toward_the_courses_limit(): void
    {
        $first = Course::factory()->create();
        $second = Course::factory()->create();
        $this->service->enroll($this->student, $first);
        $this->service->enroll($this->student, $second);
        $this->service->cancel($this->student, $first);

        // Un posto nel piano si è liberato: ci si può iscrivere a un altro corso
        $enrollment = $this->service->enroll($this->student, $this->course);

        $this->assertSame(Enrollment::ACTIVE, $enrollment->status);
    }

    public function test_an_existing_enrollment_survives_the_cancellation_of_the_subscription(): void
    {
        $enrollment = $this->service->enroll($this->student, $this->course);

        $this->subscription->update(['cancelled_at' => now()]);

        // L'abbonamento serve per iscriversi: chi è già iscritto continua a frequentare
        $this->assertSame(Enrollment::ACTIVE, $enrollment->fresh()->status);
    }
}
