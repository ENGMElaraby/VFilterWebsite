<?php

use App\Enums\Filter\CommandStatus;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandAlreadyStartedException;
use App\Models\AuditLog;
use App\Models\Filter\FilterCommand;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC'));
    $this->admin = $this->signIn(User::factory()->admin()->withTwoFactor()->create());
});

it('cancels a pending command and audits it', function () {
    $command = FilterCommand::factory()->create();

    app(CommandQueue::class)->cancel($command);

    expect($command->fresh())
        ->status->toBe(CommandStatus::Cancelled)
        ->result->toBe('Cancelled by '.$this->admin->email)
        ->and($command->fresh()->processed_at->toIso8601String())->toBe('2026-10-17T12:00:00+00:00');

    $entry = AuditLog::query()->where('action', 'command.cancelled')->sole();
    expect($entry->old_values)->toBe(['status' => 'pending'])->and($entry->new_values)->toBe(['status' => 'cancelled']);
});

it('refuses to cancel a command that is no longer pending', function (CommandStatus $status) {
    $command = FilterCommand::factory()->create(['status' => $status]);

    expect(fn () => app(CommandQueue::class)->cancel($command))->toThrow(CommandAlreadyStartedException::class);
    expect($command->fresh()->status)->toBe($status)
        ->and(AuditLog::query()->where('action', 'command.cancelled')->count())->toBe(0);
})->with([CommandStatus::Running, CommandStatus::Done, CommandStatus::Failed, CommandStatus::Cancelled]);

it('loses the race when the filter claims the command first', function () {
    $command = FilterCommand::factory()->create();
    FilterCommand::query()->whereKey($command->id)->toBase()->update(['status' => 'running']);

    expect(fn () => app(CommandQueue::class)->cancel($command))->toThrow(CommandAlreadyStartedException::class);
    expect($command->fresh()->status)->toBe(CommandStatus::Running)
        ->and(AuditLog::query()->where('action', 'command.cancelled')->count())->toBe(0);
});

it('refuses viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    $command = FilterCommand::factory()->create();

    expect(fn () => app(CommandQueue::class)->cancel($command))->toThrow(AuthorizationException::class);
    expect($command->fresh()->status)->toBe(CommandStatus::Pending);
});
