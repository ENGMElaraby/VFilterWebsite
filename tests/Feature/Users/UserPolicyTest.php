<?php

use App\Filament\Resources\Users\UserResource;
use App\Models\User;

it('lets only the owner open user management', function () {
    $this->signIn(User::factory()->owner()->withTwoFactor()->create());

    $this->get('/admin/users')->assertOk();
    expect(UserResource::canViewAny())->toBeTrue();
});

it('refuses admins and viewers, by url and in the menu', function (string $role) {
    $this->signIn(User::factory()->{$role}()->withTwoFactor()->create());
    $other = User::factory()->viewer()->create();

    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/users/create')->assertForbidden();
    $this->get("/admin/users/{$other->id}/edit")->assertForbidden();

    // Filament hides menu items the user cannot access.
    expect(UserResource::canAccess())->toBeFalse();
})->with(['admin', 'viewer']);

it('protects the owner row from every change in the panel', function () {
    $owner = $this->signIn(User::factory()->owner()->withTwoFactor()->create());
    $staff = User::factory()->admin()->create();

    expect($owner->can('update', $owner))->toBeFalse()
        ->and($owner->can('delete', $owner))->toBeFalse()
        ->and($owner->can('update', $staff))->toBeTrue()
        ->and($owner->can('delete', $staff))->toBeFalse();

    $this->get("/admin/users/{$owner->id}/edit")->assertForbidden();
});

it('never allows a second owner', function () {
    User::factory()->owner()->create();

    expect(fn () => User::factory()->owner()->create())->toThrow(LogicException::class);
});
