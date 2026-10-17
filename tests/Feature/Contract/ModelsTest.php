<?php

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Enums\Filter\ConfigArea;
use App\Enums\Filter\CooldownAction;
use App\Enums\Filter\GatewayMode;
use App\Enums\Filter\LogCategory;
use App\Enums\Filter\LogLevel;
use App\Enums\Filter\ModuleState;
use App\Enums\Filter\ModuleType;
use App\Enums\Filter\RestartScope;
use App\Enums\Filter\SettingType;
use App\Models\Filter\FilterAreaRule;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterCooldown;
use App\Models\Filter\FilterGaPermission;
use App\Models\Filter\FilterLog;
use App\Models\Filter\FilterModule;
use App\Models\Filter\FilterSetting;
use App\Models\Filter\FilterStatus;

it('round-trips enums, json and dates', function () {
    $module = FilterModule::factory()->create(['type' => ModuleType::Gateway, 'name' => 'Gateway']);
    $rule = FilterAreaRule::factory()->create(['params' => ['pc' => 2, 'ip' => 3]]);
    $command = FilterCommand::factory()->create(['command' => CommandName::SetGatewayMode, 'payload' => ['mode' => 'public']]);
    $log = FilterLog::factory()->create(['level' => LogLevel::Security, 'category' => LogCategory::Exploit, 'data' => ['opcode' => '0x7021']]);

    expect($module->fresh()->type)->toBe(ModuleType::Gateway)
        ->and($rule->fresh()->params)->toBe(['pc' => 2, 'ip' => 3])
        ->and($command->fresh())
        ->command->toBe(CommandName::SetGatewayMode)
        ->status->toBe(CommandStatus::Pending)
        ->payload->toBe(['mode' => 'public'])
        ->and($log->fresh())
        ->level->toBe(LogLevel::Security)
        ->category->toBe(LogCategory::Exploit);
});

it('saves and finds string-keyed rows', function () {
    FilterSetting::query()->create([
        'key' => 'logs.retention_days', 'value' => '30', 'value_type' => SettingType::Int,
        'group' => 'logs', 'requires_restart' => RestartScope::None,
    ]);
    FilterCooldown::query()->create(['action' => CooldownAction::Stall, 'seconds' => 10]);
    FilterGaPermission::query()->create(['username' => 'helper1', 'notice' => true]);
    FilterStatus::query()->create(['module' => 'Gateway', 'state' => ModuleState::Running, 'gateway_mode' => GatewayMode::Public]);
    FilterConfigVersion::query()->create(['area' => ConfigArea::Settings, 'version' => 4]);

    expect(FilterSetting::find('logs.retention_days')->typedValue())->toBe(30)
        ->and(FilterCooldown::find('stall')->seconds)->toBe(10)
        ->and(FilterGaPermission::find('helper1'))->notice->toBeTrue()->ban->toBeFalse()
        ->and(FilterStatus::find('Gateway'))->state->toBe(ModuleState::Running)->online_count->toBe(0)
        ->and(FilterConfigVersion::find('settings')->version)->toBe(4);
});

it('names each configuration model area', function () {
    expect(FilterModule::configArea())->toBe(ConfigArea::Modules)
        ->and(FilterSetting::configArea())->toBe(ConfigArea::Settings)
        ->and(FilterCooldown::configArea())->toBe(ConfigArea::Cooldowns)
        ->and(FilterGaPermission::configArea())->toBe(ConfigArea::GaPermissions)
        ->and(FilterAreaRule::configArea())->toBe(ConfigArea::AreaRules);
});
