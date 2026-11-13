<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Communauté (W05 bloc A, BACKEND-SCHEMA §4.3, F-RCH-07 à F-RCH-09, F-RCH-17) : avis (une note
 * par développeur et par module, suppression logique), favoris, abonnements à 3 niveaux ; note
 * vérifiée moyenne et masquage des avis sur le module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules');
            $table->foreignUuid('user_id')->constrained('users');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['module_id', 'user_id']);
        });

        Schema::create('stars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('module_id')->constrained('modules');
            $table->timestamps();

            $table->unique(['user_id', 'module_id']);
            $table->index('module_id');
        });

        Schema::create('watches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('module_id')->constrained('modules');
            $table->string('level', 16);
            $table->timestamps();

            $table->unique(['user_id', 'module_id']);
            $table->index(['module_id', 'level']);
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('reviews_hidden')->default(false)->after('language');
            $table->decimal('rating_avg', 3, 2)->nullable()->after('download_count');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['reviews_hidden', 'rating_avg', 'rating_count']);
        });
        Schema::dropIfExists('watches');
        Schema::dropIfExists('stars');
        Schema::dropIfExists('reviews');
    }
};
