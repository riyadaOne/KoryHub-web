<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sauvegardes (W08.5, BACKEND-SCHEMA §4.6, F-ADM-08, F-ADM-10) : chaque sauvegarde quotidienne ou
 * mensuelle et chaque test de restauration, avec son statut ; sans suppression logique. Alimente
 * S-72 et le rapport du lundi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 16);
            $table->string('status', 16)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('location')->nullable();
            $table->text('note')->nullable();

            $table->index(['kind', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
