# Contract change 0.1.1 → 0.2.0 (MINOR)

Applied to `docs/shared-contract.md` in **VFilterWebsite and VFilter** in the same change.

1. **filter_config_version**: one row per configuration area (`area` PK, `version`, `updated_at`)
   instead of a single `id = 1` row. Rule 4 becomes: "the website increments the counter of each
   area it changed in the same transaction as the change; the filter polls all counters every 2 s and
   reloads only the areas whose counter changed."
2. **String types**: `varchar(n)` → `nvarchar(n)` everywhere (Unicode names).
3. **Exact types and nullability** for `filter_status` and `filter_sessions` (see data-model.md).
4. **Indexes added**: `filter_modules(type)`, unique `filter_modules(name)`, unique
   `filter_lists(list, value)`, `filter_area_rules(rule, enabled)` + `(area)`,
   `filter_commands(status, created_at)`, `filter_sessions(module)`, `(username)`, `(ip)`,
   `filter_settings(group)`.
5. **filter_commands.target** widened to `nvarchar(64)`.
6. **Defaults**: `filter_modules.enabled` 0, `filter_area_rules.enabled` 1, `filter_cooldowns.seconds` 0,
   `filter_settings.requires_restart` `none`, `filter_commands.status` `pending`, `filter_status.state`
   `stopped`, counts 0.
7. Times: `datetime2(3)`, UTC.
