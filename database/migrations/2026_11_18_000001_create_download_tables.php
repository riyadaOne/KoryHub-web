<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Téléchargements (BACKEND-SCHEMA §4.3, §8, F-RCH-14, F-REG-13, F-REG-17) : compteurs publics
 * anonymes, premier téléchargement (suppression sous 72 h, passage en Privé), lien privé entre un
 * compte et les versions qu'il a téléchargées, statistiques par jour pour le score (W05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->unsignedBigInteger('download_count')->default(0)->after('latest_version_id');
            $table->timestamp('third_party_downloaded_at')->nullable()->after('download_count');
        });

        Schema::table('module_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('download_count')->default(0)->after('deprecation_message');
            $table->timestamp('first_downloaded_at')->nullable()->after('download_count');
        });

        Schema::create('module_downloads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('module_id')->constrained('modules');
            $table->foreignUuid('version_id')->constrained('module_versions');
            $table->timestamp('first_at');
            $table->timestamp('last_at');
            $table->unsignedInteger('count')->default(1);

            $table->unique(['user_id', 'version_id']);
            $table->index(['user_id', 'last_at']);
        });

        Schema::create('download_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules');
            $table->foreignUuid('version_id')->constrained('module_versions');
            $table->date('day');
            $table->unsignedInteger('count')->default(0);

            $table->unique(['version_id', 'day']);
            $table->index(['module_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_stats');
        Schema::dropIfExists('module_downloads');

        Schema::table('module_versions', function (Blueprint $table) {
            $table->dropColumn(['download_count', 'first_downloaded_at']);
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['download_count', 'third_party_downloaded_at']);
        });
    }
};
