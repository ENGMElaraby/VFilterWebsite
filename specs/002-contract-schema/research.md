# Research: 002 Contract Schema

## 1. datetime2 on SQL Server
- **Decision**: `Schema::defaultTimePrecision(3)` in `AppServiceProvider::register()` so every
  `dateTime()`/`timestamps()` column is `datetime2(3)` on SQL Server (Laravel maps precision 0 to the
  older `datetime`). Applies to 001 tables too; nothing is deployed yet, so no data migration.
- **Alternatives**: per-column precision (easy to forget); raw SQL (not portable to SQLite tests).

## 2. String types
- **Decision**: Laravel `string(n)` → `nvarchar(n)` on SQL Server. The contract's `varchar(n)` is
  updated to `nvarchar(n)` (Unicode, needed for Arabic/Chinese names). The C# side reads both the same.

## 3. Change counter per area (clarified)
- **Decision**: `filter_config_version(area nvarchar(16) PK, version bigint, updated_at datetime2)`,
  one row per `ConfigArea` (`settings`, `lists`, `area_rules`, `cooldowns`, `modules`, `ga_permissions`).
  Bump with one atomic statement `UPDATE … SET version = version + 1, updated_at = ? WHERE area = ?`;
  if 0 rows, insert `version = 1`. Inside the save transaction, so readers never see a new version
  before its data. Filter polls `SELECT area, version FROM filter_config_version` every 2 s.
- **Alternatives**: single row (rejected in clarify); triggers (hidden logic, harder to test).

## 4. One write path
- **Decision**: `ConfigStore` for row tables, keyed by model → area (`FilterModule::AREA` etc.),
  `SettingsRepository` for settings. Both: diff against stored values, write only changes, one
  transaction (`AuditLogger::transaction`), audit each changed item, bump each touched area once.
  Filter-owned models (`FilterStatus`, `FilterSession`, `FilterLog`) are not accepted by `ConfigStore`;
  `FilterCommand` is written by feature 003's command queue, not here.
- **Alternatives**: model observers (fire per row, can't bump once per save, harder to keep atomic).

## 5. Audit actions
- **Decision**: `setting.updated` (target_type `setting`, target_id key), and
  `<area>.created|updated|deleted` for row tables (e.g. `modules.updated`, target_id = primary key),
  `config.defaults_installed` for the defaults command. Labels added in `lang/{en,ar}/audit.php`.

## 6. Typed settings
- **Decision**: `SettingType` enum with `cast(string $raw)` and `serialize(mixed $value)`; validation:
  bool → true/false only; int → integer within PHP int; string → ≤ 4000 chars; json → valid JSON;
  color → `#RRGGBB` or `#RRGGBBAA`. Values stored as strings (`bool` as `1`/`0`).

## 7. Module limits
- **Decision**: `ModuleRules::assertValid()` runs inside the `modules` save transaction after the
  write and throws (rolling back) unless: exactly 1 `gateway` named `Gateway`, exactly 1 `download`
  named `Download`, 1–5 `agent`s named `Agent1..AgentN` with no gaps. Ports 1–65535; IPs valid IPv4/IPv6
  or hostnames.

## 8. Defaults
- **Decision**: `php artisan vfilter:install-defaults` (also called from `DatabaseSeeder`) inserts
  missing rows only, via `ConfigStore`/`SettingsRepository` as `system`, so it is audited and versioned.
  Modules: Gateway 127.0.0.1:15779, Download 127.0.0.1:15881, Agent1 127.0.0.1:15884, guard = same,
  all disabled. 17 cooldowns at 0. Six counter rows at 0. Core settings from spec Assumptions.

## 9. Tests on two engines
- **Decision**: structure test reads `Schema::getColumns/getIndexes` and checks names, nullability,
  primary keys and index columns on any engine; a `@group sqlsrv` test also checks SQL types
  (`nvarchar`, `datetime2`, `bit`, `bigint`).
