<?php

use App\Filament\Resources\FilterSessions\Pages\ListFilterSessions;
use App\Filter\CommandQueue;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterSession;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->signIn(User::factory()->admin()->withTwoFactor()->create()));

it('turns timed bans off by default', function () {
    expect(config('vfilter.timed_bans'))->toBeFalse()
        ->and(app(CommandQueue::class)->banDurations())->toBe([]);
});

it('refuses timed bans while they are off, and still queues permanent ones', function () {
    config(['vfilter.timed_bans' => false]);
    $session = FilterSession::factory()->create(['username' => 'sara']);

    expect(fn () => app(CommandQueue::class)->ban($session, 'cheating', 1440))
        ->toThrow(InvalidArgumentException::class, __('panel.sessions.errors.timed_bans_off'));
    expect(FilterCommand::count())->toBe(0);

    $command = app(CommandQueue::class)->ban($session, 'cheating', null);
    expect($command->payload['duration_minutes'])->toBeNull();
});

it('offers only Permanent in the ban dialog while timed bans are off', function () {
    config(['vfilter.timed_bans' => false]);
    $session = FilterSession::factory()->create(['username' => 'sara']);

    Livewire::test(ListFilterSessions::class)
        ->mountTableAction('ban', $session)
        ->assertTableActionDataSet(['duration' => 'permanent'])
        ->assertFormFieldExists('duration', 'mountedActionSchema0', fn ($field): bool => array_map('strval', array_keys($field->getOptions())) === ['permanent'])
        ->setTableActionData(['reason' => 'cheating'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect(FilterCommand::query()->sole()->payload['duration_minutes'])->toBeNull();
});

it('offers and accepts the timed lengths when they are on', function () {
    config(['vfilter.timed_bans' => true]);
    $session = FilterSession::factory()->create(['username' => 'sara']);

    expect(app(CommandQueue::class)->banDurations())->toBe(CommandQueue::BAN_DURATIONS);

    Livewire::test(ListFilterSessions::class)
        ->mountTableAction('ban', $session)
        ->assertTableActionDataSet(['duration' => '1440'])
        ->assertFormFieldExists('duration', 'mountedActionSchema0', fn ($field): bool => array_map('strval', array_keys($field->getOptions())) === ['60', '1440', '10080', '43200', 'permanent'])
        ->setTableActionData(['reason' => 'cheating', 'duration' => '43200'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect(FilterCommand::query()->sole()->payload['duration_minutes'])->toBe(43200);
});
