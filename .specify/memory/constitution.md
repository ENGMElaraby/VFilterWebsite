<!--
Sync Impact Report
- Version: 0.0.0 → 1.0.0 (initial ratification)
- Principles added: I–VII
- Templates checked: plan-template.md (Constitution Check gate uses these principles) ✅
-->
# VFilterWebsite Constitution

## Core Principles

### I. The Shared Contract Is Law
`docs/shared-contract.md` defines every table the filter reads or writes. The website owns the
schema and its migrations, but any change to a contract table MUST update the contract in **both**
VFilterWebsite and VFilter in the same change set, with a version bump. A change that breaks the
filter is a defect even if the website's tests pass.

### II. Database-Only Control
The website controls the filter only through the database: config tables, `filter_config_version`,
and the `filter_commands` queue. It MUST NOT open sockets to the filter or game servers, run shell
commands on the server, or write to the SRO_VT_* game databases without a dedicated, approved spec.

### III. Security First (NON-NEGOTIABLE)
- Every route and admin page is authenticated and authorized by role (`owner` > `admin` > `viewer`).
- Every config change and command is written to `audit_log` (who, when, old → new).
- Secrets (SQL credentials, SMTP passwords, keys) live in `.env` only, never in the DB, logs or UI.
- Login has rate limiting and 2FA for `owner`. CSRF, validation and escaping follow Laravel defaults.
- Input that reaches the filter (IPs, IDs, area values, regex-like lists) is validated server-side.

### IV. Test-First for Behaviour
Each user story in a spec gets Pest feature tests written before or alongside its implementation.
Settings validation, command queueing, permissions and audit logging always have tests. CI (or
`php artisan test` locally) must be green before merging.

### V. Catalog-Driven Settings
The ~300 filter switches come from a single settings catalog (key, type, group, default,
validation, restart requirement, label, help text in EN/AR). Pages are generated from it. Adding a
setting means adding one catalog entry, not a new hand-written form.

### VI. Clear, Bilingual, Responsive UI
English and Arabic (RTL), with every string translatable. Each setting shows its help text and
whether it needs a filter / GameServer / client restart. The panel is usable on a phone.

### VII. Simplicity and Current Versions
Use the latest stable PHP and Laravel, plus first-party or widely adopted packages (Filament,
Pest, Pint, Larastan). No speculative abstractions; YAGNI. Complexity must be justified in the
plan's Complexity Tracking table.

## Technical Constraints
- PHP latest stable, Laravel latest stable, Filament for the admin UI, SQL Server (`pdo_sqlsrv`).
- Production target: Windows Server + IIS or Nginx, or a Linux host that can reach SQL Server.
- Times are stored in UTC.
- Log tables can grow to millions of rows. Queries are paginated and indexed, with no unbounded scans.

## Development Workflow
- Spec Kit flow for every feature: specify → clarify → plan → tasks → analyze → implement.
- One feature per branch (`NNN-short-name`), merged by PR into `main`.
- Gates before merge: Pint clean, Larastan passing, Pest green, and contract docs in sync.

## Governance
This constitution overrides other practices. Amendments need a written rationale, a version bump
(semver: MAJOR = principle removed or redefined, MINOR = principle added, PATCH = wording), and an
update to dependent templates. `CLAUDE.md` holds day-to-day guidance.

**Version**: 1.0.0 | **Ratified**: 2026-10-17 | **Last Amended**: 2026-10-17
