<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesKeycloak;
use Tests\TestCase;

/** Il profilo dell'utente e l'elenco utenti per gli amministratori. */
class UserApiTest extends TestCase
{
    use FakesKeycloak;
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

    // ---------- Il proprio profilo (PUT /api/user) ----------

    public function test_a_guest_cannot_update_a_profile(): void
    {
        $this->putJson('/api/user', ['address' => 'Via Roma 1'])->assertUnauthorized();
    }

    public function test_a_user_can_set_their_address(): void
    {
        $user = User::factory()->create(['address' => null]);
        $this->actingAs($user);

        $this->putJson('/api/user', ['address' => 'Via Roma 1, Milano'])
            ->assertOk()
            ->assertJsonPath('address', 'Via Roma 1, Milano');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'address' => 'Via Roma 1, Milano']);
    }

    public function test_the_response_has_the_same_shape_as_the_profile(): void
    {
        $user = User::factory()->create();
        Course::factory()->create()->instructors()->attach($user->id);
        $this->actingAs($user);

        $this->putJson('/api/user', ['address' => 'Via Verdi 2'])
            ->assertOk()
            ->assertJsonPath('teaches_courses', true)
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('keycloak_id');
    }

    public function test_an_empty_address_clears_it(): void
    {
        $user = User::factory()->create(['address' => 'Via Roma 1']);
        $this->actingAs($user);

        $this->putJson('/api/user', ['address' => ''])->assertOk()->assertJsonPath('address', null);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'address' => null]);
    }

    public function test_name_email_and_role_cannot_be_changed_from_the_profile(): void
    {
        $user = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.com', 'role' => 'user']);
        $this->actingAs($user);

        // Sono dati di Keycloak: anche se inviati, vengono ignorati (e il ruolo non si può usare per diventare admin)
        $this->putJson('/api/user', ['address' => 'Via Roma 1', 'name' => 'Altro', 'email' => 'altro@example.com', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('name', 'Mario Rossi')
            ->assertJsonPath('email', 'mario@example.com')
            ->assertJsonPath('role', 'user');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Mario Rossi', 'email' => 'mario@example.com', 'role' => 'user']);
    }

    public function test_a_too_long_address_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/user', ['address' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address']);
    }

    public function test_one_user_cannot_change_another_users_address(): void
    {
        $other = User::factory()->create(['address' => 'Via Altrui 9']);
        $this->actingAs(User::factory()->create());

        // La rotta non ha un parametro con l'utente: si modifica sempre e solo il proprio profilo
        $this->putJson('/api/user', ['address' => 'Via Mia 1', 'id' => $other->id])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $other->id, 'address' => 'Via Altrui 9']);
    }

    public function test_the_profile_update_works_with_a_real_keycloak_token(): void
    {
        $this->setUpKeycloak();

        $this->withToken($this->keycloakToken(['email' => 'mario@example.com']))
            ->putJson('/api/user', ['address' => 'Via Roma 1'])
            ->assertOk()
            ->assertJsonPath('address', 'Via Roma 1');

        // In un test la guardia resta in memoria tra due richieste (in produzione ogni richiesta è separata): la si azzera
        $this->app['auth']->forgetGuards();

        // Il token successivo riallinea nome ed email ma non tocca l'indirizzo, che è dell'applicazione
        $this->withToken($this->keycloakToken(['email' => 'mario@example.com', 'name' => 'Mario Bianchi']))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('name', 'Mario Bianchi')
            ->assertJsonPath('address', 'Via Roma 1');
    }
}
