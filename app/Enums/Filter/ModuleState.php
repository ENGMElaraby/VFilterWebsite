<?php

namespace App\Enums\Filter;

/**
 * Module state reported by the filter heartbeat. Values are part of docs/shared-contract.md.
 */
enum ModuleState: string
{
    case Stopped = 'stopped';
    case Starting = 'starting';
    case Running = 'running';
    case Error = 'error';
}
