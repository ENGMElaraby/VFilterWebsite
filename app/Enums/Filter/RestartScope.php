<?php

namespace App\Enums\Filter;

/**
 * What must restart before a setting takes effect. Values are part of docs/shared-contract.md.
 */
enum RestartScope: string
{
    case None = 'none';
    case Filter = 'filter';
    case Gameserver = 'gameserver';
    case Client = 'client';
}
