<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I piani di abbonamento che l'amministratore può assegnare agli utenti (per esempio "Mensile", "Annuale").
 * Un piano stabilisce quanto dura l'abbonamento e a quanti corsi ci si può iscrivere contemporaneamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_days');   // durata in giorni
            $table->unsignedSmallInteger('max_courses');     // corsi attivi contemporaneamente
            $table->boolean('is_active')->default(true);     // un piano disattivato non si assegna più
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
