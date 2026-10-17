<?php

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\User;
use App\Services\AuditLogger;

it('lets owners and admins read the audit log', function (string $role) {
    $this->signIn(User::factory()->{$role}()->withTwoFactor()->create());
    $entry = app(AuditLogger::class)->log('user.created');

    $this->get('/admin/audit-logs')->assertOk();
    $this->get("/admin/audit-logs/{$entry->id}")->assertOk();
    expect(AuditLogResource::canAccess())->toBeTrue();
})->with(['owner', 'admin']);

it('refuses viewers by url and hides the menu item', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    $entry = app(AuditLogger::class)->log('user.created');

    $this->get('/admin/audit-logs')->assertForbidden();
    $this->get("/admin/audit-logs/{$entry->id}")->assertForbidden();
    expect(AuditLogResource::canAccess())->toBeFalse();
});

it('offers no way to create, edit or delete entries', function () {
    $owner = $this->signIn(User::factory()->owner()->withTwoFactor()->create());
    $entry = app(AuditLogger::class)->log('user.created');

    expect(array_keys(AuditLogResource::getPages()))->toBe(['index', 'view'])
        ->and($owner->can('create', $entry::class))->toBeFalse()
        ->and($owner->can('update', $entry))->toBeFalse()
        ->and($owner->can('delete', $entry))->toBeFalse();
});
