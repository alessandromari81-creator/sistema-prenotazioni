<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I materiali (file PDF) allegati a un corso: li vedono solo gli iscritti, gli istruttori e gli amministratori.
 * Si salva il percorso del file nel disco privato, il titolo mostrato e la dimensione; il file non è mai raggiungibile
 * da un indirizzo pubblico, ma solo attraverso l'API (che controlla chi lo chiede).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            // Eliminando il corso spariscono le righe (i file li cancella il servizio)
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');                          // il nome mostrato (non quello del file dell'utente)
            $table->string('file_path');                      // percorso nel disco privato
            $table->unsignedInteger('size_bytes');
            $table->timestamps();

            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
