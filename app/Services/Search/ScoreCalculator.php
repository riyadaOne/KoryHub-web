<?php

namespace App\Services\Search;

use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\DownloadStat;
use App\Models\Module;
use App\Models\ModuleDownload;
use App\Models\Review;

/**
 * Score de classement (TRD §14, F-RCH-04, T-34) ; ne sert qu'à trier.
 *
 *   score = a × log(1 + téléchargements sur 90 jours)
 *         + b × note vérifiée (moyenne bayésienne ramenée entre 0 et 1)
 *         + c × récence de la dernière version (moitié tous les 90 jours)
 *         + d × certification (0 jusqu'à W06)
 *
 * Avis masqués par l'auteur : note et certification à 0 (F-RCH-08). Seules les notes vérifiées
 * comptent (F-RCH-17). Poids dans config/koryhub.php (koryhub.score).
 */
class ScoreCalculator
{
    /** Calcule et enregistre le score de chaque module public ; renvoie le nombre de modules. */
    public function refreshAll(): int
    {
        $count = 0;
        Module::query()->where('visibility', Visibility::Public)->with('latestVersion')->chunkById(200, function ($modules) use (&$count) {
            foreach ($modules as $module) {
                $module->forceFill(['score' => $this->score($module), 'score_computed_at' => now()])->saveQuietly();
                $count++;
            }
        });

        return $count;
    }

    public function score(Module $module): float
    {
        $weights = config('koryhub.score');

        $downloads = (int) DownloadStat::query()
            ->where('module_id', $module->id)
            ->where('day', '>=', now()->subDays(90)->toDateString())
            ->sum('count');

        $rating = $module->reviews_hidden ? 0.0 : $this->rating($module, $weights['prior_count'], $weights['prior_mean']);
        $certification = 0.0; // W06 : 1 si la dernière version est certifiée

        return round(
            $weights['downloads'] * log(1 + $downloads)
            + $weights['rating'] * $rating
            + $weights['recency'] * $this->recency($module, $weights['half_life_days'])
            + $weights['certification'] * ($module->reviews_hidden ? 0.0 : $certification),
            6,
        );
    }

    /** Moyenne bayésienne des notes vérifiées, entre 0 et 1 : une seule note ne domine pas. */
    private function rating(Module $module, int $priorCount, float $priorMean): float
    {
        $verified = Review::query()
            ->where('module_id', $module->id)
            ->whereIn('user_id', ModuleDownload::query()->select('user_id')->where('module_id', $module->id))
            ->selectRaw('COALESCE(SUM(rating), 0) as total, COUNT(*) as n')
            ->first();

        $mean = ($priorCount * $priorMean + (float) $verified->total) / ($priorCount + (int) $verified->n);

        return $mean / 5;
    }

    /** Récence de la dernière version publiée : 1 aujourd'hui, 0,5 après une demi-vie. */
    private function recency(Module $module, int $halfLifeDays): float
    {
        $published = $module->latestVersion?->published_at
            ?? $module->versions()->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated])->max('published_at');
        if ($published === null) {
            return 0.0;
        }

        $days = max(0, now()->diffInDays($published, true));

        return 0.5 ** ($days / $halfLifeDays);
    }
}
