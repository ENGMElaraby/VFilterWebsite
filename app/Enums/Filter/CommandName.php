<?php

namespace App\Enums\Filter;

/**
 * Instructions the website queues for the filter. Values are part of docs/shared-contract.md.
 */
enum CommandName: string
{
    case Start = 'start';
    case Stop = 'stop';
    case Restart = 'restart';
    case SetGatewayMode = 'set_gateway_mode';
    case Reload = 'reload';
    case Kick = 'kick';
    case Notice = 'notice';
    case Ban = 'ban';
}
