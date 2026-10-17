<?php

use App\Config\InvalidModuleSetupException;
use App\Config\ModuleSlots;
use App\Enums\Filter\ConfigArea;
use App\Enums\Filter\ModuleType;
use App\Models\AuditLog;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterModule;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(fn () => $this->signIn(User::factory()->admin()->withTwoFactor()->create()));

function modulesVersion(): int
{
    return (int) FilterConfigVersion::query()->whereKey(ConfigArea::Modules->value)->value('version');
}

it('adds the next agent, disabled, after the last one', function () {
    seedFilterModules(agents: 1);
    $before = modulesVersion();

    $agent = app(ModuleSlots::class)->addAgent();

    expect($agent->fresh())
        ->name->toBe('Agent2')
        ->type->toBe(ModuleType::Agent)
        ->module_port->toBe(15885)
        ->guard_port->toBe(15885)
        ->enabled->toBeFalse()
        ->and(modulesVersion())->toBe($before + 1)
        ->and(AuditLog::query()->where('action', 'modules.created')->count())->toBe(1);
});

it('stops at five agents', function () {
    seedFilterModules(agents: 5);

    expect(fn () => app(ModuleSlots::class)->addAgent())->toThrow(InvalidModuleSetupException::class);
    expect(FilterModule::query()->where('type', ModuleType::Agent)->count())->toBe(5);
});

it('removes only the highest-numbered agent', function () {
    seedFilterModules(agents: 3);

    app(ModuleSlots::class)->removeLastAgent();

    expect(FilterModule::query()->where('type', ModuleType::Agent)->orderBy('name')->pluck('name')->all())->toBe(['Agent1', 'Agent2'])
        ->and(AuditLog::query()->where('action', 'modules.deleted')->count())->toBe(1);
});

it('keeps the last agent', function () {
    seedFilterModules(agents: 1);

    expect(fn () => app(ModuleSlots::class)->removeLastAgent())->toThrow(InvalidModuleSetupException::class);
    expect(FilterModule::query()->where('name', 'Agent1')->exists())->toBeTrue();
});

it('refuses viewers', function () {
    seedFilterModules(agents: 2);
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    expect(fn () => app(ModuleSlots::class)->addAgent())->toThrow(AuthorizationException::class)
        ->and(fn () => app(ModuleSlots::class)->removeLastAgent())->toThrow(AuthorizationException::class);
});
