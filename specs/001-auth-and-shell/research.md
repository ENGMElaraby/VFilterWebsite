# Research: 001 Admin Panel Shell

Checked against installed code: Filament 5.10.2 (`vendor/filament/filament/src`), Laravel 13.37.

## 1. Sign-in, password reset, profile
- **Decision**: Filament panel built-ins: `->login(CustomLogin::class)`, `->passwordReset()`,
  `->profile(CustomEditProfile::class)`.
- **Rationale**: First-party, already installed, translated (Filament ships `ar`), Livewire-tested.
- **Alternatives**: Laravel Fortify/Breeze (second auth stack beside Filament, more code); Jetstream (heavy).

## 2. Two-factor for everyone, no opt-out
- **Decision**: `->multiFactorAuthentication([MandatoryAppAuthentication::make()->recoverable()], isRequired: true)`.
  `MandatoryAppAuthentication` extends `Filament\Auth\MultiFactor\App\AppAuthentication` and
  overrides `getActions()` to drop `DisableAppAuthenticationAction` (Filament shows it even when
  MFA is required). Code reuse prevention on (`verifyCode(..., shouldPreventCodeReuse: true)`
  is used by the challenge). 8 recovery codes, regenerable from the profile.
- **User model**: implements `HasAppAuthentication`, `HasAppAuthenticationRecovery` using
  Filament's `InteractsWithAppAuthentication` + `InteractsWithAppAuthenticationRecovery`
  traits. Columns `app_authentication_secret` (encrypted text) and
  `app_authentication_recovery_codes` (encrypted array, hashed codes) on `users`; both hidden.
- **Owner/console reset of 2FA**: set both columns to null → required-setup middleware forces
  re-enrolment at next sign-in.
- **Alternatives**: `laragear/two-factor`, `filament-breezy` (extra packages; Filament 5 has it natively).

## 3. Sign-in throttle per email + IP (FR-004)
- **Decision**: Filament's `Login` rate-limits 5/min **per IP only**. Custom `Login` page adds a
  Laravel `RateLimiter` key `login:{lower(email)}|{ip}` (5 attempts / 60 s) before
  authenticating; on hit fire `Illuminate\Auth\Events\Lockout` and show Filament's throttled
  notification. The MFA challenge reuses Filament's built-in limiter. Failures are audited via
  the `Failed` event.
