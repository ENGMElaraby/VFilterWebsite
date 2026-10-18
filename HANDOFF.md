# HANDOFF: VFilterWebsite

**Date:** 2026-10-17 · **Status:** Laravel 13 + Filament 5 app scaffolded. Feature **001-auth-and-shell is built**
(PR #1 open). Feature **002-contract-schema is built** (branch `002-contract-schema`, from 001). Feature
**003-dashboard-and-control is built** (branch `003-dashboard-and-control`, from 002). Feature **004-logs is built**
(branch `004-logs`, from 003). **Phase 1 (001–004) is complete.** Feature **005-security-lists is built** (branch `005-security-lists`, from 004).
Next on the website: `006-settings-catalog`.

## Architecture change (2026-10-18)
The production game server is **AegisCore** (`ENGMElaraby/AegisCore-SRO`), and it implements the filter side of the
contract natively. VFilter (C#) is now an optional edge shield. **Nothing changes for the website**: same DB, tables,
commands and status rules. Contract 0.3.4 documents this.

What AegisCore does with the website's commands:
- **Start/Stop/Restart:** stop and start the real module containers.
- **Ban:** permanent bans only for now. A timed ban fails with "timed bans not supported yet; choose Permanent".
- **Notice:** works (payload `{"message": "..."}`).

Website follow-ups:
- A **notice page**: send a system notice to all players. Owner/admin only, audited.
- Consider hiding the timed ban durations while AegisCore is the filter side.

## What exists
| Path | What |
| --- | --- |
| `CLAUDE.md` | Project guide for Claude Code (stack, commands, conventions) |
| `.specify/memory/constitution.md` | Project rules, v1.0.0 (ratified) |
| `.specify/` + `.claude/skills/speckit-*` | GitHub Spec Kit (Claude integration, `sh` scripts, git extension) |
| `docs/shared-contract.md` | VFILTER DB contract shared with the filter, v0.3.4 draft (tables in 002; `cancelled` status in 003; kick/ban payloads in 004; list formats + `cafe_limit` in 005; filter side may be AegisCore, `notice` payload in 0.3.4) |
| `docs/references.md` | Outside repos to read (AegisCore-SRO, silkroad-docs, …), ranked, with licenses |
| `docs/features/silk-stall.md` | Planned Silk Stall feature (after the client DLL) |
| `specs/001-auth-and-shell/` | Spec, plan, tasks for the panel shell (done) |
| `specs/003-dashboard-and-control/` | Dashboard, commands, Modules page, connection health (done) |
| `specs/004-logs/` | Logs search/live/export, retention prune, sessions kick/ban (done) |
| `specs/005-security-lists/` | Security lists, Game Assistants grid, net cafe limits (done) |
| `app/`, `database/`, `lang/`, `tests/` | The Laravel app: sign-in + 2FA, users, audit log, EN/AR |
| `docs/maxiguard-feature-map.md` | All 50 MaxiGuard panel pages we're matching |

## Decisions made
- The website is built **first** and the filter second, as you asked. The website controls the filter only
  through the shared `VFILTER` SQL Server DB (config tables, version counter, command queue).
- Stack: latest stable PHP + Laravel, Filament admin, Pest, SQL Server (`pdo_sqlsrv`), EN + AR (RTL).
- We are self-hosted, so there are no license/top-up/purchase pages.
- Panel users: owner + staff with roles `admin` and `viewer` (001).
- Two-factor (authenticator app) is required for **every** panel user; it can't be turned off (001).
- Password recovery: email link when a mail server is set, and owner reset always; the console
  resets the owner (001).
- The panel is public over HTTPS, with an optional IP allow-list (`PANEL_ALLOWED_IPS`) (001).
- Admin UI: Filament (chosen).
- Dashboard (003): status polls every 5 s; "online" = heartbeat < 15 s by the DB clock. Admins/owner queue
  Start/Stop/Restart (per module, All agents = one command per agent, All), gateway mode, and Cancel while
  pending. Viewers are read-only and see real module addresses masked.
- Logs (004): searches are always time-bounded (default last 24 h) and exact-match; export max 100k rows.
  Ban = game account ban with a chosen duration (1 h/1 d/7 d/30 d/permanent) + reason, applied by the filter.
- Security lists (005): every list is edited as rows (value + comment) except Blocked words, which is pasted
  text for bulk. IP allow/block lists accept CIDR ranges.
- **Production needs the Laravel scheduler** (`php artisan schedule:run` every minute, e.g. Windows Task
  Scheduler) so `vfilter:prune-logs` runs daily at 03:30.

## Open questions (answer in `/speckit-clarify`)
1. SQL Server version and host. Will the panel run on the same Windows server as SRO, or elsewhere?
   (Needed to run migrations and the `@group sqlsrv` tests; so far everything ran on SQLite.)
2. Production web server: IIS on the SRO box, or a separate Linux VPS?

## Step 0: local setup (done once)
The app is already scaffolded. On a fresh clone:
```bash
cd /Users/mohammedelaraby/Work/Silkroad/Filter/VFilterWebsite
php -v      # must be 8.5. Your global `php` is 7.4: set PHP 8.5 as default in the Herd app.
composer install && cp .env.example .env && php artisan key:generate
# SQL Server: fill DB_* in .env. Local preview without SQL Server: in .env set
#   DB_FILTER_DRIVER=sqlite and DB_DATABASE=<absolute path>/database/database.sqlite
php artisan migrate
php artisan vfilter:install-defaults        # default modules, cooldowns, core settings
php artisan vfilter:create-owner          # the one owner; 2FA is set up at first sign-in
php artisan serve                         # http://127.0.0.1:8000/admin
```
Herd's PHP 8.5 already has `sqlsrv` / `pdo_sqlsrv`.

## Step 1: build features with Spec Kit (open Claude Code in this folder)
Run these in order. Each one runs specify → clarify → plan → tasks → analyze → implement.

**001-auth-and-shell** — ✅ done (branch `001-auth-and-shell`)
```
/speckit-specify Admin panel shell for VFilter: login with email+password, 2FA for owner, roles owner/admin/viewer, user management (owner only), audit log page showing who changed what, English + Arabic (RTL) language switch, dark/light theme. Uses the VFILTER SQL Server database as the default connection. See docs/shared-contract.md (website-only tables).
```
**002-contract-schema** — ✅ done (branch `002-contract-schema`)
```
/speckit-specify Create all VFILTER contract tables exactly as in docs/shared-contract.md as Laravel migrations on the filter connection, plus Eloquent models, a SettingsRepository that bumps filter_config_version and writes audit_log in one transaction, and seeders for default modules and settings.
```
**003-dashboard-and-control** — ✅ done (branch `003-dashboard-and-control`)
```
/speckit-specify Dashboard like MaxiGuard Filter Control: per-module status cards from filter_status (online if heartbeat < 15s), online player count, Start/Restart/Stop for Gateway/Agent/All and gateway mode switch GM/Inspection/Public, all via filter_commands with live command status. Includes Modules page (filter_modules: 1 gateway, 1 download, 1-5 agents, module IP/port, guard IP/port) and a read-only DB connection health check.
```
**004-logs** — ✅ done (branch `004-logs`)
```
/speckit-specify Logs viewer for filter_logs: filters by level, category, module, IP, username, charname, opcode and time range; live tail; export CSV; retention setting logs.retention_days with a scheduled prune job; live sessions page from filter_sessions with kick/ban actions via filter_commands.
```
**005-security-lists** — ✅ done (branch `005-security-lists`)
```
/speckit-specify Security pages backed by filter_lists and filter_ga_permissions: Allowed GM IPs, proxy-bypass IPs/usernames, Game Assistants with a per-command permission grid, Blocked IPs, Blocked HWIDs, Net Cafe IPs with special per-IP limits, blocked words. One value per line with // comments, validated.
```
**006-settings-catalog**
```
/speckit-specify Generate docs/settings-catalog.md and a PHP settings catalog covering every switch in the "Other Settings" section of docs/maxiguard-feature-map.md (38 tabs), with key, type, default, validation, restart requirement and EN/AR labels; render it as tabbed Filament settings pages saved via SettingsRepository. Drop EU-only options (server is Chinese only, cap 100).
```
**007-area-rules**
```
/speckit-specify One generic area-rule editor over filter_area_rules and filter_cooldowns covering every In-game restrictions page in docs/maxiguard-feature-map.md (cooldowns, teleports, trace, party, pets, skills, stall, alchemy, berserk, PK, items, resurrection, reverse, PVP-mode, region PC/IP limits, conditionals), with region/world pickers loaded from SRO_VT_SHARD.
```
Later: visuals/events/triggers pages, then the item mall and custom-window admin (daily reward,
lucky spin, battle pass).
After the client DLL: **Silk Stall** admin (settings + sales log), planned in
`docs/features/silk-stall.md` with a draft `/speckit-specify` prompt.

## Definition of done for phase 1 (features 001–004)
You log in, see each module's status, click "Restart Agent" and see the command move from pending
to done (done once the filter exists; until then it stays pending), and browse logs.
