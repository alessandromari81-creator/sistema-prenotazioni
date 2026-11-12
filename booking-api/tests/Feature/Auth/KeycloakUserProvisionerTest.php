<?php

namespace Tests\Feature\Auth;

use App\Auth\InvalidKeycloakToken;
use App\Auth\KeycloakUserProvisioner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il legame tra l'identità di Keycloak e il profilo Laravel: creazione al primo accesso, allineamento dei dati,
 * collegamento dei profili già esistenti.
 */
class KeycloakUserProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private const SUB = '9d3f1c1e-5a56-4c0e-8d52-2f9b6a1c0001';

    private KeycloakUserProvisioner $provisioner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['keycloak.admin_role' => 'admin']);
        $this->provisioner = app(KeycloakUserProvisioner::class);
    }

    /** I claim di un utente normale, con la possibilità di cambiarne alcuni. */
    private function claims(array $override = []): array
    {
        return array_merge([
            'sub' => self::SUB,
            'email' => 'mario@example.com',
            'email_verified' => true,
            'name' => 'Mario Rossi',
            'realm_access' => ['roles' => []],
        ], $override);
    }

    public function test_the_first_access_creates_the_profile(): void
    {
        $user = $this->provisioner->resolve($this->claims());

        $this->assertTrue($user->exists);
        $this->assertSame(self::SUB, $user->keycloak_id);
        $this->assertSame('Mario Rossi', $user->name);
        $this->assertSame('mario@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('user', $user->role);
        $this->assertNull($user->password);   // le password vivono solo in Keycloak
    }

    public function test_the_second_access_reuses_the_same_profile(): void
    {
        $first = $this->provisioner->resolve($this->claims());
        $second = $this->provisioner->resolve($this->claims());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_admin_role_in_the_token_makes_the_user_an_admin(): void
    {
        $user = $this->provisioner->resolve($this->claims(['realm_access' => ['roles' => ['admin']]]));

        $this->assertTrue($user->isAdmin());
    }

    public function test_losing_the_admin_role_in_keycloak_removes_it_here_too(): void
    {
        $this->provisioner->resolve($this->claims(['realm_access' => ['roles' => ['admin']]]));

        $user = $this->provisioner->resolve($this->claims());

        $this->assertFalse($user->isAdmin());
        $this->assertDatabaseHas('users', ['keycloak_id' => self::SUB, 'role' => 'user']);
    }

    public function test_name_and_email_are_aligned_with_keycloak(): void
    {
        $this->provisioner->resolve($this->claims());

        $this->provisioner->resolve($this->claims(['name' => 'Mario Bianchi', 'email' => 'mario.bianchi@example.com']));

        $this->assertDatabaseHas('users', ['keycloak_id' => self::SUB, 'name' => 'Mario Bianchi', 'email' => 'mario.bianchi@example.com']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_name_falls_back_to_given_and_family_name_then_to_the_email(): void
    {
        $withNames = $this->provisioner->resolve($this->claims(['name' => null, 'given_name' => 'Anna', 'family_name' => 'Verdi']));
        $this->assertSame('Anna Verdi', $withNames->name);

        $other = $this->provisioner->resolve($this->claims(['sub' => '9d3f1c1e-5a56-4c0e-8d52-2f9b6a1c0002', 'email' => 'luca@example.com', 'name' => null]));
        $this->assertSame('luca', $other->name);
    }

    public function test_an_existing_profile_with_the_same_verified_email_is_linked(): void
    {
        $existing = User::factory()->create(['keycloak_id' => null, 'email' => 'mario@example.com', 'name' => 'Da seeder']);

        $user = $this->provisioner->resolve($this->claims());

        $this->assertSame($existing->id, $user->id);
        $this->assertSame(self::SUB, $user->fresh()->keycloak_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_email_match_ignores_case(): void
    {
        $existing = User::factory()->create(['keycloak_id' => null, 'email' => 'mario@example.com']);

        $user = $this->provisioner->resolve($this->claims(['email' => 'MARIO@Example.com']));

        $this->assertSame($existing->id, $user->id);
    }

    public function test_an_existing_profile_is_not_linked_if_the_email_is_not_verified(): void
    {
        // Senza questa regola, chi si registra con l'email di un altro senza verificarla ne prenderebbe il profilo
        User::factory()->create(['keycloak_id' => null, 'email' => 'mario@example.com']);

        $this->expectException(InvalidKeycloakToken::class);
        $this->provisioner->resolve($this->claims(['email_verified' => false]));
    }

    public function test_a_profile_already_linked_to_another_identity_is_not_taken_over(): void
    {
        User::factory()->create(['keycloak_id' => '9d3f1c1e-5a56-4c0e-8d52-2f9b6a1c0099', 'email' => 'mario@example.com']);

        $this->expectException(InvalidKeycloakToken::class);
        $this->provisioner->resolve($this->claims());
    }

    public function test_changing_to_an_email_of_another_profile_is_refused(): void
    {
        User::factory()->create(['email' => 'occupata@example.com']);
        $this->provisioner->resolve($this->claims());

        $this->expectException(InvalidKeycloakToken::class);
        $this->provisioner->resolve($this->claims(['email' => 'occupata@example.com']));
    }

    public function test_a_sub_that_is_not_a_uuid_is_refused_without_touching_the_database(): void
    {
        $this->expectException(InvalidKeycloakToken::class);
        $this->provisioner->resolve($this->claims(['sub' => 'non-un-uuid']));
    }

    public function test_a_token_without_sub_or_email_is_refused(): void
    {
        $this->expectException(InvalidKeycloakToken::class);
        $this->provisioner->resolve(['sub' => self::SUB]);
    }
}
