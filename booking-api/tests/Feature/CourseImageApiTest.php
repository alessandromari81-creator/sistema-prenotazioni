<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** L'immagine dei corsi: caricamento e rimozione (solo admin), lettura pubblica, pulizia dei file. */
class CourseImageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // I file finiscono in un disco finto: nessun file vero sul disco durante i test
        Storage::fake('local');
    }

    /** Un'immagine finta valida (PNG 20x20) per le richieste del test. */
    private function png(string $name = 'foto.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 20, 20);
    }

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_an_admin_uploads_an_image_and_the_course_shows_its_address(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $response = $this->post("/api/courses/{$course->id}/image", ['image' => $this->png()], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('id', $course->id);
        $this->assertStringStartsWith("/api/courses/{$course->id}/image?v=", $response->json('image_url'));
        Storage::disk('local')->assertExists($course->fresh()->image_path);
    }

    public function test_the_stored_file_name_is_chosen_by_the_server_not_by_the_user(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png('../../evil.png')], ['Accept' => 'application/json'])->assertOk();

        $path = $course->fresh()->image_path;
        $this->assertStringStartsWith('course-images/', $path);
        $this->assertStringNotContainsString('evil', $path);
    }

    public function test_a_course_without_image_has_a_null_address(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->getJson("/api/courses/{$course->id}")->assertOk()->assertJsonPath('image_url', null);
    }

    public function test_the_image_is_readable_without_logging_in(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png()], ['Accept' => 'application/json'])->assertOk();

        $this->app['auth']->forgetGuards();

        // Nessun token: un tag <img> non può mandarlo
        $response = $this->get("/api/courses/{$course->id}/image");

        $response->assertOk();
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
    }

    public function test_reading_the_image_of_a_course_without_one_is_a_404(): void
    {
        $course = Course::factory()->create();

        $this->get("/api/courses/{$course->id}/image")->assertNotFound();
    }

    public function test_a_normal_user_cannot_upload_or_remove_an_image(): void
    {
        $this->actingAs(User::factory()->create());
        $course = Course::factory()->create();

        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png()], ['Accept' => 'application/json'])->assertForbidden();
        $this->deleteJson("/api/courses/{$course->id}/image")->assertForbidden();
    }

    public function test_a_guest_cannot_upload_an_image(): void
    {
        $course = Course::factory()->create();

        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png()], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_only_jpeg_png_and_webp_are_accepted(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->post("/api/courses/{$course->id}/image", ['image' => UploadedFile::fake()->create('foto.gif', 10, 'image/gif')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->post("/api/courses/{$course->id}/image", ['image' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertNull($course->fresh()->image_path);
    }

    public function test_an_image_over_two_megabytes_is_rejected(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->post("/api/courses/{$course->id}/image", ['image' => UploadedFile::fake()->create('grande.png', 2049, 'image/png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_the_image_field_is_required(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->postJson("/api/courses/{$course->id}/image", [])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_uploading_again_replaces_the_old_file(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png('a.png')], ['Accept' => 'application/json'])->assertOk();
        $first = $course->fresh()->image_path;

        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png('b.png')], ['Accept' => 'application/json'])->assertOk();

        $second = $course->fresh()->image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_removing_the_image_deletes_the_file_and_answers_204(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png()], ['Accept' => 'application/json'])->assertOk();
        $path = $course->fresh()->image_path;

        $response = $this->deleteJson("/api/courses/{$course->id}/image");

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($course->fresh()->image_path);
    }

    public function test_removing_when_there_is_no_image_is_harmless(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->deleteJson("/api/courses/{$course->id}/image")->assertNoContent();
    }

    public function test_deleting_the_course_deletes_its_image_file(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png()], ['Accept' => 'application/json'])->assertOk();
        $path = $course->fresh()->image_path;

        $this->deleteJson("/api/courses/{$course->id}")->assertNoContent();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_course_that_cannot_be_deleted_keeps_its_image(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();
        $this->post("/api/courses/{$course->id}/image", ['image' => $this->png()], ['Accept' => 'application/json'])->assertOk();
        $path = $course->fresh()->image_path;
        Enrollment::factory()->create(['course_id' => $course->id, 'status' => Enrollment::ACTIVE]);

        $this->deleteJson("/api/courses/{$course->id}")->assertUnprocessable();

        Storage::disk('local')->assertExists($path);
    }

    public function test_the_image_path_cannot_be_set_through_the_course_update(): void
    {
        $this->loginAsAdmin();
        $course = Course::factory()->create();

        $this->putJson("/api/courses/{$course->id}", ['image_path' => 'course-images/altro.png'])->assertOk();

        $this->assertNull($course->fresh()->image_path);
    }
}
