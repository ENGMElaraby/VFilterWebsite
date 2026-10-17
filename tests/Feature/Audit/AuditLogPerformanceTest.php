<?php

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * SC-007: first page of the audit log in under 2 s with 1,000,000 rows.
 * Needs a real SQL Server: run with `php artisan test --group=sqlsrv` and DB_FILTER_DRIVER=sqlsrv.
 */

const AUDIT_ROWS = 1_000_000;
const MAX_SECONDS = 2.0;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlsrv') {
        $this->markTestSkipped('Needs SQL Server (DB_FILTER_DRIVER=sqlsrv).');
    }

    // Set-based insert: 1,000,000 rows over the last 365 days, mixed actions and actors.
    DB::statement(<<<'SQL'
        WITH n AS (
            SELECT TOP (?) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS i
            FROM sys.all_objects a CROSS JOIN sys.all_objects b CROSS JOIN sys.all_objects c
        )
        INSERT INTO audit_log (created_at, actor_type, actor_id, actor_label, ip, action, target_type, target_id)
        SELECT DATEADD(SECOND, -CAST(i * 31 AS INT), SYSUTCDATETIME()),
               'user', (i % 20) + 1, CONCAT('user', i % 20, '@example.test'), '10.0.0.1',
               CASE i % 4 WHEN 0 THEN 'auth.login' WHEN 1 THEN 'user.updated' WHEN 2 THEN 'auth.logout' ELSE 'user.created' END,
               'user', CAST(i % 20 AS VARCHAR(64))
        FROM n
        SQL, [AUDIT_ROWS]);

    $this->signIn(User::factory()->owner()->withTwoFactor()->create());
});

function timeFirstPage(Closure $configure): float
{
    $started = microtime(true);
    $configure(Livewire::test(ListAuditLogs::class));

    return microtime(true) - $started;
}

it('shows the first page fast with and without filters', function (Closure $configure) {
    expect(timeFirstPage($configure))->toBeLessThan(MAX_SECONDS);
})->with([
    'no filter' => fn () => fn ($page) => $page,
    'actor' => fn () => fn ($page) => $page->filterTable('actor_id', 5),
    'action' => fn () => fn ($page) => $page->filterTable('action', 'user.updated'),
    'date range' => fn () => fn ($page) => $page->filterTable('created_at', ['from' => now()->subDays(30)->toDateString(), 'until' => now()->toDateString()]),
])->group('sqlsrv');
