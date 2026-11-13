<?php

namespace App\Services\Cli;

use RuntimeException;

/** Code de connexion du CLI expiré, déjà autorisé, refusé ou utilisé (S-26). */
class DeviceCodeUnavailable extends RuntimeException {}
