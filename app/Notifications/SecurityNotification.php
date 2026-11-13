<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Notification de sécurité (TRD §19, T-32), par e-mail et dans la cloche, dans la langue
 * du compte. Jamais désactivable. Types : cli_login, token_created, token_revoked,
 * token_expiring, password_changed. Seuls le type et ses paramètres sont enregistrés :
 * la cloche traduit à l'affichage (lang/*\/notifications.php).
 */
class SecurityNotification extends Notification
{
    /** @param  array{token?: string|null, ip?: string|null, expires_at?: string|null}  $params */
    public function __construct(public string $kind, public array $params = []) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function databaseType(User $notifiable): string
    {
        return $this->kind;
    }

    /** @return array{kind: string, params: array<string, string|null>} */
    public function toArray(User $notifiable): array
    {
        return ['kind' => $this->kind, 'params' => $this->params];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $replace = self::replacements($this->params);
        $message = (new MailMessage)
            ->subject(__("notifications.{$this->kind}.title", $replace))
            ->line(__("notifications.{$this->kind}.mail", $replace));

        if (! empty($this->params['token'])) {
            $message->line(__('notifications.detail_token', $replace));
        }
        if (! empty($this->params['ip'])) {
            $message->line(__('notifications.detail_ip', $replace));
        }
        if (array_key_exists('expires_at', $this->params) && $this->kind !== 'token_revoked') {
            $message->line(__('notifications.detail_expires', $replace));
        }

        return $message
            ->action(__("notifications.{$this->kind}.action"), self::url($this->kind))
            ->line(__('notifications.not_you'));
    }

    /** Page vers laquelle mènent l'e-mail et la ligne de la cloche. */
    public static function url(string $kind): string
    {
        return $kind === 'password_changed' ? route('security.edit') : route('tokens.index');
    }

    /**
     * @param  array<string, string|null>  $params
     * @return array<string, string>
     */
    public static function replacements(array $params): array
    {
        $expires = $params['expires_at'] ?? null;

        return [
            'token' => (string) ($params['token'] ?? ''),
            'ip' => (string) ($params['ip'] ?? ''),
            'expires' => $expires === null
                ? __('tokens.never')
                : Carbon::parse($expires)->locale(app()->getLocale())->isoFormat('LL'),
        ];
    }
}
