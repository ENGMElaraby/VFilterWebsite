<?php

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Enums\Filter\GatewayMode;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandInProgressException;
use App\Filter\Exceptions\GatewayModeUnchangedException;
use App\Filter\FilterOverview;
use App\Filter\ModuleCard;
use App\Models\AuditLog;
use App\Models\Filter\FilterCommand;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    seedFilterModules();
    reportStatus('Gateway', ['gateway_mode' => GatewayMode::Gm->value]);
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
});

function gatewayCard(): ModuleCard
{
    return app(FilterOverview::class)->modules()[0];
}

it('queues a gateway mode change with the mode in the payload', function () {
    $command = app(CommandQueue::class)->setGatewayMode(GatewayMode::Public);

    expect($command->fresh())
        ->command->toBe(CommandName::SetGatewayMode)
        ->target->toBe('gateway')
        ->payload->toBe(['mode' => 'public']);
    expect(AuditLog::query()->where('action', 'command.queued')->sole()->new_values['payload'])->toBe(['mode' => 'public']);
});

it('refuses the mode the gateway is already in', function () {
    expect(fn () => app(CommandQueue::class)->setGatewayMode(GatewayMode::Gm))
        ->toThrow(GatewayModeUnchangedException::class);
    expect(FilterCommand::count())->toBe(0);
});

it('allows only one mode change at a time, next to power commands', function () {
    app(CommandQueue::class)->setGatewayMode(GatewayMode::Public);

    expect(fn () => app(CommandQueue::class)->setGatewayMode(GatewayMode::Inspection))
        ->toThrow(CommandInProgressException::class);

    app(CommandQueue::class)->queue(CommandName::Restart, 'gateway');
    expect(FilterCommand::count())->toBe(2);
});

it('refuses viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    expect(fn () => app(CommandQueue::class)->setGatewayMode(GatewayMode::Public))
        ->toThrow(AuthorizationException::class);
});

it('shows the pending mode on the gateway card until the change is done', function () {
    $command = app(CommandQueue::class)->setGatewayMode(GatewayMode::Public);
    expect(gatewayCard()->pendingMode)->toBe(GatewayMode::Public);

    $command->forceFill(['status' => CommandStatus::Running])->save();
    expect(gatewayCard()->pendingMode)->toBe(GatewayMode::Public);

    $command->forceFill(['status' => CommandStatus::Done])->save();
    expect(gatewayCard()->pendingMode)->toBeNull();
});
