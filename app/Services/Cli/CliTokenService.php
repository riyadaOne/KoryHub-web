<?php

namespace App\Services\Cli;

use App\Enums\SecurityEventType;
use App\Enums\TokenOrigin;
use App\Enums\TokenStatus;
use App\Exceptions\InvalidTransition;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Accounts\SecurityLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jetons du CLI (TRD §7.2, F-AUT-05, F-AUT-08, F-AUT-18) : création et révocation, notées
 * dans le journal de sécurité (avec notification) et dans l'audit.
 */
class CliTokenService
{
    /** Durées proposées en jours ; null = sans expiration (F-AUT-05). */
    public const LIFETIMES = [7, 30, 90, 365, null];

    public const DEFAULT_LIFETIME = 90;

    public function __construct(private SecurityLog $securityLog, private AuditLogger $audit) {}

    /**
     * Crée un jeton et renvoie [jeton en clair, ligne]. Le jeton en clair n'est rendu qu'ici :
     * seule son empreinte SHA-256 est enregistrée (F-AUT-09).
     *
     * @return array{0: string, 1: PersonalAccessToken}
     */
    public function create(User $user, string $name, ?int $lifetimeDays, TokenOrigin $origin): array
    {
        if (! in_array($lifetimeDays, self::LIFETIMES, true)) {
            throw new \InvalidArgumentException("Durée de jeton refusée : {$lifetimeDays}");
        }

        $plain = self::generate();

        $token = DB::transaction(function () use ($user, $name, $lifetimeDays, $origin, $plain) {
            /** @var PersonalAccessToken $token */
            $token = $user->tokens()->create([
                'name' => Str::limit($name, 250, ''),
                'token' => hash('sha256', $plain),
                'abilities' => ['*'],
                'origin' => $origin,
                'expires_at' => $lifetimeDays === null ? null : now()->addDays($lifetimeDays),
            ]);

            $this->audit->record('token.created', $token, [
                'origin' => [null, $origin],
                'expires_at' => [null, $token->expires_at],
            ], actor: $user);

            $type = $origin === TokenOrigin::CliLogin ? SecurityEventType::CliLogin : SecurityEventType::TokenCreated;
            $this->securityLog->record($user, $type, $token);

            return $token;
        });

        return [$plain, $token];
    }

    /**
     * Révoque un jeton (F-AUT-08) : sa ligne reste, il n'est jamais réactivé.
     * Un jeton déjà révoqué ou expiré ne peut pas l'être une seconde fois.
     */
    public function revoke(PersonalAccessToken $token, ?User $actor = null, ?string $reason = null): void
    {
        $status = $token->status();
        if (! $status->isUsable()) {
            throw new InvalidTransition($status, TokenStatus::Revoked);
        }

        DB::transaction(function () use ($token, $actor, $reason) {
            $token->forceFill(['revoked_at' => now(), 'revoked_by' => $actor?->getKey()])->save();

            $this->audit->record('token.revoked', $token, ['revoked_at' => [null, $token->revoked_at]], $reason, $actor);

            /** @var User $owner */
            $owner = $token->tokenable;
            $this->securityLog->record($owner, SecurityEventType::TokenRevoked, $token);
        });
    }

    /** Jeton préfixé kory_ (TRD §7.2) : 40 caractères aléatoires et une somme de contrôle. */
    public static function generate(): string
    {
        $entropy = Str::random(40);

        return config('koryhub.tokens.prefix').$entropy.hash('crc32b', $entropy);
    }
}
