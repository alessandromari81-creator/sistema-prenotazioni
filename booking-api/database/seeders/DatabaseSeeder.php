<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Bookable;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        // Crea 3 risorse prenotabili di prova usando la factory
        $bookables = Bookable::factory()->count(3)->create();

        // Per ogni risorsa creiamo gli orari di disponibilità
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
}
