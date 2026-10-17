<?php

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Filter\CommandRow;
use App\Filter\FilterOverview;
use App\Models\Filter\FilterCommand;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC'));
    seedFilterModules(agents: 1);
});

function rowFor(FilterCommand $command): CommandRow
{
    return collect(app(FilterOverview::class)->recentCommands())->firstOrFail(fn (CommandRow $row): bool => $row->id === $command->id);
}

it('lists the 20 newest commands, newest first', function () {
    FilterCommand::factory()->count(25)->create();

    $ids = array_map(fn (CommandRow $row): int => $row->id, app(FilterOverview::class)->recentCommands());

    expect($ids)->toHaveCount(20)->and($ids[0])->toBe(25)->and($ids[19])->toBe(6);
});

it('names who requested each command', function () {
    $admin = User::factory()->admin()->create(['name' => 'Sara']);

    expect(rowFor(FilterCommand::factory()->create(['created_by' => $admin->id]))->requestedBy)->toBe('Sara')
        ->and(rowFor(FilterCommand::factory()->create(['created_by' => 999]))->requestedBy)->toBe('#999');
});

it('marks pending commands whose target is offline', function () {
    reportStatus('Agent1');
    $online = FilterCommand::factory()->create(['target' => 'agent1']);
    $offline = FilterCommand::factory()->create(['target' => 'gateway']);
    $done = FilterCommand::factory()->create(['target' => 'gateway', 'status' => CommandStatus::Done]);

    expect(rowFor($online)->waitingOffline)->toBeFalse()
        ->and(rowFor($offline)->waitingOffline)->toBeTrue()
        ->and(rowFor($done)->waitingOffline)->toBeFalse();
});

it('treats "all" as offline only when no module is online', function () {
    $all = FilterCommand::factory()->create(['target' => 'all']);
    expect(rowFor($all)->waitingOffline)->toBeTrue();

    reportStatus('Download');
    expect(rowFor($all)->waitingOffline)->toBeFalse();
});

it('flags commands not picked up after two minutes', function (int $age, bool $flagged) {
    $command = FilterCommand::factory()->create(['created_at' => now('UTC')->subSeconds($age)]);

    expect(rowFor($command)->notPickedUp)->toBe($flagged);
})->with([[120, false], [121, true]]);

it('shortens long results and keeps the mode of mode changes', function () {
    $long = FilterCommand::factory()->create(['status' => CommandStatus::Failed, 'result' => str_repeat('x', 500)]);
    $mode = FilterCommand::factory()->create(['command' => CommandName::SetGatewayMode, 'target' => 'gateway', 'payload' => ['mode' => 'public']]);

    expect(mb_strlen(rowFor($long)->shortResult))->toBeLessThanOrEqual(201)
        ->and(rowFor($long)->result)->toHaveLength(500)
        ->and(rowFor($mode)->mode?->value)->toBe('public');
});
