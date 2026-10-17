<?php

namespace App\Lists;

use InvalidArgumentException;

/**
 * One or more lines are invalid; nothing was saved.
 */
class InvalidListException extends InvalidArgumentException
{
    public function __construct(public readonly ParsedList $parsed)
    {
        parent::__construct(implode("\n", array_map(
            fn (array $error): string => $error['line'] === 0
                ? $error['reason']
                : __('panel.lists.errors.line', ['line' => $error['line'], 'reason' => $error['reason']]),
            $parsed->errors,
        )));
    }
}
