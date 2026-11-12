<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chi insegna a quale corso (tabella "pivot" tra corsi e utenti). Un corso può avere più istruttori e un utente
 * può insegnare in più corsi. La stessa coppia non può comparire due volte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_instructor', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['course_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_instructor');
    }
};
