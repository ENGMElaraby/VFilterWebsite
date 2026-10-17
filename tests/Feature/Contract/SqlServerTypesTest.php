<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Contract\ContractSchema;

/*
 * Exact SQL Server column types (nvarchar lengths, datetime2, bit, bigint, int).
 * Run with DB_FILTER_DRIVER=sqlsrv: php artisan test --group=sqlsrv
 */

it('uses the agreed SQL Server column types', function (string $table, array $expected) {
    if (DB::connection()->getDriverName() !== 'sqlsrv') {
        $this->markTestSkipped('Needs SQL Server (DB_FILTER_DRIVER=sqlsrv).');
    }

    $actual = collect(Schema::getColumns($table))->mapWithKeys(fn (array $column): array => [
        $column['name'] => strtolower(preg_replace('/^datetime2\(\d+\)$/', 'datetime2', $column['type'])),
    ])->all();

    foreach ($expected['columns'] as $name => [$type]) {
        expect($actual[$name] ?? null)->toBe($type, "{$table}.{$name}");
    }
})->with(fn (): array => collect(ContractSchema::tables())
    ->map(fn (array $expected, string $table): array => [$table, $expected])
    ->all())->group('sqlsrv');
