<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** I corsi vista dall'API: consultazione, gestione (solo admin), istruttori e elenco iscritti. */
class CourseApiTest extends TestCase
{
    use RefreshDatabase;

    /** Un utente normale, già autenticato per le richieste del test. */
    private function loginAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    /** Un amministratore, già autenticato per le richieste del test. */
    private function loginAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    /** I dati di un corso valido per le richieste di creazione. */
    private function courseData(array $override = []): array
    {
        return array_merge([
            'name' => 'Yoga',
            'description' => 'Posture e respirazione.',
            'capacity' => 12,
            'day_of_week' => 1,
            'start_time' => '18:30',
            'duration_minutes' => 60,
        ], $override);
    }

    // ---------- consultazione ----------

    public function test_a_guest_cannot_see_courses(): void
    {
        $this->getJson('/api/courses')->assertUnauthorized();
        $this->getJson('/api/courses/1')->assertUnauthorized();
    }

    public function test_the_list_has_the_paginator_shape_and_the_course_fields(): void
    {
        $this->loginAsUser();
        $course = Course::factory()->create(['name' => 'Yoga', 'capacity' => 10, 'day_of_week' => 2, 'start_time' => '18:30', 'duration_minutes' => 60]);
        $course->instructors()->attach(User::factory()->create(['name' => 'Luca Verdi']));

        $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonStructure(['current_page', 'data', 'per_page', 'total', 'last_page'])
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Yoga')
            ->assertJsonPath('data.0.capacity', 10)
            ->assertJsonPath('data.0.day_of_week', 2)
            ->assertJsonPath('data.0.start_time', '18:30:00')
            ->assertJsonPath('data.0.duration_minutes', 60)
            ->assertJsonPath('data.0.enrolled_count', 0)
            ->assertJsonPath('data.0.spots_left', 10)
            ->assertJsonPath('data.0.is_enrolled', false)
            ->assertJsonPath('data.0.is_instructor', false)
            ->assertJsonPath('data.0.instructors.0.name', 'Luca Verdi');
    }

    public function test_instructors_are_shown_without_email_or_personal_data(): void
    {
        $this->loginAsUser();
        $course = Course::factory()->create();
        $course->instructors()->attach(User::factory()->create(['address' => 'Via Segreta 1']));

        $this->getJson("/api/courses/{$course->id}")
            ->assertOk()
            ->assertJsonMissingPath('instructors.0.email')
            ->assertJsonMissingPath('instructors.0.address')
            ->assertJsonMissingPath('instructors.0.role');
    }

