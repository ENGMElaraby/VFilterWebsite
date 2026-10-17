<?php

use App\Auth\MandatoryAppAuthentication;
use App\Filament\Pages\Auth\Login;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\EditProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function userWithRecoveryCodes(array $codes): User
{
    $user = User::factory()->withTwoFactor()->create(['email' => 'ann@example.test']);
    AppAuthentication::make()->saveRecoveryCodes($user, $codes);
    $user->save();

    return $user;
}

function startChallenge(): Testable
{
    return Livewire::test(Login::class)
        ->fillForm(['email' => 'ann@example.test', 'password' => 'password'])
        ->call('authenticate');
}

it('forces a signed-in user without two-factor to set it up first', function () {
    $this->actingAs(User::factory()->owner()->create());

    $this->get('/admin')->assertRedirect('/admin/multi-factor-authentication/set-up');
    $this->get('/admin/multi-factor-authentication/set-up')->assertOk();
});

it('lets an enrolled user through', function () {
    $this->signIn();

    $this->get('/admin')->assertOk();
});

it('audits enrolment', function () {
    $user = $this->signIn(User::factory()->create());

    app(MandatoryAppAuthentication::class)->saveSecret($user, 'NEWSECRET');

    expect(AuditLog::where('action', 'auth.two_factor_enabled')->sole()->actor_id)->toBe($user->id);
});

it('rejects a reused authenticator code', function () {
    $user = userWithRecoveryCodes(['code-one']);
    $code = $this->totpFor($user);

    startChallenge()
        ->fillForm(['app' => ['code' => $code]], 'multiFactorChallengeForm')
        ->call('authenticate');

    expect(Auth::id())->toBe($user->id);
    Auth::logout();

    startChallenge()
        ->fillForm(['app' => ['code' => $code]], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertHasFormErrors(['app.code'], 'multiFactorChallengeForm');

    expect(Auth::check())->toBeFalse()
        ->and(AuditLog::where('action', 'auth.two_factor_failed')->sole())
        ->actor_type->toBe('guest')
        ->target_id->toBe((string) $user->id);
});

it('accepts a recovery code once', function () {
    $user = userWithRecoveryCodes(['code-one', 'code-two']);

    startChallenge()
        ->fillForm(['app' => ['useRecoveryCode' => true, 'recoveryCode' => 'code-one']], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertHasNoFormErrors(form: 'multiFactorChallengeForm');

    expect(Auth::id())->toBe($user->id)
        ->and(AuditLog::where('action', 'auth.recovery_code_used')->sole()->target_id)->toBe((string) $user->id);
    Auth::logout();

    startChallenge()
        ->fillForm(['app' => ['useRecoveryCode' => true, 'recoveryCode' => 'code-one']], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertHasFormErrors(['app.recoveryCode'], 'multiFactorChallengeForm');

    expect(Auth::check())->toBeFalse();
});

it('throttles wrong authenticator codes', function () {
    userWithRecoveryCodes(['code-one']);
    $page = startChallenge();

    // Filament allows 5 sign-in requests per IP per minute; the password step was the first.
    foreach (range(1, 4) as $ignored) {
        $page->fillForm(['app' => ['code' => '000000']], 'multiFactorChallengeForm')->call('authenticate');
    }

    $page->fillForm(['app' => ['code' => '000000']], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertNotified();

    expect(Auth::check())->toBeFalse()
        ->and(AuditLog::where('action', 'auth.two_factor_failed')->count())->toBe(4);
});

it('offers no way to turn two-factor off', function () {
    $this->signIn();

    $actionNames = collect(app(MandatoryAppAuthentication::class)->getActions())
        ->map(fn ($action) => $action->getName());

    expect($actionNames)->not->toContain('disableAppAuthentication');

    Livewire::test(EditProfile::class)->assertActionDoesNotExist('disableAppAuthentication');
});
