<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;

it('records the signed-in user as actor with ip and utc time', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');
    $user = User::factory()->admin()->create();
    $this->actingAs($user);
    request()->server->set('REMOTE_ADDR', '10.1.2.3');

    $entry = app(AuditLogger::class)->log('user.updated', $user, ['name' => 'Old'], ['name' => 'New']);

    expect($entry->fresh())
        ->actor_type->toBe('user')
        ->actor_id->toBe($user->id)
        ->actor_label->toBe($user->email)
        ->ip->toBe('10.1.2.3')
        ->action->toBe('user.updated')
        ->target_type->toBe('user')
        ->target_id->toBe((string) $user->id)
        ->old_values->toBe(['name' => 'Old'])
        ->new_values->toBe(['name' => 'New'])
        ->and($entry->fresh()->created_at->toDateTimeString())->toBe('2026-10-17 10:00:00');
});

it('records console and system actors', function () {
    $logger = app(AuditLogger::class);

    expect($logger->asConsole()->log('user.created')->ip)->toBeNull();

    expect($logger->asConsole()->log('user.created')->actor_type)->toBe('console')
        ->and($logger->asSystem()->log('user.created')->actor_type)->toBe('system')
        ->and($logger->log('user.created')->actor_type)->toBe('system');
});

it('records a guest actor with the attempted label', function () {
    $entry = app(AuditLogger::class)->log('auth.login_failed', actorType: 'guest', actorLabel: 'who@example.test');

    expect($entry)->actor_type->toBe('guest')->actor_id->toBeNull()->actor_label->toBe('who@example.test');
});

it('redacts secret keys at any depth', function () {
    $entry = app(AuditLogger::class)->log('user.updated', null, [], [
        'name' => 'Ann',
        'password' => 'secret-pass',
        'nested' => ['token' => 'abc', 'code' => '123456', 'keep' => 'yes'],
        'app_authentication_secret' => 'S3CR3T',
        'app_authentication_recovery_codes' => ['a', 'b'],
    ]);

    $json = json_encode($entry->fresh()->new_values);

    expect($entry->fresh()->new_values)->toBe([
        'name' => 'Ann',
        'password' => '[redacted]',
        'nested' => ['token' => '[redacted]', 'code' => '[redacted]', 'keep' => 'yes'],
        'app_authentication_secret' => '[redacted]',
        'app_authentication_recovery_codes' => '[redacted]',
    ])->and($json)->not->toContain('secret-pass')->not->toContain('S3CR3T')->not->toContain('123456');
});

it('commits a change and its entry together or not at all', function () {
    $logger = app(AuditLogger::class);

    expect(fn () => $logger->transaction(function () use ($logger) {
        User::factory()->create(['email' => 'rollback@example.test']);
        $logger->log('user.created');
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect(User::where('email', 'rollback@example.test')->exists())->toBeFalse()
        ->and(AuditLog::count())->toBe(0);

    $logger->transaction(function () use ($logger) {
        User::factory()->create(['email' => 'kept@example.test']);
        $logger->log('user.created');
    });

    expect(User::where('email', 'kept@example.test')->exists())->toBeTrue()
        ->and(AuditLog::count())->toBe(1);
});

it('refuses to update or delete entries', function () {
    $entry = app(AuditLogger::class)->log('user.created');

    expect(fn () => $entry->update(['action' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});
