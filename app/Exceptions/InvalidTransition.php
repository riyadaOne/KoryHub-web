<?php

namespace App\Exceptions;

use App\Enums\HasStatusBadge;
use DomainException;

/**
 * Transition de statut refusée par un service de cycle de vie (TRD §11, T-01).
 * Le message est traduit : il peut être affiché tel quel à l'écran ou renvoyé par l'API.
 */
class InvalidTransition extends DomainException
{
    public function __construct(
        public readonly HasStatusBadge $from,
        public readonly HasStatusBadge $to,
    ) {
        parent::__construct(__('common.invalid_transition', [
            'from' => $from->label(),
            'to' => $to->label(),
        ]));
    }
}
