<?php

namespace Tests\Feature\Services;

use App\Jobs\SendRegistrationConfirmation;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Registrazione in due passi, provata senza HTTP. Il token vero nasce nel job, qui lo si inserisce a mano. */
class RegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'mario@example.com';

    private const TOKEN = 'un-token-di-prova';

    private RegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RegistrationService::class);
    }

    private function data(array $override = []): array
    {
        return [
            'name' => 'Mario Rossi',
            'email' => self::EMAIL,
            'password' => 'password123',
            'address' => 'Via Roma 1, Milano',
            ...$override,
        ];
    }

    /** Una registrazione in attesa con un token noto (salvato come hash, come fa il job). */
    private function pending(array $override = []): PendingRegistration
    {
        return PendingRegistration::create([
            'email' => self::EMAIL,
            'name' => 'Mario Rossi',
            'password' => Hash::make('password123'),
            'address' => 'Via Roma 1, Milano',
            'token_hash' => hash('sha256', self::TOKEN),
            'expires_at' => now()->addHours(24),
            ...$override,
        ]);
    }

    // ---------- register (passo 1) ----------

    public function test_register_stores_a_pending_row_and_queues_the_email_job(): void
    {
        Queue::fake();

        $this->service->register($this->data());

        $this->assertDatabaseCount('users', 0);
        $row = PendingRegistration::firstOrFail();
        $this->assertSame(self::EMAIL, $row->email);
        $this->assertNull($row->token_hash);   // il token nasce nel job, non qui
        $this->assertTrue(Hash::check('password123', $row->password));
        Queue::assertPushed(SendRegistrationConfirmation::class, 1);
    }

    public function test_register_lowercases_the_email(): void
    {
        Queue::fake();

        $this->service->register($this->data(['email' => 'MARIO@Example.com']));

        $this->assertSame(self::EMAIL, PendingRegistration::firstOrFail()->email);
    }

    public function test_register_does_nothing_if_the_email_already_belongs_to_a_user(): void
    {
        Queue::fake();
        User::factory()->create(['email' => self::EMAIL]);

        $this->service->register($this->data());

        $this->assertDatabaseCount('pending_registrations', 0);
        Queue::assertNothingPushed();
    }

    public function test_the_last_request_wins_and_resets_the_token(): void
    {
        Queue::fake();
        $this->service->register($this->data(['name' => 'Primo Nome']));
        PendingRegistration::firstOrFail()->update(['token_hash' => hash('sha256', self::TOKEN)]);

        $this->service->register($this->data(['name' => 'Secondo Nome']));

        $this->assertDatabaseCount('pending_registrations', 1);
        $row = PendingRegistration::firstOrFail();
        $this->assertSame('Secondo Nome', $row->name);
        $this->assertNull($row->token_hash);   // il link della prima mail non vale più
        Queue::assertPushed(SendRegistrationConfirmation::class, 2);
    }

    // ---------- confirm (passo 2) ----------

    public function test_confirm_creates_a_verified_user_and_removes_the_pending_row(): void
    {
        $this->pending();

        $user = $this->service->confirm(self::EMAIL, self::TOKEN);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Mario Rossi', $user->name);
        $this->assertSame('Via Roma 1, Milano', $user->address);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password123', $user->password));   // la password non è stata hashata due volte
        $this->assertDatabaseCount('pending_registrations', 0);
    }

    public function test_confirm_accepts_the_email_in_any_case(): void
    {
        $this->pending();

        $this->assertNotNull($this->service->confirm('MARIO@example.COM', self::TOKEN));
    }

    public function test_confirm_returns_null_for_a_wrong_token_and_keeps_the_row(): void
    {
        $this->pending();

        $this->assertNull($this->service->confirm(self::EMAIL, 'token-sbagliato'));

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('pending_registrations', 1);
    }

    public function test_confirm_returns_null_for_an_unknown_email(): void
    {
        $this->assertNull($this->service->confirm('sconosciuto@example.com', self::TOKEN));
    }

    public function test_confirm_returns_null_for_an_expired_link(): void
    {
        $this->pending(['expires_at' => now()->subMinute()]);

        $this->assertNull($this->service->confirm(self::EMAIL, self::TOKEN));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_confirm_returns_null_if_the_token_was_reset(): void
    {
        // token_hash a null: una richiesta più recente ha annullato il link precedente
        $this->pending(['token_hash' => null]);

        $this->assertNull($this->service->confirm(self::EMAIL, self::TOKEN));
    }

    public function test_confirm_returns_null_and_cleans_up_if_the_user_was_created_in_the_meantime(): void
    {
        $this->pending();
        User::factory()->create(['email' => self::EMAIL]);

        $this->assertNull($this->service->confirm(self::EMAIL, self::TOKEN));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pending_registrations', 0);
    }

    public function test_a_confirmed_link_cannot_be_used_twice(): void
    {
        $this->pending();

        $this->assertNotNull($this->service->confirm(self::EMAIL, self::TOKEN));
        $this->assertNull($this->service->confirm(self::EMAIL, self::TOKEN));
    }
}
