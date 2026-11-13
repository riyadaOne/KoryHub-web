<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Journal de sécurité : jeton concerné par l'événement (connexion du CLI, jeton créé
 * ou révoqué ; BACKEND-SCHEMA §4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->foreignUuid('token_id')->nullable()->after('user_agent')->constrained('personal_access_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('token_id');
        });
    }
};
