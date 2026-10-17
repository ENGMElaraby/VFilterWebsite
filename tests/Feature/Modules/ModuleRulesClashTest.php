<?php

use App\Config\ConfigStore;
use App\Config\InvalidModuleSetupException;
use App\Models\Filter\FilterModule;
use App\Models\User;

beforeEach(function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    seedFilterModules(agents: 2);
});

it('refuses two modules on the same guard address and port', function () {
    $agent2 = FilterModule::query()->where('name', 'Agent2')->sole();

    expect(fn () => app(ConfigStore::class)->update($agent2, ['guard_port' => 15884]))
        ->toThrow(InvalidModuleSetupException::class, 'Agent1');
    expect($agent2->fresh()->guard_port)->toBe(15885);
});

it('allows the same port on another guard address', function () {
    $agent2 = FilterModule::query()->where('name', 'Agent2')->sole();

    app(ConfigStore::class)->update($agent2, ['guard_port' => 15884, 'guard_ip' => '203.0.113.11']);

    expect($agent2->fresh()->guard_port)->toBe(15884);
});
