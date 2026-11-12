<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'immagine di un corso (mostrata nelle schede). Si salva solo il percorso del file nel disco privato dell'app:
 * l'immagine si legge poi da GET /api/courses/{id}/image. È facoltativa: senza, il frontend disegna un'immagine di ripiego.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
