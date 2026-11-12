<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** I materiali PDF dei corsi: visibili solo a iscritti, istruttori e admin; gestiti solo dall'admin; file sempre privati. */
class CourseMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /** Crea un materiale con un file vero (finto disco) nel corso indicato. */
    private function material(Course $course, string $title = 'Scheda di allenamento'): CourseMaterial
    {
        $path = 'course-materials/'.uniqid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 contenuto');

        return CourseMaterial::factory()->create(['course_id' => $course->id, 'title' => $title, 'file_path' => $path]);
    }

    private function pdf(string $name = 'dispensa.pdf', int $kb = 50): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    private function enroll(User $user, Course $course, string $status = Enrollment::ACTIVE): void
    {
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => $status]);
    }

    // ---------- chi può vedere ----------

    public function test_a_guest_cannot_see_materials(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course);

        $this->getJson("/api/courses/{$course->id}/materials")->assertUnauthorized();
        $this->get("/api/courses/{$course->id}/materials/{$material->id}")->assertUnauthorized();
    }

    public function test_an_enrolled_user_sees_the_list_and_downloads_the_pdf(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course, 'Scheda di allenamento');
        $user = User::factory()->create();
        $this->enroll($user, $course);
        $this->actingAs($user);

        $this->getJson("/api/courses/{$course->id}/materials")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Scheda di allenamento')
            ->assertJsonMissingPath('0.file_path');

        $response = $this->get("/api/courses/{$course->id}/materials/{$material->id}");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_a_user_who_is_not_enrolled_gets_403_on_list_and_file(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course);
        $this->actingAs(User::factory()->create());

        $this->getJson("/api/courses/{$course->id}/materials")->assertForbidden();
        $this->get("/api/courses/{$course->id}/materials/{$material->id}", ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_a_cancelled_enrollment_does_not_give_access(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course);
        $user = User::factory()->create();
        $this->enroll($user, $course, Enrollment::CANCELLED);
        $this->actingAs($user);

        $this->get("/api/courses/{$course->id}/materials/{$material->id}", ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_being_enrolled_in_another_course_does_not_give_access(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course);
        $user = User::factory()->create();
        $this->enroll($user, Course::factory()->create());
        $this->actingAs($user);

        $this->getJson("/api/courses/{$course->id}/materials")->assertForbidden();
        $this->get("/api/courses/{$course->id}/materials/{$material->id}", ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_an_instructor_of_the_course_can_see_its_materials_but_not_those_of_other_courses(): void
    {
        $course = Course::factory()->create();
        $other = Course::factory()->create();
        $this->material($course);
        $instructor = User::factory()->create();
        $course->instructors()->attach($instructor);
        $this->actingAs($instructor);

        $this->getJson("/api/courses/{$course->id}/materials")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/courses/{$other->id}/materials")->assertForbidden();
    }

    public function test_an_admin_can_see_the_materials_of_any_course(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course);
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson("/api/courses/{$course->id}/materials")->assertOk()->assertJsonCount(1);
        $this->get("/api/courses/{$course->id}/materials/{$material->id}")->assertOk();
    }

    public function test_a_material_cannot_be_read_through_another_course(): void
    {
        // Un iscritto al corso B non può leggere il materiale del corso A scrivendo "/courses/B/materials/{id di A}"
        $a = Course::factory()->create();
        $b = Course::factory()->create();
        $materialOfA = $this->material($a);
        $user = User::factory()->create();
        $this->enroll($user, $b);
        $this->actingAs($user);

        $this->get("/api/courses/{$b->id}/materials/{$materialOfA->id}", ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_a_missing_file_is_a_404_not_a_500(): void
    {
        $course = Course::factory()->create();
        $material = CourseMaterial::factory()->create(['course_id' => $course->id]); // la riga c'è, il file no
        $this->actingAs(User::factory()->admin()->create());

        $this->get("/api/courses/{$course->id}/materials/{$material->id}", ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_the_file_has_no_public_address(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course);

        // Il disco usato è quello privato: il file non è nel disco pubblico, e l'indirizzo /storage/... (che Laravel crea
        // per il disco "local") si apre solo con una firma valida, che nessuna rotta dell'app genera per i materiali
        $this->assertFalse(Storage::disk('public')->exists($material->file_path));
        $status = $this->get('/storage/'.$material->file_path)->getStatusCode();
        $this->assertContains($status, [403, 404]);
    }

    // ---------- gestione (solo admin) ----------

    public function test_an_admin_uploads_a_pdf(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post("/api/courses/{$course->id}/materials", ['title' => 'Dispensa 1', 'file' => $this->pdf()], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('title', 'Dispensa 1')->assertJsonPath('course_id', $course->id)->assertJsonMissingPath('file_path');
        $material = CourseMaterial::firstOrFail();
        $this->assertStringStartsWith('course-materials/', $material->file_path);
        Storage::disk('local')->assertExists($material->file_path);
    }

    public function test_the_stored_name_is_chosen_by_the_server(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->post("/api/courses/{$course->id}/materials", ['title' => 'X', 'file' => $this->pdf('../../evil.pdf')], ['Accept' => 'application/json'])->assertCreated();

        $this->assertStringNotContainsString('evil', CourseMaterial::firstOrFail()->file_path);
    }

    public function test_only_pdf_files_are_accepted(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->post("/api/courses/{$course->id}/materials", ['title' => 'X', 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        // Un file che finge di essere PDF solo dall'estensione ma dentro è codice PHP: si guarda il contenuto vero.
        // (Il file finto di Laravel deduce il tipo dal nome, quindi qui serve un file vero su disco)
        $path = tempnam(sys_get_temp_dir(), 'finto');
        file_put_contents($path, '<?php echo 1;');
        $fake = new UploadedFile($path, 'finto.pdf', 'application/pdf', null, true);
        $this->post("/api/courses/{$course->id}/materials", ['title' => 'X', 'file' => $fake], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        @unlink($path);
        $this->assertDatabaseCount('course_materials', 0);
    }

    public function test_a_pdf_over_ten_megabytes_is_rejected(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->post("/api/courses/{$course->id}/materials", ['title' => 'Grande', 'file' => $this->pdf('grande.pdf', 10241)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_title_and_file_are_required(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson("/api/courses/{$course->id}/materials", [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'file']);
    }

    public function test_a_normal_user_and_an_instructor_cannot_upload_or_delete(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course);
        $instructor = User::factory()->create();
        $course->instructors()->attach($instructor);

        foreach ([User::factory()->create(), $instructor] as $user) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($user);
            $this->post("/api/courses/{$course->id}/materials", ['title' => 'X', 'file' => $this->pdf()], ['Accept' => 'application/json'])->assertForbidden();
            $this->deleteJson("/api/courses/{$course->id}/materials/{$material->id}")->assertForbidden();
        }

        $this->assertDatabaseCount('course_materials', 1);
    }

    public function test_deleting_a_material_removes_the_file_and_answers_204(): void
    {
        $course = Course::factory()->create();
        $material = $this->material($course);
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->deleteJson("/api/courses/{$course->id}/materials/{$material->id}");

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        Storage::disk('local')->assertMissing($material->file_path);
        $this->assertDatabaseCount('course_materials', 0);
    }

    public function test_a_material_cannot_be_deleted_through_another_course(): void
    {
        $a = Course::factory()->create();
        $b = Course::factory()->create();
        $material = $this->material($a);
        $this->actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/courses/{$b->id}/materials/{$material->id}")->assertNotFound();

        Storage::disk('local')->assertExists($material->file_path);
    }

    public function test_deleting_the_course_removes_the_files_of_its_materials(): void
    {
        $course = Course::factory()->create();
        $first = $this->material($course);
        $second = $this->material($course);
        $this->actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/courses/{$course->id}")->assertNoContent();

        Storage::disk('local')->assertMissing($first->file_path);
        Storage::disk('local')->assertMissing($second->file_path);
        $this->assertDatabaseCount('course_materials', 0);
    }
}
