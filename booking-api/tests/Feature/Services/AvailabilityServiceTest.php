<?php

namespace Tests\Feature\Services;

use App\Models\Availability;
use App\Models\Bookable;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Fasce orarie settimanali: il formato lo controlla la Form Request, le sovrapposizioni il servizio. */
class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    private Bookable $bookable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AvailabilityService::class);
        $this->bookable = Bookable::factory()->create();
    }

    private function add(int $day, string $start, string $end, ?Bookable $bookable = null): Availability
    {
        return $this->service->add($bookable ?? $this->bookable, [
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function test_it_saves_the_window_with_seconds(): void
    {
        $availability = $this->add(1, '09:00', '13:00');

        $this->assertSame($this->bookable->id, $availability->bookable_id);
        $this->assertDatabaseHas('availabilities', [
            'bookable_id' => $this->bookable->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);
    }

    public function test_it_rejects_an_overlapping_window(): void
    {
        $this->add(1, '09:00', '13:00');

        try {
            $this->add(1, '12:00', '14:00');
            $this->fail('Doveva lanciare una ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'La fascia si sovrappone a una già esistente in quel giorno.',
                $e->errors()['start_time'][0]
            );
        }

        $this->assertDatabaseCount('availabilities', 1);
    }

    public function test_it_rejects_a_window_contained_in_an_existing_one(): void
    {
        $this->add(1, '09:00', '13:00');

        $this->expectException(ValidationException::class);
        $this->add(1, '10:00', '11:00');
    }

    public function test_it_rejects_a_window_that_contains_an_existing_one(): void
    {
        $this->add(1, '10:00', '11:00');

        $this->expectException(ValidationException::class);
        $this->add(1, '09:00', '13:00');
    }

    public function test_touching_windows_are_allowed(): void
    {
        $this->add(1, '09:00', '12:00');
        $this->add(1, '12:00', '14:00');

        $this->assertDatabaseCount('availabilities', 2);
    }

    public function test_the_same_hours_on_a_different_day_are_allowed(): void
    {
        $this->add(1, '09:00', '13:00');
        $this->add(2, '09:00', '13:00');

        $this->assertDatabaseCount('availabilities', 2);
    }

    public function test_the_same_hours_on_a_different_bookable_are_allowed(): void
    {
        $this->add(1, '09:00', '13:00');
        $this->add(1, '09:00', '13:00', Bookable::factory()->create());

        $this->assertDatabaseCount('availabilities', 2);
    }
}
