<?php

namespace App\Services\Modules;

use App\Exceptions\ApiError;
use RuntimeException;

/**
 * Refus d'une publication (W03) : code stable pour le CLI, valeurs du message traduit
 * (lang/*\/api.php). Les refus « de contenu » sont gardés comme version rejetée et notifiés
 * à l'auteur ; les autres (droits, archive illisible, numéro déjà pris) ne laissent rien.
 */
class PublicationError extends RuntimeException
{
    /** Codes qui laissent une version rejetée (BACKEND-SCHEMA §6) et notifient l'auteur. */
    public const KEEPS_REJECTED_VERSION = [
        'manifest_invalid', 'license_unknown', 'files_mismatch', 'binary_forbidden', 'svg_forbidden', 'secret_detected',
    ];

    /** @param  array<string, string|int>  $replace */
    public function __construct(
        public readonly string $errorCode,
        public readonly array $replace = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($errorCode);
    }

    public function keepsRejectedVersion(): bool
    {
        return in_array($this->errorCode, self::KEEPS_REJECTED_VERSION, true);
    }

    /** Message dans la langue en cours. */
    public function message(): string
    {
        return __("api.errors.{$this->errorCode}", $this->replace);
    }

    public function toApiError(): ApiError
    {
        return ApiError::make($this->status, $this->errorCode, replace: $this->replace);
    }
}
