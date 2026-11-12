<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Bookable;
use App\Models\Course;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedUsers();
        $this->seedGym();

        // Crea 3 sale prenotabili di prova usando la factory
        $bookables = Bookable::factory()->count(3)->create();

        // Per ogni sala creiamo gli orari di disponibilità
        foreach ($bookables as $bookable) {
            // Giorni da 1 a 5: da lunedì a venerdì (standard ISO)
            foreach ([1, 2, 3, 4, 5] as $day) {
                // createMany inserisce più righe in una volta sola; partendo dalla
                // relazione, Laravel imposta in automatico il bookable_id corretto
                $bookable->availabilities()->createMany([
                    // Fascia della mattina
                    ['day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '13:00'],
                    // Fascia del pomeriggio (la pausa pranzo resta in mezzo)
                    ['day_of_week' => $day, 'start_time' => '15:00', 'end_time' => '19:00'],
                ]);
            }
        }
    }

    /**
     * Gli identificativi che Keycloak assegna agli utenti di prova (vedi keycloak/palestra-realm.json).
     * Sono fissi, così il profilo Laravel è già collegato all'identità giusta prima del primo accesso.
     *
     * @var array<string, array{id: string, name: string, role: string}>
     */
    public const DEMO_USERS = [
        'admin@example.com' => ['id' => '00000000-0000-4000-8000-000000000001', 'name' => 'Admin Palestra', 'role' => 'admin'],
        'luca.verdi@example.com' => ['id' => '00000000-0000-4000-8000-000000000002', 'name' => 'Luca Verdi', 'role' => 'user'],
        'sara.neri@example.com' => ['id' => '00000000-0000-4000-8000-000000000003', 'name' => 'Sara Neri', 'role' => 'user'],
        'mario.rossi@example.com' => ['id' => '00000000-0000-4000-8000-000000000004', 'name' => 'Mario Rossi', 'role' => 'user'],
        'anna.bianchi@example.com' => ['id' => '00000000-0000-4000-8000-000000000005', 'name' => 'Anna Bianchi', 'role' => 'user'],
    ];

    /**
     * Crea i profili Laravel degli utenti di prova, collegati a quelli del realm di Keycloak.
     * Non hanno password: il login lo fa Keycloak.
     */
    private function seedUsers(): void
    {
        foreach (self::DEMO_USERS as $email => $data) {
            User::factory()->create([
                'keycloak_id' => $data['id'],
                'name' => $data['name'],
                'email' => $email,
                'role' => $data['role'],
            ]);
        }
    }

    /**
     * Dati di prova della palestra: piani di abbonamento, corsi, istruttori e un abbonamento.
     *  - Luca Verdi insegna Yoga e Pilates, Sara Neri insegna Spinning e CrossFit;
     *  - Mario Rossi ha un abbonamento mensile attivo (può iscriversi); Anna Bianchi non ne ha ancora uno,
     *    così si vede cosa succede senza abbonamento.
     */
    private function seedGym(): void
    {
        $plans = [
            'mensile' => SubscriptionPlan::create(['name' => 'Mensile', 'description' => 'Un mese, fino a 2 corsi.', 'duration_days' => 30, 'max_courses' => 2, 'is_active' => true]),
            'trimestrale' => SubscriptionPlan::create(['name' => 'Trimestrale', 'description' => 'Tre mesi, fino a 4 corsi.', 'duration_days' => 90, 'max_courses' => 4, 'is_active' => true]),
            'annuale' => SubscriptionPlan::create(['name' => 'Annuale', 'description' => 'Un anno, fino a 6 corsi.', 'duration_days' => 365, 'max_courses' => 6, 'is_active' => true]),
        ];

        // [nome, descrizione, posti, giorno (1 = lunedì), ora, durata in minuti, istruttore]
        $courses = [
            ['Yoga', 'Posture e respirazione per ritrovare equilibrio e flessibilità.', 12, 1, '18:30', 60, 'luca.verdi@example.com'],
            ['Pilates', 'Esercizi a corpo libero per rinforzare il centro del corpo.', 10, 3, '19:00', 55, 'luca.verdi@example.com'],
            ['Spinning', 'Allenamento in sella a ritmo di musica, per tutti i livelli.', 20, 2, '18:00', 45, 'sara.neri@example.com'],
            ['CrossFit', 'Allenamento funzionale ad alta intensità.', 8, 4, '19:30', 60, 'sara.neri@example.com'],
        ];

        foreach ($courses as [$name, $description, $capacity, $day, $time, $minutes, $instructorEmail]) {
            $course = Course::create([
                'name' => $name,
                'description' => $description,
                'capacity' => $capacity,
                'day_of_week' => $day,
                'start_time' => $time,
                'duration_minutes' => $minutes,
                'is_active' => true,
            ]);

            $course->instructors()->attach(User::where('email', $instructorEmail)->value('id'));
        }

        app(SubscriptionService::class)->assign(User::where('email', 'mario.rossi@example.com')->firstOrFail(), $plans['mensile']);
    }
}
