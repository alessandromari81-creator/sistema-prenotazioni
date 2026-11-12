<?php

namespace Tests\Feature\Services;

use App\Models\Availability;
use App\Models\Bookable;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Prova BookingService direttamente, senza passare da HTTP: si chiama il servizio come farebbe
 * un comando o un job. Le regole sono le stesse che i test dell'API verificano da fuori.
 */
class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    private Bookable $bookable;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookingService::class);
        $this->user = User::factory()->create(['role' => 'user']);

        // Una sala con slot da 30 minuti, prenotabile il lunedì dalle 9 alle 13 (ora di Roma)
        $this->bookable = Bookable::factory()->create(['slot_minutes' => 30, 'is_active' => true]);
        Availability::factory()->create([
            'bookable_id' => $this->bookable->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);
    }

    /** Il prossimo lunedì all'ora indicata (ora di Roma), come istante UTC in formato ISO 8601. */
    private function mondayAt(string $time): string
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return Carbon::now('Europe/Rome')->next(Carbon::MONDAY)->setTime($h, $m)->utc()->toIso8601String();
    }

    /** Esegue $action e controlla che lanci una ValidationException con quel messaggio su quel campo. */
    private function assertInvalid(callable $action, string $field, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertSame($message, $e->errors()[$field][0] ?? null);

            return;
        }

        $this->fail('Doveva lanciare una ValidationException.');
    }

    // ---------- create ----------

    public function test_create_saves_a_confirmed_booking_and_notifies_the_user(): void
    {
        Notification::fake();

        $booking = $this->service->create($this->user, $this->bookable->id, $this->mondayAt('09:00'));

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($this->user->id, $booking->user_id);
        $this->assertSame($this->bookable->id, $booking->bookable_id);
        // La fine si ricava dalla durata dello slot (30 minuti)
        $this->assertSame(30, (int) $booking->starts_at->diffInMinutes($booking->ends_at));
        $this->assertDatabaseCount('bookings', 1);
        Notification::assertSentTo($this->user, BookingConfirmed::class);
    }

    public function test_create_rejects_an_inactive_bookable(): void
    {
        $this->bookable->update(['is_active' => false]);

        $this->assertInvalid(
            fn () => $this->service->create($this->user, $this->bookable->id, $this->mondayAt('09:00')),
            'bookable_id',
            'Questa sala non è prenotabile.'
        );
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_create_rejects_a_time_outside_availability(): void
    {
        // 14:00 non rientra nella fascia 9-13
        $this->assertInvalid(
            fn () => $this->service->create($this->user, $this->bookable->id, $this->mondayAt('14:00')),
            'starts_at',
            'Orario fuori dalla disponibilità della sala.'
        );
    }

    public function test_create_rejects_a_slot_that_does_not_end_inside_the_window(): void
    {
        // Parte alle 12:45 ma lo slot da 30 minuti finirebbe alle 13:15, oltre la fascia
        $this->assertInvalid(
            fn () => $this->service->create($this->user, $this->bookable->id, $this->mondayAt('12:45')),
            'starts_at',
            'Orario fuori dalla disponibilità della sala.'
        );
    }

    public function test_create_rejects_a_time_that_is_not_the_start_of_a_slot(): void
    {
        $this->assertInvalid(
            fn () => $this->service->create($this->user, $this->bookable->id, $this->mondayAt('09:10')),
            'starts_at',
            'L\'orario non coincide con l\'inizio di uno slot.'
        );
    }

    public function test_create_rejects_a_slot_that_is_already_booked(): void
    {
        Notification::fake();
        $this->service->create($this->user, $this->bookable->id, $this->mondayAt('10:00'));

        $other = User::factory()->create(['role' => 'user']);

        $this->assertInvalid(
            fn () => $this->service->create($other, $this->bookable->id, $this->mondayAt('10:00')),
            'starts_at',
            'Lo slot è già stato prenotato.'
        );
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_a_cancelled_slot_can_be_booked_again(): void
    {
        Notification::fake();
        $first = $this->service->create($this->user, $this->bookable->id, $this->mondayAt('10:00'));
        $this->service->cancel($this->user, $first);

        $other = User::factory()->create(['role' => 'user']);
        $second = $this->service->create($other, $this->bookable->id, $this->mondayAt('10:00'));

        $this->assertSame('confirmed', $second->status);
    }

    public function test_create_does_not_notify_when_the_booking_fails(): void
    {
        Notification::fake();

        try {
            $this->service->create($this->user, $this->bookable->id, $this->mondayAt('14:00'));
        } catch (ValidationException) {
            // atteso
        }

        Notification::assertNothingSent();
    }

    // ---------- cancel ----------

    private function bookingStartingIn(int $hours, string $status = 'confirmed'): Booking
    {
        return Booking::factory()->create([
            'user_id' => $this->user->id,
            'bookable_id' => $this->bookable->id,
            'starts_at' => now()->addHours($hours),
            'ends_at' => now()->addHours($hours)->addMinutes(30),
            'status' => $status,
        ]);
    }

    public function test_cancel_sets_the_status_to_cancelled(): void
    {
        $booking = $this->bookingStartingIn(24);

        $result = $this->service->cancel($this->user, $booking);

        $this->assertSame('cancelled', $result->status);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_cancel_rejects_an_already_cancelled_booking(): void
    {
        $booking = $this->bookingStartingIn(24, 'cancelled');

        $this->assertInvalid(
            fn () => $this->service->cancel($this->user, $booking),
            'booking',
            'La prenotazione è già annullata.'
        );
    }

    public function test_a_normal_user_cannot_cancel_less_than_two_hours_before(): void
    {
        $booking = $this->bookingStartingIn(1);

        $this->assertInvalid(
            fn () => $this->service->cancel($this->user, $booking),
            'booking',
            'Si può annullare fino a 2 ore prima dell\'inizio.'
        );
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_a_normal_user_can_cancel_just_over_two_hours_before(): void
    {
        $booking = $this->bookingStartingIn(3);

        $this->assertSame('cancelled', $this->service->cancel($this->user, $booking)->status);
    }

    public function test_an_admin_can_cancel_less_than_two_hours_before(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->bookingStartingIn(1);

        $this->assertSame('cancelled', $this->service->cancel($admin, $booking)->status);
    }
}
