<?php

namespace App\Services\Cli;

use App\Enums\DeviceCodeStatus;
use App\Enums\TokenOrigin;
use App\Exceptions\ApiError;
use App\Models\CliDeviceCode;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Connexion du CLI par code (TRD §7.3, F-AUT-06), comme la connexion par appareil de GitHub
 * (RFC 8628) : le CLI demande un code, le développeur l'autorise sur S-26, le CLI interroge
 * l'API toutes les 5 secondes et reçoit son jeton une seule fois.
 */
class DeviceAuthorizationService
{
    public const LIFETIME_MINUTES = 15;

    public const INTERVAL_SECONDS = 5;

    /** Interrogations au plus par code : 15 minutes à 5 secondes, avec de la marge. */
    public const MAX_ATTEMPTS = 300;

    /** Consonnes sans caractère ambigu : aucun mot ne peut se former (RFC 8628 §6.1). */
    private const ALPHABET = 'BCDFGHJKLMNPQRSTVWXZ';

    public function __construct(private CliTokenService $tokens) {}

    /**
     * Ouvre une demande de connexion ; renvoie [code de l'appareil en clair, ligne].
     *
     * @return array{0: string, 1: CliDeviceCode}
     */
    public function start(?string $userAgent): array
    {
        $deviceCode = Str::random(48);

        $row = CliDeviceCode::create([
            'device_code_hash' => hash('sha256', $deviceCode),
            'user_code' => $this->uniqueUserCode(),
            'client_info' => self::clientInfo($userAgent),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return [$deviceCode, $row];
    }

    /** Code en attente et encore valable, saisi sur S-26 (casse et tiret indifférents). */
    public function findPending(string $userCode): ?CliDeviceCode
    {
        $code = self::normalize($userCode);

        if ($code === null) {
            return null;
        }

        return CliDeviceCode::query()
            ->where('user_code', $code)
            ->where('status', DeviceCodeStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();
    }

    /** Le développeur autorise le terminal (S-26) et choisit la durée du jeton. */
    public function approve(CliDeviceCode $code, User $user, ?int $lifetimeDays): void
    {
        if (! in_array($lifetimeDays, CliTokenService::LIFETIMES, true)) {
            throw new \InvalidArgumentException('Durée de jeton refusée.');
        }

        $this->transition($code, DeviceCodeStatus::Approved, [
            'user_id' => $user->getKey(),
            'token_lifetime_days' => $lifetimeDays,
            'approved_at' => now(),
        ]);
    }

    /** Le développeur refuse : le terminal reçoit access_denied. */
    public function deny(CliDeviceCode $code, User $user): void
    {
        $this->transition($code, DeviceCodeStatus::Denied, ['user_id' => $user->getKey()]);
    }

    /**
     * Interrogation du CLI : le jeton une seule fois si le code est autorisé, sinon une
     * erreur de la norme RFC 8628 (authorization_pending, slow_down, access_denied, expired_token).
     *
     * @return array{0: string, 1: PersonalAccessToken}
     */
    public function exchange(string $deviceCode): array
    {
        $outcome = DB::transaction(function () use ($deviceCode) {
            $code = CliDeviceCode::query()
                ->where('device_code_hash', hash('sha256', $deviceCode))
                ->lockForUpdate()
                ->first();

            if ($code === null) {
                return 'expired_token';
            }

            $tooSoon = $code->last_polled_at !== null
                && $code->last_polled_at->gt(now()->subSeconds(self::INTERVAL_SECONDS - 1));
            $code->forceFill(['attempts' => $code->attempts + 1, 'last_polled_at' => now()])->save();

            if ($code->status === DeviceCodeStatus::Pending && ($code->isExpired() || $code->attempts > self::MAX_ATTEMPTS)) {
                $code->forceFill(['status' => DeviceCodeStatus::Expired])->save();
            }

            return match ($code->status) {
                DeviceCodeStatus::Pending => $tooSoon ? 'slow_down' : 'authorization_pending',
                DeviceCodeStatus::Denied => 'access_denied',
                DeviceCodeStatus::Expired, DeviceCodeStatus::Consumed => 'expired_token',
                DeviceCodeStatus::Approved => $this->issue($code),
            };
        });

        // Les erreurs sont levées hors de la transaction pour garder le compteur d'interrogations.
        if (is_string($outcome)) {
            throw ApiError::make(400, $outcome);
        }

        return $outcome;
    }

    /** Tâche planifiée : codes échus passés à « expiré », lignes effacées 7 jours après. */
    public function prune(): int
    {
        CliDeviceCode::query()
            ->where('status', DeviceCodeStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => DeviceCodeStatus::Expired]);

        return CliDeviceCode::query()->where('expires_at', '<', now()->subDays(7))->delete();
    }

    /**
     * Nom lisible du terminal, tiré de l'en-tête User-Agent du CLI (« kory/0.2.0 (windows; amd64) »).
     * Le CLI n'envoie rien d'autre sur la machine (T-44).
     */
    public static function clientInfo(?string $userAgent): ?string
    {
        if ($userAgent === null || ! preg_match('#^kory/(\S+) \(([a-z0-9]+); ([a-z0-9]+)\)#', $userAgent, $m)) {
            return null;
        }

        return "kory {$m[1]} · ".self::systemName($m[2])." ({$m[3]})";
    }

    /** Saisie libre (« wdjbmjht », « WDJB MJHT ») ramenée à « WDJB-MJHT » ; null sinon. */
    public static function normalize(string $input): ?string
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $input));

        return strlen($letters) === 8 ? substr($letters, 0, 4).'-'.substr($letters, 4) : null;
    }

    private static function systemName(string $os): string
    {
        return match ($os) {
            'windows' => 'Windows',
            'darwin' => 'macOS',
            'linux' => 'Linux',
            default => $os,
        };
    }

    /**
     * @return array{0: string, 1: PersonalAccessToken}
     */
    private function issue(CliDeviceCode $code): array
    {
        /** @var User $user */
        $user = $code->user;
        $system = preg_match('/ · (\S+)/u', (string) $code->client_info, $m) ? $m[1] : 'CLI';

        [$plain, $token] = $this->tokens->create($user, "kory · {$system}", $code->token_lifetime_days, TokenOrigin::CliLogin);

        $code->forceFill([
            'status' => DeviceCodeStatus::Consumed,
            'token_id' => $token->getKey(),
            'consumed_at' => now(),
        ])->save();

        return [$plain, $token];
    }

    /** @param  array<string, mixed>  $attributes */
    private function transition(CliDeviceCode $code, DeviceCodeStatus $next, array $attributes): void
    {
        DB::transaction(function () use ($code, $next, $attributes) {
            $fresh = CliDeviceCode::query()->lockForUpdate()->findOrFail($code->getKey());

            if (! $fresh->status->canBecome($next) || $fresh->isExpired()) {
                throw new DeviceCodeUnavailable;
            }

            $fresh->forceFill(['status' => $next, ...$attributes])->save();
            $code->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    private function uniqueUserCode(): string
    {
        do {
            $letters = '';
            for ($i = 0; $i < 8; $i++) {
                $letters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = substr($letters, 0, 4).'-'.substr($letters, 4);
        } while (CliDeviceCode::query()->where('user_code', $code)->where('expires_at', '>', now())->exists());

        return $code;
    }
}
