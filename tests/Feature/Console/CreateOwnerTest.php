<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the first owner from the console', function () {
    $this->artisan('vfilter:create-owner', ['--email' => 'Owner@Example.test', '--name' => 'Owner'])
        ->expectsQuestion('Password', 'a-strong-password-1')
        ->expectsQuestion('Confirm password', 'a-strong-password-1')
        ->assertSuccessful();

    $owner = User::sole();

    expect($owner)
        ->email->toBe('owner@example.test')
        ->role->toBe(UserRole::Owner)
        ->is_active->toBeTrue()
        ->must_change_password->toBeFalse()
        ->app_authentication_secret->toBeNull()
        ->and(Hash::check('a-strong-password-1', $owner->password))->toBeTrue();

    expect(AuditLog::sole())
        ->action->toBe('user.created')
        ->actor_type->toBe('console')
        ->target_id->toBe((string) $owner->id)
        ->new_values->toBe(['name' => 'Owner', 'email' => 'owner@example.test', 'role' => 'owner']);
});

it('asks for missing email and name', function () {
    $this->artisan('vfilter:create-owner')
        ->expectsQuestion('Email', 'owner@example.test')
        ->expectsQuestion('Name', 'Owner')
        ->expectsQuestion('Password', 'a-strong-password-1')
        ->expectsQuestion('Confirm password', 'a-strong-password-1')
        ->assertSuccessful();

    expect(User::sole()->role)->toBe(UserRole::Owner);
});

it('refuses to create a second owner', function () {
    User::factory()->owner()->create();

    $this->artisan('vfilter:create-owner', ['--email' => 'second@example.test', '--name' => 'Second'])
        ->assertFailed();

    expect(User::count())->toBe(1);
});

it('refuses a taken email or a weak or mismatched password', function (string $email, string $password, string $confirmation) {
    User::factory()->admin()->create(['email' => 'taken@example.test']);

    $this->artisan('vfilter:create-owner', ['--email' => $email, '--name' => 'Owner'])
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->assertFailed();

    expect(User::where('role', 'owner')->exists())->toBeFalse()
        ->and(AuditLog::count())->toBe(0);
})->with([
    'taken email' => ['TAKEN@example.test', 'a-strong-password-1', 'a-strong-password-1'],
    'short password' => ['new@example.test', 'short-pass', 'short-pass'],
    'mismatch' => ['new@example.test', 'a-strong-password-1', 'a-strong-password-2'],
]);
