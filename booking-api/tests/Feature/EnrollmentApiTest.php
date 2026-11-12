<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le iscrizioni vista dall'API: iscriversi, annullare, "le mie cose" e un percorso completo. */
class EnrollmentApiTest extends TestCase
{
    use RefreshDatabase;

    /** Uno studente con un abbonamento attivo (fino a 2 corsi), già autenticato. */
    private function loginAsSubscribedStudent(int $maxCourses = 2): User
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => SubscriptionPlan::factory()->create(['max_courses' => $maxCourses])->id,
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_a_guest_cannot_enroll(): void
    {
        $course = Course::factory()->create();

        $this->postJson("/api/courses/{$course->id}/enrollment")->assertUnauthorized();
        $this->deleteJson("/api/courses/{$course->id}/enrollment")->assertUnauthorized();
        $this->getJson('/api/me/courses')->assertUnauthorized();
    }

    // ---------- iscriversi ----------

    public function test_enrolling_answers_204_with_no_body(): void
    {
        $user = $this->loginAsSubscribedStudent();
        $course = Course::factory()->create(['capacity' => 5]);

        $response = $this->postJson("/api/courses/{$course->id}/enrollment")->assertNoContent();

        // È un comando: l'esito sta nel codice di stato, non c'è un corpo
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $user->id, 'status' => 'active']);
    }

    public function test_the_updated_course_is_read_with_a_get_after_enrolling(): void
    {
        $this->loginAsSubscribedStudent();
        $course = Course::factory()->create(['capacity' => 5]);

        $this->postJson("/api/courses/{$course->id}/enrollment")->assertNoContent();

        // I dati aggiornati (iscritti, posti liberi, "sono iscritto?") si chiedono al corso
        $this->getJson("/api/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('enrolled_count', 1)
            ->assertJsonPath('spots_left', 4)
            ->assertJsonPath('is_enrolled', true);
    }

    public function test_enrolling_without_a_subscription_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $course = Course::factory()->create();

        $this->postJson("/api/courses/{$course->id}/enrollment")
            ->assertUnprocessable()
            ->assertJsonPath('errors.subscription.0', 'Per iscriverti serve un abbonamento attivo.');
    }

    public function test_an_instructor_cannot_enroll_in_their_own_course(): void
    {
        $user = $this->loginAsSubscribedStudent();
        $course = Course::factory()->create();
        $course->instructors()->attach($user->id);

        $this->postJson("/api/courses/{$course->id}/enrollment")
            ->assertUnprocessable()
            ->assertJsonPath('errors.course.0', 'Sei istruttore di questo corso: non puoi iscriverti come studente.');
    }

    public function test_an_instructor_can_enroll_in_another_course(): void
    {
        $user = $this->loginAsSubscribedStudent();
        Course::factory()->create()->instructors()->attach($user->id);
        $other = Course::factory()->create();

        $this->postJson("/api/courses/{$other->id}/enrollment")->assertNoContent();
    }

    public function test_a_full_course_is_refused(): void
    {
        $this->loginAsSubscribedStudent();
        $course = Course::factory()->create(['capacity' => 1]);
        Enrollment::factory()->create(['course_id' => $course->id]);

        $this->postJson("/api/courses/{$course->id}/enrollment")
            ->assertUnprocessable()
            ->assertJsonPath('errors.course.0', 'Il corso è al completo.');
    }

    public function test_the_plan_limit_is_enforced(): void
    {
        $this->loginAsSubscribedStudent(1);
        $first = Course::factory()->create();
        $second = Course::factory()->create();

        $this->postJson("/api/courses/{$first->id}/enrollment")->assertNoContent();
        $this->postJson("/api/courses/{$second->id}/enrollment")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subscription']);
    }

    public function test_enrolling_twice_is_refused(): void
    {
        $this->loginAsSubscribedStudent();
        $course = Course::factory()->create();

        $this->postJson("/api/courses/{$course->id}/enrollment")->assertNoContent();
        $this->postJson("/api/courses/{$course->id}/enrollment")
            ->assertUnprocessable()
            ->assertJsonPath('errors.course.0', 'Sei già iscritto a questo corso.');
    }

    public function test_an_inactive_course_cannot_be_joined(): void
    {
        $this->loginAsSubscribedStudent();
        $course = Course::factory()->inactive()->create();

        $this->postJson("/api/courses/{$course->id}/enrollment")->assertUnprocessable()->assertJsonValidationErrors(['course']);
    }

    public function test_enrolling_in_an_unknown_course_is_not_found(): void
    {
        $this->loginAsSubscribedStudent();

        $this->postJson('/api/courses/999999/enrollment')->assertNotFound();
    }

    // ---------- annullare ----------

    public function test_a_student_can_cancel_and_the_seat_is_freed(): void
    {
        $this->loginAsSubscribedStudent();
        $course = Course::factory()->create(['capacity' => 5]);
        $this->postJson("/api/courses/{$course->id}/enrollment")->assertNoContent();

        $response = $this->deleteJson("/api/courses/{$course->id}/enrollment")->assertNoContent();

        $this->assertSame('', $response->getContent());
        $this->getJson("/api/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('enrolled_count', 0)
            ->assertJsonPath('spots_left', 5)
            ->assertJsonPath('is_enrolled', false);
    }

    public function test_cancelling_keeps_the_row_with_status_cancelled(): void
    {
        $user = $this->loginAsSubscribedStudent();
        $course = Course::factory()->create();
        $this->postJson("/api/courses/{$course->id}/enrollment")->assertNoContent();

        $this->deleteJson("/api/courses/{$course->id}/enrollment")->assertNoContent();

        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $user->id, 'status' => 'cancelled']);
    }

    public function test_cancelling_without_being_enrolled_is_refused(): void
    {
        $this->loginAsSubscribedStudent();
        $course = Course::factory()->create();

        $this->deleteJson("/api/courses/{$course->id}/enrollment")
            ->assertUnprocessable()
            ->assertJsonPath('errors.course.0', 'Non sei iscritto a questo corso.');
    }

    public function test_one_user_cannot_cancel_the_enrollment_of_another(): void
    {
        $other = User::factory()->create();
        $course = Course::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $other->id]);
        $this->loginAsSubscribedStudent();

        // L'iscrizione è sempre "la mia": non esiste un modo di indicare quella di un altro
        $this->deleteJson("/api/courses/{$course->id}/enrollment")->assertUnprocessable();
        $this->assertDatabaseHas('enrollments', ['user_id' => $other->id, 'status' => 'active']);
    }

    // ---------- le mie cose ----------

    public function test_my_courses_lists_only_my_active_enrollments(): void
    {
        $user = $this->loginAsSubscribedStudent();
        $mine = Course::factory()->create(['name' => 'Mio']);
        $cancelled = Course::factory()->create(['name' => 'Annullato']);
        Enrollment::factory()->create(['course_id' => $mine->id, 'user_id' => $user->id]);
        Enrollment::factory()->cancelled()->create(['course_id' => $cancelled->id, 'user_id' => $user->id]);
        Enrollment::factory()->create();   // di un altro

        $this->getJson('/api/me/courses')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Mio')
            ->assertJsonPath('0.is_enrolled', true);
    }

    public function test_my_teaching_lists_the_courses_i_teach_with_their_students_count(): void
    {
        $user = $this->loginAsSubscribedStudent();
        $taught = Course::factory()->create(['name' => 'Insegno']);
        $taught->instructors()->attach($user->id);
        Enrollment::factory()->count(2)->create(['course_id' => $taught->id]);
        Course::factory()->create(['name' => 'Altro']);

        $this->getJson('/api/me/teaching')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Insegno')
            ->assertJsonPath('0.enrolled_count', 2)
            ->assertJsonPath('0.is_instructor', true);
    }

    public function test_my_teaching_is_empty_if_i_do_not_teach(): void
    {
        $this->loginAsSubscribedStudent();

        $this->getJson('/api/me/teaching')->assertOk()->assertExactJson([]);
    }

    public function test_my_subscription_shows_the_plan_and_the_usage(): void
    {
        $user = $this->loginAsSubscribedStudent(3);
        Enrollment::factory()->create(['user_id' => $user->id]);

        $this->getJson('/api/me/subscription')
            ->assertOk()
            ->assertJsonPath('subscription.is_active', true)
            ->assertJsonPath('subscription.plan.max_courses', 3)
            ->assertJsonPath('courses_used', 1)
            ->assertJsonPath('courses_left', 2);
    }

    public function test_my_subscription_is_null_without_one(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/me/subscription')
            ->assertOk()
            ->assertJsonPath('subscription', null)
            ->assertJsonPath('courses_left', 0);
    }

    // ---------- percorso completo ----------

    public function test_the_whole_story_from_the_admin_to_the_instructor(): void
    {
        $admin = User::factory()->admin()->create();
        $instructor = User::factory()->create(['name' => 'Luca Verdi']);
        $student = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);

        // 1. L'admin crea un piano e un corso, assegna l'istruttore e dà l'abbonamento allo studente
        $this->actingAs($admin);
        $planId = $this->postJson('/api/subscription-plans', ['name' => 'Mensile', 'duration_days' => 30, 'max_courses' => 2])->assertCreated()->json('id');
        $courseId = $this->postJson('/api/courses', ['name' => 'Yoga', 'capacity' => 10, 'day_of_week' => 1, 'start_time' => '18:30', 'duration_minutes' => 60])->assertCreated()->json('id');
        $this->putJson("/api/courses/{$courseId}/instructors/{$instructor->id}")->assertOk();
        $this->postJson("/api/users/{$student->id}/subscriptions", ['subscription_plan_id' => $planId])->assertCreated();

        // 2. Lo studente si iscrive
        $this->actingAs($student);
        $this->postJson("/api/courses/{$courseId}/enrollment")->assertNoContent();
        $this->getJson('/api/me/courses')->assertJsonPath('0.name', 'Yoga');

        // 3. L'istruttore vede il corso che insegna e l'elenco degli iscritti
        $this->actingAs($instructor);
        $this->getJson('/api/me/teaching')->assertJsonPath('0.enrolled_count', 1);
        $this->getJson("/api/courses/{$courseId}/students")
            ->assertOk()
            ->assertJsonPath('0.student.name', 'Mario Rossi');

        // 4. Ma l'istruttore non può iscriversi al proprio corso
        $this->postJson("/api/courses/{$courseId}/enrollment")->assertUnprocessable();
    }
}
