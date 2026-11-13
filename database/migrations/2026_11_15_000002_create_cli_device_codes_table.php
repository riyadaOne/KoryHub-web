<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Connexion du CLI par code (BACKEND-SCHEMA §4.1, TRD §7.3) : le code de l'appareil n'est
 * gardé que haché ; le code affiché (ABCD-EFGH) est valable 15 minutes. Table technique,
 * sans suppression logique : lignes effacées 7 jours après expiration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cli_device_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('device_code_hash', 64)->unique();
            $table->char('user_code', 9)->index();
            $table->string('status', 16)->default('pending');
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->unsignedSmallInteger('token_lifetime_days')->nullable();
            $table->foreignUuid('token_id')->nullable()->constrained('personal_access_tokens');
            $table->string('client_info')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('last_polled_at')->nullable();
            $table->dateTime('expires_at')->index();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cli_device_codes');
    }
};
