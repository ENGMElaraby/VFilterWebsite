<?php

use App\Config\ConfigStore;
use App\Enums\Filter\CooldownAction;
use App\Models\AuditLog;
use App\Models\Filter\FilterAreaRule;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterCooldown;
use App\Models\Filter\FilterListEntry;
use App\Models\Filter\FilterLog;
use App\Models\Filter\FilterStatus;
use App\Models\User;

beforeEach(function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $this->store = app(ConfigStore::class);
});

function areaVersion(string $area): int
{
    return (int) FilterConfigVersion::find($area)?->version;
}

it('creates, updates and deletes with audit and one bump each', function () {
    $entry = $this->store->create(FilterListEntry::class, ['list' => 'blocked_ips', 'value' => '1.2.3.4']);

    expect(areaVersion('lists'))->toBe(1)
        ->and(AuditLog::where('action', 'lists.created')->sole()->new_values)
        ->toMatchArray(['list' => 'blocked_ips', 'value' => '1.2.3.4']);

    $this->store->update($entry, ['comment' => 'spammer']);

    expect(areaVersion('lists'))->toBe(2)
        ->and(AuditLog::where('action', 'lists.updated')->sole())
        ->old_values->toBe(['comment' => null])
        ->new_values->toBe(['comment' => 'spammer']);

    $this->store->delete($entry);

    expect(areaVersion('lists'))->toBe(3)
        ->and(FilterListEntry::count())->toBe(0)
        ->and(AuditLog::where('action', 'lists.deleted')->sole()->old_values)->toMatchArray(['value' => '1.2.3.4']);
});

it('does nothing on an update without changes', function () {
    $cooldown = $this->store->create(FilterCooldown::class, ['action' => CooldownAction::Stall, 'seconds' => 10]);

    $this->store->update($cooldown, ['seconds' => 10]);

    expect(areaVersion('cooldowns'))->toBe(1)
        ->and(AuditLog::where('action', 'cooldowns.updated')->count())->toBe(0);
});

it('bumps each touched area once per batch', function () {
    $this->store->batch(function (ConfigStore $store): void {
        $store->create(FilterListEntry::class, ['list' => 'blocked_ips', 'value' => '1.1.1.1']);
        $store->create(FilterListEntry::class, ['list' => 'blocked_ips', 'value' => '2.2.2.2']);
        $store->create(FilterAreaRule::class, ['rule' => 'pk.block', 'area' => '-1']);
    });

    expect(areaVersion('lists'))->toBe(1)
        ->and(areaVersion('area_rules'))->toBe(1)
        ->and(AuditLog::where('action', 'lists.created')->count())->toBe(2);
});

it('rolls back everything when a batch fails', function () {
    expect(fn () => $this->store->batch(function (ConfigStore $store): void {
        $store->create(FilterListEntry::class, ['list' => 'blocked_ips', 'value' => '1.1.1.1']);
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect(FilterListEntry::count())->toBe(0)
        ->and(areaVersion('lists'))->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

it('refuses rows the filter owns', function (string $model) {
    expect(fn () => $this->store->create($model, []))->toThrow(InvalidArgumentException::class);
})->with([FilterStatus::class, FilterLog::class]);
