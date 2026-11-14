<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Rapport de certification PDF (W06.3, F-MOD-06, T-35, BACKEND-SCHEMA §4.5) : un par langue,
 * généré une fois, jamais réécrit ; empreinte pour la vérification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('certification_id')->constrained('certifications');
            $table->string('locale', 8);
            $table->string('path');
            $table->char('sha256', 64);
            $table->timestamp('generated_at');

            $table->unique(['certification_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_reports');
    }
};
