<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I corsi della palestra (yoga, spinning...). Ogni corso si tiene una volta a settimana, in un giorno e a un'ora fissi,
 * ha un numero massimo di posti e può indicare la sala (una risorsa prenotabile) in cui si svolge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('capacity');                 // posti disponibili
            $table->unsignedTinyInteger('day_of_week');               // 1 = lunedì ... 7 = domenica
            $table->time('start_time');                               // ora di inizio (ora di Roma)
            $table->unsignedSmallInteger('duration_minutes');         // durata della lezione
            // La sala è facoltativa: se la risorsa viene eliminata il corso resta, senza sala
            $table->foreignId('bookable_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);              // un corso disattivato non accetta nuove iscrizioni
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
