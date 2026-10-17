<?php

namespace App\Enums\Filter;

/**
 * Lifecycle of a queued command: pending -> running -> done | failed, or pending -> cancelled (website). Values are part of docs/shared-contract.md.
 */
enum CommandStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Statuses that still block another command of the same group for the same target.
     *
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::Pending, self::Running];
    }
}
