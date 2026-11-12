<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le iscrizioni degli utenti ai corsi. Annullare un'iscrizione non cancella la riga: cambia lo stato in "cancelled"
 * (si tiene lo storico) e iscriversi di nuovo la riattiva. Per ogni coppia corso-utente esiste una sola riga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // L'abbonamento usato per iscriversi (solo informativo: se sparisce, l'iscrizione resta)
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');      // 'active' oppure 'cancelled'
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
