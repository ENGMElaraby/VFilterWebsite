<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\AuditLog;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('saves language and theme from the profile', function () {
    $user = $this->signIn();

    Livewire::test(EditProfile::class)
        ->fillForm(['locale' => 'ar', 'theme' => 'light'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh())->locale->toBe('ar')->theme->toBe('light');
});

it('needs the current password to change the password, then audits it', function () {
    $user = $this->signIn();

    Livewire::test(EditProfile::class)
        ->fillForm(['password' => 'profile-strong-pass-5', 'passwordConfirmation' => 'profile-strong-pass-5'])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'profile-strong-pass-5',
            'passwordConfirmation' => 'profile-strong-pass-5',
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('profile-strong-pass-5', $user->fresh()->password))->toBeTrue()
        ->and(AuditLog::where('action', 'user.password_changed')->sole()->actor_id)->toBe($user->id)
        ->and(json_encode(AuditLog::all()->toArray()))->not->toContain('profile-strong-pass-5');
});

it('needs the current password to change the email', function () {
    $this->signIn();

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'new-address@example.test'])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);
});

it('lets users regenerate recovery codes but not turn two-factor off', function () {
    $this->signIn();

    $names = collect(Filament::getMultiFactorAuthenticationProviders()['app']->getActions())
        ->map(fn ($action) => $action->getName());

    expect($names)->toContain('regenerateAppAuthenticationRecoveryCodes')
        ->not->toContain('disableAppAuthentication');
});
