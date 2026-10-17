<?php

use App\Filter\DatabaseClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('uses the app clock in UTC on sqlite', function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC'));

    expect(app(DatabaseClock::class)->now()->toIso8601String())->toBe('2026-10-17T12:00:00+00:00');
});

it('reads the SQL Server clock', function () {
    if (DB::connection('filter')->getDriverName() !== 'sqlsrv') {
        $this->markTestSkipped('Needs SQL Server (DB_FILTER_DRIVER=sqlsrv).');
    }

    $server = Carbon::parse(DB::connection('filter')->selectOne('SELECT SYSUTCDATETIME() AS now')->now, 'UTC');

    expect(abs(app(DatabaseClock::class)->now()->diffInSeconds($server)))->toBeLessThan(2);
})->group('sqlsrv');