- **Alternatives**: Middleware on the Livewire update route (can't see the email cleanly).

## 4. Generic errors, disabled users (FR-005, FR-006)
- **Decision**: `User::canAccessPanel()` returns `is_active`. Filament then shows the same
  "credentials do not match" error for disabled users. `EnsureUserIsActive` middleware logs out
  and invalidates the session on the next request after disabling. Disabling also deletes the
  user's rows in `sessions` (database driver) so other devices end too.

## 5. Session lifetime and invalidation
- **Decision**: `SESSION_DRIVER=database` on `filter`, `SESSION_LIFETIME=120` (idle), 
  `SESSION_EXPIRE_ON_CLOSE=false`, `SESSION_SECURE_COOKIE=true` in production. Password reset by
  owner or email → delete the user's `sessions` rows + rotate `remember_token`.
  `AuthenticateSession` middleware (already in panel) logs out other devices on password change.

## 6. Forced password change (FR-013)
- **Decision**: `must_change_password` bool. `EnsurePasswordIsChanged` (panel auth middleware)
  redirects to a simple `ChangePassword` page until changed. Set on owner-created users and
  owner password resets. Not set for the console-created owner, who chose their own password.

## 7. Password policy (FR-007)
- **Decision**: `Password::defaults()` in `AppServiceProvider`: `min(12)`, plus `->uncompromised()`
  in production only (HIBP k-anonymity call; skipped when offline/tests, matching "where available").

## 8. Roles and authorization
- **Decision**: `UserRole` backed enum (`owner`, `admin`, `viewer`) cast on `users.role`, with
  `rank()` for `atLeast()` checks. Policies: `UserPolicy` (owner only; owner row cannot be
  deleted/disabled/demoted; nobody can create a second owner from the panel), `AuditLogPolicy`
  (viewAny/view: owner+admin; create/update/delete: false). Filament resources respect policies
  for menu visibility and direct URL access.
- **Exactly one owner**: unique filtered index is not portable to SQLite tests; enforced in
  `UserPolicy`, the console command (refuses if an owner exists), and a model `saving` guard.
- **Alternatives**: `spatie/laravel-permission` (overkill for 3 fixed roles; YAGNI).

## 9. Audit log
- **Decision**: `audit_log` table, `AuditLog` model with `updating`/`deleting` hooks that throw
  (append-only). `AuditLogger::log(string $action, ?Model $target, array $old = [], array $new = [])`
  records actor (auth user, or `console`/`system`), IP, UTC time. A redaction list
  (`password`, `remember_token`, `app_authentication_secret`, `app_authentication_recovery_codes`,
  `token`, `code`) is replaced by `"[redacted]"`. `AuditLogger::transaction(Closure)` wraps a write
  and its entry in one DB transaction for later features (FR-019).
- **Events**: `Login`, `Failed`, `Logout`, `Lockout`, `PasswordReset` listeners; `UserObserver`
  for create/update (diff of dirty attributes); MFA enrolment/reset logged from our actions and
  the profile. Recovery-code use: Filament has no event, so `MandatoryAppAuthentication` overrides
  `verifyRecoveryCode()` to log on success.
- **Indexes**: `(created_at)`, `(actor_id, created_at)`, `(action, created_at)` for SC-007.
- **Alternatives**: `owen-it/laravel-auditing`, `spatie/laravel-activitylog` (good, but we need
  strict redaction, append-only, console actor and a transaction helper; small enough to own).

## 10. Language (EN/AR, RTL) per user
- **Decision**: `users.locale` (`en`|`ar`). `ApplyUserPreferences` middleware (panel middleware,
  after `StartSession`) sets `app()->setLocale()` from the user, else from
  `Accept-Language` (ar/en, default en) before sign-in. Filament reads
  `__('filament-panels::layout.direction')` → `rtl` for `ar` automatically. A language switch
  (user menu item + sign-in page link) saves the choice (session pre-login, user column after).
  Missing `ar` keys fall back to `en` (`fallback_locale=en`).
- **Alternatives**: `bezhansalleh/filament-language-switch` (verify Filament 5 support first; not
  needed for two locales).

## 11. Theme per user, across devices (FR-021)
- **Decision**: Filament stores the theme in browser `localStorage['theme']`. Add `users.theme`
  (`light`|`dark`|`system`). A render hook (`PanelsRenderHook::HEAD_START`) prints a tiny inline
  script that sets `localStorage.theme` from the user's saved value on each page load; a profile
  field and the user-menu theme switcher save changes back (Livewire action on profile; the
  built-in switcher is replaced by our menu items to keep one source of truth).

## 12. IP allow-list (FR-007b)
- **Decision**: `PANEL_ALLOWED_IPS` env (comma-separated IPs/CIDRs, empty = off), read via
  `config/vfilter.php`. `RestrictToAllowedIps` is the first panel middleware; 403 with no detail.
  `TrustProxies` configured from env so the real client IP is used behind IIS/Nginx.

## 13. Mail for reset links (FR-007a)
- **Decision**: Show "Forgot password?" only when `config('mail.default')` is not `log`/`array`
  and `MAIL_HOST` is set (`vfilter.mail_enabled` helper). Filament's `RequestPasswordReset` already
  answers neutrally and throttles (2/min). Token expiry 60 min (`auth.passwords.users.expire`).
  Failures to send are caught and logged, user still sees the neutral message.

## 14. SQL Server as default connection
- **Decision**: rename the `sqlsrv` connection to `filter` in `config/database.php`, `DB_CONNECTION=filter`.
  `.env.example` gets `DB_HOST/PORT/DATABASE=VFILTER/USERNAME/PASSWORD`, `DB_ENCRYPT`,
  `DB_TRUST_SERVER_CERTIFICATE`. `phpunit.xml`: `DB_CONNECTION=filter` with a test-only
  override mapping `filter` to SQLite `:memory:` (driver set by `DB_FILTER_DRIVER=sqlite`).
  Migrations use portable schema builder types (`dateTime` → `datetime2` on SQL Server).
- **Note**: the `account`/`shard`/`log` connections are added by later features, not here.

## 15. First owner and recovery (FR-008)
- **Decision**: `php artisan vfilter:create-owner` (interactive or `--email --name`, password
  prompted hidden, policy-validated; refuses if any owner exists). `php artisan vfilter:reset-owner
  --password` (prompted) and/or `--two-factor` (clears secret + codes), both end owner sessions and
  write an audit entry with actor `console`.
