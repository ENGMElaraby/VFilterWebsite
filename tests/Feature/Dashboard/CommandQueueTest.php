<?php

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandInProgressException;
use App\Models\AuditLog;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC'));
    seedFilterModules(agents: 2);
    $this->admin = $this->signIn(User::factory()->admin()->withTwoFactor()->create());
});

it('queues a pending command and audits it', function () {
    $command = app(CommandQueue::class)->queue(CommandName::Restart, 'agent1');

    expect($command->fresh())
        ->command->toBe(CommandName::Restart)
        ->target->toBe('agent1')
        ->status->toBe(CommandStatus::Pending)
        ->created_by->toBe($this->admin->id)
        ->and($command->fresh()->created_at->toIso8601String())->toBe('2026-10-17T12:00:00+00:00');

    $entry = AuditLog::query()->where('action', 'command.queued')->sole();
    expect($entry->target_id)->toBe((string) $command->id)
        ->and($entry->new_values)->toBe(['command' => 'restart', 'target' => 'agent1', 'payload' => null]);
});

it('accepts all, gateway and download as targets', function (string $target) {
    expect(app(CommandQueue::class)->queue(CommandName::Start, $target)->target)->toBe($target);
})->with(['all', 'gateway', 'download', 'agent2']);

it('refuses unknown, disabled and non-module targets', function (string $target) {
    FilterModule::query()->where('name', 'Agent2')->update(['enabled' => false]);

    expect(fn () => app(CommandQueue::class)->queue(CommandName::Start, $target))
        ->toThrow(InvalidArgumentException::class);
    expect(FilterCommand::count())->toBe(0);
})->with(['agent3', 'agent2', 'Agent1', 'someplayer', '']);

it('only queues start, stop and restart', function () {
    expect(fn () => app(CommandQueue::class)->queue(CommandName::Kick, 'agent1'))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    expect(fn () => app(CommandQueue::class)->queue(CommandName::Stop, 'gateway'))
        ->toThrow(AuthorizationException::class);
    expect(FilterCommand::count())->toBe(0)->and(AuditLog::query()->where('action', 'command.queued')->count())->toBe(0);
});

it('refuses a second power command for a target while one is in progress', function (CommandStatus $status) {
    FilterCommand::factory()->create(['command' => CommandName::Start, 'target' => 'agent1', 'status' => $status]);

    expect(fn () => app(CommandQueue::class)->queue(CommandName::Stop, 'agent1'))
        ->toThrow(CommandInProgressException::class);
})->with([CommandStatus::Pending, CommandStatus::Running]);

it('allows a new power command once the previous one has finished', function (CommandStatus $status) {
    FilterCommand::factory()->create(['command' => CommandName::Start, 'target' => 'agent1', 'status' => $status]);

    app(CommandQueue::class)->queue(CommandName::Stop, 'agent1');

    expect(FilterCommand::count())->toBe(2);
})->with([CommandStatus::Done, CommandStatus::Failed, CommandStatus::Cancelled]);

it('does not let an "all" command block a single module', function () {
    app(CommandQueue::class)->queue(CommandName::Restart, 'all');
    app(CommandQueue::class)->queue(CommandName::Restart, 'agent1');

    expect(FilterCommand::count())->toBe(2);
});

it('writes nothing when the audit entry fails', function () {
    $this->mock(AuditLogger::class, function ($mock): void {
        $mock->shouldReceive('transaction')->andReturnUsing(fn (Closure $callback) => DB::connection('filter')->transaction($callback));
        $mock->shouldReceive('log')->andThrow(new RuntimeException('audit down'));
    });

    expect(fn () => app(CommandQueue::class)->queue(CommandName::Restart, 'agent1'))->toThrow(RuntimeException::class);
    expect(FilterCommand::count())->toBe(0);
});

it('refuses while another request holds the lock for the target', function () {
    $this->travelBack(); // Lock::block() measures its wait with the clock, so time must move.
    $lock = Cache::lock('filter-command:agent1', 10);
    $lock->get();

    try {
        expect(fn () => app(CommandQueue::class)->queue(CommandName::Restart, 'agent1'))
            ->toThrow(CommandInProgressException::class);
    } finally {
        $lock->release();
    }

    expect(FilterCommand::count())->toBe(0);
});
