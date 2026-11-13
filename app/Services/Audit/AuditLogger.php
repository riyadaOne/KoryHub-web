<?php

namespace App\Services\Audit;

use App\Enums\ActorType;
use App\Models\AuditEvent;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Écrit dans le journal d'audit (TRD §21, F-ADM-04) : qui, quand, quoi, sur quel objet,
 * et pour chaque champ la valeur avant et après. Aucune donnée personnelle : les champs
 * listés dans koryhub.audit.redacted_fields sont notés comme modifiés, sans leur valeur.
 */
class AuditLogger
{
    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes  champ => [avant, après]
     * @param  bool  $bySystem  automatisme : l'auteur est « Système », même si un compte est connecté
     */
    public function record(
        string $action,
        Model $subject,
        array $changes = [],
        ?string $reason = null,
        ?User $actor = null,
        bool $bySystem = false,
    ): AuditEvent {
        $actor = $bySystem ? null : ($actor ?? auth()->user());

        return DB::transaction(function () use ($action, $subject, $changes, $reason, $actor) {
            $event = AuditEvent::create([
                'occurred_at' => now(),
                'actor_type' => $actor === null ? ActorType::System : ActorType::User,
                'actor_id' => $actor?->getKey(),
                'action' => $action,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => (string) $subject->getKey(),
                'reason' => $reason,
            ]);

            $redacted = config('koryhub.audit.redacted_fields', []);

            foreach ($changes as $field => [$before, $after]) {
                $hidden = in_array($field, $redacted, true);

                $event->changes()->create([
                    'field' => $field,
                    'before' => $hidden ? null : $this->stringify($before),
                    'after' => $hidden ? null : $this->stringify($after),
                ]);
            }

            return $event;
        });
    }

    /** Champs modifiés d'un model qui vient d'être enregistré, au format attendu par record(). */
    public function changesOf(Model $model): array
    {
        $changes = [];

        // Après l'enregistrement, getOriginal() renvoie déjà la nouvelle valeur : l'ancienne est dans getPrevious().
        $previous = $model->getPrevious();

        foreach (array_keys($model->getChanges()) as $field) {
            if (in_array($field, [$model->getUpdatedAtColumn(), $model->getCreatedAtColumn()], true)) {
                continue;
            }

            $changes[$field] = [$previous[$field] ?? null, $model->getAttribute($field)];
        }

        return $changes;
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
