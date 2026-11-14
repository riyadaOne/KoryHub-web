<?php

namespace App\Notifications;

use App\Enums\BackupKind;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Textes et liens communs aux notifications, pour l'e-mail et pour la cloche : seuls le type
 * et ses paramètres sont enregistrés, le texte est traduit à l'affichage (lang/*\/notifications.php).
 */
final class NotificationText
{
    /**
     * Valeurs des messages : paramètres enregistrés, et date d'expiration mise en forme.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    public static function replacements(array $params): array
    {
        $replace = [];
        foreach ($params as $key => $value) {
            if (is_scalar($value)) {
                $replace[$key] = (string) $value;
            }
        }

        // Incident de sauvegarde (W08) : type traduit à l'affichage, dans la langue du lecteur.
        if (is_string($params['backup_kind'] ?? null)) {
            $replace['what'] = __('admin.health.incident', ['kind' => BackupKind::tryFrom($params['backup_kind'])?->label() ?? $params['backup_kind']]);
        }

        $expires = $params['expires_at'] ?? null;
        $replace['expires'] = $expires === null
            ? __('tokens.never')
            : Carbon::parse($expires)->locale(app()->getLocale())->isoFormat('LL');

        return $replace + ['token' => '', 'ip' => '', 'module' => '', 'version' => '', 'reason' => '', 'installed' => '', 'target' => '', 'count' => '', 'what' => '', 'note' => '', 'from' => '', 'to' => ''];
    }

    /**
     * Page vers laquelle mènent l'e-mail et la ligne de la cloche.
     *
     * @param  array<string, mixed>  $params
     */
    public static function url(string $kind, array $params = []): string
    {
        if (in_array($kind, ['version_published', 'version_rejected', 'update_available', 'version_deprecated', 'new_version_watched', 'version_at_risk', 'version_removed'], true)
            && preg_match('#^@([a-z0-9-]+)/([a-z0-9-]+)$#', (string) ($params['module'] ?? ''), $m)
            && is_string($params['version'] ?? null)) {
            return route('modules.version', ['author' => $m[1], 'module' => $m[2], 'number' => $params['version']]);
        }

        // Certification (W06) : le dossier, côté développeur ou côté administration.
        if (is_string($params['certification'] ?? null)) {
            if ($kind === 'admin_certification_requested') {
                return Route::has('admin.certifications.show')
                    ? route('admin.certifications.show', ['certification' => $params['certification']])
                    : route('dashboard');
            }

            return route('certifications.show', ['certification' => $params['certification']]);
        }

        // Signalements (W07) : le signalement côté administration, ou S-48 pour son auteur.
        if (is_string($params['report'] ?? null)) {
            if (str_starts_with($kind, 'admin_')) {
                return Route::has('admin.reports.show')
                    ? route('admin.reports.show', ['report' => $params['report']])
                    : route('dashboard');
            }

            return route('reports.mine');
        }

        return match ($kind) {
            'account_suspended' => route('home'),
            'admin_incident' => Route::has('admin.health') ? route('admin.health') : route('dashboard'),
            'admin_weekly_report' => Route::has('admin.home') ? route('admin.home') : route('dashboard'),
            'password_changed' => route('security.edit'),
            'version_published', 'version_rejected' => route('modules.mine'),
            'update_available', 'version_deprecated' => route('downloads.index'),
            'new_version_watched', 'module_archived' => route('watching.index'),
            default => route('tokens.index'),
        };
    }
}
