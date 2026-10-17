<?php

namespace App\Filter\Exceptions;

use App\Enums\Filter\CommandGroup;
use RuntimeException;

/**
 * A command of the same group is already pending or running for this target (FR-011).
 */
class CommandInProgressException extends RuntimeException
{
    public function __construct(public readonly string $target, public readonly CommandGroup $group)
    {
        parent::__construct(__('panel.commands.errors.in_progress', ['target' => $target]));
    }
}
