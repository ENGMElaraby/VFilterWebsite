---
description: "Tasks for 001 Admin Panel Shell"
---

# Tasks: Admin Panel Shell (Auth, Roles, Audit, Language, Theme)

**Input**: `specs/001-auth-and-shell/` (plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md)

**Tests**: Required. Constitution IV (test-first): each story's Pest tests are written first and must fail before implementation.

**Organization**: by user story. Paths are relative to the repo root (single Laravel app).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: US1–US4 from spec.md

---

## Phase 1: Setup

- [X] T001 Rename the `sqlsrv` connection to `filter` in config/database.php, set `'default' => env('DB_CONNECTION', 'filter')`, add `encrypt` / `trust_server_certificate` from `DB_ENCRYPT` / `DB_TRUST_SERVER_CERTIFICATE`, and let `DB_FILTER_DRIVER` switch the `filter` connection to `sqlite` (`database` = `DB_DATABASE`) for tests
- [X] T002 [P] Update .env.example: `DB_CONNECTION=filter`, `DB_HOST`, `DB_PORT=1433`, `DB_DATABASE=VFILTER`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ENCRYPT=yes`, `DB_TRUST_SERVER_CERTIFICATE=false`, `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, `SESSION_SECURE_COOKIE=true`, `PANEL_ALLOWED_IPS=`, `TRUSTED_PROXIES=`, `APP_DISPLAY_TIMEZONE=UTC`, `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en`, `MAIL_MAILER=log`
- [X] T003 [P] Set phpunit.xml env: `DB_CONNECTION=filter`, `DB_FILTER_DRIVER=sqlite`, `DB_DATABASE=:memory:`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`, `PANEL_ALLOWED_IPS=` (empty)
- [X] T004 [P] Create config/vfilter.php with `allowed_ips` (array from `PANEL_ALLOWED_IPS`, comma-separated IPs/CIDRs, empty = off), `audit.redact` keys (`password`, `password_confirmation`, `current_password`, `remember_token`, `app_authentication_secret`, `app_authentication_recovery_codes`, `token`, `code`), `login.max_attempts=5`, `login.decay_seconds=60`
- [X] T005 [P] Create lang skeletons lang/en/panel.php, lang/ar/panel.php, lang/en/roles.php, lang/ar/roles.php, lang/en/audit.php, lang/ar/audit.php; publish Laravel's lang files (`php artisan lang:publish`) and add Arabic lang/ar/{validation,auth,passwords,pagination}.php
- [X] T006 Confirm baseline is green: `php artisan test`, `./vendor/bin/phpstan`, `./vendor/bin/pint --test`

---

## Phase 2: Foundational (blocks all stories)

- [X] T007 Create app/Enums/UserRole.php: string-backed enum `owner|admin|viewer`, `rank()` (owner 3, admin 2, viewer 1), `atLeast(self $role): bool`, `label()` via `__('roles.'.value)`, `assignable(): array` returning `[admin, viewer]`
- [X] T008 Migration database/migrations/2026_10_17_000001_add_panel_columns_to_users_table.php: add `role` varchar(16) default `viewer` indexed, `is_active` bool default true, `must_change_password` bool default false, `locale` varchar(5) default `en`, `theme` varchar(8) default `system`, `app_authentication_secret` text null, `app_authentication_recovery_codes` text null, `last_login_at` dateTime null
- [X] T009 [P] Migration database/migrations/2026_10_17_000002_create_audit_log_table.php: `id` bigIncrements, `created_at` dateTime (UTC), `actor_type` varchar(16), `actor_id` unsignedBigInteger null (no FK), `actor_label` nvarchar(255) null, `ip` varchar(45) null, `action` varchar(64), `target_type` varchar(64) null, `target_id` varchar(64) null, `old_values` longText null, `new_values` longText null; indexes `(created_at)`, `(actor_id, created_at)`, `(action, created_at)`
- [X] T010 Update app/Models/User.php: casts (`role` → UserRole, `is_active`/`must_change_password` bool, `last_login_at` datetime), fillable, hidden secrets; implement `FilamentUser` (`canAccessPanel()` = `is_active`), `HasAppAuthentication`, `HasAppAuthenticationRecovery` with Filament's `InteractsWithAppAuthentication` + `InteractsWithAppAuthenticationRecovery`; email lower-cased on set; `isOwner()`; `saving` guard: refuse a second owner
- [X] T011 Update database/factories/UserFactory.php: states `owner()`, `admin()`, `viewer()`, `inactive()`, `mustChangePassword()`, `withTwoFactor()` (sets a generated secret)
- [X] T012 [P] Create app/Models/AuditLog.php (table `audit_log`, no `updated_at`, JSON casts on old/new values, `updating`/`deleting` throw `LogicException`)
- [X] T013 Write tests/Feature/Audit/AuditLoggerTest.php first: records actor user/console/system/guest, IP, UTC time; redacts every key in `vfilter.audit.redact` (nested too); `transaction()` rolls back both the change and the entry on exception; AuditLog update/delete throw
- [X] T014 Create app/Services/AuditLogger.php: `log(string $action, ?Model $target = null, array $old = [], array $new = [], ?string $actorType = null, ?string $actorLabel = null)`, `asConsole()`/`asSystem()` scope, `transaction(Closure $fn)`; register as singleton in app/Providers/AppServiceProvider.php; make T013 pass
- [X] T015 Configure `Password::defaults()` in app/Providers/AppServiceProvider.php: `min(12)`, `uncompromised()` only when `app()->isProduction()`
- [X] T016 [P] Create app/Http/Middleware/RestrictToAllowedIps.php (403 when `vfilter.allowed_ips` non-empty and request IP not matched; IPv4/IPv6 + CIDR via `Symfony\Component\HttpFoundation\IpUtils`) and configure trusted proxies from `TRUSTED_PROXIES` in bootstrap/app.php
- [X] T017 [P] Write tests/Feature/Security/IpAllowListTest.php: off when empty; 403 on `/admin/login` from non-listed IP when set; allowed IP passes
- [X] T018 Rewire app/Providers/Filament/AdminPanelProvider.php: `RestrictToAllowedIps` first in `middleware()`; remove `FilamentInfoWidget`; `navigationGroups` (Dashboard, Logs, Security, Settings, In-game rules, Administration); brand name `VFilter`; keep `AccountWidget`

**Checkpoint**: migrations run on SQLite, AuditLogger tested, panel boots.

---

## Phase 3: User Story 1 – Owner signs in securely (P1) 🎯 MVP

**Goal**: console-created owner signs in with email + password + TOTP; everything else redirects to sign-in; throttling; audit of auth events.

**Independent Test**: `vfilter:create-owner`, sign in, enrol 2FA, reach dashboard; signed-out URLs redirect; 6th wrong password refused.

### Tests (write first, must fail)

- [X] T019 [P] [US1] tests/Feature/Console/CreateOwnerTest.php: creates owner (`role=owner`, `is_active=1`, `must_change_password=0`, no 2FA); refuses when an owner exists, email taken, or password < 12 chars; writes `user.created` with actor `console`
- [X] T020 [P] [US1] tests/Feature/Console/ResetOwnerTest.php: `--password` (prompted) changes hash + rotates remember token; `--two-factor` nulls secret + codes; both delete owner sessions; requires at least one option; audit `user.password_reset` / `user.two_factor_reset` by `console`
- [X] T021 [P] [US1] tests/Feature/Auth/AccessTest.php: guest → every panel route redirects to `/admin/login`; intended URL restored after sign-in
- [X] T022 [P] [US1] tests/Feature/Auth/LoginTest.php (Livewire Filament Login): valid credentials proceed to MFA; wrong password and disabled user show the same generic error; `last_login_at` set after full sign-in; audit `auth.login`, `auth.login_failed` (actor_label = attempted email), `auth.logout`
- [X] T023 [P] [US1] tests/Feature/Auth/LoginThrottleTest.php: 5 failures for same email+IP within 60 s → 6th refused with throttle message even with correct password; different email from same IP not blocked by the email key; `auth.lockout` audited
- [X] T024 [P] [US1] tests/Feature/Auth/TwoFactorTest.php: user without 2FA is forced to setup page for every panel URL; setup shows recovery codes; challenge accepts current TOTP, rejects reused code, accepts a recovery code once and rejects it the second time (`auth.recovery_code_used` audited); 6th wrong code within 60 s is refused by the throttle and failures are audited (`auth.two_factor_failed`); profile shows no "disable" action; `auth.two_factor_enabled` audited

### Implementation

- [X] T025 [US1] Create app/Console/Commands/CreateOwner.php (`vfilter:create-owner {--email=} {--name=}`, hidden password prompt + confirm, validation with `Password::defaults()`) per contracts/console-commands.md
- [X] T026 [US1] Create app/Console/Commands/ResetOwner.php (`vfilter:reset-owner {--password} {--two-factor}`), deletes owner rows in `sessions`, audit as console
- [X] T027 [US1] Create app/Auth/MandatoryAppAuthentication.php extending `Filament\Auth\MultiFactor\App\AppAuthentication`: `getActions()` without `DisableAppAuthenticationAction`; `verifyRecoveryCode()` logs `auth.recovery_code_used` on success; `saveSecret()` logs `auth.two_factor_enabled` when a secret is set; regenerate action logs `auth.recovery_codes_regenerated`
- [X] T028 [US1] Create app/Filament/Pages/Auth/Login.php extending Filament `Login`: before authenticating, `RateLimiter` key `login:{lower(email)}|{ip}` with `vfilter.login.max_attempts`/`decay_seconds`; on hit fire `Lockout` and show Filament's throttled notification; clear key on success
- [X] T029 [US1] Create app/Listeners/RecordAuthEvents.php for `Login` (also sets `last_login_at`), `Failed`, `Logout`, `Lockout` → AuditLogger; register in app/Providers/AppServiceProvider.php
- [X] T030 [US1] In app/Providers/Filament/AdminPanelProvider.php: `->login(Login::class)`, `->multiFactorAuthentication([MandatoryAppAuthentication::make()->recoverable()->recoveryCodeCount(8)], isRequired: true)`, `->profile()` placeholder (finished in US4)
- [X] T031 [US1] Make T019–T024 pass; run `php artisan test tests/Feature/Auth tests/Feature/Console tests/Feature/Audit tests/Feature/Security`, `phpstan`, `pint`

**Checkpoint**: MVP – owner can sign in securely; all auth events audited.

---

## Phase 4: User Story 2 – Owner manages users and roles (P1)

**Goal**: owner-only Users resource with create, edit, disable/enable, reset password, reset 2FA; forced password change; disabled users kicked; owner row protected; email reset path.

**Independent Test**: owner creates admin + viewer; they cannot open Users; disabling ends the viewer's session.

### Tests (write first, must fail)

- [ ] T032 [P] [US2] tests/Feature/Users/UserPolicyTest.php: owner can viewAny/create/update non-owner; admin and viewer get 403 on `/admin/users` and see no menu item; nobody can update/disable/delete the owner row or assign `owner`
- [ ] T033 [P] [US2] tests/Feature/Users/ManageUsersTest.php (Livewire resource pages): create with role ∈ {admin, viewer}, unique email (case-insensitive), password policy; edit name/email/role; audit `user.created`/`user.updated` with only changed fields and no secrets
- [ ] T034 [P] [US2] tests/Feature/Users/UserActionsTest.php: disable → `is_active=0`, sessions rows deleted, `user.disabled`; enable → `user.enabled`; reset password → `must_change_password=1`, sessions deleted, `user.password_reset`; reset 2FA → secret+codes null, sessions deleted, `user.two_factor_reset`; no delete action
- [ ] T035 [P] [US2] tests/Feature/Auth/ForcedPasswordChangeTest.php: user with `must_change_password` is redirected to `/admin/change-password` from every page; after change flag cleared, `user.password_changed` audited
- [ ] T036 [P] [US2] tests/Feature/Auth/DisabledUserTest.php: signed-in user disabled → next request logged out to sign-in
- [ ] T037 [P] [US2] tests/Feature/Auth/PasswordResetTest.php: with `MAIL_MAILER=log` the forgot link is hidden and the request page returns 404; with mail enabled request shows neutral message for unknown email, sends notification for known; reset link works once and ends sessions; still requires 2FA after reset; `auth.password_reset_requested` + `auth.password_reset` audited

### Implementation

- [ ] T038 [US2] Create app/Policies/UserPolicy.php (owner-only; owner row immutable for role/is_active/delete; `delete` always false) and register via `Gate::policy` in AppServiceProvider
- [ ] T039 [US2] Create app/Observers/UserObserver.php: `created` → `user.created` ({name,email,role}), `updated` → `user.updated` with dirty fields among name/email/role/locale/theme only; `is_active` changes → `user.disabled`/`user.enabled`
- [ ] T040 [US2] Create app/Filament/Resources/Users/UserResource.php (+ Pages List/Create/Edit, Schemas/UserForm.php, Tables/UsersTable.php) in group Administration: form fields name, email, role (select from `UserRole::assignable()`), password on create (`Password::defaults()`, confirmed) → sets `must_change_password=1`; table columns name, email, role badge, active, 2FA enrolled, last sign-in; row actions Disable/Enable, Reset password (modal with new password), Reset two-factor (confirm) – hidden for the owner row
- [ ] T041 [US2] Create app/Support/EndUserSessions.php helper (delete `sessions` rows by `user_id`, rotate `remember_token`) used by actions, console reset and password reset listener
- [ ] T042 [US2] Create app/Http/Middleware/EnsureUserIsActive.php and app/Http/Middleware/EnsurePasswordIsChanged.php; add both to `authMiddleware()` after `Authenticate`
- [X] T042a [US1] Create app/Http/Middleware/PreventBackHistory.php setting `Cache-Control: no-store, no-cache, must-revalidate, private` and `Pragma: no-cache` on every panel response; add to panel `middleware()`; test in tests/Feature/Auth/AccessTest.php (header present on `/admin` and `/admin/login`)
- [ ] T043 [US2] Create app/Filament/Pages/ChangePassword.php (current password, new password `Password::defaults()` confirmed; clears flag; audit `user.password_changed`; hidden from navigation)
- [ ] T044 [US2] Enable email reset in AdminPanelProvider: `->passwordReset()` only when `config('mail.default')` not in [`log`, `array`]; set `auth.passwords.users.expire=60`; listener for `PasswordReset` event → audit + EndUserSessions; audit `auth.password_reset_requested` from a custom `RequestPasswordReset` page (neutral response unchanged)
- [ ] T045 [US2] Make T032–T037 pass; phpstan; pint

**Checkpoint**: owner shares access safely.

---

## Phase 5: User Story 3 – Audit log page (P2)

**Goal**: read-only audit log for owner + admin with filters and fast pagination.

**Independent Test**: after US1/US2 actions, admin sees entries; viewer gets 403.

### Tests (write first, must fail)

- [ ] T046 [P] [US3] tests/Feature/Audit/AuditLogPolicyTest.php: owner and admin can list/view; viewer 403 and no menu item; no create/edit/delete routes or actions for anyone
- [ ] T047 [P] [US3] tests/Feature/Audit/AuditLogPageTest.php: newest first, paginated 25; filters actor, action, date range; old → new values rendered; action labels translated, unknown action shows raw id; times shown in `APP_DISPLAY_TIMEZONE`

### Implementation

- [ ] T048 [US3] Create app/Policies/AuditLogPolicy.php (viewAny/view: `role->atLeast(admin)`; create/update/delete/restore/forceDelete: false) and register
- [ ] T049 [US3] Create app/Filament/Resources/AuditLogs/AuditLogResource.php (+ Pages List/View, Tables/AuditLogsTable.php, Schemas/AuditLogInfolist.php) in group Administration: columns time, actor (label + type badge), action (translated), target, IP; filters SelectFilter actor, SelectFilter action, date range (created_at from/until); default sort `created_at desc`; view page shows old/new values as key → old → new list; no create/edit/delete
- [ ] T050 [US3] Fill lang/en/audit.php and lang/ar/audit.php with labels for every action in contracts/audit-actions.md
- [ ] T051 [US3] Make T046–T047 pass; phpstan; pint

---

## Phase 6: User Story 4 – Language and theme (P3)

**Goal**: per-user EN/AR (RTL) and light/dark/system saved on the account.

**Independent Test**: choose Arabic + dark, sign in elsewhere, still Arabic RTL + dark.

### Tests (write first, must fail)

- [ ] T052 [P] [US4] tests/Feature/Preferences/LocaleTest.php: guest with `Accept-Language: ar` gets Arabic login with `dir="rtl"`; other languages get English; signed-in user's `locale` applied on every page; switching saves to the user and audits `user.updated`
- [ ] T053 [P] [US4] tests/Feature/Preferences/ThemeTest.php: profile saves `theme` ∈ {light, dark, system}; page HTML contains the head script that sets `localStorage.theme` to the saved value
- [ ] T054 [P] [US4] tests/Feature/Preferences/ProfileTest.php: changing email or password requires current password; a profile password change writes `user.password_changed` with no secret values; no 2FA disable action; recovery codes can be regenerated
- [ ] T055 [P] [US4] tests/Feature/Preferences/TranslationsTest.php: every key in lang/en/{panel,roles,audit}.php exists in lang/ar (and vice versa)

### Implementation

- [ ] T056 [US4] Create app/Http/Middleware/ApplyUserPreferences.php (user locale, else `Accept-Language` ar/en, else `en`; also session `locale` for guests) and add after `StartSession` in panel `middleware()`
- [ ] T057 [US4] Create app/Filament/Pages/Auth/EditProfile.php extending Filament `EditProfile`: add `locale` (select en/ar) and `theme` (select light/dark/system) fields; current password required for email/password change; a password change here audits `user.password_changed`; register with `->profile(EditProfile::class, isSimple: false)`
- [ ] T058 [US4] Add user-menu items in AdminPanelProvider (`->userMenuItems`): "English"/"العربية" switch and theme switch that save to the user; add a language link on the login page via `PanelsRenderHook::AUTH_LOGIN_FORM_AFTER`; disable Filament's built-in theme switcher (`->darkMode(true)` kept, switcher replaced)
- [ ] T059 [US4] Add `PanelsRenderHook::HEAD_START` view resources/views/filament/theme-sync.blade.php that writes the user's `theme` into `localStorage.theme` before Filament's script runs
- [ ] T060 [US4] Fill lang/en/panel.php + lang/ar/panel.php (navigation groups, user menu, profile fields, change password page, throttle and generic messages) and lang/en/roles.php + lang/ar/roles.php
- [ ] T061 [US4] Make T052–T055 pass; phpstan; pint

---

## Phase 7: Polish & cross-cutting

- [ ] T062 [P] tests/Feature/Security/RouteAuthorizationTest.php: iterate every registered `/admin` route and assert guest → redirect, viewer → only allowed pages (SC-003)
- [ ] T062b [P] tests/Feature/Audit/AuditLogPerformanceTest.php tagged `@group sqlsrv` (skipped on SQLite): seed 1,000,000 `audit_log` rows, assert the first page query with each filter runs under 2 s (SC-007)
- [ ] T063 [P] Update docs/shared-contract.md in **both** repos (VFilterWebsite and ../VFilter): version 0.1.0 → 0.1.1 (PATCH), note that website-only table columns are defined in `specs/001-auth-and-shell/data-model.md`
- [ ] T064 [P] Update HANDOFF.md (status: 001 built; next 002) and CLAUDE.md commands (`vfilter:create-owner`, `vfilter:reset-owner`)
- [ ] T065 Run the full quickstart.md "Tests only" section; then, if a SQL Server is reachable, the SQL Server section and scenarios 1–9
- [ ] T066 Final gates: `php artisan test`, `./vendor/bin/phpstan`, `./vendor/bin/pint --test` all green; coverage ≥ 80% for app/ (`php artisan test --coverage --min=80` when a coverage driver is available)

---

## Dependencies & order

- Setup (T001–T006) → Foundational (T007–T018) → stories.
- US1 (Phase 3) first: MVP and it provides MFA wiring + auth audit.
- US2 depends on US1's panel wiring (T030) and EndUserSessions is shared with US1's ResetOwner (T026 may inline it first; T041 extracts).
- US3 depends only on Foundational (AuditLog + AuditLogger) and can run in parallel with US2.
- US4 depends on Foundational; its profile page replaces US1's placeholder (T030).
- Polish after all stories.

## Parallel examples

- Phase 1: T002, T003, T004, T005 together.
- Phase 2: T009, T012, T016, T017 together after T007/T008.
- US1 tests T019–T024 together; then T025/T026/T027/T028 in parallel (different files), then T029–T031.
- US2 tests T032–T037 together.
- US3 (T046–T051) alongside US2.
- US4 tests T052–T055 together.

## Implementation strategy

1. MVP = Setup + Foundational + US1 → stop and validate quickstart scenarios 1, 2, 8, 9.
2. Add US2 → validate scenarios 3, 4, 7.
3. Add US3 → scenario 5. 4. Add US4 → scenario 6. 5. Polish.
