<?php

namespace App\Enums\Filter;

/**
 * Lifecycle of a queued command: pending -> running -> done | failed. Values are part of docs/shared-contract.md.
 */
enum CommandStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
}
