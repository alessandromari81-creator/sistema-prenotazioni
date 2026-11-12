<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Il profilo dell'utente e l'elenco utenti per gli amministratori. */
class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_says_whether_the_user_teaches(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('teaches_courses', false);

        Course::factory()->create()->instructors()->attach($user->id);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('teaches_courses', true);
    }

    public function test_the_user_list_is_for_admins_only(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_a_guest_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_an_admin_lists_users_with_search_and_pagination(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Admin']));
        User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
        User::factory()->create(['name' => 'Anna Bianchi', 'email' => 'anna@example.com']);

        $this->getJson('/api/users?per_page=2')
            ->assertOk()
            ->assertJsonStructure(['current_page', 'data', 'per_page', 'total'])
            ->assertJsonPath('total', 3)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/users?search=ROSSI')->assertJsonPath('total', 1)->assertJsonPath('data.0.email', 'mario@example.com');
        $this->getJson('/api/users?search=anna@')->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Anna Bianchi');
    }

    public function test_the_user_list_does_not_expose_secrets(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.keycloak_id')
            ->assertJsonMissingPath('data.0.remember_token');
    }
}
