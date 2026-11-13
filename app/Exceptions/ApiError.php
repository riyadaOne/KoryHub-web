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
    /** @var array<string, mixed> champs ajoutés au corps (heure de reprise…) */
    private array $extra = [];

    /** @var array<string, string> */
    private array $headers = [];

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

    /**
     * @param  array<string, list<string>>  $errors  erreurs par champ
     * @param  array<string, string|int>  $replace  valeurs du message (fichier, ligne, version…)
     */
    public static function make(int $status, string $errorCode, array $errors = [], array $replace = []): self
    {
        return new self($status, $errorCode, __("api.errors.{$errorCode}", $replace), $errors);
    }

    public static function upgradeRequired(): self
    {
        return self::make(426, 'upgrade_required');
    }

    /** @param  array<string, mixed>  $extra */
    public function withExtra(array $extra): self
    {
        $this->extra = $extra + $this->extra;

        return $this;
    }

    /** @param  array<string, string>  $headers */
    public function withHeaders(array $headers): self
    {
        $this->headers = $headers + $this->headers;

        return $this;
    }

    public function render(): JsonResponse
    {
        $body = ['message' => $this->getMessage(), 'code' => $this->errorCode] + $this->extra;

        if ($this->errors !== []) {
            $body['errors'] = $this->errors;
        }

        return response()->json($body, $this->status, $this->headers);
    }
}
