<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = $this->signIn(User::factory()->owner()->withTwoFactor()->create());
});

function validNewUser(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sara',
        'email' => 'Sara@Example.test',
        'role' => 'admin',
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
    ], $overrides);
}

it('creates a staff user who must change the password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(validNewUser())
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'sara@example.test')->sole();

    expect($user)
        ->role->toBe(UserRole::Admin)
        ->must_change_password->toBeTrue()
        ->is_active->toBeTrue();

    expect(AuditLog::where('action', 'user.created')->sole())
        ->actor_id->toBe($this->owner->id)
        ->new_values->toBe(['name' => 'Sara', 'email' => 'sara@example.test', 'role' => 'admin']);
});

it('rejects a duplicate email, a weak password and the owner role', function (array $data, string $field) {
    User::factory()->create(['email' => 'taken@example.test']);

    Livewire::test(CreateUser::class)
        ->fillForm(validNewUser($data))
        ->call('create')
        ->assertHasFormErrors([$field]);

    expect(User::count())->toBe(2);
})->with([
    'duplicate email' => [['email' => 'TAKEN@example.test'], 'email'],
    'weak password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'owner role' => [['role' => 'owner'], 'role'],
]);

it('edits name, email and role and audits only what changed', function () {
    $user = User::factory()->viewer()->create(['name' => 'Old Name', 'email' => 'old@example.test']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Old Name', 'email' => 'old@example.test', 'role' => 'admin'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->role)->toBe(UserRole::Admin);

    $entry = AuditLog::where('action', 'user.updated')->sole();
    expect($entry->old_values)->toBe(['role' => 'viewer'])
        ->and($entry->new_values)->toBe(['role' => 'admin']);
});
