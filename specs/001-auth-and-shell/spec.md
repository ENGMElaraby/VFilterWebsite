# Feature Specification: Admin Panel Shell (Auth, Roles, Audit, Language, Theme)

**Feature Branch**: `001-auth-and-shell`

**Created**: 2026-10-17

**Status**: Draft

**Input**: User description: "Admin panel shell for VFilter: login with email+password, 2FA for owner, roles owner/admin/viewer, user management (owner only), audit log page showing who changed what, English + Arabic (RTL) language switch, dark/light theme. Uses the VFILTER SQL Server database as the default connection. See docs/shared-contract.md (website-only tables)."

## Overview

The VFilter panel is the only way the server team configures and controls the VFilter proxy filter.
This feature delivers the empty, secured shell that every later page (dashboard, logs, settings,
security lists, area rules) plugs into: who can sign in, what each person may do, a record of who
changed what, and the basic look and language of the panel. It holds no filter pages yet.

## Clarifications

### Session 2026-10-17

- Q: Who must use two-factor to sign in? → A: Everyone (owner, admin and viewer).
- Q: How does a user recover a forgotten password? → A: Both: self-service email reset link when a mail server is configured, and owner reset (console for the owner) always available.
- Q: Where is the panel reachable from? → A: Public internet over HTTPS, protected by two-factor and rate limits, with an optional IP allow-list (off by default).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Owner signs in securely (Priority: P1)

The server owner opens the panel URL, enters an email and password, then confirms a one-time code
from an authenticator app, and lands on an (empty) dashboard. Anyone without valid credentials
sees only the sign-in page.

**Why this priority**: Nothing else in the panel can exist safely without authentication. The
panel will control a live game server, so the owner account is the highest-value target.

**Independent Test**: Create the first owner from the server console, sign in with email,
password and authenticator code, reach the dashboard, sign out, and confirm every panel URL
redirects to sign-in when signed out.

**Acceptance Scenarios**:

1. **Given** no panel users exist, **When** the operator runs the documented console setup step with an email, name and password, **Then** exactly one owner account is created and the step refuses to create a second owner the same way.
2. **Given** any user (owner, admin or viewer) without two-factor set up, **When** they sign in for the first time, **Then** they must enrol an authenticator app (scan code, confirm one code) and receive one-time recovery codes before reaching any other page.
3. **Given** a user with two-factor enrolled, **When** they enter the correct email and password, **Then** they are asked for a current authenticator code or a recovery code, and only after a valid code reach the dashboard.
4. **Given** a signed-out visitor, **When** they open any panel page directly, **Then** they are sent to the sign-in page and return to the requested page after signing in.
5. **Given** a user who enters a wrong password 5 times in a row, **When** they try again, **Then** further attempts from that email and IP are refused for a cooling-off period and the attempts are recorded.
6. **Given** a signed-in user, **When** they sign out, **Then** their session ends and the back button does not show panel data.

---

### User Story 2 - Owner manages panel users and roles (Priority: P1)

The owner adds staff accounts, gives each one a role (admin or viewer), changes roles, disables
or re-enables accounts, and resets a staff member's password. Only the owner can see or use this.

**Why this priority**: The owner needs to share access with staff without sharing the owner
login, and the role split is what every later page uses to decide who may change settings.

**Independent Test**: As owner, create an admin and a viewer, sign in as each, and confirm the
admin and viewer cannot open user management while the owner can; disable the viewer and confirm
they can no longer sign in.

**Acceptance Scenarios**:

1. **Given** a signed-in owner, **When** they create a user with name, email, role (admin or viewer) and an initial password, **Then** the user can sign in and is asked to change the password on first sign-in.
2. **Given** a signed-in admin or viewer, **When** they try to open user management by menu or URL, **Then** the menu item is hidden and direct access is refused.
3. **Given** an owner editing a user, **When** they change the role or disable the account, **Then** the change applies on the user's next request (a disabled user's open sessions end).
4. **Given** an owner, **When** they reset another user's password or two-factor, **Then** the user's sessions end and they must set a new password or re-enrol two-factor at next sign-in.
5. **Given** the only owner account, **When** anyone tries to delete it, disable it, or change its role, **Then** the panel refuses, so there is always exactly one working owner.
6. **Given** an owner creating a user, **When** the email is already used or the password is weaker than the policy, **Then** the form shows a clear error and nothing is saved.

---

### User Story 3 - Owner and admins review the audit log (Priority: P2)

The owner or an admin opens the audit log and sees, newest first, who did what and when: sign-ins,
failed sign-ins, user changes, and (in later features) every settings change with old and new
values. They can filter by person, action type and date range.

**Why this priority**: The constitution requires every change to be traceable. This feature creates
the log and the page; later features only add entries.

**Independent Test**: Sign in as owner, create a user, change their role, sign out; sign in as an
admin and confirm the audit log lists those actions with time, actor, action and old → new values.

