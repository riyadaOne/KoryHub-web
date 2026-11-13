<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Premier kory push accepté (F-REG-12, F-REG-33) : certification des droits et message de
 * prudence, posés ensemble (BACKEND-SCHEMA §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('rights_certified_at')->nullable()->after('avatar_path');
            $table->dateTime('publication_notice_accepted_at')->nullable()->after('rights_certified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rights_certified_at', 'publication_notice_accepted_at']);
        });
    }
};
