<?php

namespace App\Logs;

use App\Enums\Filter\LogCategory;
use App\Enums\Filter\LogLevel;
use App\Models\Filter\FilterLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one place that turns LogFilters into a query; the table, Live and export all use it.
 */
class LogQuery
{
    /**
     * @param  Builder<FilterLog>  $query
     * @return Builder<FilterLog>
     */
    public function apply(Builder $query, LogFilters $filters): Builder
    {
        $query->whereBetween('ts', [$filters->from, $filters->until])
            ->when($filters->levels !== [], fn (Builder $q) => $q->whereIn('level', array_map(fn (LogLevel $l): string => $l->value, $filters->levels)))
            ->when($filters->categories !== [], fn (Builder $q) => $q->whereIn('category', array_map(fn (LogCategory $c): string => $c->value, $filters->categories)))
            ->when($filters->module !== null, fn (Builder $q) => $q->where('module', $filters->module))
            ->when($filters->ip !== null, fn (Builder $q) => $q->where('ip', $filters->ip))
            ->when($filters->opcode !== null, fn (Builder $q) => $q->where('opcode', $filters->opcode));

        $this->whereName($query, 'username', $filters->username);
        $this->whereName($query, 'charname', $filters->charname);

        return $query->orderByDesc('ts')->orderByDesc('id');
    }

    /**
     * Exact, case-insensitive. SQL Server's default collation already ignores case (and keeps the
     * index); SQLite needs NOCASE.
     *
     * @param  Builder<FilterLog>  $query
     */
    private function whereName(Builder $query, string $column, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        if (DB::connection($query->getModel()->getConnectionName())->getDriverName() === 'sqlite') {
            $query->whereRaw("{$column} = ? COLLATE NOCASE", [$value]);

            return;
        }

        $query->where($column, $value);
    }
}
