<?php

use App\Filament\Widgets\ModuleStatusWidget;
use App\Filament\Widgets\OnlinePlayersWidget;
use App\Filament\Widgets\RecentCommandsWidget;
use App\Filter\Exceptions\FilterDatabaseUnavailableException;
use App\Filter\FilterOverview;
use App\Models\Filter\FilterModule;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    seedFilterModules(agents: 2);
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
});

it('shows the dashboard with every module to viewers', function () {
    reportStatus('Agent1', ['online_count' => 40]);

    $this->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(ModuleStatusWidget::class)
        ->assertSeeLivewire(OnlinePlayersWidget::class)
        ->assertSeeLivewire(RecentCommandsWidget::class);

    Livewire::test(ModuleStatusWidget::class)
        ->assertSeeInOrder(['Gateway', 'Download', 'Agent1', 'Agent2'])
        ->assertSee(__('panel.module_card.online'))
        ->assertSee(__('panel.module_card.never_reported'));

    Livewire::test(OnlinePlayersWidget::class)->assertSee('40');
});

it('refreshes every 5 seconds', function () {
    Livewire::test(ModuleStatusWidget::class)->assertSeeHtml('wire:poll.5s');
    Livewire::test(OnlinePlayersWidget::class)->assertSeeHtml('wire:poll.5s');
    Livewire::test(RecentCommandsWidget::class)->assertSeeHtml('wire:poll.5s');
});

it('shows a banner instead of failing when VFILTER is unreachable', function () {
    $this->mock(FilterOverview::class, function ($mock): void {
        $mock->shouldReceive('modules')->andThrow(new FilterDatabaseUnavailableException);
        $mock->shouldReceive('onlinePlayers')->andThrow(new FilterDatabaseUnavailableException);
    });

    Livewire::test(ModuleStatusWidget::class)
        ->assertSee(__('panel.dashboard.database_unavailable'))
        ->assertDontSee('Gateway');
    Livewire::test(OnlinePlayersWidget::class)->assertSee('—');
});

it('marks disabled modules', function () {
    FilterModule::query()->where('name', 'Agent2')->update(['enabled' => false]);

    Livewire::test(ModuleStatusWidget::class)->assertSee(__('panel.module_card.disabled'));
});

it('escapes text reported by the filter', function () {
    reportStatus('Agent1', ['last_error' => '<script>alert(1)</script>']);

    Livewire::test(ModuleStatusWidget::class)
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSee('<script>alert(1)</script>');
});
