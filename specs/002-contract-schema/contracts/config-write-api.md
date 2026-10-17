# Contract: configuration write path (panel side)

Only these two classes write filter configuration. Later features (003–007) must use them.

## SettingsRepository (`App\Settings\SettingsRepository`)
- `get(string $key): mixed` — typed value; throws `UnknownSettingException` for unknown keys.
- `all(?string $group = null): array<string, mixed>` — typed values by key.
- `set(array $values): int` — `[key => value, …]`. Validates every key exists and every value fits its
  `SettingType` before writing anything (`InvalidSettingValueException` lists all problems). Writes
  only changed keys (`value`, `updated_at`, `updated_by`), one `setting.updated` audit entry per key
  (old → new as typed values), bumps `settings` once if anything changed. Returns number changed.
- `define(string $key, SettingType $type, string $group, mixed $default, RestartScope $restart)` —
  adds a missing setting (defaults/catalog); never overwrites an existing value.

## ConfigStore (`App\Config\ConfigStore`)
For `FilterModule`, `FilterListEntry`, `FilterAreaRule`, `FilterCooldown`, `FilterGaPermission`.
- `create(class-string $model, array $attributes): Model` → audit `<area>.created` (new values).
- `update(Model $model, array $attributes): Model` → only changed attributes; audit `<area>.updated`
  (old → new); no-op if nothing changed.
- `delete(Model $model): void` → audit `<area>.deleted` (old values).
- `batch(Closure $fn): mixed` — several writes in one transaction; each touched area bumped once.
- Refuses filter-owned models (`FilterStatus`, `FilterSession`, `FilterLog`, `FilterCommand`).
- `modules` writes run `ModuleRules::assertValid()` before commit.

## Audit action ids added
`setting.updated`, `modules.created|updated|deleted`, `lists.created|updated|deleted`,
`area_rules.created|updated|deleted`, `cooldowns.created|updated|deleted`,
`ga_permissions.created|updated|deleted`, `config.defaults_installed`.
