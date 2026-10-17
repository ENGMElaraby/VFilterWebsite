<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Contract\ContractSchema;

/**
 * @return array{primary: list<string>, indexes: list<array{0: list<string>, 1: bool}>}
 */
function actualIndexes(string $table): array
{
    $primary = [];
    $indexes = [];

    foreach (Schema::getIndexes($table) as $index) {
        if ($index['primary']) {
            $primary = $index['columns'];

            continue;
        }

        $indexes[] = [$index['columns'], (bool) $index['unique']];
    }

    usort($indexes, fn (array $a, array $b): int => strcmp(implode(',', $a[0]), implode(',', $b[0])));

    return ['primary' => $primary, 'indexes' => $indexes];
}

it('creates every contract table with the agreed columns, nullability and indexes', function (string $table, array $expected) {
    expect(Schema::hasTable($table))->toBeTrue();

    $columns = collect(Schema::getColumns($table))->mapWithKeys(fn (array $column): array => [
        $column['name'] => (bool) $column['nullable'],
    ])->all();

    $expectedColumns = collect($expected['columns'])->map(fn (array $definition): bool => $definition[1])->all();

    expect($columns)->toEqualCanonicalizing($expectedColumns);

    $indexes = actualIndexes($table);
    $expectedIndexes = $expected['indexes'];
    usort($expectedIndexes, fn (array $a, array $b): int => strcmp(implode(',', $a[0]), implode(',', $b[0])));

    expect($indexes['primary'])->toBe($expected['primary'])
        ->and($indexes['indexes'])->toBe($expectedIndexes);
})->with(fn (): array => collect(ContractSchema::tables())
    ->map(fn (array $expected, string $table): array => [$table, $expected])
    ->all());

it('creates exactly the eleven contract tables', function () {
    $filterTables = collect(Schema::getTableListing())
        ->map(fn (string $name): string => str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name)
        ->filter(fn (string $name): bool => str_starts_with($name, 'filter_'))
        ->sort()->values()->all();

    $expected = array_keys(ContractSchema::tables());
    sort($expected);

    expect($filterTables)->toBe($expected);
});

it('is repeatable and its rollback removes only the contract tables', function () {
    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('Nothing to migrate');

    foreach (['2026_10_17_100002_create_filter_runtime_tables.php', '2026_10_17_100001_create_filter_config_tables.php'] as $file) {
        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/'.$file, '--force' => true]);
    }

    foreach (array_keys(ContractSchema::tables()) as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('audit_log'))->toBeTrue();
});
