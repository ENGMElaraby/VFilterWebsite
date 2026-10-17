<?php

use App\Enums\Filter\LogCategory;
use App\Enums\Filter\LogLevel;
use App\Logs\LogFilters;
use App\Logs\LogQuery;
use App\Models\Filter\FilterLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC')));

function now17(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-10-17 12:00:00', 'UTC');
}

/**
 * @param  array<string, mixed>  $input
 * @return list<int>
 */
function matchingIds(array $input): array
{
    $filters = LogFilters::fromArray($input, now17(), 'UTC');

    return app(LogQuery::class)->apply(FilterLog::query(), $filters)->pluck('id')->all();
}

it('defaults to the last 24 hours, newest first', function () {
    $old = FilterLog::factory()->create(['ts' => now17()->subHours(25)]);
    $a = FilterLog::factory()->create(['ts' => now17()->subHours(2)]);
    $b = FilterLog::factory()->create(['ts' => now17()->subMinute()]);

    expect(matchingIds([]))->toBe([$b->id, $a->id])->not->toContain($old->id);
});

it('includes both ends of the time range', function () {
    $start = FilterLog::factory()->create(['ts' => '2026-10-10 00:00:00']);
    $end = FilterLog::factory()->create(['ts' => '2026-10-11 00:00:00']);
    FilterLog::factory()->create(['ts' => '2026-10-11 00:00:01']);

    expect(matchingIds(['from' => '2026-10-10 00:00:00', 'until' => '2026-10-11 00:00:00']))->toBe([$end->id, $start->id]);
});

it('reads the range in the display time zone', function () {
    $log = FilterLog::factory()->create(['ts' => '2026-10-16 21:30:00']);
    $filters = LogFilters::fromArray(['from' => '2026-10-17 00:00:00', 'until' => '2026-10-17 01:00:00'], now17(), 'Asia/Riyadh');

    expect(app(LogQuery::class)->apply(FilterLog::query(), $filters)->pluck('id')->all())->toBe([$log->id]);
});

it('refuses a range that ends before it starts', function () {
    expect(fn () => LogFilters::fromArray(['from' => '2026-10-17 10:00:00', 'until' => '2026-10-17 09:00:00'], now17(), 'UTC'))
        ->toThrow(InvalidArgumentException::class);
});

it('filters by level and category lists', function () {
    $hit = FilterLog::factory()->create(['level' => LogLevel::Security, 'category' => LogCategory::Exploit]);
    FilterLog::factory()->create(['level' => LogLevel::Security, 'category' => LogCategory::Flood]);
    FilterLog::factory()->create(['level' => LogLevel::Info, 'category' => LogCategory::Exploit]);

    expect(matchingIds(['levels' => ['security'], 'categories' => ['exploit']]))->toBe([$hit->id]);
});

it('matches module, ip and opcode exactly', function () {
    $hit = FilterLog::factory()->create(['module' => 'Agent1', 'ip' => '10.0.0.1', 'opcode' => '7021']);
    FilterLog::factory()->create(['module' => 'Agent1', 'ip' => '10.0.0.11', 'opcode' => '7021']);
    FilterLog::factory()->create(['module' => 'Agent2', 'ip' => '10.0.0.1', 'opcode' => '7021']);
    FilterLog::factory()->create(['module' => 'Agent1', 'ip' => '10.0.0.1', 'opcode' => '7022']);

    expect(matchingIds(['module' => 'Agent1', 'ip' => '10.0.0.1', 'opcode' => '7021']))->toBe([$hit->id]);
});

it('upper-cases the opcode', function () {
    $hit = FilterLog::factory()->create(['opcode' => '70A1']);

    expect(matchingIds(['opcode' => '70a1']))->toBe([$hit->id]);
});

it('matches names exactly, ignoring letter case', function () {
    $hit = FilterLog::factory()->create(['username' => 'Sara', 'charname' => 'Knight']);
    FilterLog::factory()->create(['username' => 'Sarah', 'charname' => 'Knight']);

    expect(matchingIds(['username' => 'sara', 'charname' => 'KNIGHT']))->toBe([$hit->id]);
});

it('refuses values longer than their column', function () {
    expect(fn () => LogFilters::fromArray(['opcode' => str_repeat('A', 9)], now17(), 'UTC'))
        ->toThrow(InvalidArgumentException::class);
});

it('ignores letter case on SQL Server without COLLATE', function () {
    if (DB::connection('filter')->getDriverName() !== 'sqlsrv') {
        $this->markTestSkipped('Needs SQL Server (DB_FILTER_DRIVER=sqlsrv).');
    }

    $hit = FilterLog::factory()->create(['username' => 'Sara']);

    expect(matchingIds(['username' => 'SARA']))->toBe([$hit->id]);
})->group('sqlsrv');
