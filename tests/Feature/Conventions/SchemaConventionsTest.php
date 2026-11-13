<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Conventions de schéma de KoryHub (TRD §23, T-03).
 */

/** Tables du framework et des paquets : leur schéma est imposé, elles échappent aux conventions. */
const FRAMEWORK_TABLES = [
    'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    'sessions', 'password_reset_tokens', 'passkeys',
];

/**
 * Tables métier sans suppression logique, avec la raison (BACKEND-SCHEMA §4).
 * users : un compte supprimé garde sa ligne au statut « deleted » (RGPD, nom réservé).
 */
const TABLES_WITHOUT_SOFT_DELETES = [
    'users',
    // Journal d'audit : ajout seul, rien ne se supprime (TRD §21).
    'audit_events',
    'audit_event_changes',
    // Journal de sécurité : ajout seul, effacé avec le compte (TRD §21).
    'security_events',
];

/** @return list<string> */
function businessTables(): array
{
    return collect(Schema::getTables(DB::connection()->getDatabaseName()))
        ->pluck('name')
        ->reject(fn (string $table) => in_array($table, FRAMEWORK_TABLES, true))
        ->values()
        ->all();
}

test('chaque table métier a une clé primaire UUID (char 36)', function () {
    foreach (businessTables() as $table) {
        $id = collect(Schema::getColumns($table))->firstWhere('name', 'id');

        expect($id)->not->toBeNull("La table {$table} n'a pas de colonne id.")
            ->and($id['type'])->toBe('char(36)', "La clé de {$table} n'est pas un UUID.");
    }
});

test('chaque table métier a une suppression logique, sauf exception documentée', function () {
    expect(businessTables())->not->toBeEmpty();

    foreach (businessTables() as $table) {
        if (in_array($table, TABLES_WITHOUT_SOFT_DELETES, true)) {
            continue;
        }

        expect(Schema::hasColumn($table, 'deleted_at'))
            ->toBeTrue("La table {$table} n'a pas de colonne deleted_at.");
    }
});

test('les identifiants générés sont des UUID version 7', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and($user->id[14])->toBe('7');
});

test('chaque model déclare ses champs remplissables', function () {
    $models = collect(File::allFiles(app_path('Models')))
        ->map(fn ($file) => 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
        ->filter(fn (string $class) => is_subclass_of($class, Model::class));

    expect($models)->not->toBeEmpty();

    foreach ($models as $class) {
        $model = new $class;

        expect($model->getFillable())->not->toBeEmpty("{$class} ne déclare pas \$fillable.")
            ->and($model->getGuarded())->not->toBe([], "{$class} utilise \$guarded = [].");
    }
});
