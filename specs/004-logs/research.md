# Research: Logs and Live Sessions (004)

## R1. Fast filters on millions of rows (FR-003, SC-001)
- **Decision**: The time range is a required filter. It defaults to the last 24 h and can never be
  removed, so every query is bounded on `ts` (indexed). IP and username use their own indexes. Category
  uses `(category, ts)`. Level, module, charname and opcode are residual predicates inside the time
  window. Pagination is "simple" (no `COUNT(*)` over millions of rows), with 50 per page, ordered
  `ts desc, id desc`.
- **Case-insensitive names**: SQL Server's default collation is case-insensitive, so a plain `=`
  keeps the index. SQLite (tests/preview) adds `COLLATE NOCASE` through one helper
  (`LogQuery::whereName`).
- **Alternatives**: free-text `LIKE '%x%'` (full scans, rejected for v1), full-text index (schema change, later).

## R2. Live tail (FR-005)
- **Decision**: a `$live` Livewire property on the list page. The table's `poll()` returns `'5s'` only
  while `$live` is true and the table is on page 1. Leaving page 1 sets `$live = false`
  (`updatedPaginators` hook). The Live/Pause header action toggles it.

## R3. CSV export (FR-006, SC-003)
- **Decision**: a header action that returns `response()->streamDownload()` (Livewire passes downloads
  through). It writes a UTF-8 BOM so Excel shows Arabic, uses `fputcsv`, and prefixes any cell that starts
  with `= + - @ \t \r` with `'`. Rows come from the same `LogQuery` via `lazyById(1000)` (newest first by
  `id desc`), with a cap of 100,000. When the cap is hit, the action notifies before streaming (a count
  limited to 100,001 rows). The audit entry `logs.exported` records {filters, rows}.

## R4. Retention + prune (FR-007..009, SC-004)
- **Setting**: the `logs.retention_days` key already exists (002 defaults, int). It is saved via
  `SettingsRepository::set()` (audited `setting.updated`, version bump). Validation is 1–3650 days. The
  modal asks for confirmation when the new value is smaller.
- **Job**: `vfilter:prune-logs` is an artisan command, scheduled `->dailyAt('03:30')->withoutOverlapping()`
  in `routes/console.php`. Each loop runs `FilterLog::where('ts','<',$cutoff)->orderBy('id')->limit(5000)->delete()`.
  Laravel compiles this to `DELETE TOP (5000)` on SQL Server and a `rowid IN (… LIMIT)` subquery on
  SQLite. Each batch is short, and the loop stops after 120 s. The cutoff is
  `DatabaseClock::now() − days`.
- **Record of runs**: the audit entry `logs.pruned` (actor system) holds {cutoff, deleted, complete}. The Logs
  page shows the latest one. No new table is needed.

## R5. Kick / ban (FR-011..014)
- **Decision**: add `CommandGroup::Player` (kick, ban) so the duplicate guard runs per player target. Add
  `CommandQueue::kick(FilterSession, ?reason)` and `ban(FilterSession, reason, ?minutes)`. They use the same lock,
  transaction and audit as 003. Target validation for player commands is "non-empty, ≤ 64 chars"
  (contract column). Module names are not required.
- **Ban durations**: 60, 1440, 10080, 43200 minutes, or null (permanent). Ban needs `username`.
- **Contract 0.3.1 (PATCH, both repos)**: kick payload `{"session_id":int,"ip":string,"reason":string|null}`,
  ban payload `{"session_id":int,"reason":string,"duration_minutes":int|null}`. Ban means "ban the game
  account and disconnect it".

## R6. Pages
- `FilterLogResource` with list and view pages (no create/edit). `FilterSessionResource` with a list page
  that polls every 5 s and has Kick/Ban row actions. Both sit in nav group "Logs". The DB-unavailable
  banner comes from catching `QueryException` in the list pages. Both are read-only for every role,
  and kick/ban are admin+ (FilterCommandPolicy::create).
