<?php

namespace App\Enums\Filter;

/**
 * Kind of proxy listener. Values are part of docs/shared-contract.md.
 */
enum ModuleType: string
{
    case Gateway = 'gateway';
    case Agent = 'agent';
    case Download = 'download';
}
