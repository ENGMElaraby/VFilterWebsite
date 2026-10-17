<?php

namespace App\Enums\Filter;

/**
 * Configuration areas, each with its own change counter in filter_config_version. Values are part of docs/shared-contract.md.
 */
enum ConfigArea: string
{
    case Settings = 'settings';
    case Lists = 'lists';
    case AreaRules = 'area_rules';
    case Cooldowns = 'cooldowns';
    case Modules = 'modules';
    case GaPermissions = 'ga_permissions';
}
