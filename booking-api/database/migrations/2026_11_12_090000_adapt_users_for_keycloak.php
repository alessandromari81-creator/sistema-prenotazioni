<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara la tabella users all'autenticazione con Keycloak.
 *
 *  - keycloak_id: l'identificativo dell'utente in Keycloak (claim "sub" del token), che lo lega al profilo Laravel;
 *  - password: ora facoltativa, perché le password vivono solo in Keycloak;
 *  - elimina le tabelle del vecchio sistema (registrazione in due passi e token Sanctum), se presenti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('keycloak_id')->nullable()->unique()->after('id');
            $table->string('password')->nullable()->change();
        });

        Schema::dropIfExists('pending_registrations');
        Schema::dropIfExists('personal_access_tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('keycloak_id');
            // Le password non si possono ricostruire: la colonna resta facoltativa anche tornando indietro
        });
    }
};
