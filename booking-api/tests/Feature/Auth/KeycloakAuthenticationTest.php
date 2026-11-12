<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesKeycloak;
use Tests\TestCase;

/**
 * L'autenticazione vista dall'API: ogni richiesta porta un JWT e la guardia "keycloak" lo trasforma in un utente.
 */
class KeycloakAuthenticationTest extends TestCase
{
    use FakesKeycloak;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpKeycloak();
    }

    public function test_a_request_without_token_gets_401(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Non autenticato.']);
    }

    public function test_a_request_with_an_invalid_token_gets_401(): void
    {
        $this->withToken('non-un-jwt')->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_request_with_an_expired_token_gets_401(): void
    {
        $this->withToken($this->keycloakToken(['exp' => time() - 60]))->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_request_with_a_token_for_another_api_gets_401(): void
    {
        $this->withToken($this->keycloakToken(['aud' => 'altra-api']))->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_valid_token_returns_the_user_profile(): void
    {
        $this->withToken($this->keycloakToken(['email' => 'mario@example.com', 'name' => 'Mario Rossi']))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'mario@example.com')
            ->assertJsonPath('name', 'Mario Rossi')
            ->assertJsonPath('role', 'user')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('keycloak_id');
    }

    public function test_the_first_request_creates_the_laravel_profile(): void
    {
        $this->assertDatabaseCount('users', 0);

        // Anche al primo accesso lo stato è 200 (non 201, che una Resource darebbe per un modello appena creato)
        $this->withToken($this->keycloakToken())->getJson('/api/user')->assertStatus(200);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['keycloak_id' => '9d3f1c1e-5a56-4c0e-8d52-2f9b6a1c0001']);
    }

    public function test_a_normal_user_cannot_use_admin_routes(): void
    {
        $this->withToken($this->keycloakToken())
            ->postJson('/api/bookables', ['name' => 'Sala', 'slot_minutes' => 30])
            ->assertForbidden();
    }

    public function test_the_admin_role_in_the_token_opens_admin_routes(): void
    {
        $token = $this->keycloakToken(['realm_access' => ['roles' => ['admin']]]);

        $this->withToken($token)
            ->postJson('/api/bookables', ['name' => 'Sala', 'slot_minutes' => 30])
            ->assertCreated();
    }

    public function test_the_role_follows_the_token_and_not_what_is_stored(): void
    {
        // Il profilo è admin nel database, ma il token non ha più il ruolo: conta il token
        User::factory()->create(['keycloak_id' => '9d3f1c1e-5a56-4c0e-8d52-2f9b6a1c0001', 'email' => 'utente@example.com', 'role' => 'admin']);

        $this->withToken($this->keycloakToken())
            ->postJson('/api/bookables', ['name' => 'Sala', 'slot_minutes' => 30])
            ->assertForbidden();
    }

    public function test_an_unverified_email_cannot_take_over_an_existing_profile(): void
    {
        User::factory()->create(['keycloak_id' => null, 'email' => 'utente@example.com']);

        $this->withToken($this->keycloakToken(['email_verified' => false]))->getJson('/api/user')->assertUnauthorized();
    }

    public function test_the_old_cookie_routes_are_gone(): void
    {
        $this->postJson('/api/login', [])->assertNotFound();
        $this->postJson('/api/register', [])->assertNotFound();
        $this->getJson('/sanctum/csrf-cookie')->assertNotFound();
    }
}
