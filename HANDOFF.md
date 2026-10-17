# HANDOFF: VFilterWebsite

**Date:** 2026-10-17 · **Status:** Spec Kit and project docs are set up. No Laravel code yet.

## What exists
| Path | What |
| --- | --- |
| `CLAUDE.md` | Project guide for Claude Code (stack, commands, conventions) |
| `.specify/memory/constitution.md` | Project rules, v1.0.0 (ratified) |
| `.specify/` + `.claude/skills/speckit-*` | GitHub Spec Kit (Claude integration, `sh` scripts, git extension) |
| `docs/shared-contract.md` | VFILTER DB contract shared with the filter, v0.1.0 draft |
| `docs/maxiguard-feature-map.md` | All 50 MaxiGuard panel pages we're matching |

## Decisions made
- The website is built **first** and the filter second, as you asked. The website controls the filter only
  through the shared `VFILTER` SQL Server DB (config tables, version counter, command queue).
- Stack: latest stable PHP + Laravel, Filament admin, Pest, SQL Server (`pdo_sqlsrv`), EN + AR (RTL).
- We are self-hosted, so there are no license/top-up/purchase pages.

## Open questions (answer in `/speckit-clarify`)
1. SQL Server version and host. Will the panel run on the same Windows server as SRO, or elsewhere?
2. Production web server: IIS on the SRO box, or a separate Linux VPS?
3. Panel users: just you, or also staff (admin/viewer roles)?
4. Filament (fast, admin-style) vs a custom Livewire/Blade design. The default is Filament.

## Step 0: one-time setup on your Mac
```bash
cd /Users/mohammedelaraby/Work/Silkroad/Filter/VFilterWebsite
php -v                                   # expect latest stable (8.5.x); update via Herd if older
# SQL Server driver for PHP (Herd: Settings → PHP → Extensions, or):
pecl install sqlsrv pdo_sqlsrv           # needs: brew install unixodbc msodbcsql18
# Create the Laravel app IN this folder (keeps .specify/.claude/docs):
laravel new tmp-app --no-interaction && rsync -a tmp-app/ ./ && rm -rf tmp-app
composer require filament/filament && php artisan filament:install --panels
composer require --dev pestphp/pest larastan/larastan && ./vendor/bin/pest --init
git add -A && git commit -m "Scaffold Laravel + Filament"
git push -u origin main
```
I couldn't push to GitHub from here: this session has no access to `ENGMElaraby/VFilterWebsite`,
and push to `VFilter` is refused. Push from your Mac. To let Claude push later, install the Claude
GitHub App on both repos.

## Step 1: build features with Spec Kit (open Claude Code in this folder)
Run these in order. Each one runs specify → clarify → plan → tasks → analyze → implement.

**001-auth-and-shell**
```
/speckit-specify Admin panel shell for VFilter: login with email+password, 2FA for owner, roles owner/admin/viewer, user management (owner only), audit log page showing who changed what, English + Arabic (RTL) language switch, dark/light theme. Uses the VFILTER SQL Server database as the default connection. See docs/shared-contract.md (website-only tables).
```
**002-contract-schema**
```
/speckit-specify Create all VFILTER contract tables exactly as in docs/shared-contract.md as Laravel migrations on the filter connection, plus Eloquent models, a SettingsRepository that bumps filter_config_version and writes audit_log in one transaction, and seeders for default modules and settings.
```
**003-dashboard-and-control**
```
/speckit-specify Dashboard like MaxiGuard Filter Control: per-module status cards from filter_status (online if heartbeat < 15s), online player count, Start/Restart/Stop for Gateway/Agent/All and gateway mode switch GM/Inspection/Public, all via filter_commands with live command status. Includes Modules page (filter_modules: 1 gateway, 1 download, 1-5 agents, module IP/port, guard IP/port) and a read-only DB connection health check.
```
**004-logs**
```
/speckit-specify Logs viewer for filter_logs: filters by level, category, module, IP, username, charname, opcode and time range; live tail; export CSV; retention setting logs.retention_days with a scheduled prune job; live sessions page from filter_sessions with kick/ban actions via filter_commands.
```
**005-security-lists**
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

## Definition of done for phase 1 (features 001–004)
You log in, see each module's status, click "Restart Agent" and see the command move from pending
to done (done once the filter exists; until then it stays pending), and browse logs.
