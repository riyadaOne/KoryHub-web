<?php

namespace App\Services\Community;

use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\Module;
use App\Models\ModuleDownload;
use App\Models\Review;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Modules\ModuleRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Avis (F-RCH-07, F-RCH-17, W05 bloc A) : une note de 1 à 5 et un commentaire par développeur et
 * par module, jamais sur son propre module ; modification historisée dans l'audit (sans le texte
 * du commentaire) ; suppression logique, restaurée s'il note à nouveau. Tient à jour la note
 * vérifiée moyenne du module.
 */
class ReviewService
{
    public const MAX_COMMENT = 2000;

    public function __construct(private AuditLogger $audit) {}

    /** Le compte peut-il noter ce module : public, au moins une version publiée, pas le sien. */
    public function canReview(Module $module, ?User $user): bool
    {
        return $user !== null
            && $user->id !== $module->author_id
            && $module->visibility === Visibility::Public
            && $module->versions()->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated])->exists();
    }

    /** Crée, modifie ou restaure l'avis du compte. */
    public function save(Module $module, User $user, int $rating, ?string $comment): Review
    {
        if ($user->id === $module->author_id) {
            throw new ModuleRuleViolation('community.errors.own_module');
        }
        if (! $this->canReview($module, $user) || $rating < 1 || $rating > 5) {
            throw new ModuleRuleViolation('community.errors.cannot_review');
        }

        $comment = $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, self::MAX_COMMENT);

        return DB::transaction(function () use ($module, $user, $rating, $comment) {
            $review = Review::withTrashed()->where('module_id', $module->id)->where('user_id', $user->id)->first();

            if ($review === null) {
                $review = Review::create(['module_id' => $module->id, 'user_id' => $user->id, 'rating' => $rating, 'comment' => $comment]);
                $this->audit->record('review.created', $review, ['rating' => [null, $rating], 'comment' => [null, $comment]], actor: $user);
            } else {
                $restored = $review->trashed();
                $changes = array_filter([
                    'rating' => $review->rating !== $rating ? [$review->rating, $rating] : null,
                    'comment' => $review->comment !== $comment ? [$review->comment, $comment] : null,
                    'deleted_at' => $restored ? [$review->deleted_at, null] : null,
                ]);
                if ($restored) {
                    $review->restore();
                }
                $review->forceFill(['rating' => $rating, 'comment' => $comment])->save();
                if ($changes !== []) {
                    $this->audit->record($restored ? 'review.restored' : 'review.updated', $review, $changes, actor: $user);
                }
            }

            $this->refreshRating($module);

            return $review;
        });
    }

    /** Supprime l'avis du compte (suppression logique, trace gardée dans l'audit). */
    public function delete(Review $review, User $user): void
    {
        if ($review->user_id !== $user->id) {
            throw new ModuleRuleViolation('community.errors.cannot_review');
        }

        DB::transaction(function () use ($review, $user) {
            $review->delete();
            $this->audit->record('review.deleted', $review, ['deleted_at' => [null, now()]], actor: $user);
            $this->refreshRating($review->module);
        });
    }

    /** Note vérifiée moyenne et nombre de notes vérifiées (F-RCH-17) ; les autres ne comptent pas. */
    public function refreshRating(Module $module): void
    {
        $verified = Review::query()
            ->where('module_id', $module->id)
            ->whereIn('user_id', ModuleDownload::query()->select('user_id')->where('module_id', $module->id))
            ->selectRaw('AVG(rating) as average, COUNT(*) as total')
            ->first();

        $module->forceFill([
            'rating_avg' => $verified->total > 0 ? round((float) $verified->average, 2) : null,
            'rating_count' => (int) $verified->total,
        ])->save();
    }
}
