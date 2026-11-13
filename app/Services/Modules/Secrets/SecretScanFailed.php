<?php

namespace App\Services\Modules\Secrets;

use RuntimeException;

/** La recherche de secrets n'a pas abouti (programme absent, délai dépassé) : la publication est refusée. */
class SecretScanFailed extends RuntimeException {}
