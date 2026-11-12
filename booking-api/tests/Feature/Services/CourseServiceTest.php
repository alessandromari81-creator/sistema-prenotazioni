<?php

namespace Tests\Feature\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La gestione dei corsi: creazione, modifica, eliminazione e istruttori (compresa la regola istruttore/studente).
 */
class CourseServiceTest extends TestCase
{
    use RefreshDatabase;

    private CourseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CourseService::class);
    }

    /** Controlla che $action lanci una ValidationException con quel messaggio su quel campo. */
    private function assertInvalid(callable $action, string $field, ?string $message = null): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
            if ($message !== null) {
                $this->assertSame($message, $e->errors()[$field][0]);
            }

            return;
        }

        $this->fail('Doveva lanciare una ValidationException.');
    }

    // ---------- create e update ----------

    public function test_create_saves_the_course(): void
    {
        $course = $this->service->create([
            'name' => 'Yoga', 'capacity' => 12, 'day_of_week' => 1, 'start_time' => '18:30', 'duration_minutes' => 60, 'is_active' => true,
        ]);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name' => 'Yoga', 'capacity' => 12, 'day_of_week' => 1]);
    }

    public function test_update_changes_only_the_given_fields(): void
    {
        $course = Course::factory()->create(['name' => 'Yoga', 'capacity' => 12]);

        $this->service->update($course, ['name' => 'Yoga dolce']);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name' => 'Yoga dolce', 'capacity' => 12]);
    }

    public function test_the_capacity_cannot_go_below_the_enrolled_students(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);
        Enrollment::factory()->count(3)->create(['course_id' => $course->id]);

        $this->assertInvalid(
            fn () => $this->service->update($course, ['capacity' => 2]),
            'capacity',
            'I posti non possono essere meno degli iscritti attuali (3).'
        );
        $this->assertSame(10, $course->fresh()->capacity);
    }

    public function test_the_capacity_can_be_lowered_down_to_the_enrolled_students(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);
        Enrollment::factory()->count(3)->create(['course_id' => $course->id]);

        $this->service->update($course, ['capacity' => 3]);

        $this->assertSame(3, $course->fresh()->capacity);
    }

    public function test_cancelled_enrollments_do_not_block_lowering_the_capacity(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);
        Enrollment::factory()->cancelled()->count(5)->create(['course_id' => $course->id]);

        $this->service->update($course, ['capacity' => 1]);

        $this->assertSame(1, $course->fresh()->capacity);
    }

    // ---------- delete ----------

    public function test_a_course_without_students_can_be_deleted(): void
    {
        $course = Course::factory()->create();

        $this->service->delete($course);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_a_course_with_active_students_cannot_be_deleted(): void
    {
        $course = Course::factory()->create();
        Enrollment::factory()->count(2)->create(['course_id' => $course->id]);

        $this->assertInvalid(fn () => $this->service->delete($course), 'course', 'Il corso ha 2 iscritti: disattivalo invece di eliminarlo.');
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_a_course_with_only_cancelled_enrollments_can_be_deleted(): void
    {
        $course = Course::factory()->create();
        Enrollment::factory()->cancelled()->create(['course_id' => $course->id]);

        $this->service->delete($course);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_deleting_a_course_removes_its_instructor_assignments(): void
    {
        $course = Course::factory()->create();
        $course->instructors()->attach(User::factory()->create()->id);

        $this->service->delete($course);

        $this->assertDatabaseCount('course_instructor', 0);
    }

    // ---------- istruttori ----------

    public function test_add_instructor_assigns_the_user_to_the_course(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();

        $this->service->addInstructor($course, $user);

        $this->assertTrue($course->isTaughtBy($user));
    }

    public function test_adding_the_same_instructor_twice_changes_nothing(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();

        $this->service->addInstructor($course, $user);
        $this->service->addInstructor($course, $user);

        $this->assertSame(1, $course->instructors()->count());
    }

    public function test_a_course_can_have_more_instructors(): void
    {
        $course = Course::factory()->create();

        $this->service->addInstructor($course, User::factory()->create());
        $this->service->addInstructor($course, User::factory()->create());

        $this->assertSame(2, $course->instructors()->count());
    }

    public function test_a_student_of_the_course_cannot_become_its_instructor(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);

        $this->assertInvalid(
            fn () => $this->service->addInstructor($course, $student),
            'user_id',
            'Questo utente è iscritto al corso come studente: non può esserne anche istruttore.'
        );
        $this->assertFalse($course->isTaughtBy($student));
    }

    public function test_a_student_who_cancelled_can_become_instructor(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();
        Enrollment::factory()->cancelled()->create(['course_id' => $course->id, 'user_id' => $user->id]);

        $this->service->addInstructor($course, $user);

        $this->assertTrue($course->isTaughtBy($user));
    }

    public function test_a_student_of_another_course_can_be_instructor_here(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $user->id]);   // studente di un altro corso

        $this->service->addInstructor($course, $user);

        $this->assertTrue($course->isTaughtBy($user));
    }

    public function test_remove_instructor_unassigns_the_user(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();
        $course->instructors()->attach($user->id);

        $this->service->removeInstructor($course, $user);

        $this->assertFalse($course->isTaughtBy($user));
    }

    public function test_removing_someone_who_is_not_an_instructor_changes_nothing(): void
    {
        $course = Course::factory()->create();
        $instructor = User::factory()->create();
        $course->instructors()->attach($instructor->id);

        $this->service->removeInstructor($course, User::factory()->create());

        $this->assertSame(1, $course->instructors()->count());
    }
}
