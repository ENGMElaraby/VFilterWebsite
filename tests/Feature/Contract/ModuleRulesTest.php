<?php

use App\Config\ConfigStore;
use App\Config\InvalidModuleSetupException;
use App\Enums\Filter\ModuleType;
use App\Models\Filter\FilterModule;
use App\Models\User;

function moduleRow(ModuleType $type, string $name, array $overrides = []): array
{
    return array_merge([
        'type' => $type, 'name' => $name, 'module_ip' => '10.0.0.1', 'module_port' => 15779,
        'guard_ip' => 'play.example.test', 'guard_port' => 15779, 'enabled' => false,
    ], $overrides);
}

function validModules(): array
{
    return [
        moduleRow(ModuleType::Gateway, 'Gateway'),
        moduleRow(ModuleType::Download, 'Download', ['module_port' => 15881, 'guard_port' => 15881]),
        moduleRow(ModuleType::Agent, 'Agent1', ['module_port' => 15884, 'guard_port' => 15884]),
    ];
}

function saveModules(array $rows): void
{
    app(ConfigStore::class)->batch(function (ConfigStore $store) use ($rows): void {
        foreach ($rows as $row) {
            $store->create(FilterModule::class, $row);
        }
    });
}

beforeEach(fn () => $this->signIn(User::factory()->owner()->withTwoFactor()->create()));

it('accepts one gateway, one download and agents in order', function () {
    saveModules([...validModules(), moduleRow(ModuleType::Agent, 'Agent2', ['module_port' => 15885, 'guard_port' => 15885])]);

    expect(FilterModule::count())->toBe(4);
});

it('refuses invalid module setups and rolls back', function (array $rows, string $message) {
    expect(fn () => saveModules($rows))->toThrow(InvalidModuleSetupException::class, $message);
    expect(FilterModule::count())->toBe(0);
})->with([
    'second gateway' => [[...validModules(), moduleRow(ModuleType::Gateway, 'Gateway2')], 'gateway'],
    'missing download' => [[moduleRow(ModuleType::Gateway, 'Gateway'), moduleRow(ModuleType::Agent, 'Agent1')], 'download'],
    'no agent' => [[moduleRow(ModuleType::Gateway, 'Gateway'), moduleRow(ModuleType::Download, 'Download')], 'agent'],
    'gap in agents' => [[...validModules(), moduleRow(ModuleType::Agent, 'Agent3')], 'Agent'],
    'sixth agent' => [[...validModules(), ...array_map(fn ($i) => moduleRow(ModuleType::Agent, 'Agent'.$i), range(2, 6))], 'agent'],
    'port zero' => [[moduleRow(ModuleType::Gateway, 'Gateway', ['module_port' => 0]), ...array_slice(validModules(), 1)], 'port'],
    'port too high' => [[moduleRow(ModuleType::Gateway, 'Gateway', ['guard_port' => 70000]), ...array_slice(validModules(), 1)], 'port'],
    'bad address' => [[moduleRow(ModuleType::Gateway, 'Gateway', ['module_ip' => 'not an ip!']), ...array_slice(validModules(), 1)], 'address'],
]);
