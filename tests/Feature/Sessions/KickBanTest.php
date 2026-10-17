<?php

use App\Enums\Filter\CommandName;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandInProgressException;
use App\Models\AuditLog;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(fn () => $this->signIn(User::factory()->admin()->withTwoFactor()->create()));

it('kicks by character, else username, else IP', function (array $names, string $target) {
    $session = FilterSession::factory()->create(array_merge(['ip' => '10.1.1.1'], $names));

    $command = app(CommandQueue::class)->kick($session, 'afk');

    expect($command->fresh())
        ->command->toBe(CommandName::Kick)
        ->target->toBe($target)
        ->payload->toBe(['session_id' => $session->id, 'ip' => '10.1.1.1', 'reason' => 'afk'])
        ->and(AuditLog::query()->where('action', 'command.queued')->count())->toBe(1);
})->with([
    'character' => [['username' => 'sara', 'charname' => 'Knight'], 'Knight'],
    'username' => [['username' => 'sara', 'charname' => null], 'sara'],
    'ip' => [['username' => null, 'charname' => null], '10.1.1.1'],
]);

it('bans the game account for a chosen time', function (?int $minutes) {
    $session = FilterSession::factory()->create(['username' => 'sara', 'charname' => 'Knight']);

    $command = app(CommandQueue::class)->ban($session, 'speed hack', $minutes);

    expect($command->fresh())
        ->command->toBe(CommandName::Ban)
        ->target->toBe('sara')
        ->payload->toBe(['session_id' => $session->id, 'reason' => 'speed hack', 'duration_minutes' => $minutes]);
})->with([60, 1440, 10080, 43200, null]);

it('refuses bans it cannot apply', function (array $session, string $reason, ?int $minutes) {
    $row = FilterSession::factory()->create($session);

    expect(fn () => app(CommandQueue::class)->ban($row, $reason, $minutes))->toThrow(InvalidArgumentException::class);
    expect(FilterCommand::count())->toBe(0);
})->with([
    'no username' => [['username' => null], 'cheating', 60],
    'empty reason' => [['username' => 'sara'], '  ', 60],
    'long reason' => [['username' => 'sara'], str_repeat('x', 201), 60],
    'odd duration' => [['username' => 'sara'], 'cheating', 5],
]);

it('refuses a second kick or ban for the same player while one is in progress', function () {
    $session = FilterSession::factory()->create(['username' => 'sara', 'charname' => null]);
    app(CommandQueue::class)->kick($session);

    expect(fn () => app(CommandQueue::class)->ban($session, 'again', null))->toThrow(CommandInProgressException::class)
        ->and(fn () => app(CommandQueue::class)->kick($session))->toThrow(CommandInProgressException::class);
});

it('refuses viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    $session = FilterSession::factory()->create();

    expect(fn () => app(CommandQueue::class)->kick($session))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CommandQueue::class)->ban($session, 'x', null))->toThrow(AuthorizationException::class);
});
