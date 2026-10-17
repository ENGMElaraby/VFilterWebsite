# Feature Specification: Shared Contract Schema and Settings Repository

**Feature Branch**: `002-contract-schema`

**Created**: 2026-10-17

**Status**: Draft

**Input**: User description: "Create all VFILTER contract tables exactly as in docs/shared-contract.md as Laravel migrations on the filter connection, plus Eloquent models, a SettingsRepository that bumps filter_config_version and writes audit_log in one transaction, and seeders for default modules and settings."

## Overview

The panel and the filter never talk directly: they share the `VFILTER` database. This feature creates
every table the two sides share (as described in `docs/shared-contract.md`), and the one safe way the
panel changes filter configuration: save the change, tell the filter something changed, and record
who did it, all together or not at all. It adds no new pages; later features (dashboard, logs,
security lists, settings, area rules) build their screens on top of it.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The shared database exists exactly as agreed (Priority: P1)

The server operator runs the panel's database setup once on an empty `VFILTER` database. Every
table, column, default and index in the shared contract is created, so the filter can be built and
tested against the same structure.

**Why this priority**: Nothing else in phase 1 (dashboard, logs, commands) can work until these
tables exist, and the filter team (C#) codes against them.

**Independent Test**: Run the setup on an empty database and compare the resulting tables, columns,
types, nullability and indexes with `docs/shared-contract.md`; run the rollback and confirm the
contract tables are gone while panel tables from feature 001 remain.

**Acceptance Scenarios**:

1. **Given** an empty `VFILTER` database with feature 001 applied, **When** the operator runs the database setup, **Then** all 11 contract tables exist: `filter_modules`, `filter_settings`, `filter_lists`, `filter_area_rules`, `filter_cooldowns`, `filter_ga_permissions`, `filter_commands`, `filter_status`, `filter_sessions`, `filter_logs`, `filter_config_version`.
2. **Given** the tables exist, **When** their structure is compared with the contract, **Then** every column name, type, length, nullability and listed index matches.
3. **Given** the setup has run, **When** it is run again, **Then** nothing changes and no error occurs.
4. **Given** the setup has run, **When** the operator rolls back this feature, **Then** only the contract tables are removed.

---

### User Story 2 - Every configuration change reaches the filter and is traceable (Priority: P1)

When a panel user (in later features) changes any filter setting, list, area rule, cooldown, module
or Game Assistant permission, the change is saved, the filter is told that configuration changed,
and the audit log records who changed what from which old value to which new value, as one unit.

**Why this priority**: This is the backbone of "control the filter through the database". If the
filter is not told, changes silently do nothing; if the audit entry is missing, the constitution is
broken.

**Independent Test**: Through the shared saving capability, change a setting; confirm the stored value,
the incremented change counter and one audit entry with old → new. Force a failure mid-save and
confirm none of the three happened.

**Acceptance Scenarios**:

1. **Given** a setting with value `false`, **When** an admin saves `true`, **Then** the setting holds `true`, the configuration change counter increased by exactly 1, and one audit entry shows the actor, the setting key and `false → true`.
2. **Given** an admin saves a value identical to the current one, **When** the save runs, **Then** nothing is written, the counter does not change and no audit entry is created.
3. **Given** an admin saves several settings at once (one form), **When** the save completes, **Then** all changed values are stored, the counter increases by exactly 1, and each changed key has its own audit entry.
4. **Given** an error happens after the value was written but before the audit entry, **When** the save fails, **Then** the value, the counter and the audit log are all unchanged.
5. **Given** a value that does not match the setting's type (e.g. text for a number), **When** it is saved, **Then** it is refused with a clear error and nothing is written.
6. **Given** two admins save different settings at the same moment, **When** both finish, **Then** both changes are stored and the counter increased by 2.

---

### User Story 3 - A fresh install starts with safe defaults (Priority: P2)

After setup, the operator loads the defaults once. The database then holds a sensible starting
configuration the filter can read immediately: the module slots, every cooldown at "off", the
counter row, and the core settings needed by phase 1.

**Why this priority**: Without defaults the dashboard and the filter start from an empty, invalid
configuration. Lower than P1 because it is a one-time convenience on top of the schema.

**Independent Test**: On an empty database run setup + defaults; confirm the expected rows exist;
run the defaults again and confirm nothing is duplicated and no operator-changed value is overwritten.

**Acceptance Scenarios**:

1. **Given** an empty contract schema, **When** the defaults are loaded, **Then** there is one Gateway, one Download and one Agent module (all disabled until their addresses are filled in), all 17 cooldown actions at 0 seconds, one change-counter row, and the core settings with their default values.
2. **Given** the operator changed a setting or a module address, **When** the defaults are loaded again, **Then** the changed values are kept and only missing rows are added.
3. **Given** defaults are loaded, **When** this happens, **Then** it is recorded in the audit log as a system action and the change counter increases once.

---

### Edge Cases

- The filter reads while the panel is mid-save: it sees either the whole old configuration or the whole new one, never half (transaction).
- The change counter row is missing (manually deleted): the next save recreates it instead of failing.
- A setting key that is not known to the panel is saved: refused (only known keys can be written).
- A save is attempted from a console or scheduled job with no signed-in user: allowed, audited as `system` or `console`.
- Module limits are violated at the data level (a second gateway, a sixth agent): refused when saved through the panel's saving capability.
- Very large tables (`filter_logs`, `filter_sessions`): created with the contract's indexes so later pages stay fast at millions of rows.
- Column named `group` (a reserved word in SQL): must work on SQL Server.

## Requirements *(mandatory)*

### Functional Requirements

**Schema**

- **FR-001**: The panel's database setup MUST create the 11 contract tables in the `VFILTER` database with the columns, types, lengths, nullability, primary keys and indexes defined in `docs/shared-contract.md`.
- **FR-002**: Where the contract leaves a detail open (exact types for `filter_status` and `filter_sessions`, indexes for lists, rules, commands and sessions), this feature MUST define it, and MUST update `docs/shared-contract.md` in **both** repositories with a MINOR version bump in the same change.
- **FR-003**: All date-time columns MUST store UTC.
- **FR-004**: The setup MUST be repeatable (re-running changes nothing) and reversible (rollback removes only the contract tables).
- **FR-005**: The setup MUST work on SQL Server (production) and on the in-memory test database.

**Saving configuration**

- **FR-006**: The panel MUST provide one shared capability for writing filter configuration (settings, lists, area rules, cooldowns, modules, Game Assistant permissions). Later features MUST use it; nothing else writes those tables.
- **FR-007**: Each save MUST, in one transaction: write only what actually changed, increase the configuration change counter by exactly 1 if anything changed, and write one audit entry per changed item with actor, item and old → new values.
- **FR-008**: A save with no actual change MUST write nothing.
- **FR-009**: Setting values MUST be validated against the setting's declared type (`bool`, `int`, `string`, `json`, `color`) before saving; invalid values MUST be refused with a clear message.
- **FR-010**: Only settings that exist (seeded or from the settings catalog) MUST be writable; unknown keys MUST be refused.
- **FR-011**: Module saves MUST enforce exactly 1 gateway, exactly 1 download and 1–5 agents named `Agent1`…`Agent5` in order.
- **FR-012**: Setting reads by the panel MUST return typed values (true/false, numbers, text, decoded JSON, color string).
- **FR-013**: Concurrent saves MUST not lose updates to the change counter (each save adds exactly 1).

**Defaults**

- **FR-014**: The panel MUST provide a repeatable defaults step that adds missing rows only: module slots (Gateway, Download, Agent1, disabled), all 17 cooldown actions at 0, the change-counter row, and the core settings listed in Assumptions.
- **FR-015**: Loading defaults MUST never overwrite an existing value and MUST be audited as a `system` action.

**Data access for later features**

- **FR-016**: Each contract table MUST have a data model the panel can use for reading (dashboard, logs, sessions, commands) with typed fields and the contract's allowed values (e.g. command status, log level, module type) as fixed lists.
- **FR-017**: Rows the filter owns (`filter_status`, `filter_sessions`, `filter_logs`, and `filter_commands.status/result/processed_at`) MUST be read-only from the panel's saving capability.

### Key Entities

- **Module**: one proxy listener (gateway / agent / download): name, real address (hidden from players), public guard address, enabled.
- **Setting**: one filter switch: key, value, type, tab (group), which restart it needs, last changed when and by whom.
- **List entry**: one value in a named list (allowed IPs, blocked HWIDs, …) with an optional comment.
- **Area rule**: "do or block X in area Y" with optional target and parameters, enabled flag.
- **Cooldown**: seconds (0–32000) per action.
- **Game Assistant permission**: per username, one on/off per GM command.
- **Command**: one website → filter instruction with status lifecycle `pending → running → done | failed`.
- **Module status**: one heartbeat row per module written by the filter.
- **Live session**: one connected player written by the filter.
- **Filter log entry**: one event written by the filter.
- **Configuration change counter**: one number increased on every configuration save.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On an empty database, setup + defaults complete in under 30 seconds.
- **SC-002**: 100% of contract tables, columns and listed indexes match `docs/shared-contract.md` (automated structure test).
- **SC-003**: 100% of configuration saves that change something produce exactly one counter increase and one audit entry per changed item; 0% of failed saves leave partial changes (automated tests).
- **SC-004**: Re-running setup or defaults produces zero changes to existing data.
- **SC-005**: The filter team can start coding against the schema the same day: the contract document and the database agree with no open questions on column types.

## Assumptions

- Core settings seeded now (the full ~300-switch catalog comes in feature 006): `server.start_in_inspection` (bool, false), `logs.retention_days` (int, 30), `limits.ip` (int, 0 = unlimited), `limits.pc` (int, 0), `login.captcha.enabled` (bool, false).
- Default module addresses use vSRO defaults (Gateway 15779, Download 15881, Agent 15884) on `127.0.0.1`, disabled until the operator fills in real and guard addresses in feature 003.
- `filter_config_version` stays one row (`id = 1`) as the contract says; the filter reloads all configuration when the number changes (configuration is small). Splitting per area is a later option.
- The `VFILTER` database is on the same SQL Server instance as the game databases; cross-database access is not used in this feature.
- Panel tables from feature 001 already exist in the same database.
