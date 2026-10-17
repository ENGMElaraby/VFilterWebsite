<?php

namespace App\Filter;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Current UTC time as the database server sees it, so web/DB clock skew cannot flip a module's
 * online badge (spec 003, research R2). SQLite (tests, local preview) uses the app clock.
 */
class DatabaseClock
{
    private const CONNECTION = 'filter';

    public function now(): CarbonImmutable
    {
        $connection = DB::connection(self::CONNECTION);

        if ($connection->getDriverName() !== 'sqlsrv') {
            return CarbonImmutable::now('UTC');
        }

        $value = $connection->selectOne('SELECT SYSUTCDATETIME() AS now')->now;

        return CarbonImmutable::parse((string) $value, 'UTC');
    }
}
