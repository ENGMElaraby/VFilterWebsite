<?php

namespace App\Config;

use App\Enums\Filter\ModuleType;
use App\Enums\Filter\RestartScope;
use App\Enums\Filter\SettingType;

/**
 * Starting configuration for a fresh VFILTER database (spec 002, Assumptions).
 * The full settings catalog arrives in feature 006.
 */
final class FilterDefaults
{
    private const LOCALHOST = '127.0.0.1';

    /**
     * vSRO default ports. Disabled until the operator sets real and guard addresses.
     *
     * @return list<array<string, mixed>>
     */
    public static function modules(): array
    {
        $module = fn (ModuleType $type, string $name, int $port): array => [
            'type' => $type,
            'name' => $name,
            'module_ip' => self::LOCALHOST,
            'module_port' => $port,
            'guard_ip' => self::LOCALHOST,
            'guard_port' => $port,
            'enabled' => false,
        ];

        return [
            $module(ModuleType::Gateway, 'Gateway', 15779),
            $module(ModuleType::Download, 'Download', 15881),
            $module(ModuleType::Agent, 'Agent1', 15884),
        ];
    }

    /**
     * @return list<array{0: string, 1: SettingType, 2: string, 3: mixed, 4: RestartScope}> key, type, group, default, restart
     */
    public static function settings(): array
    {
        return [
            ['server.start_in_inspection', SettingType::Bool, 'server', false, RestartScope::Filter],
            ['logs.retention_days', SettingType::Int, 'logs', 30, RestartScope::None],
            ['limits.ip', SettingType::Int, 'limits', 0, RestartScope::None],
            ['limits.pc', SettingType::Int, 'limits', 0, RestartScope::None],
            ['login.captcha.enabled', SettingType::Bool, 'login', false, RestartScope::None],
        ];
    }
}
