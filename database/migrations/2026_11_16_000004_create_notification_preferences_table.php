<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Préférences de notification (S-43, BACKEND-SCHEMA §4.1) : une ligne par type réglé ;
 * sans ligne, les deux canaux sont actifs. Les types de sécurité ne sont pas réglables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('type', 64);
            $table->boolean('mail')->default(true);
            $table->boolean('database')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
