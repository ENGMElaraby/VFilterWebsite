<?php

use App\Models\AuditLog;
use App\Models\Filter\FilterLog;
use App\Models\Filter\FilterModule;
use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/*
 * Expected result of GET on every panel page, per visitor (SC-003).
 * 'ok' = 200, 'login' = redirect to sign-in, 'deny' = 403, 'skip' = not a page for this check
 * (guest-only auth pages, or pages that redirect by design).
 */
const PANEL_ACCESS = [
    'filament.admin.pages.dashboard' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.pages.connection-health' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.filter-modules.index' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.filter-modules.edit' => ['guest' => 'login', 'viewer' => 'deny', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.filter-logs.index' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.filter-logs.view' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.filter-sessions.index' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.pages.security.allowed-ips' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.pages.security.proxy-bypass-ips' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.pages.security.proxy-bypass-usernames' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.pages.security.blocked-ips' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.pages.security.blocked-hwids' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.pages.security.net-cafe-ips' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.pages.security.blocked-words' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.game-assistants.index' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.auth.profile' => ['guest' => 'login', 'viewer' => 'ok', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.audit-logs.index' => ['guest' => 'login', 'viewer' => 'deny', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.audit-logs.view' => ['guest' => 'login', 'viewer' => 'deny', 'admin' => 'ok', 'owner' => 'ok'],
    'filament.admin.resources.users.index' => ['guest' => 'login', 'viewer' => 'deny', 'admin' => 'deny', 'owner' => 'ok'],
    'filament.admin.resources.users.create' => ['guest' => 'login', 'viewer' => 'deny', 'admin' => 'deny', 'owner' => 'ok'],
    'filament.admin.resources.users.edit' => ['guest' => 'login', 'viewer' => 'deny', 'admin' => 'deny', 'owner' => 'ok'],
    'filament.admin.pages.change-password' => ['guest' => 'login', 'viewer' => 'skip', 'admin' => 'skip', 'owner' => 'skip'],
    'filament.admin.auth.multi-factor-authentication.set-up-required' => ['guest' => 'login', 'viewer' => 'skip', 'admin' => 'skip', 'owner' => 'skip'],
    'filament.admin.auth.login' => ['guest' => 'ok', 'viewer' => 'skip', 'admin' => 'skip', 'owner' => 'skip'],
    'filament.admin.auth.password-reset.request' => ['guest' => 'skip', 'viewer' => 'skip', 'admin' => 'skip', 'owner' => 'skip'],
    'filament.admin.auth.password-reset.reset' => ['guest' => 'skip', 'viewer' => 'skip', 'admin' => 'skip', 'owner' => 'skip'],
    'panel.locale' => ['guest' => 'skip', 'viewer' => 'skip', 'admin' => 'skip', 'owner' => 'skip'],
];

/**
 * @return list<string>
 */
function panelGetRouteNames(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => in_array('GET', $route->methods(), true)
            && str_starts_with($route->uri(), 'admin'))
        ->map(fn (RouteDefinition $route): ?string => $route->getName())
        ->filter()
        ->sort()
        ->values()
        ->all();
}

it('has an access rule for every panel page', function () {
    $names = panelGetRouteNames();
    sort($names);
    $covered = array_keys(PANEL_ACCESS);
    sort($covered);

    expect($names)->toBe($covered);
});

it('enforces the access rules', function (string $visitor) {
    $owner = User::factory()->owner()->withTwoFactor()->create();
    $staff = User::factory()->viewer()->withTwoFactor()->create();
    $module = FilterModule::factory()->create();
    $log = FilterLog::factory()->create();
    $entry = AuditLog::query()->create(['created_at' => now(), 'actor_type' => 'system', 'action' => 'user.created']);

    if ($visitor !== 'guest') {
        $this->actingAs($visitor === 'owner' ? $owner : User::factory()->{$visitor}()->withTwoFactor()->create());
    }

    foreach (PANEL_ACCESS as $name => $rules) {
        $expected = $rules[$visitor];

        if ($expected === 'skip') {
            continue;
        }

        $parameters = match ($name) {
            'filament.admin.resources.audit-logs.view' => ['record' => $entry->id],
            'filament.admin.resources.users.edit' => ['record' => $staff->id],
            'filament.admin.resources.filter-modules.edit' => ['record' => $module->id],
            'filament.admin.resources.filter-logs.view' => ['record' => $log->id],
            default => [],
        };

        $response = $this->get(route($name, $parameters));

        match ($expected) {
            'ok' => $response->assertOk(),
            'deny' => $response->assertForbidden(),
            'login' => $response->assertRedirect(route('filament.admin.auth.login')),
        };
    }
})->with(['guest', 'viewer', 'admin', 'owner']);
