<?php

use App\Enums\Filter\CommandName;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandInProgressException;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    seedFilterModules(agents: 3);
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
});

it('queues one command per enabled agent', function () {
    $result = app(CommandQueue::class)->queueForAllAgents(CommandName::Restart);

    expect(FilterCommand::query()->orderBy('id')->pluck('target')->all())->toBe(['agent1', 'agent2', 'agent3'])
        ->and($result->queued)->toHaveCount(3)
        ->and($result->skipped)->toBe([]);
});

it('skips agents that already have a command in progress', function () {
    FilterCommand::factory()->create(['command' => CommandName::Restart, 'target' => 'agent2']);

    $result = app(CommandQueue::class)->queueForAllAgents(CommandName::Restart);

    expect(array_map(fn (FilterCommand $command): string => $command->target, $result->queued))->toBe(['agent1', 'agent3'])
        ->and($result->skipped)->toBe(['Agent2']);
});

it('leaves out disabled agents', function () {
    FilterModule::query()->where('name', 'Agent3')->update(['enabled' => false]);

    app(CommandQueue::class)->queueForAllAgents(CommandName::Stop);

    expect(FilterCommand::query()->pluck('target')->all())->toBe(['agent1', 'agent2']);
});

it('fails when every agent is busy', function () {
    foreach (['agent1', 'agent2', 'agent3'] as $target) {
        FilterCommand::factory()->create(['command' => CommandName::Start, 'target' => $target]);
    }

    expect(fn () => app(CommandQueue::class)->queueForAllAgents(CommandName::Restart))
        ->toThrow(CommandInProgressException::class);
    expect(FilterCommand::count())->toBe(3);
});

it('refuses when no agent is enabled', function () {
    FilterModule::query()->where('type', 'agent')->update(['enabled' => false]);

    expect(fn () => app(CommandQueue::class)->queueForAllAgents(CommandName::Restart))
        ->toThrow(InvalidArgumentException::class);
});

it('skips an agent whose lock another request holds', function () {
    $lock = Cache::lock('filter-command:agent2', 10);
    $lock->get();

    try {
        $result = app(CommandQueue::class)->queueForAllAgents(CommandName::Restart);
    } finally {
        $lock->release();
    }

    expect($result->skipped)->toBe(['Agent2'])->and(FilterCommand::query()->pluck('target')->all())->toBe(['agent1', 'agent3']);
});
