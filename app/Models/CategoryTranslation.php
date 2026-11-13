<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nom d'une catégorie dans une langue.
 *
 * @property string $id
 * @property string $category_id
 * @property string $locale
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['category_id', 'locale', 'name', 'description'])]
class CategoryTranslation extends Model
{
    use HasUuids, SoftDeletes;
}
