<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function seedSessionFor(User $user): void
{
    DB::table('sessions')->insert([
        'id' => str()->random(40),
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
}

it('resets the owner password and ends their sessions', function () {
    $owner = User::factory()->owner()->withTwoFactor()->create();
    $oldToken = $owner->remember_token;
    seedSessionFor($owner);

    $this->artisan('vfilter:reset-owner', ['--password' => true])
        ->expectsQuestion('New password', 'brand-new-password-9')
        ->expectsQuestion('Confirm password', 'brand-new-password-9')
        ->assertSuccessful();

    $owner->refresh();

    expect(Hash::check('brand-new-password-9', $owner->password))->toBeTrue()
        ->and($owner->remember_token)->not->toBe($oldToken)
        ->and($owner->app_authentication_secret)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0);

    expect(AuditLog::where('action', 'user.password_reset')->sole())
        ->actor_type->toBe('console')->target_id->toBe((string) $owner->id);
});

it('resets the owner two-factor', function () {
    $owner = User::factory()->owner()->withTwoFactor()->create();
    $owner->saveAppAuthenticationRecoveryCodes(['hashed-code']);
    $owner->save();
    seedSessionFor($owner);

    $this->artisan('vfilter:reset-owner', ['--two-factor' => true])->assertSuccessful();

    $owner->refresh();

    expect($owner->app_authentication_secret)->toBeNull()
        ->and($owner->app_authentication_recovery_codes)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0)
        ->and(AuditLog::where('action', 'user.two_factor_reset')->sole()->actor_type)->toBe('console');
});

it('needs at least one option and an existing owner', function () {
    $this->artisan('vfilter:reset-owner', ['--two-factor' => true])->assertFailed();

    User::factory()->owner()->create();

    $this->artisan('vfilter:reset-owner')->assertFailed();
});
