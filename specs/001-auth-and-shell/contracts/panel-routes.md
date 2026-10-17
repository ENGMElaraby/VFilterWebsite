# Contract: Panel pages and access

Panel path: `/admin`. Every page below `/admin` passes, in order: `RestrictToAllowedIps` →
session/CSRF → `ApplyUserPreferences` → `Authenticate` → `EnsureUserIsActive` →
`EnsurePasswordIsChanged` → MFA-required setup → page policy.

| Page | Path (under /admin) | Guest | viewer | admin | owner | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Sign in | `/login` | ✓ | – | – | – | Email + password, throttled per email+IP and per IP; language link |
| 2FA challenge | (Filament MFA step on login) | ✓* | – | – | – | *after valid password; authenticator code or recovery code |
| 2FA setup (required) | `/multi-factor-authentication/set-up` | – | ✓ | ✓ | ✓ | Forced until enrolled; shows recovery codes once |
| Forgot password | `/password-reset/request` | ✓ | – | – | – | Only when mail configured; neutral response |
| Reset password | `/password-reset/reset` | ✓ | – | – | – | Signed link, 60 min, single use |
| Change password (forced) | `/change-password` | – | ✓ | ✓ | ✓ | Only reachable/required when `must_change_password` |
| Dashboard | `/` | – | ✓ | ✓ | ✓ | Placeholder (filled by feature 003) |
| Profile | `/profile` | – | ✓ | ✓ | ✓ | Name, email, password (current password required), 2FA (regenerate codes, no disable), language, theme |
| Users | `/users` (+ create/edit) | – | 403 | 403 | ✓ | Hidden from menu for non-owners |
| Audit log | `/audit-logs` (+ view) | – | 403 | ✓ | ✓ | Read-only, filters: actor, action, date range |

Guests requesting any non-guest page → redirect to `/admin/login`, then back to the intended URL.
IP not allowed (when list is on) → 403 on every page including `/login`.

## User actions (Users page, owner only)

| Action | Allowed targets | Effect |
| --- | --- | --- |
| Create | – | role ∈ {admin, viewer}, initial password, `must_change_password=1` |
| Edit | non-owner | name, email, role ∈ {admin, viewer} |
| Disable / Enable | non-owner | `is_active` toggle; disable deletes sessions |
| Reset password | non-owner | new password, `must_change_password=1`, sessions deleted |
| Reset two-factor | non-owner | secret + codes cleared, sessions deleted |
| Delete | none | Not offered (disable instead; keeps audit actor history readable) |

Every action writes one audit entry (see `audit-actions.md`).

## Menu groups (registered now, filled later)

Dashboard · Logs · Security · Settings · In-game rules · Administration (Users, Audit log).
Groups with no visible items are hidden.
