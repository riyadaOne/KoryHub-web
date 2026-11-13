<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Architecture et prérequis d'une version (BACKEND-SCHEMA §4.2, F-REG-22, F-REG-27, TRD §22) :
 * copie de kory.json à la publication, jamais modifiée ; sans suppression logique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('module_versions');
            $table->string('key', 64);
            $table->string('kind', 16);
            $table->foreignUuid('file_id')->constrained('version_files');
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['version_id', 'key']);
        });

        Schema::create('version_component_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('module_versions');
            $table->foreignUuid('from_component_id')->constrained('version_components');
            $table->foreignUuid('to_component_id')->constrained('version_components');
            $table->string('kind', 16);

            $table->unique(['from_component_id', 'to_component_id', 'kind'], 'component_links_unique');
        });

        Schema::create('version_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('module_versions');
            $table->string('ecosystem', 64)->nullable();
            $table->string('name');
            $table->string('version_constraint', 64)->nullable();

            $table->unique(['version_id', 'ecosystem', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_requirements');
        Schema::dropIfExists('version_component_links');
        Schema::dropIfExists('version_components');
    }
};
