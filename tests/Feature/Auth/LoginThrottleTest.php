<?php

use App\Filament\Pages\Auth\Login;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

function failLogin(string $email): void
{
    Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => 'wrong-password'])
        ->call('authenticate');
}

it('blocks the sixth attempt for the same email and ip, even with the right password', function () {
    User::factory()->create(['email' => 'ann@example.test']);

    foreach (range(1, 5) as $ignored) {
        failLogin('ann@example.test');
    }

    Livewire::test(Login::class)
        ->fillForm(['email' => 'ANN@example.test', 'password' => 'password'])
        ->call('authenticate')
        ->assertNotified();

    expect(Auth::check())->toBeFalse()
        ->and(AuditLog::where('action', 'auth.login_failed')->count())->toBe(5)
        ->and(AuditLog::where('action', 'auth.lockout')->sole()->actor_label)->toBe('ann@example.test');
});

it('does not lock other emails from the same ip because of one email', function () {
    User::factory()->create(['email' => 'bob@example.test']);

    foreach (range(1, 4) as $ignored) {
        failLogin('ann@example.test');
    }

    Livewire::test(Login::class)
        ->fillForm(['email' => 'bob@example.test', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(Auth::check())->toBeTrue();
});
