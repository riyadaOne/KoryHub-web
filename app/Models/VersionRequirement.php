<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Prérequis d'une version (F-REG-27) : code extérieur au kory, jamais inclus ; informatif,
 * KoryHub n'installe rien.
 *
 * @property string $id
 * @property string $version_id
 * @property string|null $ecosystem
 * @property string $name
 * @property string|null $version_constraint
 */
#[Fillable(['version_id', 'ecosystem', 'name', 'version_constraint'])]
class VersionRequirement extends Model
{
    use HasUuids;

    public $timestamps = false;
}
