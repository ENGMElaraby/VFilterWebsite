<?php

namespace App\Enums\Filter;

/**
 * Filter log level. Values are part of docs/shared-contract.md.
 */
enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warn = 'warn';
    case Error = 'error';
    case Security = 'security';
}
