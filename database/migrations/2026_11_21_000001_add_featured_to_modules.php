<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Mise en avant (W05 bloc C, F-RCH-12, BACKEND-SCHEMA §4.2) : décision humaine de l'admin
 * (case cochée en W08) ; S-01 n'affiche la section que si un module est coché.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('reviews_hidden');
            $table->timestamp('featured_at')->nullable()->after('is_featured');
            $table->index(['is_featured', 'featured_at']);
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'featured_at']);
            $table->dropColumn(['is_featured', 'featured_at']);
        });
    }
};
