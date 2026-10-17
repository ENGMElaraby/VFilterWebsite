<?php

use App\Logs\LogPruner;
use App\Logs\LogRetention;
use App\Models\AuditLog;
use App\Models\Filter\FilterLog;
use App\Settings\SettingsRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC'));
    $this->artisan('vfilter:install-defaults');
    app(SettingsRepository::class)->set([LogRetention::KEY => 7]);
});

it('removes events older than the retention period only', function () {
    $kept = FilterLog::factory()->create(['ts' => now('UTC')->subDays(6)]);
    FilterLog::factory()->create(['ts' => now('UTC')->subDays(8)]);

    $result = app(LogPruner::class)->prune();

    expect($result->deleted)->toBe(1)
        ->and($result->complete)->toBeTrue()
        ->and(FilterLog::query()->pluck('id')->all())->toBe([$kept->id]);

    $entry = AuditLog::query()->where('action', 'logs.pruned')->sole();
    expect($entry->actor_type)->toBe('system')->and($entry->new_values['deleted'])->toBe(1);
});

it('deletes in batches until done', function () {
    FilterLog::factory()->count(5)->create(['ts' => now('UTC')->subDays(30)]);

    $result = app()->make(LogPruner::class, ['batchSize' => 2])->prune();

    expect($result->deleted)->toBe(5)->and($result->complete)->toBeTrue()->and(FilterLog::count())->toBe(0);
});

it('stops when the time budget runs out and finishes next time', function () {
    FilterLog::factory()->count(5)->create(['ts' => now('UTC')->subDays(30)]);

    $result = app()->make(LogPruner::class, ['batchSize' => 2])->prune(budgetSeconds: 0);

    expect($result->deleted)->toBe(2)->and($result->complete)->toBeFalse()->and(FilterLog::count())->toBe(3);
});

it('runs from the console and is scheduled daily', function () {
    FilterLog::factory()->create(['ts' => now('UTC')->subDays(9)]);

    $this->artisan('vfilter:prune-logs')->expectsOutputToContain('Removed 1 events')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command, 'vfilter:prune-logs'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('30 3 * * *');
});

it('deletes in TOP batches on SQL Server', function () {
    if (DB::connection('filter')->getDriverName() !== 'sqlsrv') {
        $this->markTestSkipped('Needs SQL Server (DB_FILTER_DRIVER=sqlsrv).');
    }

    FilterLog::factory()->count(5)->create(['ts' => now('UTC')->subDays(30)]);

    expect(app()->make(LogPruner::class, ['batchSize' => 2])->prune()->deleted)->toBe(5);
})->group('sqlsrv');
