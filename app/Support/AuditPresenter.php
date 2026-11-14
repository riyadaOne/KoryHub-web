<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\AuditEventChange;
use App\Models\Category;
use App\Models\Certification;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\PersonalAccessToken;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Lang;

/**
 * Présentation du journal d'audit (S-47, S-71, F-ADM-04 à F-ADM-07) : libellé traduit d'une action
 * (l'action brute si elle n'a pas de libellé), libellé de l'objet sans donnée personnelle
 * (@identifiant pour un compte), valeurs avant et après (« modifié » pour un champ masqué).
 */
final class AuditPresenter
{
    /** Types d'objet proposés dans les filtres de S-71 : classe => clé du libellé. */
    public const SUBJECTS = [
        Module::class => 'module',
        ModuleVersion::class => 'version',
        User::class => 'user',
        Review::class => 'review',
        Report::class => 'report',
        Certification::class => 'certification',
        PersonalAccessToken::class => 'token',
        Category::class => 'category',
    ];

    public static function action(string $action): string
    {
        $key = 'audit.actions.'.str_replace('.', '_', $action);

        return Lang::has($key) ? __($key) : $action;
    }

    public static function subjectType(string $class): string
    {
        $key = 'audit.subjects.'.(self::SUBJECTS[$class] ?? 'other');

        return __($key);
    }

    /** Nom court de l'objet, même supprimé ; null s'il n'existe plus. */
    public static function subject(AuditEvent $event): ?string
    {
        $class = $event->subject_type;
        if (! class_exists($class)) {
            return null;
        }
        $query = $class::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }
        $subject = $query->find($event->subject_id);

        return match (true) {
            $subject instanceof Module => $subject->fullName(),
            $subject instanceof ModuleVersion => $subject->module()->withTrashed()->first()?->fullName().' '.$subject->version,
            $subject instanceof User => '@'.$subject->handle,
            $subject instanceof Review, $subject instanceof Certification => $subject->module()->withTrashed()->first()?->fullName(),
            $subject instanceof Report => $subject->target_type->label(),
            $subject instanceof PersonalAccessToken => $subject->name,
            $subject instanceof Category => $subject->slug,
            default => null,
        };
    }

    /** Champ masqué (donnée personnelle) : noté comme modifié, sans valeur. */
    public static function isRedacted(AuditEventChange $change): bool
    {
        return in_array($change->field, config('koryhub.audit.redacted_fields', []), true);
    }
}
