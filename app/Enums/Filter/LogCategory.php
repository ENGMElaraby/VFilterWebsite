<?php

namespace App\Enums\Filter;

/**
 * Filter log category. Values are part of docs/shared-contract.md.
 */
enum LogCategory: string
{
    case Connection = 'connection';
    case Login = 'login';
    case Exploit = 'exploit';
    case Flood = 'flood';
    case Limit = 'limit';
    case Restriction = 'restriction';
    case Command = 'command';
    case Chat = 'chat';
    case Gm = 'gm';
}
