<?php

use App\Enums\Filter\CooldownAction;
use App\Enums\Filter\ModuleType;
use App\Models\AuditLog;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterCooldown;
use App\Models\Filter\FilterModule;
use App\Settings\SettingsRepository;

function versions(): array
{
    return FilterConfigVersion::query()->orderBy('area')->pluck('version', 'area')->all();
}

it('installs safe defaults on an empty database', function () {
    $this->artisan('vfilter:install-defaults')->assertSuccessful();

    $modules = FilterModule::query()->orderBy('name')->get();

    expect($modules->pluck('name')->all())->toBe(['Agent1', 'Download', 'Gateway'])
        ->and($modules->pluck('enabled')->unique()->all())->toBe([false])
        ->and(FilterModule::where('name', 'Gateway')->sole())
        ->type->toBe(ModuleType::Gateway)->module_port->toBe(15779)->guard_port->toBe(15779)
        ->and(FilterModule::where('name', 'Download')->sole()->module_port)->toBe(15881)
        ->and(FilterModule::where('name', 'Agent1')->sole()->module_port)->toBe(15884);

    expect(FilterCooldown::count())->toBe(count(CooldownAction::cases()))
        ->and(FilterCooldown::query()->sum('seconds'))->toBe(0);

    expect(app(SettingsRepository::class)->all())->toBe([
        'limits.ip' => 0,
        'limits.pc' => 0,
        'login.captcha.enabled' => false,
        'logs.retention_days' => 30,
        'server.start_in_inspection' => false,
    ]);

    expect(versions())->toBe([
        'area_rules' => 0, 'cooldowns' => 1, 'ga_permissions' => 0,
        'lists' => 0, 'modules' => 1, 'settings' => 1,
    ]);

    expect(AuditLog::where('action', 'config.defaults_installed')->sole())
        ->actor_type->toBe('system')
        ->new_values->toBe(['modules' => 3, 'cooldowns' => 17, 'settings' => 5]);
});

it('adds nothing and overwrites nothing the second time', function () {
    $this->artisan('vfilter:install-defaults')->assertSuccessful();
    FilterModule::where('name', 'Gateway')->update(['module_ip' => '10.0.0.5']);
    app(SettingsRepository::class)->set(['logs.retention_days' => 90]);
    $before = versions();

    $this->artisan('vfilter:install-defaults')
        ->expectsOutputToContain('0 rows added')
        ->assertSuccessful();

    expect(FilterModule::where('name', 'Gateway')->sole()->module_ip)->toBe('10.0.0.5')
        ->and(app(SettingsRepository::class)->get('logs.retention_days'))->toBe(90)
        ->and(versions())->toBe($before)
        ->and(AuditLog::where('action', 'config.defaults_installed')->count())->toBe(1);
});
