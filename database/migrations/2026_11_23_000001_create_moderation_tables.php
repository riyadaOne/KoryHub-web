<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Modération (W07, BACKEND-SCHEMA §4.5, F-MOD-09 à F-MOD-13, F-RCH-13) : signalements ;
 * avertissement de sécurité et retrait légal d'une version ; avis masqué par la modération.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users');
            $table->string('target_type', 16);
            $table->char('target_id', 36);
            $table->string('reason', 32);
            $table->text('details')->nullable();
            $table->string('status', 24)->default('open');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users');
            $table->timestamp('examined_at')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users');
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['target_type', 'target_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('reporter_id');
        });

        Schema::table('module_versions', function (Blueprint $table) {
            $table->timestamp('at_risk_at')->nullable()->after('deprecation_message');
            $table->text('at_risk_message')->nullable()->after('at_risk_at');
            $table->foreignUuid('at_risk_report_id')->nullable()->after('at_risk_message')->constrained('reports');
            $table->timestamp('removed_at')->nullable()->after('at_risk_report_id');
            $table->foreignUuid('removed_by')->nullable()->after('removed_at')->constrained('users');
            $table->text('removed_reason')->nullable()->after('removed_by');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('comment');
            $table->foreignUuid('hidden_by')->nullable()->after('hidden_at')->constrained('users');
            $table->foreignUuid('hidden_report_id')->nullable()->after('hidden_by')->constrained('reports');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_report_id');
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn('hidden_at');
        });

        Schema::table('module_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('removed_by');
            $table->dropConstrainedForeignId('at_risk_report_id');
            $table->dropColumn(['at_risk_at', 'at_risk_message', 'removed_at', 'removed_reason']);
        });

        Schema::dropIfExists('reports');
    }
};
