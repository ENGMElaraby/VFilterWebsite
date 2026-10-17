# Data Model: 001 Admin Panel Shell

All tables live in `VFILTER` (connection `filter`). Website-only: the filter ignores them.
Times are UTC (`datetime2` on SQL Server).

## users (alter Laravel's default table)

| column | type | rules |
| --- | --- | --- |
| id | bigint PK | |
| name | nvarchar(255) | required, ≤ 255 |
| email | nvarchar(255) unique | required, valid email, stored lower-case |
| email_verified_at | datetime2 null | unused in v1 (kept from Laravel default) |
| password | nvarchar(255) | bcrypt/argon hash; policy: ≥ 12 chars, uncompromised (prod) |
| remember_token | nvarchar(100) null | rotated on password reset |
| **role** | varchar(16) | `owner` \| `admin` \| `viewer`; exactly one `owner` |
| **is_active** | bit default 1 | owner row always 1 |
| **must_change_password** | bit default 0 | set by owner create/reset |
| **locale** | varchar(5) default `en` | `en` \| `ar` |
| **theme** | varchar(8) default `system` | `light` \| `dark` \| `system` |
| **app_authentication_secret** | nvarchar(max) null | encrypted (Filament trait), hidden |
| **app_authentication_recovery_codes** | nvarchar(max) null | encrypted array of hashed codes, hidden |
| **last_login_at** | datetime2 null | set on successful sign-in (after 2FA) |
| created_at / updated_at | datetime2 | |

Index: `role`.

**States**
```
created by owner ──► must_change_password=1, no 2FA
   sign-in ─► change password ─► enrol 2FA (recovery codes shown once) ─► active use
owner "reset password"  ─► must_change_password=1, sessions deleted
owner "reset two-factor" ─► secret+codes = null, sessions deleted ─► re-enrol at next sign-in
owner "disable" ─► is_active=0, sessions deleted ─► sign-in refused (generic error)
owner "enable"  ─► is_active=1
```
Owner row: role, is_active and deletion locked in the panel; console can reset password/2FA.

## audit_log (new)

| column | type | rules |
| --- | --- | --- |
| id | bigint PK | |
| created_at | datetime2 | UTC, indexed |
| actor_type | varchar(16) | `user` \| `console` \| `system` \| `guest` (failed sign-in) |
| actor_id | bigint null | `users.id` when `actor_type=user`; no FK (entries survive user removal) |
| actor_label | nvarchar(255) null | name/email snapshot at the time (or attempted email for `guest`) |
| ip | varchar(45) null | IPv4/IPv6 |
| action | varchar(64) | canonical name, see `contracts/audit-actions.md` |
| target_type | varchar(64) null | e.g. `user`, later `setting`, `command` |
| target_id | varchar(64) null | string to allow non-integer keys (setting keys) |
| old_values | nvarchar(max) null | JSON, redacted |
| new_values | nvarchar(max) null | JSON, redacted |

Indexes: `(created_at)`, `(actor_id, created_at)`, `(action, created_at)`.
No `updated_at`. Model blocks update and delete. No FK so entries are never cascaded away.

## sessions (Laravel default, database driver)

`id` PK, `user_id` (indexed), `ip_address`, `user_agent`, `payload`, `last_activity` (indexed).
Used to end all of a user's sessions on disable / reset.

## password_reset_tokens (Laravel default)

`email` PK, `token` (hashed), `created_at`. Expiry 60 min, single use.

## Other default tables

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` from the scaffold stay, on `filter`.

## Validation summary

| field | rule |
| --- | --- |
| email | required, email, unique:users (case-insensitive) |
| password | `Password::defaults()` (min 12, uncompromised in prod), confirmed |
| role (panel) | in: `admin`, `viewer` (owner never assignable from the panel) |
| locale | in: `en`, `ar` |
| theme | in: `light`, `dark`, `system` |
