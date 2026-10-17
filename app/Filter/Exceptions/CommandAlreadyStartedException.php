<?php

namespace App\Filter\Exceptions;

use RuntimeException;

/**
 * The filter already picked the command up (or it already finished), so it can't be cancelled.
 */
class CommandAlreadyStartedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('panel.commands.errors.already_started'));
    }
}
