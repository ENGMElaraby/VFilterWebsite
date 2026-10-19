<?php

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\NoticeAlreadyQueuedException;
use App\Models\AuditLog;
use App\Models\Filter\FilterCommand;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

it('queues a trimmed notice for all players and audits it', function (string $role) {
    $this->signIn(User::factory()->{$role}()->withTwoFactor()->create());

    $command = app(CommandQueue::class)->notice("  Server restarts in 10 minutes \n");

    expect($command->fresh())
        ->command->toBe(CommandName::Notice)
        ->target->toBe(CommandQueue::ALL)
        ->payload->toBe(['message' => 'Server restarts in 10 minutes'])
        ->status->toBe(CommandStatus::Pending);

    $entry = AuditLog::query()->where('action', 'command.queued')->sole();
    expect($entry->new_values)->toBe([
        'command' => 'notice',
        'target' => 'all',
        'payload' => ['message' => 'Server restarts in 10 minutes'],
    ]);
})->with(['admin', 'owner']);

it('refuses viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    expect(fn () => app(CommandQueue::class)->notice('hello'))->toThrow(AuthorizationException::class);
    expect(FilterCommand::count())->toBe(0);
});

it('refuses a message it cannot send', function (string $message, string $reason) {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    expect(CommandQueue::noticeError($message))->toBe(__('panel.notices.errors.'.$reason, ['max' => CommandQueue::NOTICE_MAX_LENGTH]))
        ->and(fn () => app(CommandQueue::class)->notice($message))->toThrow(InvalidArgumentException::class);
    expect(FilterCommand::count())->toBe(0);
})->with([
    'empty' => ['', 'required'],
    'spaces only' => ["   \n\t ", 'required'],
    'too long' => [str_repeat('x', 201), 'too_long'],
    'line break' => ["first line\nsecond line", 'one_line'],
    'tab' => ["a\tb", 'one_line'],
    'nul' => ["a\0b", 'one_line'],
]);

it('counts characters, not bytes', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $arabic = str_repeat('س', CommandQueue::NOTICE_MAX_LENGTH);

    expect(CommandQueue::noticeError($arabic))->toBeNull()
        ->and(app(CommandQueue::class)->notice($arabic)->payload)->toBe(['message' => $arabic]);
});

it('refuses the same message while it is still in progress', function (CommandStatus $status) {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $first = app(CommandQueue::class)->notice('Event starts now');
    FilterCommand::query()->whereKey($first->id)->update(['status' => $status->value]);

    expect(fn () => app(CommandQueue::class)->notice('  Event starts now '))->toThrow(NoticeAlreadyQueuedException::class);
    expect(FilterCommand::count())->toBe(1);
})->with([CommandStatus::Pending, CommandStatus::Running]);

it('allows a repeat once the first one finished, and different messages at once', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $first = app(CommandQueue::class)->notice('Event starts now');
    app(CommandQueue::class)->notice('Double drop is on');
    FilterCommand::query()->whereKey($first->id)->update(['status' => CommandStatus::Done->value]);

    app(CommandQueue::class)->notice('Event starts now');

    expect(FilterCommand::query()->where('command', 'notice')->count())->toBe(3);
});
