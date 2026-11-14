<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Certification (W06, BACKEND-SCHEMA §4.5, F-MOD-01 à F-MOD-08) : dossier sur une version
 * (un seul ouvert par module), résultats des contrôles préliminaires, échanges pendant un
 * complément. Le rapport PDF (certification_reports) arrive au bloc C.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules');
            $table->foreignUuid('version_id')->constrained('module_versions');
            $table->foreignUuid('requested_by')->constrained('users');
            $table->string('status', 24)->default('requested');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users');
            $table->timestamp('requested_at');
            $table->timestamp('audit_started_at')->nullable();
            $table->timestamp('complement_requested_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('users');
            $table->text('decision_reason')->nullable();
            $table->text('architecture_comment')->nullable();
            $table->text('documentation_comment')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users');
            $table->text('revoked_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'requested_at']);
            $table->index(['module_id', 'status']);
            $table->index('version_id');
        });

        Schema::create('certification_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('certification_id')->constrained('certifications');
            $table->foreignUuid('version_id')->constrained('module_versions');
            $table->string('code', 64);
            $table->boolean('passed');
            $table->string('detail')->nullable();
            $table->timestamp('checked_at');
        });

        Schema::create('certification_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('certification_id')->constrained('certifications');
            $table->foreignUuid('author_id')->constrained('users');
            $table->text('body');
            $table->foreignUuid('version_id')->nullable()->constrained('module_versions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_messages');
        Schema::dropIfExists('certification_checks');
        Schema::dropIfExists('certifications');
    }
};
