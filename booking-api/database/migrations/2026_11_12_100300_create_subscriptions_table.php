<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gli abbonamenti degli utenti: un piano assegnato a una persona per un periodo.
 * È "attivo" se non è stato annullato e oggi è compreso tra l'inizio e la fine (estremi inclusi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Un piano già assegnato non si elimina (restrict): si disattiva, così lo storico resta leggibile
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');                          // ultimo giorno di validità, incluso
            $table->timestamp('cancelled_at')->nullable();    // valorizzato se l'admin lo annulla
            $table->timestamps();

            $table->index(['user_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
