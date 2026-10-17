<?php

use App\Enums\Filter\ModuleState;
use App\Enums\Filter\ModuleType;
use App\Models\Filter\FilterModule;
use App\Models\Filter\FilterStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Fixture: Gateway, Download and Agent1..AgentN written straight to filter_modules (no audit,
 * no version bump). Use ConfigStore in tests that check those.
 */
function seedFilterModules(int $agents = 1, bool $enabled = true): void
{
    $row = fn (ModuleType $type, string $name, int $port): array => [
        'type' => $type, 'name' => $name, 'module_ip' => '10.0.0.10', 'module_port' => $port,
        'guard_ip' => '203.0.113.10', 'guard_port' => $port, 'enabled' => $enabled,
    ];

    FilterModule::query()->create($row(ModuleType::Gateway, 'Gateway', 15779));
    FilterModule::query()->create($row(ModuleType::Download, 'Download', 15881));

    foreach (range(1, $agents) as $number) {
        FilterModule::query()->create($row(ModuleType::Agent, 'Agent'.$number, 15883 + $number));
    }
}

/**
 * Fixture: what the filter would write to filter_status for one module. Heartbeat defaults to now.
 *
 * @param  array<string, mixed>  $attributes
 */
function reportStatus(string $module, array $attributes = []): void
{
    FilterStatus::query()->updateOrCreate(['module' => $module], array_merge([
        'state' => ModuleState::Running->value,
        'online_count' => 0,
        'connections' => 0,
        'started_at' => now('UTC')->subHour(),
        'heartbeat_at' => now('UTC'),
    ], $attributes));
}
