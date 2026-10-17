<?php

use App\Filament\Pages\Auth\Login;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('signs in with password then authenticator code', function () {
    $user = User::factory()->admin()->withTwoFactor()->create(['email' => 'ann@example.test']);

    $page = Livewire::test(Login::class)
        ->fillForm(['email' => 'ann@example.test', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(Auth::check())->toBeFalse();

    $page->fillForm(['app' => ['code' => $this->totpFor($user)]], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertHasNoFormErrors(form: 'multiFactorChallengeForm');

    expect(Auth::id())->toBe($user->id)
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'auth.login')->sole())
        ->actor_type->toBe('user')
        ->actor_id->toBe($user->id);
});

it('lets a user without two-factor in, so they can enrol', function () {
    $user = User::factory()->create(['email' => 'new@example.test']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'new@example.test', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(Auth::id())->toBe($user->id);

    $this->get('/admin')->assertRedirect('/admin/multi-factor-authentication/set-up');
});

it('shows the same error for a wrong password, unknown email and disabled user', function (string $email, string $password) {
    User::factory()->create(['email' => 'ann@example.test']);
    User::factory()->inactive()->create(['email' => 'off@example.test']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate')
        ->assertHasFormErrors(['email' => __('filament-panels::auth/pages/login.messages.failed')]);

    expect(Auth::check())->toBeFalse()
        ->and(AuditLog::where('action', 'auth.login_failed')->sole())
        ->actor_type->toBe('guest')
        ->actor_label->toBe($email);
})->with([
    'wrong password' => ['ann@example.test', 'not-the-password'],
    'unknown email' => ['nobody@example.test', 'password'],
    'disabled user' => ['off@example.test', 'password'],
]);

it('audits sign-out', function () {
    $user = $this->signIn();

    $this->post('/admin/logout')->assertRedirect();

    expect(AuditLog::where('action', 'auth.logout')->sole()->actor_id)->toBe($user->id);
});
