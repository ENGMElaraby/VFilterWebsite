<?php

use App\Enums\Filter\CommandName;
use App\Filament\Resources\FilterSessions\FilterSessionResource;
use App\Filament\Resources\FilterSessions\Pages\ListFilterSessions;
use App\Filament\Widgets\RecentCommandsWidget;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterSession;
use App\Models\User;
use Livewire\Livewire;

it('lists sessions with a count and refreshes every 5 seconds', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    $sessions = FilterSession::factory()->count(3)->create();

    $this->get(FilterSessionResource::getUrl())->assertOk();
    Livewire::test(ListFilterSessions::class)
        ->assertCanSeeTableRecords($sessions)
        ->assertSee(__('panel.sessions.heading', ['count' => 3]))
        ->assertSeeHtml('wire:poll.5s')
        ->assertTableActionHidden('kick', $sessions[0])
        ->assertTableActionHidden('ban', $sessions[0]);
});

it('filters by player', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    $hit = FilterSession::factory()->create(['charname' => 'Knight']);
    $miss = FilterSession::factory()->create(['charname' => 'Mage']);

    Livewire::test(ListFilterSessions::class)
        ->filterTable('charname', ['value' => 'Knight'])
        ->assertCanSeeTableRecords([$hit])
        ->assertCanNotSeeTableRecords([$miss]);
});

it('lets admins kick and ban, and shows them in recent commands', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $kicked = FilterSession::factory()->create(['username' => 'ali', 'charname' => 'Rogue']);
    $banned = FilterSession::factory()->create(['username' => 'sara', 'charname' => 'Knight']);

    Livewire::test(ListFilterSessions::class)
        ->callTableAction('kick', $kicked)
        ->assertNotified(__('panel.sessions.kick_queued', ['target' => 'Rogue']))
        ->callTableAction('ban', $banned, data: ['duration' => '10080', 'reason' => 'speed hack'])
        ->assertHasNoTableActionErrors()
        ->assertNotified(__('panel.sessions.ban_queued', ['target' => 'sara']));

    expect(FilterCommand::query()->orderBy('id')->get()->map(fn ($c) => [$c->command, $c->target])->all())
        ->toBe([[CommandName::Kick, 'Rogue'], [CommandName::Ban, 'sara']]);

    Livewire::test(RecentCommandsWidget::class)->assertSee('Rogue')->assertSee('sara')->assertSee(__('panel.sessions.durations.10080'));
});

it('requires a reason to ban and offers ban only for logged-in sessions', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $session = FilterSession::factory()->create(['username' => 'sara']);
    $anonymous = FilterSession::factory()->create(['username' => null, 'charname' => null]);

    Livewire::test(ListFilterSessions::class)
        ->assertTableActionHidden('ban', $anonymous)
        ->assertTableActionVisible('kick', $anonymous)
        ->callTableAction('ban', $session, data: ['duration' => '60', 'reason' => ''])
        ->assertHasTableActionErrors(['reason' => 'required']);

    expect(FilterCommand::count())->toBe(0);
});
