# Contract: Audit actions

`action` values are stable, language-independent identifiers (`<area>.<verb>`). The UI shows a
translated label per action from `lang/{en,ar}/audit.php`; unknown actions show the raw id.
Later features add their own (`setting.updated`, `command.queued`, …) using the same rules.

| action | actor_type | target | old → new values |
| --- | --- | --- | --- |
| `auth.login` | user | user | – |
| `auth.login_failed` | guest | – (actor_label = attempted email) | – |
| `auth.lockout` | guest | – (actor_label = attempted email) | – |
| `auth.logout` | user | user | – |
| `auth.password_reset_requested` | guest | user if exists, else none | – |
| `auth.password_reset` | user/guest | user | – |
| `auth.recovery_code_used` | user | user | – |
| `auth.two_factor_enabled` | user | user | – |
| `auth.recovery_codes_regenerated` | user | user | – |
| `user.created` | user/console | user | – → {name, email, role} |
| `user.updated` | user | user | changed fields only (name, email, role, locale, theme) |
| `user.disabled` / `user.enabled` | user | user | {is_active} |
| `user.password_reset` | user/console | user | – |
| `user.two_factor_reset` | user/console | user | – |
| `user.password_changed` | user | user (self) | – |

## Rules
1. Never store: `password`, `remember_token`, `app_authentication_secret`,
   `app_authentication_recovery_codes`, reset `token`, any TOTP/recovery `code`. If such a key
   reaches `AuditLogger`, its value is replaced with `"[redacted]"`.
2. `ip` is the client IP (after trusted proxies). `created_at` is UTC.
3. Entries are append-only: the model refuses update and delete; no panel action edits them.
4. `AuditLogger::transaction(fn)` commits a change and its entry together or neither (for
   later `SettingsRepository`).
5. `auth.password_reset_requested` must not reveal existence in the UI; the entry is internal.
