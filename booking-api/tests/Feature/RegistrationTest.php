<?php

namespace Tests\Feature;

use App\Jobs\SendRegistrationConfirmation;
use App\Mail\ConfirmRegistrationMail;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'mario@example.com';

    private function payload(array $override = []): array
    {
        return [
            'name' => 'Mario Rossi',
            'email' => self::EMAIL,
            'password' => 'password123',
            'address' => 'Via Roma 1, Milano',
            ...$override,
        ];
    }

    /** Invia il form (coda sync nei test) e restituisce il token ricevuto per mail. */
    private function registerAndGetToken(array $override = []): string
    {
        $data = $this->payload($override);
        $this->postJson('/api/register', $data)->assertStatus(202);

        $token = '';
        Mail::assertSent(ConfirmRegistrationMail::class, function ($mail) use (&$token, $data) {
            if (! $mail->hasTo(strtolower($data['email']))) {
                return false;
            }
            parse_str((string) parse_url($mail->confirmUrl, PHP_URL_QUERY), $query);
            $token = (string) $query['token'];

            return true;
        });

        $this->assertNotSame('', $token, 'Il token non è stato trovato nella mail');

        return $token;
    }

    private function confirm(string $token, string $email = self::EMAIL)
    {
        return $this->postJson('/api/register/confirm', ['email' => $email, 'token' => $token]);
    }

    public function test_register_stores_pending_row_and_dispatches_job_without_creating_user(): void
    {
        Queue::fake();

        $this->postJson('/api/register', $this->payload())->assertStatus(202);

        $this->assertDatabaseHas('pending_registrations', [
            'email' => self::EMAIL,
            'name' => 'Mario Rossi',
            'address' => 'Via Roma 1, Milano',
        ]);
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
        Queue::assertPushed(SendRegistrationConfirmation::class);
    }

    public function test_password_and_token_are_stored_hashed(): void
    {
        Mail::fake();
        $token = $this->registerAndGetToken();

        $row = PendingRegistration::firstOrFail()->makeVisible(['password', 'token_hash']);

        $this->assertNotSame('password123', $row->password);
        $this->assertTrue(Hash::check('password123', $row->password));
        $this->assertSame(hash('sha256', $token), $row->token_hash);
    }

    public function test_mail_link_points_to_frontend(): void
    {
        Mail::fake();
        $this->registerAndGetToken();

        Mail::assertSent(ConfirmRegistrationMail::class, fn ($m) => str_starts_with(
            $m->confirmUrl,
            rtrim(config('app.frontend_url'), '/').'/confirm-registration?'
        ));
    }

    public function test_full_flow_creates_verified_user_with_address_who_can_log_in(): void
    {
        Mail::fake();
        $token = $this->registerAndGetToken();

        $this->confirm($token)->assertStatus(201);

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('Mario Rossi', $user->name);
        $this->assertSame('Via Roma 1, Milano', $user->address);
        $this->assertSame('user', $user->fresh()->role);
        $this->assertDatabaseCount('pending_registrations', 0);

        // Il login funziona (richiesta dal frontend, quindi con sessione Sanctum)
        $this->withHeader('Referer', 'http://localhost:4200')
            ->postJson('/api/login', ['email' => self::EMAIL, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('address', 'Via Roma 1, Milano');

        // Il token non è riutilizzabile
        $this->confirm($token)->assertStatus(410);
    }

    public function test_wrong_token_is_rejected_and_nothing_is_created(): void
    {
        Mail::fake();
        $this->registerAndGetToken();

        $this->confirm(str_repeat('a', 64))->assertStatus(410);

        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
        $this->assertDatabaseHas('pending_registrations', ['email' => self::EMAIL]);
    }

    public function test_expired_registration_cannot_be_confirmed(): void
    {
        Mail::fake();
        $token = $this->registerAndGetToken();
        PendingRegistration::query()->update(['expires_at' => now()->subMinute()]);

        $this->confirm($token)->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
    }

    public function test_latest_request_wins_and_old_link_stops_working(): void
    {
        Mail::fake();
        $first = $this->registerAndGetToken(['password' => 'attacker-pass1', 'name' => 'Intruso']);
        $second = $this->registerAndGetToken(['password' => 'owner-pass123', 'name' => 'Mario Rossi']);

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('pending_registrations', 1);

        // Il link della prima richiesta non vale più
        $this->confirm($first)->assertStatus(410);

        // Quello dell'ultima crea l'utente con i dati dell'ultima richiesta
        $this->confirm($second)->assertStatus(201);
        $user = User::firstWhere('email', self::EMAIL);
        $this->assertSame('Mario Rossi', $user->name);
        $this->assertTrue(Hash::check('owner-pass123', $user->password));
    }

    public function test_old_link_is_invalid_as_soon_as_a_new_request_arrives(): void
    {
        Mail::fake();
        $first = $this->registerAndGetToken();

        // Nuova richiesta con la coda ferma: il token vecchio deve già essere invalido
        Queue::fake();
        $this->postJson('/api/register', $this->payload(['name' => 'Altro']))->assertStatus(202);

        $this->confirm($first)->assertStatus(410);
    }

    public function test_registering_an_existing_email_gives_same_response_and_sends_nothing(): void
    {
        Mail::fake();
        User::factory()->create(['email' => self::EMAIL]);

        $this->postJson('/api/register', $this->payload())
            ->assertStatus(202)
            ->assertJsonMissingValidationErrors('email');

        $this->assertDatabaseCount('pending_registrations', 0);
        Mail::assertNothingSent();
    }

    public function test_email_is_case_insensitive(): void
    {
        Mail::fake();
        $token = $this->registerAndGetToken(['email' => 'Mario@Example.com']);

        $this->confirm($token, 'MARIO@example.com')->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => self::EMAIL]);
    }

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/register', ['email' => 'non-una-email', 'password' => 'corta'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'address']);
    }

    public function test_register_is_throttled_per_email(): void
    {
        Queue::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/register', $this->payload())->assertStatus(202);
        }

        $this->postJson('/api/register', $this->payload())
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after'])
            ->assertJsonMissingPath('trace')
            ->assertHeader('Retry-After');
    }

    public function test_confirm_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->confirm(str_repeat('c', 64))->assertStatus(410);
        }

        $this->confirm(str_repeat('c', 64))->assertStatus(429);
    }

    public function test_prune_removes_only_expired_rows(): void
    {
        $base = ['name' => 'X', 'password' => 'x', 'address' => 'Via X'];
        PendingRegistration::create([...$base, 'email' => 'old@example.com', 'expires_at' => now()->subDay()]);
        PendingRegistration::create([...$base, 'email' => 'new@example.com', 'expires_at' => now()->addDay()]);

        $this->artisan('model:prune', ['--model' => [PendingRegistration::class]])->assertSuccessful();

        $this->assertDatabaseMissing('pending_registrations', ['email' => 'old@example.com']);
        $this->assertDatabaseHas('pending_registrations', ['email' => 'new@example.com']);
    }
}
