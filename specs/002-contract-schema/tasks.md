---
description: "Tasks for 002 Shared Contract Schema and Settings Repository"
---

# Tasks: Shared Contract Schema and Settings Repository

**Input**: `specs/002-contract-schema/` · **Tests**: required (constitution IV), written first.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

- [ ] T001 Set `Schema::defaultTimePrecision(3)` in app/Providers/AppServiceProvider.php `register()` so every date-time column is `datetime2(3)` on SQL Server (research §1)
- [ ] T002 [P] Create enums in app/Enums/Filter/: `ConfigArea` (settings, lists, area_rules, cooldowns, modules, ga_permissions), `ModuleType` (gateway, agent, download), `CommandName` (start, stop, restart, set_gateway_mode, reload, kick, notice, ban), `CommandStatus` (pending, running, done, failed), `GatewayMode` (gm, inspection, public), `ModuleState` (stopped, starting, running, error), `LogLevel` (debug, info, warn, error, security), `LogCategory` (connection, login, exploit, flood, limit, restriction, command, chat, gm), `SettingType` (bool, int, string, json, color), `RestartScope` (none, filter, gameserver, client), `CooldownAction` (the 17 actions in data-model.md)

---

## Phase 2: Foundational

- [ ] T003 Migrations database/migrations/2026_10_17_100001_create_filter_config_tables.php (filter_modules, filter_settings, filter_lists, filter_area_rules, filter_cooldowns, filter_ga_permissions, filter_config_version) exactly per data-model.md: types, lengths, nullability, defaults, PKs, indexes, no FKs
- [ ] T004 [P] Migration database/migrations/2026_10_17_100002_create_filter_runtime_tables.php (filter_commands, filter_status, filter_sessions, filter_logs) exactly per data-model.md
- [ ] T005 [P] Models in app/Models/Filter/: FilterModule, FilterSetting, FilterListEntry, FilterAreaRule, FilterCooldown, FilterGaPermission (configuration, each with `public const AREA = ConfigArea::…`), FilterCommand, FilterStatus, FilterSession, FilterLog (filter-owned), FilterConfigVersion; table names, string PKs where defined (`$incrementing = false`, `$keyType = 'string'`), `$timestamps = false`, enum and JSON casts, datetime casts
- [ ] T006 [P] Model factories for FilterModule, FilterListEntry, FilterAreaRule, FilterCommand, FilterLog, FilterSession in database/factories/Filter/

**Checkpoint**: `php artisan migrate` works on SQLite.

---

## Phase 3: User Story 1 – Schema matches the contract (P1) 🎯 MVP

**Independent Test**: structure test passes; migrate is repeatable; rollback removes only contract tables.

- [ ] T007 [US1] tests/Feature/Contract/SchemaTest.php: for each of the 11 tables assert column names, nullability, PK and index columns per data-model.md (via `Schema::getColumns`/`getIndexes`); rollback of 002 migrations drops only `filter_*` tables; `migrate` twice is a no-op
- [ ] T008 [P] [US1] tests/Feature/Contract/SqlServerTypesTest.php `@group sqlsrv` (skipped elsewhere): `nvarchar` lengths, `datetime2`, `bit`, `bigint`, `int` per data-model.md
- [ ] T009 [P] [US1] tests/Feature/Contract/ModelsTest.php: enum casts and JSON casts round-trip for each model; string-keyed models save and find by key
- [ ] T010 [US1] Make T007–T009 pass (fix migrations/models)
- [ ] T011 [US1] Apply contracts/shared-contract-0.2.0.md to docs/shared-contract.md in **both** repos (identical file, version 0.2.0, changelog entry); update rule 4 wording

---

## Phase 4: User Story 2 – Every config change is versioned and audited (P1)

**Independent Test**: save a setting → value, `settings` counter +1, one audit entry; failure mid-save → nothing changes.

### Tests (first)

- [ ] T012 [P] [US2] tests/Feature/Contract/ConfigVersionTest.php: bump increases only that area by 1; missing row is recreated at 1; two bumps → +2; `updated_at` set (UTC)
- [ ] T013 [P] [US2] tests/Feature/Settings/SettingsRepositoryTest.php: typed get/all; set changes only differing keys; one `setting.updated` entry per key with typed old → new; `settings` counter +1 per call (not per key); no-op save writes nothing; unknown key and wrong type refused with all errors listed and nothing written; failure inside the transaction rolls back value, counter and audit; `updated_by` = actor; `define()` adds missing and never overwrites
- [ ] T014 [P] [US2] tests/Feature/Contract/SettingTypeTest.php: cast/serialize/validate for bool, int, string (≤ 4000), json, color (`#RRGGBB`, `#RRGGBBAA`)
- [ ] T015 [P] [US2] tests/Feature/Contract/ConfigStoreTest.php: create/update/delete per area audit `<area>.created|updated|deleted` with old/new and bump that area once; `batch()` touching two areas bumps each once; update with no change is a no-op; filter-owned models refused; exception inside `batch()` rolls back everything
- [ ] T016 [P] [US2] tests/Feature/Contract/ModuleRulesTest.php: second gateway, missing download, sixth agent, `Agent3` without `Agent2`, port 0/70000, bad IP → refused and rolled back; valid set accepted

### Implementation

- [ ] T017 [US2] app/Config/ConfigVersion.php: `bump(ConfigArea $area)` atomic `UPDATE … SET version = version + 1` then insert if 0 rows; `current(): array<string,int>`
- [ ] T018 [US2] app/Config/SettingValue.php (or methods on `SettingType`): `cast`, `serialize`, `validate` per research §6
- [ ] T019 [US2] app/Settings/SettingsRepository.php per contracts/config-write-api.md; exceptions app/Settings/UnknownSettingException.php, app/Settings/InvalidSettingValueException.php
- [ ] T020 [US2] app/Config/ConfigStore.php per contracts/config-write-api.md (areas from model `AREA`, bump once per touched area per transaction, audit via AuditLogger)
- [ ] T021 [US2] app/Config/ModuleRules.php per research §7, called by ConfigStore for `modules`
- [ ] T022 [US2] Add audit labels for the new actions to lang/en/audit.php and lang/ar/audit.php; add `setting` and area target types if shown
- [ ] T023 [US2] Make T012–T016 pass; phpstan; pint

---

## Phase 5: User Story 3 – Safe defaults (P2)

- [ ] T024 [US3] tests/Feature/Console/InstallDefaultsTest.php: empty DB → Gateway/Download/Agent1 disabled with vSRO default ports, 17 cooldowns at 0, 6 counter rows, core settings with defaults; second run adds 0 rows and keeps changed values; one `config.defaults_installed` audit entry (actor system) listing counts; each area that got rows bumped once
- [ ] T025 [US3] app/Console/Commands/InstallDefaults.php (`vfilter:install-defaults`) using ConfigStore/SettingsRepository; call it from database/seeders/DatabaseSeeder.php
- [ ] T026 [US3] Make T024 pass; phpstan; pint

---

## Phase 6: Polish

- [ ] T027 [P] Update CLAUDE.md (write path rule: only ConfigStore/SettingsRepository write config; `vfilter:install-defaults`) and HANDOFF.md status
- [ ] T028 Run quickstart "Tests only"; full suite, phpstan, pint green
- [ ] T029 Run quickstart SQL Server section when a server is available

## Dependencies

Setup → Foundational → US1 → US2 → US3 → Polish. US2 tests can be written in parallel with US1 implementation.
