<?php

namespace Tests\Feature\Repositories;

use App\Models\Bookable;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\BookingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L'elenco delle prenotazioni con visibilità per ruolo, ricerca, filtro, ordinamento e paginazione. */
class BookingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private BookingRepository $repository;

    private User $mario;

    private User $anna;

    private User $admin;

    private Bookable $sala;

    private Bookable $campo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(BookingRepository::class);

        $this->mario = User::factory()->create(['name' => 'Mario Rossi', 'role' => 'user']);
        $this->anna = User::factory()->create(['name' => 'Anna Verdi', 'role' => 'user']);
        $this->admin = User::factory()->create(['name' => 'Admin', 'role' => 'admin']);

        $this->sala = Bookable::factory()->create(['name' => 'Sala riunioni']);
        $this->campo = Bookable::factory()->create(['name' => 'Campo da tennis']);
    }

    private function booking(User $user, Bookable $bookable, string $startsAt, string $status = 'confirmed'): Booking
    {
        return Booking::factory()->create([
            'user_id' => $user->id,
            'bookable_id' => $bookable->id,
            'starts_at' => $startsAt,
            'ends_at' => date('Y-m-d H:i:s', strtotime($startsAt) + 1800),
            'status' => $status,
        ]);
    }

    /** Gli id dei risultati nell'ordine restituito. */
    private function ids($paginator): array
    {
        return $paginator->getCollection()->pluck('id')->all();
    }

    public function test_a_normal_user_sees_only_their_own_bookings(): void
    {
        $mine = $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00');
        $this->booking($this->anna, $this->sala, '2030-01-10 10:00:00');

        $result = $this->repository->paginateFor($this->mario, []);

        $this->assertSame([$mine->id], $this->ids($result));
    }

    public function test_an_admin_sees_everyone_s_bookings(): void
    {
        $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00');
        $this->booking($this->anna, $this->campo, '2030-01-10 10:00:00');

        $this->assertSame(2, $this->repository->paginateFor($this->admin, [])->total());
    }

    public function test_search_matches_the_bookable_name(): void
    {
        $sala = $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00');
        $this->booking($this->mario, $this->campo, '2030-01-10 10:00:00');

        $result = $this->repository->paginateFor($this->mario, ['search' => 'riunioni']);

        $this->assertSame([$sala->id], $this->ids($result));
    }

    public function test_an_admin_can_search_by_user_name(): void
    {
        $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00');
        $anna = $this->booking($this->anna, $this->sala, '2030-01-10 10:00:00');

        $result = $this->repository->paginateFor($this->admin, ['search' => 'Verdi']);

        $this->assertSame([$anna->id], $this->ids($result));
    }

    public function test_a_normal_user_cannot_search_by_user_name(): void
    {
        // Per l'utente normale la ricerca guarda solo il nome della risorsa: non può "scoprire" gli altri
        $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00');
        $this->booking($this->anna, $this->sala, '2030-01-10 10:00:00');

        $result = $this->repository->paginateFor($this->mario, ['search' => 'Verdi']);

        $this->assertSame(0, $result->total());
    }

    public function test_status_filter(): void
    {
        $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00', 'confirmed');
        $cancelled = $this->booking($this->mario, $this->sala, '2030-01-10 10:00:00', 'cancelled');

        $result = $this->repository->paginateFor($this->mario, ['status' => 'cancelled']);

        $this->assertSame([$cancelled->id], $this->ids($result));
    }

    public function test_default_order_is_by_start_ascending(): void
    {
        $later = $this->booking($this->mario, $this->sala, '2030-01-12 09:00:00');
        $earlier = $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00');

        $this->assertSame([$earlier->id, $later->id], $this->ids($this->repository->paginateFor($this->mario, [])));
    }

    public function test_order_can_be_reversed(): void
    {
        $later = $this->booking($this->mario, $this->sala, '2030-01-12 09:00:00');
        $earlier = $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00');

        $result = $this->repository->paginateFor($this->mario, ['sort_direction' => 'desc']);

        $this->assertSame([$later->id, $earlier->id], $this->ids($result));
    }

    public function test_pagination_uses_per_page_and_defaults_to_ten(): void
    {
        foreach (range(1, 12) as $i) {
            $this->booking($this->mario, $this->sala, sprintf('2030-01-%02d 09:00:00', $i));
        }

        $default = $this->repository->paginateFor($this->mario, []);
        $five = $this->repository->paginateFor($this->mario, ['per_page' => 5]);

        $this->assertSame(10, $default->perPage());
        $this->assertCount(10, $default->items());
        $this->assertSame(12, $default->total());
        $this->assertCount(5, $five->items());
        $this->assertSame(3, $five->lastPage());
    }

    public function test_results_come_with_bookable_and_user_already_loaded(): void
    {
        $this->booking($this->mario, $this->sala, '2030-01-10 09:00:00');

        $booking = $this->repository->paginateFor($this->mario, [])->items()[0];

        // Niente query nascoste quando poi la Resource legge risorsa e utente (problema N+1)
        $this->assertTrue($booking->relationLoaded('bookable'));
        $this->assertTrue($booking->relationLoaded('user'));
    }
}
