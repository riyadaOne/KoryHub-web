<?php

namespace App\Services\Moderation;

use RuntimeException;

/** Signalement ou décision de modération refusé : règle non respectée (clé de traduction). */
class ModerationRefused extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct(__($key));
    }
}
