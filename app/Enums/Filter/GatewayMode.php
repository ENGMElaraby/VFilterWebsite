<?php

namespace App\Enums\Filter;

/**
 * Who may log in through the gateway. Values are part of docs/shared-contract.md.
 */
enum GatewayMode: string
{
    case Gm = 'gm';
    case Inspection = 'inspection';
    case Public = 'public';
}
