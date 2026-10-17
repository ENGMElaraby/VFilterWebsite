<?php

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('hides the forgot-password path when no mail server is configured', function () {
    config(['mail.default' => 'log']);

    Livewire::test(Login::class)->assertDontSee('Forgot password?');
    $this->get('/admin/password-reset/request')->assertNotFound();
});

it('answers neutrally and emails only known users', function () {
    config(['mail.default' => 'smtp']);
    Notification::fake();
    $user = User::factory()->create(['email' => 'ann@example.test']);

    Livewire::test(Login::class)->assertSee('Forgot password?');

    foreach (['ann@example.test', 'nobody@example.test'] as $email) {
        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $email])
            ->call('request')
            ->assertNotified();
    }

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    Notification::assertCount(1);

    expect(AuditLog::where('action', 'auth.password_reset_requested')->pluck('actor_label')->all())
        ->toBe(['ann@example.test', 'nobody@example.test']);
});

it('resets once with a valid link and ends existing sessions', function () {
    config(['mail.default' => 'smtp']);
    $user = User::factory()->withTwoFactor()->create(['email' => 'ann@example.test']);
    DB::table('sessions')->insert([
        'id' => 'old', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp,
    ]);
    $token = Password::broker()->createToken($user);

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->fillForm(['password' => 'reset-strong-pass-4', 'passwordConfirmation' => 'reset-strong-pass-4'])
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect(Hash::check('reset-strong-pass-4', $user->password))->toBeTrue()
        ->and($user->app_authentication_secret)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(AuditLog::where('action', 'auth.password_reset')->sole()->target_id)->toBe((string) $user->id)
        ->and(Password::broker()->tokenExists($user, $token))->toBeFalse();
});

it('expires reset links after 60 minutes', function () {
    expect(config('auth.passwords.users.expire'))->toBe(60);
});