    public function test_spots_left_follow_the_enrollments(): void
    {
        $user = $this->loginAsUser();
        $course = Course::factory()->create(['capacity' => 3]);
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $user->id]);
        Enrollment::factory()->create(['course_id' => $course->id]);
        Enrollment::factory()->cancelled()->create(['course_id' => $course->id]);

        $this->getJson("/api/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('enrolled_count', 2)
            ->assertJsonPath('spots_left', 1)
            ->assertJsonPath('is_enrolled', true);
    }

    public function test_the_list_marks_the_courses_i_teach(): void
    {
        $user = $this->loginAsUser();
        $mine = Course::factory()->create(['name' => 'Mio']);
        Course::factory()->create(['name' => 'Altro']);
        $mine->instructors()->attach($user->id);

        $response = $this->getJson('/api/courses?sort_by=name&sort_direction=desc')->assertOk();

        $byName = collect($response->json('data'))->keyBy('name');
        $this->assertTrue($byName['Mio']['is_instructor']);
        $this->assertFalse($byName['Altro']['is_instructor']);
    }

    public function test_inactive_courses_are_hidden_from_normal_users_even_with_all(): void
    {
        $this->loginAsUser();
        Course::factory()->create(['name' => 'Attivo']);
        Course::factory()->inactive()->create(['name' => 'Disattivato']);

        $this->getJson('/api/courses?all=1')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_an_admin_sees_inactive_courses_with_all(): void
    {
        $this->loginAsAdmin();
        Course::factory()->create();
        Course::factory()->inactive()->create();

        $this->getJson('/api/courses')->assertJsonPath('total', 1);
        $this->getJson('/api/courses?all=1')->assertJsonPath('total', 2);
    }

    public function test_search_and_day_filters_work_through_the_api(): void
    {
        $this->loginAsUser();
        Course::factory()->create(['name' => 'Yoga', 'day_of_week' => 1]);
        Course::factory()->create(['name' => 'Yoga serale', 'day_of_week' => 4]);
        Course::factory()->create(['name' => 'Spinning', 'day_of_week' => 4]);

        $this->getJson('/api/courses?search=yoga&day_of_week=4')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Yoga serale');
    }

    public function test_invalid_list_parameters_are_rejected(): void
    {
        $this->loginAsUser();

        $this->getJson('/api/courses?day_of_week=9&per_page=500&sort_by=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['day_of_week', 'per_page', 'sort_by']);
    }

    public function test_an_inactive_course_is_not_found_for_normal_users(): void
    {
        $this->loginAsUser();
        $course = Course::factory()->inactive()->create();

        $this->getJson("/api/courses/{$course->id}")->assertNotFound();
    }

    public function test_an_admin_can_open_an_inactive_course(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->inactive()->create();

        $this->getJson("/api/courses/{$course->id}")->assertOk()->assertJsonPath('is_active', false);
    }

    public function test_an_unknown_course_is_not_found(): void
    {
        $this->loginAsUser();

        $this->getJson('/api/courses/999999')->assertNotFound();
    }

    // ---------- creazione ----------

    public function test_an_admin_can_create_a_course(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/api/courses', $this->courseData(['is_active' => true]))
            ->assertCreated()
            ->assertJsonPath('name', 'Yoga')
            ->assertJsonPath('capacity', 12)
            ->assertJsonPath('enrolled_count', 0)
            ->assertJsonPath('spots_left', 12)
            ->assertJsonPath('instructors', []);

        $this->assertDatabaseHas('courses', ['name' => 'Yoga', 'start_time' => '18:30:00']);
    }

    public function test_a_new_course_is_active_by_default(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/api/courses', $this->courseData())->assertCreated()->assertJsonPath('is_active', true);
    }

    public function test_a_course_can_indicate_the_room(): void
    {
        $this->loginAsAdmin();
        $room = \App\Models\Bookable::factory()->create(['name' => 'Sala grande']);

        $this->postJson('/api/courses', $this->courseData(['bookable_id' => $room->id]))
            ->assertCreated()
            ->assertJsonPath('bookable.name', 'Sala grande');
    }

    public function test_a_normal_user_cannot_create_a_course(): void
    {
        $this->loginAsUser();

        $this->postJson('/api/courses', $this->courseData())->assertForbidden();
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_course_creation_validates_the_data(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/api/courses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'capacity', 'day_of_week', 'start_time', 'duration_minutes']);

        $this->postJson('/api/courses', $this->courseData(['capacity' => 0, 'day_of_week' => 8, 'start_time' => '25:00', 'duration_minutes' => 5, 'bookable_id' => 9999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity', 'day_of_week', 'start_time', 'duration_minutes', 'bookable_id']);
    }

    // ---------- modifica ed eliminazione ----------

    public function test_an_admin_can_update_one_field(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create(['name' => 'Yoga', 'capacity' => 10]);

        $this->putJson("/api/courses/{$course->id}", ['name' => 'Yoga dolce'])
            ->assertOk()
            ->assertJsonPath('name', 'Yoga dolce')
            ->assertJsonPath('capacity', 10);
    }

    public function test_an_admin_can_deactivate_a_course(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->putJson("/api/courses/{$course->id}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);
    }

    public function test_the_capacity_cannot_go_below_the_enrolled_students_through_the_api(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create(['capacity' => 10]);
        Enrollment::factory()->count(3)->create(['course_id' => $course->id]);

        $this->putJson("/api/courses/{$course->id}", ['capacity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
    }

    public function test_a_normal_user_cannot_update_or_delete_a_course(): void
    {
        $this->loginAsUser();
        $course = Course::factory()->create();

        $this->putJson("/api/courses/{$course->id}", ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/courses/{$course->id}")->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_an_admin_can_delete_a_course_without_students(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->deleteJson("/api/courses/{$course->id}")->assertNoContent();
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_a_course_with_students_cannot_be_deleted(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id]);

        $this->deleteJson("/api/courses/{$course->id}")->assertUnprocessable()->assertJsonValidationErrors(['course']);
    }

    // ---------- istruttori ----------

    public function test_an_admin_can_assign_an_instructor(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $instructor = User::factory()->create(['name' => 'Luca Verdi']);

        $this->putJson("/api/courses/{$course->id}/instructors/{$instructor->id}")
            ->assertOk()
            ->assertJsonPath('instructors.0.name', 'Luca Verdi');
    }

    public function test_assigning_the_same_instructor_twice_is_harmless(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $instructor = User::factory()->create();

        $this->putJson("/api/courses/{$course->id}/instructors/{$instructor->id}")->assertOk();
        $this->putJson("/api/courses/{$course->id}/instructors/{$instructor->id}")->assertOk()->assertJsonCount(1, 'instructors');
    }

    public function test_a_student_of_the_course_cannot_be_assigned_as_its_instructor(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $student = User::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);

        $this->putJson("/api/courses/{$course->id}/instructors/{$student->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_an_instructor_can_be_a_student_of_another_course(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $user = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $user->id]);   // studente di un altro corso

        $this->putJson("/api/courses/{$course->id}/instructors/{$user->id}")->assertOk();
    }

    public function test_an_admin_can_remove_an_instructor(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $instructor = User::factory()->create();
        $course->instructors()->attach($instructor->id);

        $this->deleteJson("/api/courses/{$course->id}/instructors/{$instructor->id}")
            ->assertOk()
            ->assertJsonCount(0, 'instructors');
    }

    public function test_a_normal_user_cannot_manage_instructors(): void
    {
        $this->loginAsUser();
        $course = Course::factory()->create();
        $other = User::factory()->create();

        $this->putJson("/api/courses/{$course->id}/instructors/{$other->id}")->assertForbidden();
        $this->deleteJson("/api/courses/{$course->id}/instructors/{$other->id}")->assertForbidden();
    }

    public function test_assigning_an_unknown_user_is_not_found(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->putJson("/api/courses/{$course->id}/instructors/999999")->assertNotFound();
    }

    // ---------- elenco iscritti ----------

    private function courseWithStudents(): array
    {
        $course = Course::factory()->create();
        $active = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
        $cancelled = User::factory()->create(['name' => 'Anna Bianchi']);
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $active->id]);
        Enrollment::factory()->cancelled()->create(['course_id' => $course->id, 'user_id' => $cancelled->id]);

        return [$course, $active];
    }

    public function test_an_admin_sees_the_students_of_a_course(): void
    {
        $this->loginAsAdmin();
        [$course] = $this->courseWithStudents();

        $this->getJson("/api/courses/{$course->id}/students")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.student.name', 'Mario Rossi')
            ->assertJsonPath('0.student.email', 'mario@example.com')
            ->assertJsonPath('0.status', 'active');
    }

    public function test_the_instructor_of_the_course_sees_its_students(): void
    {
        $instructor = $this->loginAsUser();
        [$course] = $this->courseWithStudents();
        $course->instructors()->attach($instructor->id);

        $this->getJson("/api/courses/{$course->id}/students")->assertOk()->assertJsonCount(1);
    }

    public function test_the_instructor_of_another_course_cannot_see_the_students(): void
    {
        $instructor = $this->loginAsUser();
        [$course] = $this->courseWithStudents();
        Course::factory()->create()->instructors()->attach($instructor->id);

        $this->getJson("/api/courses/{$course->id}/students")->assertForbidden();
    }

    public function test_a_student_cannot_see_the_other_students(): void
    {
        [$course, $student] = $this->courseWithStudents();
        $this->actingAs($student);

        $this->getJson("/api/courses/{$course->id}/students")->assertForbidden();
    }
}
