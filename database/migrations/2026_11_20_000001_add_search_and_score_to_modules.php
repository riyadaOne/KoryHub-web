<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Recherche et score (W05 bloc B, F-RCH-01 à F-RCH-05, TRD §14) : score de classement calculé
 * chaque heure ; texte cherchable dénormalisé (README de la dernière version, étiquettes,
 * catégorie) pour que la recherche plein texte ne lise qu'une table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->decimal('score', 12, 6)->default(0)->after('rating_count');
            $table->timestamp('score_computed_at')->nullable()->after('score');
            $table->mediumText('search_text')->nullable()->after('description');

            $table->fullText(['description', 'search_text'], 'modules_search_fulltext');
            $table->index(['visibility', 'score']);
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropFullText('modules_search_fulltext');
            $table->dropIndex(['visibility', 'score']);
            $table->dropColumn(['score', 'score_computed_at', 'search_text']);
        });
    }
};
