<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Comptes (BACKEND-SCHEMA §4.1, W01) : nom @auteur, rôle, statut, langue, pays, photo.
 * Pas de suppression logique : un compte supprimé garde sa ligne au statut « deleted »,
 * données personnelles effacées (nom, e-mail, mot de passe, pays, photo deviennent null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('handle', 39)->nullable()->after('id');
            $table->string('role', 16)->default('developer')->after('password');
            $table->string('status', 16)->default('active')->after('role');
            $table->dateTime('suspended_at')->nullable()->after('status');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->dateTime('erased_at')->nullable()->after('suspension_reason');
            $table->string('locale', 8)->default('en')->after('erased_at');
            $table->char('country', 2)->nullable()->after('locale');
            $table->string('avatar_path')->nullable()->after('country');

            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        // Comptes créés avant cette migration (poste local) : nom @auteur tiré de l'e-mail.
        DB::table('users')->whereNull('handle')->orderBy('id')->each(function (object $user) {
            $base = Str::of((string) Str::before((string) $user->email, '@'))->lower()->slug()->limit(30, '')->value() ?: 'user';
            $handle = $base;

            for ($i = 2; DB::table('users')->where('handle', $handle)->exists(); $i++) {
                $handle = $base.'-'.$i;
            }

            DB::table('users')->where('id', $user->id)->update(['handle' => $handle]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('handle', 39)->nullable(false)->unique()->change();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['handle']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'handle', 'role', 'status', 'suspended_at', 'suspension_reason',
                'erased_at', 'locale', 'country', 'avatar_path',
            ]);
        });
    }
};
