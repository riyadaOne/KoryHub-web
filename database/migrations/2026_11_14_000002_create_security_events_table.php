<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Journal de sécurité d'un compte (BACKEND-SCHEMA §4.1, F-AUT-19, TRD §21) : ajout seul,
 * visible par l'intéressé seul, effacé à la suppression du compte (il contient des adresses IP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('type', 32);
            $table->string('ip', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->dateTime('occurred_at');

            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
