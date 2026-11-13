<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Journal d'audit (BACKEND-SCHEMA §4.6, TRD §21, T-29) : ajout seul.
 * Des déclencheurs MySQL refusent toute modification et toute suppression ;
 * l'utilisateur MySQL de l'application n'a pas le droit de les supprimer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->dateTime('occurred_at', precision: 6)->index();
            $table->string('actor_type', 16);
            $table->foreignUuid('actor_id')->nullable()->index()->constrained('users');
            $table->string('action', 64);
            $table->string('subject_type', 64);
            $table->char('subject_id', 36);
            $table->text('reason')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('audit_event_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('audit_event_id')->constrained('audit_events');
            $table->string('field', 64);
            $table->text('before')->nullable();
            $table->text('after')->nullable();
        });

        foreach (['audit_events', 'audit_event_changes'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = $table.'_no_'.strtolower($operation);

                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} is append-only'
                    SQL);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_event_changes');
        Schema::dropIfExists('audit_events');
    }
};
