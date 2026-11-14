<?php

namespace App\Services\Admin;

use App\Enums\Locale;
use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Moderation\ModerationRefused;
use Illuminate\Support\Facades\DB;

/**
 * Catégories officielles (F-RCH-11, F-ADM-11, W08.3) : créer avec un identifiant figé et un nom
 * dans les 4 langues, renommer, ordonner, désactiver (plus proposée, les modules la gardent),
 * supprimer seulement si aucun module ne l'utilise (suppression logique). Tout est journalisé.
 */
class CategoryService
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, string>  $names  langue => nom */
    public function create(string $slug, array $names, User $admin): Category
    {
        $this->requireAdmin($admin);
        $slug = trim($slug);
        if (! preg_match(self::SLUG_PATTERN, $slug) || mb_strlen($slug) > 64 || Category::withTrashed()->where('slug', $slug)->exists()) {
            throw new ModerationRefused('admin.categories.errors.slug');
        }
        $names = $this->requireNames($names);

        return DB::transaction(function () use ($slug, $names, $admin) {
            $category = Category::create(['slug' => $slug, 'position' => (int) Category::max('position') + 1, 'is_active' => true]);
            foreach ($names as $locale => $name) {
                $category->translations()->create(['locale' => $locale, 'name' => $name]);
            }
            $this->audit->record('category.created', $category, ['slug' => [null, $slug], ...$this->nameChanges([], $names)], actor: $admin);

            return $category;
        });
    }

    /** @param  array<string, string>  $names */
    public function rename(Category $category, array $names, User $admin): void
    {
        $this->requireAdmin($admin);
        $names = $this->requireNames($names);
        $before = $category->translations()->pluck('name', 'locale')->all();

        DB::transaction(function () use ($category, $names, $before, $admin) {
            foreach ($names as $locale => $name) {
                $category->translations()->updateOrCreate(['locale' => $locale], ['name' => $name]);
            }
            $changes = $this->nameChanges($before, $names);
            if ($changes !== []) {
                $this->audit->record('category.updated', $category, $changes, actor: $admin);
            }
        });
    }

    /** Monte (-1) ou descend (+1) d'un cran en échangeant avec la voisine. */
    public function move(Category $category, int $direction, User $admin): void
    {
        $this->requireAdmin($admin);
        $neighbour = Category::query()
            ->where('position', $direction < 0 ? '<' : '>', $category->position)
            ->orderBy('position', $direction < 0 ? 'desc' : 'asc')
            ->first();
        if ($neighbour === null) {
            return;
        }

        DB::transaction(function () use ($category, $neighbour, $admin) {
            [$a, $b] = [$category->position, $neighbour->position];
            $category->forceFill(['position' => $b])->save();
            $neighbour->forceFill(['position' => $a])->save();
            $this->audit->record('category.moved', $category, ['position' => [$a, $b]], actor: $admin);
        });
    }

    public function setActive(Category $category, bool $active, User $admin): void
    {
        $this->requireAdmin($admin);
        if ($category->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($category, $active, $admin) {
            $category->forceFill(['is_active' => $active])->save();
            $this->audit->record('category.updated', $category, ['is_active' => [! $active, $active]], actor: $admin);
        });
    }

    /** Jamais une catégorie utilisée, même par un module privé ou supprimé. */
    public function delete(Category $category, User $admin): void
    {
        $this->requireAdmin($admin);
        if ($this->modulesCount($category) > 0) {
            throw new ModerationRefused('admin.categories.errors.used');
        }

        DB::transaction(function () use ($category, $admin) {
            $category->translations()->delete();
            $category->delete();
            $this->audit->record('category.deleted', $category, ['deleted_at' => [null, now()]], actor: $admin);
        });
    }

    public function modulesCount(Category $category): int
    {
        return Module::withTrashed()->where('category_id', $category->id)->count();
    }

    /**
     * Les 4 noms sont obligatoires ensemble (TRD §20).
     *
     * @param  array<string, string>  $names
     * @return array<string, string>
     */
    private function requireNames(array $names): array
    {
        $clean = [];
        foreach (Locale::cases() as $locale) {
            $name = trim((string) ($names[$locale->value] ?? ''));
            if ($name === '' || mb_strlen($name) > 64) {
                throw new ModerationRefused('admin.categories.errors.names');
            }
            $clean[$locale->value] = $name;
        }

        return $clean;
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return array<string, array{0: ?string, 1: string}>
     */
    private function nameChanges(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $locale => $name) {
            if (($before[$locale] ?? null) !== $name) {
                $changes['name_'.$locale] = [$before[$locale] ?? null, $name];
            }
        }

        return $changes;
    }

    private function requireAdmin(User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw new ModerationRefused('moderation.errors.not_allowed');
        }
    }
}