**Acceptance Scenarios**:

1. **Given** any recorded action, **When** an owner or admin views the audit log, **Then** each entry shows time (in the viewer's display time zone), actor, action, the affected item, and old → new values where applicable.
2. **Given** a viewer-role user, **When** they try to open the audit log, **Then** access is refused.
3. **Given** thousands of entries, **When** the page loads, **Then** results are paginated and filterable by actor, action type and date range.
4. **Given** any user including the owner, **When** they look for a way to edit or delete audit entries, **Then** none exists in the panel.
5. **Given** an entry about a password or two-factor change, **When** it is displayed, **Then** no password, code or secret value appears in it.

---

### User Story 4 - Users choose language and theme (Priority: P3)

Any signed-in user switches the panel between English and Arabic, and between light and dark
themes. Arabic shows the whole panel right-to-left. The choice is remembered for that user.

**Why this priority**: Part of the team works in Arabic, and the constitution requires a bilingual,
RTL-correct panel. It is lower priority because the panel is usable in one language first.

**Independent Test**: Sign in, switch to Arabic and dark, sign out, sign in on another browser,
and confirm the panel is still Arabic, right-to-left, and dark.

**Acceptance Scenarios**:

1. **Given** a signed-in user, **When** they choose Arabic, **Then** every panel label, menu, message and validation error appears in Arabic and the layout is mirrored right-to-left.
2. **Given** a user who chose a language and theme, **When** they sign in on another device, **Then** the same language and theme apply.
3. **Given** the sign-in page before anyone signs in, **When** a visitor opens it, **Then** it uses the browser's preferred language if it is Arabic or English, otherwise English, and offers a switch.
4. **Given** a theme set to "system", **When** the device switches between light and dark, **Then** the panel follows it.

---

### Edge Cases

- The owner loses their authenticator device: they sign in with a one-time recovery code; if all
  codes are used or lost, the server operator resets the owner's two-factor from the server console
  (documented step), and this is recorded in the audit log.
- A password reset email is requested for an unknown email, or the mail server is down: the user sees the same neutral "if the account exists, a link was sent" message; the failure is logged for the operator.
- The database is unreachable: the sign-in page shows a plain "service unavailable" message with no
  technical details, and nobody is signed in.
- An owner changes their own email or password: they must confirm their current password first.
- Two owners editing the same user at once: impossible, because there is exactly one owner.
- A user is disabled while signed in: their next click ends the session.
- A disabled user tries to sign in: they get the same generic "invalid credentials" message as a
  wrong password, so account status is not revealed.
- Session idle for a long time: the user is signed out after the idle limit and must sign in again.
- A staff member loses their authenticator device and recovery codes: the owner resets their two-factor from user management; they re-enrol at next sign-in. Recorded in the audit log.
- A translation is missing in Arabic: the English text is shown rather than a raw key.

## Requirements *(mandatory)*

### Functional Requirements

**Sign-in and sessions**

- **FR-001**: The panel MUST require sign-in with email and password for every page except the sign-in, two-factor challenge, forced password-change, forgot-password and password-reset pages.
- **FR-002**: Every account (owner, admin and viewer) MUST use two-factor authentication with a time-based authenticator app code, enrolled at first sign-in before any other page is reachable; recovery codes MUST be issued once at enrolment and each code MUST work only once.
- **FR-003**: Users MUST NOT be able to turn two-factor off. The owner MUST be able to reset a staff member's two-factor (forcing re-enrolment at next sign-in); the console resets the owner's.
- **FR-004**: The panel MUST limit failed sign-in and two-factor attempts (at most 5 failures per email+IP per minute, then a cooling-off period) and record each failure in the audit log.
- **FR-005**: Sign-in error messages MUST NOT reveal whether an email exists or an account is disabled.
- **FR-006**: Sessions MUST end after 2 hours of inactivity, on sign-out, on password reset, and when the account is disabled.
- **FR-007**: Passwords MUST be at least 12 characters and MUST NOT appear in known-breach lists where that check is available; they MUST be stored only as one-way hashes.
- **FR-007a**: When a mail server is configured, any user MUST be able to request a password reset link by email from the sign-in page. The link MUST be single-use, expire after 60 minutes, and the request page MUST show the same message whether or not the email exists. A reset ends the user's sessions and still requires two-factor at next sign-in. When no mail server is configured, the forgot-password link MUST be hidden.
- **FR-007b**: The panel MUST support an optional IP allow-list, configured in the environment file and off by default. When on, requests from other IPs MUST be refused before the sign-in page is shown.
- **FR-008**: The first owner MUST be created only through a server console step, never through a public web page. The same console path MUST be able to reset the owner's password and two-factor.

**Roles and user management**

- **FR-009**: Every user MUST have exactly one role: `owner`, `admin`, or `viewer`. There MUST be exactly one owner.
- **FR-010**: Permissions MUST follow owner > admin > viewer. In this feature: owner = everything; admin = audit log and (in later features) change settings and send commands; viewer = read-only pages, no audit log.
- **FR-011**: Only the owner MUST be able to list, create, edit, disable, re-enable and reset the password of other users, and change their role between admin and viewer.
- **FR-012**: The owner account MUST NOT be deletable, disableable, or demotable through the panel.
- **FR-013**: Users created or reset by the owner MUST change their password at their next sign-in.
- **FR-014**: Every page and action MUST check the role on the server side; hiding a menu item alone is not enough.

**Audit log**

- **FR-015**: The panel MUST record in the audit log: successful and failed sign-ins, sign-outs, two-factor enrolment/reset and recovery-code use, user create/edit/disable/enable/password reset/role change, own profile changes, and language-independent action names so later features can add their own entries.
- **FR-016**: Each audit entry MUST store time (UTC), actor (or "system"/"console"), actor IP, action, affected item type and id, and old and new values; secrets (passwords, codes, two-factor secrets) MUST never be stored.
- **FR-017**: The audit log MUST be append-only from the panel: no edit or delete.
- **FR-018**: Owners and admins MUST be able to view the audit log newest first, paginated, filtered by actor, action type, and date range.
- **FR-019**: Later features MUST be able to write a settings change and its audit entry together, so a change is never saved without its entry (provided by this feature as a shared capability).

**Language and theme**

- **FR-020**: The panel MUST be fully available in English and Arabic, with Arabic shown right-to-left, including forms, tables, menus, notifications and validation messages.
- **FR-021**: Each user MUST be able to pick language (English/Arabic) and theme (light/dark/system); the choice MUST be saved on their account and apply on any device.
- **FR-022**: All user-visible text MUST come from translation files; a missing Arabic string MUST fall back to English.
- **FR-023**: The panel MUST be usable on a phone-sized screen (navigation, sign-in, user management, audit log).

**Shell and data**

- **FR-024**: The shell MUST provide a navigation menu, a dashboard placeholder, a profile page (name, email, password, two-factor, language, theme), and a place for later features to add menu groups (Dashboard, Logs, Security, Settings, In-game rules, Users, Audit log).
- **FR-025**: All panel data (users, sessions, password resets, audit log, preferences) MUST be stored in the shared `VFILTER` database as the website-only tables listed in `docs/shared-contract.md`; all times stored in UTC.
- **FR-026**: The panel MUST NOT write to the SRO game databases or contact the filter in this feature.

### Key Entities

- **Panel user**: a person who can sign in. Name, email (unique), role, enabled flag, password-change-required flag, preferred language, preferred theme, two-factor status, last sign-in time. Not related to game accounts.
- **Two-factor credential**: belongs to one panel user; secret, confirmed time, set of single-use recovery codes.
- **Session**: one signed-in browser for one user; last activity time, IP, device description.
- **Audit entry**: one recorded action; time, actor (user, console or system), actor IP, action, target type and id, old values, new values. Never edited or deleted from the panel.
- **Password reset/change requirement**: state forcing a user to set a new password at next sign-in.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An owner goes from opening the panel URL to the dashboard (email, password, authenticator code) in under 30 seconds.
- **SC-002**: The owner creates a new staff account with a role in under 1 minute.
- **SC-003**: 100% of panel pages and actions refuse signed-out visitors and users without the required role (verified by automated tests over every route).
- **SC-004**: 100% of sign-ins, failed sign-ins and user-management changes appear in the audit log within 1 second of the action, with no secret values in any entry.
- **SC-005**: Switching language or theme takes effect in one click with no sign-out, and 100% of shell screens show no untranslated text in Arabic.
- **SC-006**: After 5 wrong passwords, the 6th attempt within the cooling-off period is refused 100% of the time.
- **SC-007**: The audit log page shows the first page of results in under 2 seconds with 1,000,000 entries.

## Assumptions

- Users are a small staff team (fewer than 20 panel accounts); self-registration is not needed.
- Password recovery has two paths (clarified 2026-10-17): an email reset link when a mail server is configured, and owner reset (console for the owner) that always works. Mail server settings live in the environment file, never in the panel database.
- Authenticator-app codes (time-based one-time codes) are the two-factor method; SMS and email codes are out of scope.
- Two-factor is mandatory for every role (clarified 2026-10-17).
- Idle timeout of 2 hours and lockout of 5 failures/minute are reasonable defaults and can become settings later.
- Display times use the server's configured time zone for v1; per-user time zones are out of scope.
- Audit log retention is unlimited in v1; pruning can be added with the logs feature.
- The VFILTER SQL Server database already exists and is reachable from the panel; creating it is an operator setup step, not part of this feature.
- The panel is reachable from the public internet and MUST be served only over HTTPS in production (clarified 2026-10-17); obtaining the certificate is a deployment step, not this feature.
- The role permissions for admin vs viewer on future pages (settings, commands) are defined by each later feature; this feature provides the roles and the checks.
