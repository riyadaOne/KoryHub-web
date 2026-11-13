<?php

namespace App\Notifications;

use Illuminate\Support\Carbon;

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

        $expires = $params['expires_at'] ?? null;
        $replace['expires'] = $expires === null
            ? __('tokens.never')
            : Carbon::parse($expires)->locale(app()->getLocale())->isoFormat('LL');

        return $replace + ['token' => '', 'ip' => '', 'module' => '', 'version' => '', 'reason' => ''];
    }

    /** Page vers laquelle mènent l'e-mail et la ligne de la cloche. */
    public static function url(string $kind): string
    {
        return match ($kind) {
            'password_changed' => route('security.edit'),
            'version_published', 'version_rejected' => route('dashboard'),
            default => route('tokens.index'),
        };
    }
}
