<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Modules (kory), étiquettes, versions et fichiers (BACKEND-SCHEMA §4.2, §8). Les colonnes du
 * téléchargement (W04), des avis et du score (W05) et du retrait (W07) arrivent avec leurs
 * modules. Noms jamais réattribués : l'unicité compte aussi les lignes supprimées (UQ+).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 64)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users');
            $table->string('name', 64);
            $table->string('visibility', 16)->default('private');
            $table->string('status', 16)->default('active');
            $table->text('description')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('categories');
            $table->string('language', 64)->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->uuid('latest_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['author_id', 'name']);
            $table->index(['visibility', 'status']);
            $table->fullText(['name', 'description']);
        });

        Schema::create('module_tag', function (Blueprint $table) {
            $table->foreignUuid('module_id')->constrained('modules');
            $table->foreignUuid('tag_id')->constrained('tags');
            $table->primary(['module_id', 'tag_id']);
        });

        Schema::create('module_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules');
            $table->string('version', 64);
            $table->unsignedInteger('major');
            $table->unsignedInteger('minor');
            $table->unsignedInteger('patch');
            $table->string('prerelease', 64)->nullable();
            $table->string('status', 16)->default('submitted');
            // Numéro réservé, sauf pour une version rejetée : elle peut être renvoyée sous le même numéro.
            $table->string('version_key', 64)->nullable()->storedAs("CASE WHEN status = 'rejected' THEN NULL ELSE version END");
            $table->text('description')->nullable();
            $table->string('license', 32);
            $table->string('language', 64)->nullable();
            $table->mediumText('readme')->nullable();
            $table->string('archive_path')->nullable();
            $table->char('archive_sha256', 64);
            $table->unsignedInteger('archive_size');
            $table->unsignedInteger('files_count')->default(0);
            $table->foreignUuid('published_by')->constrained('users');
            $table->dateTime('submitted_at');
            $table->dateTime('published_at')->nullable();
            $table->string('rejection_code', 64)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('deprecated_at')->nullable();
            $table->string('deprecation_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['module_id', 'version_key']);
            $table->index(['module_id', 'major', 'minor', 'patch']);
            $table->fullText('readme');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->foreign('latest_version_id')->references('id')->on('module_versions');
        });

        Schema::create('version_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('module_versions');
            $table->string('path', 1024);
            $table->char('path_hash', 64);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->boolean('is_image')->default(false);

            $table->unique(['version_id', 'path_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_files');
        Schema::table('modules', fn (Blueprint $table) => $table->dropForeign(['latest_version_id']));
        Schema::dropIfExists('module_versions');
        Schema::dropIfExists('module_tag');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('tags');
    }
};
