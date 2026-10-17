<?php

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Enums\Filter\GatewayMode;
use App\Filament\Widgets\ModuleStatusWidget;
use App\Filament\Widgets\RecentCommandsWidget;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    seedFilterModules(agents: 2);
    reportStatus('Gateway', ['gateway_mode' => GatewayMode::Gm->value]);
});

function asAdmin(): void
{
    test()->signIn(User::factory()->admin()->withTwoFactor()->create());
}

function moduleAction(string $name, array $arguments): TestAction
{
    return TestAction::make($name)->arguments($arguments);
}

it('restarts a module after confirmation', function () {
    asAdmin();

    Livewire::test(ModuleStatusWidget::class)
        ->mountAction(moduleAction('restart', ['target' => 'agent1']))
        ->assertMountedActionModalSee(__('panel.commands.confirm_restart', ['target' => 'Agent1']))
        ->callMountedAction()
        ->assertNotified();

    expect(FilterCommand::query()->sole())
        ->command->toBe(CommandName::Restart)
        ->target->toBe('agent1');
});

it('queues nothing when the confirmation is cancelled', function () {
    asAdmin();

    Livewire::test(ModuleStatusWidget::class)
        ->mountAction(moduleAction('stop', ['target' => 'gateway']))
        ->unmountAction();

    expect(FilterCommand::count())->toBe(0);
});

it('starts without a confirmation', function () {
    asAdmin();

    Livewire::test(ModuleStatusWidget::class)->callAction(moduleAction('start', ['target' => 'download']));

    expect(FilterCommand::query()->sole()->target)->toBe('download');
});

it('queues for all agents and for the whole filter', function () {
    asAdmin();

    Livewire::test(ModuleStatusWidget::class)
        ->callAction(moduleAction('restart', ['target' => ModuleStatusWidget::ALL_AGENTS]))
        ->callAction(moduleAction('stop', ['target' => 'all']));

    expect(FilterCommand::query()->orderBy('id')->pluck('target')->all())->toBe(['agent1', 'agent2', 'all']);
});

it('tells the admin when a command is already in progress', function () {
    asAdmin();
    FilterCommand::factory()->create(['command' => CommandName::Start, 'target' => 'agent1']);

    Livewire::test(ModuleStatusWidget::class)
        ->callAction(moduleAction('restart', ['target' => 'agent1']))
        ->assertNotified(__('panel.commands.errors.in_progress', ['target' => 'agent1']));

    expect(FilterCommand::count())->toBe(1);
});

it('switches the gateway mode, warning before going public', function () {
    asAdmin();

    Livewire::test(ModuleStatusWidget::class)
        ->mountAction(moduleAction('gatewayMode', ['mode' => 'public']))
        ->assertMountedActionModalSee(__('panel.commands.confirm_public'))
        ->callMountedAction();

    expect(FilterCommand::query()->sole()->payload)->toBe(['mode' => 'public']);
});

it('gives viewers no controls', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    Livewire::test(ModuleStatusWidget::class)
        ->assertActionHidden(moduleAction('restart', ['target' => 'agent1']))
        ->assertActionHidden(moduleAction('start', ['target' => 'all']))
        ->assertActionHidden(moduleAction('gatewayMode', ['mode' => 'public']));
    // A crafted call is refused server-side too: CommandQueueTest "refuses viewers".
});

it('hides controls on disabled modules', function () {
    asAdmin();
    FilterModule::query()->where('name', 'Agent2')->update(['enabled' => false]);

    Livewire::test(ModuleStatusWidget::class)
        ->assertActionHidden(moduleAction('restart', ['target' => 'agent2']))
        ->assertActionVisible(moduleAction('restart', ['target' => 'agent1']));
});

it('cancels pending commands from the list, for admins only', function () {
    $pending = FilterCommand::factory()->create();
    $running = FilterCommand::factory()->create(['status' => CommandStatus::Running, 'target' => 'gateway']);

    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    Livewire::test(RecentCommandsWidget::class)->assertTableActionHidden('cancel', $pending);

    asAdmin();
    Livewire::test(RecentCommandsWidget::class)
        ->assertTableActionHidden('cancel', $running)
        ->callTableAction('cancel', $pending)
        ->assertNotified(__('panel.commands.cancelled'));

    expect($pending->fresh()->status)->toBe(CommandStatus::Cancelled);
});

it('shows command status and pickup hints in the list', function () {
    asAdmin();
    FilterCommand::factory()->create(['target' => 'gateway', 'created_at' => now('UTC')->subMinutes(5)]);
    FilterCommand::factory()->create(['target' => 'agent1', 'status' => CommandStatus::Failed, 'result' => 'port in use']);

    Livewire::test(RecentCommandsWidget::class)
        ->assertSee(__('panel.commands.not_picked_up'))
        ->assertSee(__('panel.commands.statuses.failed'))
        ->assertSee('port in use');
});
