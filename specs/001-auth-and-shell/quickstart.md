# Quickstart: validate 001 Admin Panel Shell

## Prerequisites
- PHP 8.5 on PATH. On the dev Mac the global `php` is 7.4: set Herd's default to 8.5
  (Herd app → PHP), or prefix commands with Herd's binary
  (`"$HOME/Library/Application Support/Herd/bin/php85"`).
- `pdo_sqlsrv` loaded (`php -m | grep sqlsrv`).
- A reachable SQL Server with an empty `VFILTER` database (or skip to "Tests only").

## Tests only (no SQL Server needed)
```bash
php artisan test            # SQLite in-memory for the filter connection
./vendor/bin/phpstan
./vendor/bin/pint --test
```
Expected: all green.

## Run against SQL Server
```bash
cp .env.example .env && php artisan key:generate
# fill DB_HOST, DB_PORT, DB_DATABASE=VFILTER, DB_USERNAME, DB_PASSWORD in .env
php artisan migrate
php artisan vfilter:create-owner --email=owner@example.test --name="Owner"
composer run dev            # http://localhost:8000/admin
```

## Scenarios (map to spec user stories)
1. **Sign-in + 2FA (US1)**: open `/admin` → redirected to login → sign in as owner → forced 2FA
   setup → scan QR, confirm code → recovery codes shown → dashboard. Sign out, sign in again →
   asked for code → dashboard. Use a recovery code once → works; same code again → refused.
2. **Throttle (US1)**: 5 wrong passwords → 6th refused with a "too many attempts" message;
   audit log shows `auth.login_failed` ×5 and `auth.lockout`.
3. **Users (US2)**: as owner create an admin and a viewer → each signs in, must change password,
   then enrol 2FA. As admin/viewer, `/admin/users` → 403 and no menu item. Disable the viewer →
   their open session ends on next click; sign-in shows the generic error.
4. **Owner protection (US2)**: no disable/delete/role change offered for the owner row.
5. **Audit (US3)**: as admin open Audit log → entries for all of the above, newest first, filters
   work, no secrets in any entry, no edit/delete. As viewer → 403.
6. **Language/theme (US4)**: switch to Arabic + dark → layout RTL, all labels Arabic. Sign in on
   another browser → still Arabic + dark.
7. **Password reset**: with mail configured (e.g. Mailpit) request a reset → link works once,
   within 60 min; with `MAIL_MAILER=log` the "Forgot password?" link is hidden.
8. **Console recovery**: `php artisan vfilter:reset-owner --two-factor` → owner re-enrols at next
   sign-in; audit shows `user.two_factor_reset` by `console`.
9. **IP allow-list**: set `PANEL_ALLOWED_IPS=10.0.0.1` → every `/admin` page returns 403 from
   localhost; clear it → works again.

See [contracts/panel-routes.md](contracts/panel-routes.md) and
[contracts/audit-actions.md](contracts/audit-actions.md) for the full expectations.
