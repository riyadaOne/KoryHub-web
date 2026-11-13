<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification de l'auteur d'un module (F-REG-19) : version publiée ou rejetée, par e-mail et
 * dans la cloche selon ses préférences (S-43), dans sa langue, envoyée en file (TRD §19).
 */
class ModuleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array{module?: string, version?: string, reason?: string|null}  $params */
    public function __construct(public NotificationType $type, public array $params = []) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->wantsNotification($this->type, 'mail') && $notifiable->email !== null ? 'mail' : null,
            $notifiable->wantsNotification($this->type, 'database') ? 'database' : null,
        ]));
    }

    public function databaseType(User $notifiable): string
    {
        return $this->type->value;
    }

    /** @return array{kind: string, params: array<string, mixed>} */
    public function toArray(User $notifiable): array
    {
        return ['kind' => $this->type->value, 'params' => $this->params];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $kind = $this->type->value;
        $replace = NotificationText::replacements($this->params);

        $message = (new MailMessage)
            ->subject(__("notifications.{$kind}.title", $replace))
            ->line(__("notifications.{$kind}.mail", $replace));

        if (! empty($this->params['reason'])) {
            $message->line(__('notifications.detail_reason', $replace));
        }

        return $message->action(__("notifications.{$kind}.action"), NotificationText::url($kind));
    }
}
