<?php

namespace Tests\Feature\Repositories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Repositories\CourseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L'elenco dei corsi: filtri, ordinamento, paginazione e campi calcolati per l'utente che guarda. */
class CourseRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CourseRepository $repository;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(CourseRepository::class);
        $this->viewer = User::factory()->create();
    }

    /** I nomi dei risultati, nell'ordine restituito. */
    private function names($paginator): array
    {
        return $paginator->getCollection()->pluck('name')->all();
    }

    public function test_inactive_courses_are_hidden_by_default(): void
    {
        Course::factory()->create(['name' => 'Yoga']);
        Course::factory()->inactive()->create(['name' => 'Vecchio corso']);

        $this->assertSame(['Yoga'], $this->names($this->repository->paginate($this->viewer, [])));
    }

    public function test_inactive_courses_can_be_included(): void
    {
        Course::factory()->create(['name' => 'Yoga']);
        Course::factory()->inactive()->create(['name' => 'Vecchio corso']);

        $this->assertSame(2, $this->repository->paginate($this->viewer, [], true)->total());
    }

    public function test_search_ignores_case(): void
    {
        Course::factory()->create(['name' => 'Yoga mattutino']);
        Course::factory()->create(['name' => 'Spinning']);

        $result = $this->repository->paginate($this->viewer, ['search' => 'YOGA']);

        $this->assertSame(['Yoga mattutino'], $this->names($result));
    }

    public function test_filter_by_day_of_week(): void
    {
        Course::factory()->create(['name' => 'Lunedì', 'day_of_week' => 1]);
        Course::factory()->create(['name' => 'Mercoledì', 'day_of_week' => 3]);

        $this->assertSame(['Mercoledì'], $this->names($this->repository->paginate($this->viewer, ['day_of_week' => 3])));
    }

    public function test_default_order_is_by_day_then_time(): void
    {
        Course::factory()->create(['name' => 'Mercoledì sera', 'day_of_week' => 3, 'start_time' => '20:00']);
        Course::factory()->create(['name' => 'Lunedì', 'day_of_week' => 1, 'start_time' => '18:00']);
        Course::factory()->create(['name' => 'Mercoledì mattina', 'day_of_week' => 3, 'start_time' => '09:00']);

        $this->assertSame(
            ['Lunedì', 'Mercoledì mattina', 'Mercoledì sera'],
            $this->names($this->repository->paginate($this->viewer, []))
        );
    }

    public function test_order_by_name_descending(): void
    {
        Course::factory()->create(['name' => 'Aerobica']);
        Course::factory()->create(['name' => 'Zumba']);

        $result = $this->repository->paginate($this->viewer, ['sort_by' => 'name', 'sort_direction' => 'desc']);

        $this->assertSame(['Zumba', 'Aerobica'], $this->names($result));
    }

    public function test_pagination_defaults_to_ten_and_respects_per_page(): void
    {
        Course::factory()->count(12)->create();

        $this->assertCount(10, $this->repository->paginate($this->viewer, [])->items());
        $this->assertCount(5, $this->repository->paginate($this->viewer, ['per_page' => 5])->items());
        $this->assertSame(12, $this->repository->paginate($this->viewer, [])->total());
    }

    public function test_enrolled_count_counts_only_active_enrollments(): void
    {
        $course = Course::factory()->create();
        Enrollment::factory()->count(2)->create(['course_id' => $course->id]);
        Enrollment::factory()->cancelled()->create(['course_id' => $course->id]);

        $found = $this->repository->findForViewer($this->viewer, $course->id);

        $this->assertSame(2, $found->enrolled_count);
    }

    public function test_is_enrolled_depends_on_who_is_looking(): void
    {
        $course = Course::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $this->viewer->id]);

        $asEnrolled = $this->repository->findForViewer($this->viewer, $course->id);
        $asOther = $this->repository->findForViewer(User::factory()->create(), $course->id);

        $this->assertTrue((bool) $asEnrolled->is_enrolled);
        $this->assertFalse((bool) $asOther->is_enrolled);
    }

    public function test_a_cancelled_enrollment_does_not_count_as_enrolled(): void
    {
        $course = Course::factory()->create();
        Enrollment::factory()->cancelled()->create(['course_id' => $course->id, 'user_id' => $this->viewer->id]);

        $this->assertFalse((bool) $this->repository->findForViewer($this->viewer, $course->id)->is_enrolled);
    }

    public function test_is_instructor_depends_on_who_is_looking(): void
    {
        $course = Course::factory()->create();
        $course->instructors()->attach($this->viewer->id);

        $this->assertTrue((bool) $this->repository->findForViewer($this->viewer, $course->id)->is_instructor);
        $this->assertFalse((bool) $this->repository->findForViewer(User::factory()->create(), $course->id)->is_instructor);
    }

    public function test_instructors_come_already_loaded_with_only_id_and_name(): void
    {
        $course = Course::factory()->create();
        $course->instructors()->attach(User::factory()->create(['name' => 'Luca Verdi']));

        $found = $this->repository->findForViewer($this->viewer, $course->id);

        $this->assertTrue($found->relationLoaded('instructors'));
        $this->assertSame('Luca Verdi', $found->instructors[0]->name);
        $this->assertArrayNotHasKey('email', $found->instructors[0]->getAttributes());
    }

    public function test_find_for_viewer_fails_for_a_missing_course(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->repository->findForViewer($this->viewer, 999999);
    }

    public function test_enrolled_by_returns_only_my_active_courses(): void
    {
        $mine = Course::factory()->create(['name' => 'Mio']);
        $cancelled = Course::factory()->create(['name' => 'Annullato']);
        $someoneElses = Course::factory()->create(['name' => 'Altrui']);
        Enrollment::factory()->create(['course_id' => $mine->id, 'user_id' => $this->viewer->id]);
        Enrollment::factory()->cancelled()->create(['course_id' => $cancelled->id, 'user_id' => $this->viewer->id]);
        Enrollment::factory()->create(['course_id' => $someoneElses->id]);

        $this->assertSame(['Mio'], $this->repository->enrolledBy($this->viewer)->pluck('name')->all());
    }

    public function test_taught_by_returns_only_the_courses_i_teach(): void
    {
        $taught = Course::factory()->create(['name' => 'Insegno']);
        Course::factory()->create(['name' => 'Altro']);
        $taught->instructors()->attach($this->viewer->id);

        $this->assertSame(['Insegno'], $this->repository->taughtBy($this->viewer)->pluck('name')->all());
    }
}
