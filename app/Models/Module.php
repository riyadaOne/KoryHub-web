<?php

namespace App\Models;

use App\Enums\ModuleStatus;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Module, ou kory (BACKEND-SCHEMA §4.2) : @auteur/nom, jamais réattribué.
 *
 * @property string $id
 * @property string $author_id
 * @property string $name
 * @property Visibility $visibility
 * @property ModuleStatus $status
 * @property string|null $description
 * @property string|null $category_id
 * @property string|null $language
 * @property Carbon|null $archived_at
 * @property string|null $latest_version_id
 * @property-read User $author
 */
#[Fillable(['author_id', 'name', 'visibility', 'status', 'description', 'category_id', 'language', 'archived_at', 'latest_version_id'])]
class Module extends Model
{
    use HasUuids, SoftDeletes;

    /** @var array<string, string> */
    protected $attributes = ['visibility' => 'private', 'status' => 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'status' => ModuleStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return HasMany<ModuleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ModuleVersion::class);
    }

    /** @return BelongsTo<ModuleVersion, $this> */
    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(ModuleVersion::class, 'latest_version_id');
    }

    /** Nom complet « @auteur/nom ». */
    public function fullName(): string
    {
        return '@'.$this->author->handle.'/'.$this->name;
    }

    /** Modules qu'un compte (ou un visiteur) peut voir : publics, ou les siens. */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('visibility', Visibility::Public)
            ->when($user, fn (Builder $q) => $q->orWhere('author_id', $user->getKey())));
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->visibility === Visibility::Public || ($user !== null && $user->is($this->author));
    }
}
