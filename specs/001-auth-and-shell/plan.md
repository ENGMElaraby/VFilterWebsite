# Implementation Plan: Admin Panel Shell (Auth, Roles, Audit, Language, Theme)

**Branch**: `001-auth-and-shell` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-auth-and-shell/spec.md`

## Summary

Turn the scaffolded Laravel 13 + Filament 5 app into the secured VFilter panel shell. Filament's
built-in auth gives sign-in, app-based two-factor (TOTP) with recovery codes, email password reset,
profile page and dark mode. We add what it lacks: a `role` enum (owner/admin/viewer) with policies,
mandatory two-factor for all users with no "disable" button, owner-only user management (create,
role, disable, reset password, reset two-factor), forced password change, per-email+IP sign-in
throttle, an append-only `audit_log` written by an `AuditLogger` service and shown in a read-only
Filament resource, per-user language (EN/AR, RTL) and theme saved on the account, an optional IP
allow-list, and console commands to create and recover the owner. Everything is stored in the
`VFILTER` SQL Server database (`filter` connection, default); tests run on SQLite in memory.

## Technical Context

**Language/Version**: PHP 8.5 (Herd `php85`; global `php` on the dev Mac is 7.4, see quickstart)

**Primary Dependencies**: Laravel 13.37, Filament 5.10 (Livewire 4, Tailwind 4), `pragmarx/google2fa`
(pulled in by Filament for TOTP), `bacon/bacon-qr-code` (Filament QR codes)

**Storage**: SQL Server, database `VFILTER`, connection `filter` (default). Session driver
`database`, cache driver `database`, both on `filter`.

**Testing**: Pest 5 + `pest-plugin-laravel`, Livewire testing helpers for Filament pages; SQLite
in-memory for the `filter` connection in `phpunit.xml`. Larastan level 6, Pint.

**Target Platform**: Web panel, public internet over HTTPS. Host: Windows Server (IIS) or Linux,
reaching SQL Server. Dev: macOS with Herd.

**Project Type**: Web application (single Laravel app, server-rendered via Filament/Livewire)

**Performance Goals**: Sign-in to dashboard < 30 s for a human (SC-001); audit log first page
< 2 s at 1,000,000 rows (SC-007).

**Constraints**: All times UTC in DB, shown in `APP_DISPLAY_TIMEZONE`. No secrets in DB/logs/UI. No writes to
SRO_VT_* databases. No contact with the filter. Every string translatable (EN/AR).

**Scale/Scope**: < 20 panel users; audit log grows unbounded (millions of rows later, from
settings and commands features); ~8 screens in this feature.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | How this plan complies |
| --- | --- | --- |
| I. Shared contract is law | Pass | Only website-only tables (`users`, `password_reset_tokens`, `sessions`, `audit_log`) are touched. Their columns are documented in `data-model.md`. The contract lists them only by name; a PATCH note pointing to `data-model.md` is added to `docs/shared-contract.md` in **both** repos (version 0.1.0 → 0.1.1). |
| II. Database-only control | Pass | No sockets, no shell, no SRO_VT_* writes. |
| III. Security first | Pass | Every page behind auth + role policy; TOTP 2FA for all users; per-email+IP throttle; audit log for every auth and user change; secrets only in `.env`; CSRF via Filament defaults; HTTPS + optional IP allow-list. |
| IV. Test-first | Pass | Each user story has Pest feature tests written before implementation (see tasks). Permissions and audit logging always tested. |
| V. Catalog-driven settings | N/A | No filter settings in this feature. The `AuditLogger` is the hook the future `SettingsRepository` will call. |
| VI. Bilingual, responsive UI | Pass | All strings in `lang/en` + `lang/ar`; Filament ships `ar`; RTL from locale; Filament layout is responsive. |
| VII. Simplicity, current versions | Pass | Uses Filament built-ins first; no extra auth packages (no Spatie permissions: 3 fixed roles fit an enum + policies). |

Post-design re-check: still passing. No Complexity Tracking entries.

## Project Structure

### Documentation (this feature)

```text
specs/001-auth-and-shell/
├── plan.md              # This file
├── research.md          # Phase 0: decisions on Filament auth, MFA, throttle, locale, theme
├── data-model.md        # Phase 1: users, audit_log, sessions, password_reset_tokens
├── quickstart.md        # Phase 1: run + validate end to end
├── contracts/
│   ├── panel-routes.md      # Pages, who can open them, what they do
│   ├── console-commands.md  # vfilter:* artisan commands
│   └── audit-actions.md     # Canonical audit action names + payload rules
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── UserRole.php                     # owner|admin|viewer, labels (EN/AR), rank compare
├── Models/
│   ├── User.php                         # + role, is_active, must_change_password, locale, theme,
│   │                                    #   MFA contracts (HasAppAuthentication[Recovery]), FilamentUser
│   └── AuditLog.php                     # append-only (throws on update/delete)
├── Policies/
│   ├── UserPolicy.php                   # owner-only; owner row protected
│   └── AuditLogPolicy.php               # owner+admin view; nobody edits/deletes
├── Services/
│   └── AuditLogger.php                  # log(action, target, old, new); redacts secrets
├── Listeners/
│   └── RecordAuthEvents.php             # Login, Failed, Logout, Lockout, PasswordReset → audit
├── Observers/
│   └── UserObserver.php                 # user create/update → audit (diff, redacted)
├── Http/Middleware/
│   ├── RestrictToAllowedIps.php         # PANEL_ALLOWED_IPS (off when empty)
│   ├── EnsureUserIsActive.php           # disabled → logout + session end
│   ├── EnsurePasswordIsChanged.php      # must_change_password → change-password page
│   └── ApplyUserPreferences.php         # locale + theme from user (or browser pre-login)
├── Auth/
│   └── MandatoryAppAuthentication.php   # extends Filament AppAuthentication, drops Disable action
├── Console/Commands/
│   ├── CreateOwner.php                  # vfilter:create-owner
│   └── ResetOwner.php                   # vfilter:reset-owner (--password / --two-factor)
├── Filament/
│   ├── Pages/
│   │   ├── Auth/Login.php               # throttle per email+IP, generic errors
│   │   ├── Auth/EditProfile.php         # + locale, theme, current-password confirm
│   │   └── ChangePassword.php           # forced change page
│   └── Resources/
│       ├── Users/                       # UserResource (owner-only) + reset actions
│       └── AuditLogs/                   # read-only list + filters + view
└── Providers/Filament/AdminPanelProvider.php   # auth, MFA required, reset, profile, groups, hooks
database/migrations/                     # alter users; create audit_log (filter connection)
lang/{en,ar}/                            # panel.php, roles.php, audit.php (+ ar validation)
tests/Feature/
├── Auth/                                # sign-in, 2FA, throttle, reset, disabled, forced change
├── Users/                               # owner CRUD, role guards, owner protection
├── Audit/                               # entries written, redaction, read-only, filters
├── Preferences/                         # locale/RTL, theme persistence
└── Console/                             # create-owner, reset-owner
```

**Structure Decision**: Single Laravel app (the existing scaffold). Filament resources/pages under
`app/Filament`, domain code in `app/Services`, `app/Policies`, `app/Enums`. Tests mirror the user
stories under `tests/Feature`.

## Phase notes

- **Phase 0** → [research.md](research.md): all decisions resolved; no open NEEDS CLARIFICATION.
- **Phase 1** → [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md).
- Shared contract: add a PATCH note in `docs/shared-contract.md` (both repos) that website-only
  table columns are defined by the website (`specs/001-auth-and-shell/data-model.md`); bump to 0.1.1.

## Complexity Tracking

None.
