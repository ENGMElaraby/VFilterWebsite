# Contract: Logs and sessions services (004)

## App\Logs\LogQuery
`apply(Builder<FilterLog> $query, LogFilters $filters): Builder`. It is pure, and the table, Live
and export all use it.

## App\Logs\LogExporter
`stream(LogFilters $filters): StreamedResponse` writes the BOM, the header row
`time,level,category,module,ip,hwid,username,charname,opcode,message` and formula-safe cells, newest
first, max 100,000 rows. It also writes the `logs.exported` audit entry. `wouldTruncate(LogFilters): bool`.

## App\Logs\LogPruner
`prune(?int $budgetSeconds = 120): PruneResult {cutoff, deleted, complete}`. It deletes in batches of
5000 rows and writes the `logs.pruned` audit entry.
Command: `php artisan vfilter:prune-logs`, scheduled daily at 03:30 without overlap.

## App\Filter\CommandQueue (added)
`kick(FilterSession $session, ?string $reason = null): FilterCommand`
`ban(FilterSession $session, string $reason, ?int $durationMinutes): FilterCommand` throws
`InvalidArgumentException` when the session has no username, the reason is empty or longer than 200 chars,
or the duration is not in the allowed set. Both throw `CommandInProgressException` on a duplicate.

## Pages
| page | route | viewer | admin/owner |
| --- | --- | --- | --- |
| Logs list | `/admin/filter-logs` | read, filter, Live, export | + Retention setting |
| Log detail | `/admin/filter-logs/{record}` | read | read |
| Sessions | `/admin/filter-sessions` | read (poll 5 s) | + Kick, Ban |
