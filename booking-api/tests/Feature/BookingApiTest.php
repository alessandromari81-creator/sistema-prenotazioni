<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Bookable;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    // Prima di ogni test ricrea tutte le tabelle da zero (database "testing")
    use RefreshDatabase;

    // La sala prenotabile usata in tutti i test (presa dal seeder)
    protected Bookable $bookable;

    /**
     * Eseguito prima di ogni singolo test: riempie il db con il seeder
     * (admin + 3 sale con orari lun-ven 9-13 e 15-19) e sceglie una sala.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->bookable = Bookable::firstOrFail();
    }

    // ---------- Funzioni di supporto ----------

    /** Crea un utente normale e lo "logga" per le richieste successive. */
    private function loginAsUser(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);
        return $user;
    }

    /** Logga l'admin creato dal seeder. */
    private function loginAsAdmin(): User
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);
        return $admin;
    }

    /** Restituisce il prossimo lunedì (sempre nel futuro) come stringa AAAA-MM-GG. */
    private function nextMonday(): string
    {
        return Carbon::now('Europe/Rome')->next(Carbon::MONDAY)->toDateString();
    }

    /** Restituisce il primo slot libero del prossimo lunedì: ['starts_at' => ..., 'ends_at' => ...]. */
    private function firstSlot(): array
    {
        $response = $this->getJson("/api/bookables/{$this->bookable->id}/slots?date=" . $this->nextMonday());
        $response->assertOk();
        return $response->json()[0];
    }

    /** Invia una prenotazione per lo slot indicato (starts_at). */
    private function book(string $startsAt)
    {
        return $this->postJson('/api/bookings', [
            'bookable_id' => $this->bookable->id,
            'starts_at'   => $startsAt,
        ]);
    }

    // ---------- Autenticazione e permessi ----------

    /** Senza login le rotte protette rispondono 401. */
    public function test_guest_cannot_list_bookings(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    /** Un utente loggato vede l'elenco delle sale. */
    public function test_user_can_list_bookables(): void
    {
        $this->loginAsUser();
        $this->getJson('/api/bookables')->assertOk();
    }

    /** Un utente normale non può cancellare una sala (Gate 'admin'). */
    public function test_normal_user_cannot_delete_bookable(): void
    {
        $this->loginAsUser();
        $this->deleteJson("/api/bookables/{$this->bookable->id}")->assertForbidden();
    }

    /** L'admin può cancellare una sala. */
    public function test_admin_can_delete_bookable(): void
    {
        $this->loginAsAdmin();
        $this->deleteJson("/api/bookables/{$this->bookable->id}")->assertSuccessful();
    }

    // ---------- Slot ----------

    /** Di lunedì (fascia 9-13 e 15-19) ci sono slot disponibili. */
    public function test_slots_are_returned_on_a_working_day(): void
    {
        $this->loginAsUser();
        $this->getJson("/api/bookables/{$this->bookable->id}/slots?date=" . $this->nextMonday())
            ->assertOk()
            ->assertJsonStructure([['starts_at', 'ends_at']]);
    }

    /** Di sabato non ci sono fasce di disponibilità: nessuno slot. */
    public function test_no_slots_on_saturday(): void
    {
        $this->loginAsUser();
        $saturday = Carbon::now('Europe/Rome')->next(Carbon::SATURDAY)->toDateString();
        $this->getJson("/api/bookables/{$this->bookable->id}/slots?date={$saturday}")
            ->assertOk()
            ->assertJson([]);
    }

    // ---------- Prenotazioni ----------

    /** Prenotazione valida: viene salvata e parte la notifica di conferma. */
    public function test_user_can_book_a_free_slot(): void
    {
        Notification::fake(); // intercetta le notifiche invece di inviarle davvero
        $user = $this->loginAsUser();
        $slot = $this->firstSlot();

        $this->book($slot['starts_at'])->assertSuccessful();

        $this->assertDatabaseHas('bookings', [
            'bookable_id' => $this->bookable->id,
            'user_id'     => $user->id,
        ]);
        Notification::assertSentTo($user, BookingConfirmed::class);
    }

    /** Lo stesso slot non può essere prenotato due volte da utenti diversi. */
    public function test_cannot_double_book_the_same_slot(): void
    {
        $slot = $this->firstSlot_forNewUser();   // primo utente prenota
        $this->book($slot['starts_at'])->assertSuccessful();

        $this->loginAsUser();                    // secondo utente riprova
        $response = $this->book($slot['starts_at']);

        // 409 (conflitto) o 422 (validazione): in ogni caso deve essere rifiutata
        $this->assertContains($response->status(), [409, 422]);
        $this->assertDatabaseCount('bookings', 1);
    }

    /** Un orario che non cade sulla griglia degli slot (es. +1 minuto) è rifiutato. */
    public function test_cannot_book_off_grid_time(): void
    {
        $this->loginAsUser();
        $slot = $this->firstSlot();
        $offGrid = Carbon::parse($slot['starts_at'])->addMinute()->toIso8601String();

        $this->book($offGrid)->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    /** Un orario fuori dalle fasce di disponibilità (sabato 10:00) è rifiutato. */
    public function test_cannot_book_outside_availability(): void
    {
        $this->loginAsUser();
        $saturday = Carbon::now('Europe/Rome')->next(Carbon::SATURDAY)->setTime(10, 0)->toIso8601String();

        $this->book($saturday)->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    /** Un utente non può annullare la prenotazione di un altro. */
    public function test_user_cannot_cancel_someone_elses_booking(): void
    {
        $slot = $this->firstSlot_forNewUser();
        $this->book($slot['starts_at'])->assertSuccessful();
        $bookingId = Booking::firstOrFail()->id;

        $this->loginAsUser(); // un altro utente
        $response = $this->deleteJson("/api/bookings/{$bookingId}");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId]);
    }

    /** Un utente può annullare la propria prenotazione. */
    public function test_user_can_cancel_own_booking(): void
    {
        $this->loginAsUser();
        $slot = $this->firstSlot();
        $this->book($slot['starts_at'])->assertSuccessful();
        $bookingId = Booking::firstOrFail()->id;

        $this->deleteJson("/api/bookings/{$bookingId}")->assertSuccessful();
    }

    /** Supporto: logga un nuovo utente e restituisce il primo slot libero. */
    private function firstSlot_forNewUser(): array
    {
        $this->loginAsUser();
        return $this->firstSlot();
    }

        // ---------- Elenco sale e parametro ?all=1 ----------

    /** Supporto: crea una sala disattivata (is_active = false) e la restituisce. */
    private function createInactiveBookable(): Bookable
    {
        return Bookable::create([
            'name'         => 'Sala disattivata',
            'description'  => null,
            'slot_minutes' => 30,
            'is_active'    => false,
        ]);
    }

    /** Un utente normale che invia ?all=1 riceve comunque solo le sale attive. */
    public function test_normal_user_with_all_param_gets_only_active_bookables(): void
    {
        $inactive = $this->createInactiveBookable();
        $this->loginAsUser();

        $response = $this->getJson('/api/bookables?all=1')->assertOk();

        // Estrae gli id delle sale restituite
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->bookable->id));   // l'attiva c'è
        $this->assertFalse($ids->contains($inactive->id));        // la disattivata no
    }

    /** L'admin che invia ?all=1 vede anche le sale disattivate. */
    public function test_admin_with_all_param_gets_inactive_bookables_too(): void
    {
        $inactive = $this->createInactiveBookable();
        $this->loginAsAdmin();

        $response = $this->getJson('/api/bookables?all=1')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->bookable->id));
        $this->assertTrue($ids->contains($inactive->id));
    }

        // ---------- Fasce orarie (availabilities) ----------

    /** Supporto: crea una sala NUOVA e senza fasce orarie (a differenza di quelle del seeder). */
    private function createEmptyBookable(int $slotMinutes = 30): Bookable
    {
        return Bookable::create([
            'name'         => 'Sala senza orari',
            'description'  => null,
            'slot_minutes' => $slotMinutes,
            'is_active'    => true,
        ]);
    }

    /** Supporto: invia la richiesta per aggiungere una fascia a una sala. */
    private function addAvailability(Bookable $bookable, int $day, string $start, string $end)
    {
        return $this->postJson("/api/bookables/{$bookable->id}/availabilities", [
            'day_of_week' => $day,
            'start_time'  => $start,
            'end_time'    => $end,
        ]);
    }

    /** L'admin può aggiungere una fascia oraria e viene salvata. */
    public function test_admin_can_add_availability(): void
    {
        $bookable = $this->createEmptyBookable();
        $this->loginAsAdmin();

        $this->addAvailability($bookable, 1, '09:00', '13:00')->assertCreated();

        $this->assertDatabaseHas('availabilities', [
            'bookable_id' => $bookable->id,
            'day_of_week' => 1,
            'start_time'  => '09:00:00',
            'end_time'    => '13:00:00',
        ]);
    }

    /** Un utente normale non può aggiungere fasce: 403 e nulla viene salvato. */
    public function test_normal_user_cannot_add_availability(): void
    {
        $bookable = $this->createEmptyBookable();
        $this->loginAsUser();

        $this->addAvailability($bookable, 1, '09:00', '13:00')->assertForbidden();

        $this->assertDatabaseCount('availabilities', Availability::count()); // nessuna nuova riga
        $this->assertSame(0, $bookable->availabilities()->count());
    }

    /** Una fascia che si sovrappone a una esistente nello stesso giorno è rifiutata. */
    public function test_cannot_add_overlapping_availability(): void
    {
        $bookable = $this->createEmptyBookable();
        $this->loginAsAdmin();

        $this->addAvailability($bookable, 1, '09:00', '13:00')->assertCreated();
        $this->addAvailability($bookable, 1, '12:00', '14:00')->assertStatus(422);

        $this->assertSame(1, $bookable->availabilities()->count());
    }

    /** Fasce che si toccano (una finisce quando l'altra inizia) sono ammesse. */
    public function test_touching_availabilities_are_allowed(): void
    {
        $bookable = $this->createEmptyBookable();
        $this->loginAsAdmin();

        $this->addAvailability($bookable, 1, '09:00', '12:00')->assertCreated();
        $this->addAvailability($bookable, 1, '12:00', '13:00')->assertCreated();

        $this->assertSame(2, $bookable->availabilities()->count());
    }

    /** Lo stesso orario in un giorno diverso non è una sovrapposizione. */
    public function test_same_hours_on_different_day_are_allowed(): void
    {
        $bookable = $this->createEmptyBookable();
        $this->loginAsAdmin();

        $this->addAvailability($bookable, 1, '09:00', '13:00')->assertCreated();
        $this->addAvailability($bookable, 2, '09:00', '13:00')->assertCreated();

        $this->assertSame(2, $bookable->availabilities()->count());
    }

    /** Dati non validi: fine prima dell'inizio e giorno fuori da 1-7 danno 422. */
    public function test_invalid_availability_data_is_rejected(): void
    {
        $bookable = $this->createEmptyBookable();
        $this->loginAsAdmin();

        $this->addAvailability($bookable, 1, '13:00', '09:00')->assertStatus(422); // fine prima dell'inizio
        $this->addAvailability($bookable, 8, '09:00', '13:00')->assertStatus(422); // giorno inesistente

        $this->assertSame(0, $bookable->availabilities()->count());
    }

    /** Dopo aver aggiunto una fascia, per quel giorno compaiono gli slot (10:00-11:00 con slot da 30 min = 2). */
    public function test_slots_appear_after_adding_availability(): void
    {
        $bookable = $this->createEmptyBookable(30);
        $this->loginAsAdmin();

        // Prima: nessuna fascia, quindi nessuno slot di lunedì
        $this->getJson("/api/bookables/{$bookable->id}/slots?date=" . $this->nextMonday())
            ->assertOk()
            ->assertJsonCount(0);

        // Aggiunge una fascia il lunedì (1) dalle 10:00 alle 11:00
        $this->addAvailability($bookable, 1, '10:00', '11:00')->assertCreated();

        // Dopo: due slot da 30 minuti
        $this->getJson("/api/bookables/{$bookable->id}/slots?date=" . $this->nextMonday())
            ->assertOk()
            ->assertJsonCount(2);
    }

    /** L'admin può eliminare una fascia. */
    public function test_admin_can_delete_availability(): void
    {
        $bookable = $this->createEmptyBookable();
        $availability = $bookable->availabilities()->create([
            'day_of_week' => 1,
            'start_time'  => '09:00:00',
            'end_time'    => '13:00:00',
        ]);
        $this->loginAsAdmin();

        $this->deleteJson("/api/availabilities/{$availability->id}")->assertNoContent();

        $this->assertDatabaseMissing('availabilities', ['id' => $availability->id]);
    }

    /** Un utente normale non può eliminare una fascia: 403 e la fascia resta. */
    public function test_normal_user_cannot_delete_availability(): void
    {
        $bookable = $this->createEmptyBookable();
        $availability = $bookable->availabilities()->create([
            'day_of_week' => 1,
            'start_time'  => '09:00:00',
            'end_time'    => '13:00:00',
        ]);
        $this->loginAsUser();

        $this->deleteJson("/api/availabilities/{$availability->id}")->assertForbidden();

        $this->assertDatabaseHas('availabilities', ['id' => $availability->id]);
    }
}