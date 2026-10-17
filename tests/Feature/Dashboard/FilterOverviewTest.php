<?php

use App\Enums\Filter\GatewayMode;
use App\Enums\Filter\ModuleState;
use App\Filter\FilterOverview;
use App\Filter\ModuleCard;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use App\Models\Filter\FilterStatus;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC'));
    seedFilterModules(agents: 2);
});

function cardFor(string $name): ModuleCard
{
    return collect(app(FilterOverview::class)->modules())->firstOrFail(fn (ModuleCard $card): bool => $card->name === $name);
}

it('orders gateway, download, then agents by number', function () {
    $names = array_map(fn (ModuleCard $card): string => $card->name, app(FilterOverview::class)->modules());

    expect($names)->toBe(['Gateway', 'Download', 'Agent1', 'Agent2']);
});

it('is online only while the heartbeat is under 15 seconds old', function (int $age, bool $online) {
    reportStatus('Agent1', ['heartbeat_at' => now('UTC')->subSeconds($age), 'connections' => 7, 'online_count' => 5]);

    $card = cardFor('Agent1');

    expect($card->online)->toBe($online)
        ->and($card->connections)->toBe($online ? 7 : null)
        ->and($card->onlineCount)->toBe($online ? 5 : null)
        ->and($card->lastHeartbeat?->toIso8601String())->toBe(now('UTC')->subSeconds($age)->toIso8601String());
})->with([
    'fresh' => [0, true],
    '14 s' => [14, true],
    '15 s' => [15, false],
    'stale' => [600, false],
    'clock 14 s ahead' => [-14, true],
    'clock an hour ahead' => [-3600, false],
]);

it('shows a module that never reported as offline', function () {
    $card = cardFor('Gateway');

    expect($card->online)->toBeFalse()
        ->and($card->lastHeartbeat)->toBeNull()
        ->and($card->state)->toBeNull();
});

it('ignores status rows for modules that are not configured', function () {
    reportStatus('Agent9');

    expect(app(FilterOverview::class)->modules())->toHaveCount(4);
});

it('matches status rows to modules regardless of case', function () {
    reportStatus('agent1', ['online_count' => 3]);

    expect(cardFor('Agent1')->online)->toBeTrue()->and(cardFor('Agent1')->onlineCount)->toBe(3);
});

it('reports state, uptime, version and the last error', function () {
    reportStatus('Agent1', [
        'state' => ModuleState::Error->value,
        'started_at' => now('UTC')->subMinutes(90),
        'version' => '0.1.0',
        'last_error' => 'bind failed',
    ]);

    $card = cardFor('Agent1');

    expect($card->state)->toBe(ModuleState::Error)
        ->and($card->uptimeSeconds)->toBe(5400)
        ->and($card->version)->toBe('0.1.0')
        ->and($card->lastError)->toBe('bind failed');
});

it('shows the gateway mode, and an unknown mode without failing', function () {
    reportStatus('Gateway', ['gateway_mode' => GatewayMode::Inspection->value]);
    expect(cardFor('Gateway')->gatewayMode)->toBe(GatewayMode::Inspection);

    FilterStatus::query()->whereKey('Gateway')->toBase()->update(['gateway_mode' => 'weird']);
    $card = cardFor('Gateway');

    expect($card->gatewayMode)->toBeNull()->and($card->unknownGatewayMode)->toBe('weird');
});

it('marks disabled modules', function () {
    FilterModule::query()->where('name', 'Agent2')->update(['enabled' => false]);

    expect(cardFor('Agent2')->enabled)->toBeFalse()->and(cardFor('Agent1')->enabled)->toBeTrue();
});

it('counts online players on online agents only', function () {
    reportStatus('Agent1', ['online_count' => 40]);
    reportStatus('Agent2', ['online_count' => 25]);
    reportStatus('Gateway', ['online_count' => 99]);

    expect(app(FilterOverview::class)->onlinePlayers())->toBe(65);

    reportStatus('Agent2', ['online_count' => 25, 'heartbeat_at' => now('UTC')->subMinute()]);
    expect(app(FilterOverview::class)->onlinePlayers())->toBe(40);
});

it('has no player count when no agent is online', function () {
    reportStatus('Gateway');

    expect(app(FilterOverview::class)->onlinePlayers())->toBeNull();
});

it('skips commands with values outside the contract instead of failing', function () {
    FilterCommand::factory()->create();
    FilterCommand::query()->toBase()->insert([
        'command' => 'teleport', 'target' => 'agent1', 'status' => 'weird', 'created_by' => 1, 'created_at' => now('UTC'),
    ]);

    expect(app(FilterOverview::class)->recentCommands())->toHaveCount(1);
});
