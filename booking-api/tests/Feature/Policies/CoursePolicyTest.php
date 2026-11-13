<?php

namespace Tests\Feature\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\CoursePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chi può vedere i materiali PDF di un corso (CoursePolicy::viewMaterials). La policy fa domande al database
 * (istruttori e iscrizioni), quindi non è un test unitario puro: usa RefreshDatabase come i test dei servizi.
 */
class CoursePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_the_materials_of_any_course(): void
    {
        $course = Course::factory()->create();            // 1. preparo: un corso...
        $admin = User::factory()->admin()->create();      //    ...e un amministratore che non c'entra col corso

        $allowed = (new CoursePolicy)->viewMaterials($admin, $course);   // 2. agisco: chiedo alla policy

        $this->assertTrue($allowed);                      // 3. controllo: deve poter vedere
    }

    public function test_an_instructor_of_the_course_can_view_the_materials(): void
    {
        $course = Course::factory()->create();            // 1. preparo: un corso...
        $user = User::factory()->create();                //    ...e un utente normale (l'istruttore non è un ruolo)

        $course->instructors()->attach($user);            //    ...assegnato come istruttore di QUESTO corso

        $allowed = (new CoursePolicy)->viewMaterials($user, $course);   // 2. agisco: chiedo alla policy

        $this->assertTrue($allowed);                      // 3. controllo: deve poter vedere
    }

    public function test_a_user_with_an_active_enrollment_can_view_the_materials(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $user->id]);   // iscrizione attiva (valore di partenza)

        $this->assertTrue((new CoursePolicy)->viewMaterials($user, $course));
    }

    public function test_a_user_with_a_cancelled_enrollment_cannot_view_the_materials(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();
        Enrollment::factory()->cancelled()->create(['course_id' => $course->id, 'user_id' => $user->id]);

        $this->assertFalse((new CoursePolicy)->viewMaterials($user, $course));   // annullata = il permesso si perde subito
    }

    public function test_a_stranger_cannot_view_the_materials(): void
    {
        $course = Course::factory()->create();
        $stranger = User::factory()->create();   // né admin, né istruttore, né iscritto

        $this->assertFalse((new CoursePolicy)->viewMaterials($stranger, $course));
    }

    public function test_being_enrolled_or_teaching_another_course_does_not_give_access(): void
    {
        $course = Course::factory()->create();
        $other = Course::factory()->create();
        $enrolledElsewhere = User::factory()->create();
        $teachesElsewhere = User::factory()->create();

        Enrollment::factory()->create(['course_id' => $other->id, 'user_id' => $enrolledElsewhere->id]);
        $other->instructors()->attach($teachesElsewhere);

        $this->assertFalse((new CoursePolicy)->viewMaterials($enrolledElsewhere, $course));
        $this->assertFalse((new CoursePolicy)->viewMaterials($teachesElsewhere, $course));
    }

    public function test_someone_elses_enrollment_does_not_give_access_to_me(): void
    {
        $course = Course::factory()->create();
        $me = User::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id]);   // un altro utente è iscritto, io no

        $this->assertFalse((new CoursePolicy)->viewMaterials($me, $course));
    }
}
