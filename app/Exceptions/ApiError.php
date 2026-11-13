<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Erreur de l'API du CLI (TRD §17) : statut HTTP, code machine stable pour le CLI,
 * message rédigé et traduit côté serveur. Réponse : { message, code, errors }.
 */
class ApiError extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors  erreurs par champ
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function make(int $status, string $errorCode, array $errors = []): self
    {
        return new self($status, $errorCode, __("api.errors.{$errorCode}"), $errors);
    }

    public static function upgradeRequired(): self
    {
        return self::make(426, 'upgrade_required');
    }

    public function render(): JsonResponse
    {
        $body = ['message' => $this->getMessage(), 'code' => $this->errorCode];

        if ($this->errors !== []) {
            $body['errors'] = $this->errors;
        }

        return response()->json($body, $this->status);
    }
}
