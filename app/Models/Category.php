<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catégorie officielle (F-RCH-11) : nom traduit dans category_translations.
 *
 * @property string $id
 * @property string $slug
 * @property int $position
 * @property bool $is_active
 */
#[Fillable(['slug', 'position', 'is_active'])]
class Category extends Model
{
    use HasUuids, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    /** @return HasMany<CategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    /** Nom dans la langue en cours, sinon en anglais, sinon le slug. */
    public function name(): string
    {
        $translations = $this->translations->keyBy('locale');

        return $translations->get(app()->getLocale())?->name
            ?? $translations->get('en')?->name
            ?? $this->slug;
    }
}
