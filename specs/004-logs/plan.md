# Implementation Plan: Logs and Live Sessions

**Branch**: `004-logs` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

## Summary

This feature adds:
- A Logs page (`FilterLogResource`) over `filter_logs`. A required time range (default 24 h) keeps
  every query on an index, and filters combine with exact matches. A Live mode polls every 5 s and
  only on page 1. CSV export is streamed, BOM, formula-safe, capped at 100k rows and audited.
- Retention: `logs.retention_days` is edited through `SettingsRepository`. A daily
  `vfilter:prune-logs` deletes in 5000-row batches within a 120 s budget, and each run is
  recorded in the audit log.
- A Sessions page (`FilterSessionResource`) that polls every 5 s, with Kick and Ban (account,
  chosen duration). These go through `CommandQueue` with a new `Player` duplicate group.
- Shared contract 0.3.1 (PATCH) with the kick/ban payloads, in both repos.

## Technical Context

**Language/Version**: PHP 8.5 · **Primary Dependencies**: Laravel 13, Filament 5, Livewire 4
**Storage**: SQL Server `VFILTER` (SQLite in tests); no migrations
**Testing**: Pest + Filament/Livewire helpers; `@group sqlsrv` for `DELETE TOP` and the CI collation
**Target Platform / Type**: as in 003 (Laravel admin panel); the scheduler must run on the server
**Performance Goals**: first page < 2 s at 5M rows (SC-001); export of 100k < 30 s (SC-003)
**Constraints**: no full scans (time bound always applied); the prune holds no long locks
**Scale/Scope**: `filter_logs` in the millions; `filter_sessions` up to a few thousand

## Constitution Check

| Principle | Status | Notes |
| --- | --- | --- |
| I. Contract is law | Pass | 0.3.1 PATCH applied to both repos. Logs pruned per contract ("website job prunes"). |
| II. DB-only control | Pass | Kick/ban go through `filter_commands`. No game DB writes, because the filter applies the ban. |
| III. Security | Pass | Kick/ban are admin+ (policy + `Gate::authorize`). Export and retention are audited. Output is escaped. CSV is formula-safe. |
| IV. Test-first | Pass | Tests per story before code. |
| V. Catalog settings | Pass | Retention is an existing catalog key, saved via SettingsRepository. 006 will move it to the settings pages. |
| VI. Bilingual | Pass | EN/AR keys, RTL and phone checked. |
| VII. Simplicity | Pass | No new tables (prune runs live in the audit log). Polling, not websockets. |

Post-design re-check: pass. No complexity entries.

## Project Structure

```text
app/Logs/LogFilters.php, LogQuery.php, LogExporter.php, LogPruner.php, PruneResult.php
app/Console/Commands/PruneLogs.php                 # vfilter:prune-logs
app/Enums/Filter/CommandGroup.php                  # + Player
app/Filter/CommandQueue.php                        # + kick(), ban()
app/Filament/Resources/FilterLogs/                 # Resource, Pages/{List,View}, Tables, Schemas (infolist)
app/Filament/Resources/FilterSessions/             # Resource, Pages/List, Tables
app/Policies/FilterLogPolicy.php, FilterSessionPolicy.php   # viewAny/view = any; no writes
routes/console.php                                 # schedule
lang/{en,ar}/panel.php, audit.php                  # new keys
docs/shared-contract.md (+ ../VFilter)             # 0.3.1
tests/Feature/Logs/{LogQueryTest,LogsPageTest,LiveTest,ExportTest,PruneTest,RetentionTest}.php
tests/Feature/Sessions/{KickBanTest,SessionsPageTest}.php
```

**Structure Decision**: the log domain lives in `app/Logs`, and the commands stay in `app/Filter/CommandQueue`.

## Complexity Tracking

None.
