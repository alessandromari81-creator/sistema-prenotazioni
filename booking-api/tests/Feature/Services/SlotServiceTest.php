<?php

namespace Tests\Feature\Services;

use App\Models\Availability;
use App\Models\Bookable;
use App\Models\Booking;
use App\Models\User;
use App\Services\SlotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Calcolo degli slot liberi: fasce orarie meno prenotazioni e orari già passati. */
class SlotServiceTest extends TestCase
{
    use RefreshDatabase;

    private SlotService $service;

    private Bookable $bookable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SlotService::class);

        // Slot da 30 minuti, lunedì dalle 9 alle 13: 8 slot al giorno
        $this->bookable = Bookable::factory()->create(['slot_minutes' => 30]);
        Availability::factory()->create([
            'bookable_id' => $this->bookable->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        // Ora fissa: venerdì 13 novembre 2026, 12:00 a Roma. Il lunedì dopo è il 16 novembre.
        $this->travelTo(Carbon::parse('2026-11-13 12:00', 'Europe/Rome'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /** Gli orari di inizio, riportati all'ora di Roma (HH:MM), per leggere i risultati più facilmente. */
    private function startTimes(array $slots): array
    {
        return array_map(
            fn ($s) => Carbon::parse($s['starts_at'])->setTimezone('Europe/Rome')->format('H:i'),
            $slots
        );
    }

    public function test_returns_every_slot_of_a_working_day(): void
    {
        $slots = $this->service->freeSlots($this->bookable, '2026-11-16');

        $this->assertSame(
            ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30'],
            $this->startTimes($slots)
        );
    }

    public function test_each_slot_has_start_and_end_in_utc_iso_format(): void
    {
        $slots = $this->service->freeSlots($this->bookable, '2026-11-16');

        // 09:00 a Roma in novembre (CET, UTC+1) sono le 08:00 UTC
        $this->assertSame('2026-11-16T08:00:00+00:00', $slots[0]['starts_at']);
        $this->assertSame('2026-11-16T08:30:00+00:00', $slots[0]['ends_at']);
    }

    public function test_returns_nothing_on_a_day_without_availability(): void
    {
        // Sabato 14 novembre: nessuna fascia
        $this->assertSame([], $this->service->freeSlots($this->bookable, '2026-11-14'));
    }

    public function test_a_booked_slot_is_not_returned(): void
    {
        Booking::factory()->create([
            'user_id' => User::factory(),
            'bookable_id' => $this->bookable->id,
            'starts_at' => '2026-11-16 09:30:00+00',   // 10:30 a Roma
            'ends_at' => '2026-11-16 10:00:00+00',
            'status' => 'confirmed',
        ]);

        $times = $this->startTimes($this->service->freeSlots($this->bookable, '2026-11-16'));

        $this->assertCount(7, $times);
        $this->assertNotContains('10:30', $times);
    }

    public function test_a_cancelled_booking_does_not_take_the_slot(): void
    {
        Booking::factory()->create([
            'user_id' => User::factory(),
            'bookable_id' => $this->bookable->id,
            'starts_at' => '2026-11-16 09:30:00+00',
            'ends_at' => '2026-11-16 10:00:00+00',
            'status' => 'cancelled',
        ]);

        $this->assertCount(8, $this->service->freeSlots($this->bookable, '2026-11-16'));
    }

    public function test_past_slots_of_today_are_not_returned(): void
    {
        // Spostiamo l'ora a lunedì 16 novembre, 10:15 a Roma: restano gli slot dalle 10:30 in poi
        $this->travelTo(Carbon::parse('2026-11-16 10:15', 'Europe/Rome'));

        $times = $this->startTimes($this->service->freeSlots($this->bookable, '2026-11-16'));

        $this->assertSame(['10:30', '11:00', '11:30', '12:00', '12:30'], $times);
    }

    public function test_a_booking_of_another_bookable_does_not_matter(): void
    {
        $other = Bookable::factory()->create(['slot_minutes' => 30]);
        Booking::factory()->create([
            'user_id' => User::factory(),
            'bookable_id' => $other->id,
            'starts_at' => '2026-11-16 09:30:00+00',
            'ends_at' => '2026-11-16 10:00:00+00',
            'status' => 'confirmed',
        ]);

        $this->assertCount(8, $this->service->freeSlots($this->bookable, '2026-11-16'));
    }

    public function test_two_windows_in_the_same_day_are_both_used(): void
    {
        Availability::factory()->create([
            'bookable_id' => $this->bookable->id,
            'day_of_week' => 1,
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
        ]);

        $times = $this->startTimes($this->service->freeSlots($this->bookable, '2026-11-16'));

        $this->assertCount(10, $times);
        $this->assertSame(['15:00', '15:30'], array_slice($times, -2));
    }
}
