<?php

use App\Filament\Pages\ConnectionHealth;
use App\Filter\ConnectionHealthCheck;
use App\Filter\HealthResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

const HEALTH_REASONS = ['timeout', 'login_failed', 'unreachable', 'database_not_found'];

function brokenConnection(string $name): void
{
    config(["database.connections.{$name}" => [
        'driver' => 'sqlite',
        'database' => '/nonexistent-dir/'.$name.'.sqlite',
        'host' => 'secret-host.example',
        'username' => 'secret-user',
        'password' => 'secret-pass',
        'prefix' => '',
    ]]);
    DB::purge($name);
}

it('reports VFILTER as reachable with all shared tables', function () {
    $result = app(ConnectionHealthCheck::class)->run()['filter'];

    expect($result->state)->toBe(HealthResult::OK)
        ->and($result->millis)->toBeGreaterThanOrEqual(0)
        ->and($result->missingTables)->toBe([])
        ->and($result->presentTables())->toBe(11);
});

it('names missing shared tables', function () {
    Schema::connection('filter')->drop('filter_logs');

    expect(app(ConnectionHealthCheck::class)->run()['filter']->missingTables)->toBe(['filter_logs']);
});

it('shows game databases without a name as not configured', function () {
    $results = app(ConnectionHealthCheck::class)->run();

    expect($results['account']->state)->toBe(HealthResult::NOT_CONFIGURED)
        ->and($results['shard']->state)->toBe(HealthResult::NOT_CONFIGURED)
        ->and($results['log']->state)->toBe(HealthResult::NOT_CONFIGURED);
});

it('reports an unreachable database with a short reason and no credentials', function () {
    brokenConnection('shard');

    $result = app(ConnectionHealthCheck::class)->run()['shard'];
    $shown = json_encode($result->toArray());

    expect($result->state)->toBe(HealthResult::UNREACHABLE)
        ->and(in_array($result->reason, HEALTH_REASONS, true) || str_starts_with((string) $result->reason, 'error:'))->toBeTrue()
        ->and($shown)->not->toContain('secret-host')
        ->and($shown)->not->toContain('secret-user')
        ->and($shown)->not->toContain('secret-pass')
        ->and($shown)->not->toContain('nonexistent-dir');
});

it('only reads', function () {
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = strtolower(ltrim($query->sql));
    });

    app(ConnectionHealthCheck::class)->run();

    expect($statements)->not->toBeEmpty();
    foreach ($statements as $sql) {
        expect(str_starts_with($sql, 'select') || str_starts_with($sql, 'pragma'))->toBeTrue($sql);
    }
});

it('lets every signed-in role run the check from the page', function (string $role) {
    $this->signIn(User::factory()->{$role}()->withTwoFactor()->create());
    brokenConnection('log');

    $this->get(ConnectionHealth::getUrl())->assertOk()->assertSee(__('panel.health.not_run'));

    Livewire::test(ConnectionHealth::class)
        ->callAction('runCheck')
        ->assertSee(__('panel.health.states.ok'))
        ->assertSee(__('panel.health.states.not_configured'))
        ->assertSee(__('panel.health.states.unreachable'))
        ->assertSee('11/11')
        ->assertDontSee('secret-pass');
})->with(['viewer', 'admin', 'owner']);

it('gives up on an unreachable SQL Server within the time budget', function () {
    if (DB::connection('filter')->getDriverName() !== 'sqlsrv') {
        $this->markTestSkipped('Needs SQL Server (DB_FILTER_DRIVER=sqlsrv).');
    }

    config(['database.connections.shard' => array_merge(config('database.connections.filter'), ['host' => '10.255.255.1', 'database' => 'SRO_VT_SHARD'])]);
    $started = microtime(true);

    $result = app(ConnectionHealthCheck::class)->run()['shard'];

    expect(microtime(true) - $started)->toBeLessThan(ConnectionHealthCheck::TIMEOUT_SECONDS + 1)
        ->and($result->state)->toBe(HealthResult::UNREACHABLE);
})->group('sqlsrv');
