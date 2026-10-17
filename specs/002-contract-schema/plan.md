# Implementation Plan: Shared Contract Schema and Settings Repository

**Branch**: `002-contract-schema` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

## Summary

Create the 11 `VFILTER` contract tables as migrations on the `filter` connection, Eloquent models
with enum-typed fields, and one write path for filter configuration: `ConfigStore` (row tables:
modules, lists, area rules, cooldowns, GA permissions) and `SettingsRepository` (key/value settings).
Every write runs in one transaction that changes only what differs, bumps the touched area's row in
`filter_config_version` (one row per area, clarified), and writes one audit entry per changed item via
`AuditLogger`. A repeatable `vfilter:install-defaults` command adds module slots, cooldowns, counter
rows and core settings without overwriting. The shared contract moves to **0.2.0** in both repos.

## Technical Context

**Language/Version**: PHP 8.5 · **Primary Dependencies**: Laravel 13, Filament 5 (no UI in this feature)
**Storage**: SQL Server `VFILTER` (`filter` connection); SQLite in-memory for tests
**Testing**: Pest; structure tests on SQLite + `@group sqlsrv` type checks on SQL Server
**Target Platform**: same as 001 · **Project Type**: Laravel web app
**Performance Goals**: setup + defaults < 30 s (SC-001); config save < 100 ms
**Constraints**: datetime columns are `datetime2` in UTC; strings are `nvarchar(n)` (Laravel's SQL Server mapping); no FKs to panel tables from contract tables (the filter must not depend on them)
**Scale/Scope**: config tables ≤ ~10k rows; `filter_logs` millions (indexed per contract)

## Constitution Check

| Principle | Status | Notes |
| --- | --- | --- |
| I. Shared contract is law | Pass | Tables follow the contract; open details and the per-area counter go into contract 0.2.0 in **both** repos in this change. |
| II. Database-only control | Pass | Only `VFILTER` tables; no game DB writes, no sockets. |
| III. Security first | Pass | Every config write is audited (old → new) in the same transaction; filter-owned tables are read-only for the panel. |
| IV. Test-first | Pass | Repository/store tests and structure tests written before code. |
| V. Catalog-driven settings | Pass | `SettingsRepository` writes only known keys; 006 adds the full catalog through it. |
| VI. Bilingual UI | Pass | New audit action labels in `lang/{en,ar}/audit.php`. |
| VII. Simplicity | Pass | No repository-per-table: one `ConfigStore` + one `SettingsRepository`. |

Post-design re-check: pass. No complexity entries.

## Project Structure

```text
specs/002-contract-schema/
├── plan.md, research.md, data-model.md, quickstart.md
└── contracts/
    ├── shared-contract-0.2.0.md   # exact diff applied to docs/shared-contract.md (both repos)
    └── config-write-api.md        # ConfigStore / SettingsRepository behaviour + audit actions

app/
├── Enums/Filter/                  # ModuleType, CommandName, CommandStatus, GatewayMode, ModuleState,
│                                  # LogLevel, LogCategory, SettingType, RestartScope, ConfigArea, CooldownAction
├── Models/Filter/                 # FilterModule, FilterSetting, FilterListEntry, FilterAreaRule,
│                                  # FilterCooldown, FilterGaPermission, FilterCommand, FilterStatus,
│                                  # FilterSession, FilterLog, FilterConfigVersion
├── Config/
│   ├── ConfigStore.php            # create/update/delete for row-based config, audited + versioned
│   ├── ConfigVersion.php          # atomic per-area bump (recreates a missing row)
│   ├── ModuleRules.php            # 1 gateway, 1 download, 1–5 agents Agent1..n
│   └── SettingValue.php           # cast/validate by SettingType
├── Settings/SettingsRepository.php  # get/all/set(many) for filter_settings
└── Console/Commands/InstallDefaults.php   # vfilter:install-defaults
database/migrations/2026_10_17_1000xx_create_filter_*.php
tests/Feature/Contract/            # structure, ConfigVersion, ConfigStore, ModuleRules, models
tests/Feature/Settings/            # SettingsRepository
tests/Feature/Console/InstallDefaultsTest.php
```

**Structure Decision**: filter contract code lives under `Filter` sub-namespaces so it is easy to keep
in step with the C# side; the write path is in `app/Config` + `app/Settings` (CLAUDE.md names
`App\Settings\SettingsRepository`).

## Complexity Tracking

None.
