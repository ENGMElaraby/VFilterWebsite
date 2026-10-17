<?php

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = $this->signIn(User::factory()->owner()->withTwoFactor()->create());
});

function entryAt(string $time, array $attributes = []): AuditLog
{
    return AuditLog::query()->create(array_merge([
        'created_at' => Carbon::parse($time, 'UTC'),
        'actor_type' => 'system',
        'action' => 'user.created',
    ], $attributes));
}

it('lists newest first, 25 per page', function () {
    $old = entryAt('2026-10-01 10:00:00');
    $new = entryAt('2026-10-02 10:00:00');
    foreach (range(1, 30) as $i) {
        entryAt('2026-09-01 10:00:00');
    }

    Livewire::test(ListAuditLogs::class)
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertCountTableRecords(32)
        ->assertSet('tableRecordsPerPage', 25);
});

it('filters by actor, action and date range', function () {
    $sara = User::factory()->admin()->create();
    $mine = entryAt('2026-10-05 10:00:00', ['actor_type' => 'user', 'actor_id' => $sara->id, 'action' => 'user.updated']);
    $other = entryAt('2026-10-05 10:00:00', ['action' => 'auth.login']);
    $older = entryAt('2026-09-01 10:00:00', ['action' => 'user.updated']);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('actor_id', $sara->id)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other, $older]);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('action', 'user.updated')
        ->assertCanSeeTableRecords([$mine, $older])
        ->assertCanNotSeeTableRecords([$other]);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('created_at', ['from' => '2026-10-01', 'until' => '2026-10-31'])
        ->assertCanSeeTableRecords([$mine, $other])
        ->assertCanNotSeeTableRecords([$older]);
});

it('shows translated action labels and raw ids for unknown actions', function () {
    entryAt('2026-10-05 10:00:00', ['action' => 'user.created']);
    entryAt('2026-10-05 10:00:00', ['action' => 'future.thing']);

    Livewire::test(ListAuditLogs::class)
        ->assertSee('User created')
        ->assertSee('future.thing');
});

it('shows times in the display time zone', function () {
    config(['vfilter.display_timezone' => 'Africa/Cairo']);
    entryAt('2026-10-05 10:00:00');

    Livewire::test(ListAuditLogs::class)->assertSee('13:00');
});

it('shows old and new values on the detail page', function () {
    $entry = entryAt('2026-10-05 10:00:00', [
        'action' => 'user.updated',
        'old_values' => ['role' => 'viewer'],
        'new_values' => ['role' => 'admin'],
    ]);

    Livewire::test(ViewAuditLog::class, ['record' => $entry->getRouteKey()])
        ->assertSee('role')
        ->assertSee('viewer')
        ->assertSee('admin');
});
