# Implementation Plan: Dashboard and Filter Control

**Branch**: `003-dashboard-and-control` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-dashboard-and-control/spec.md`

## Summary

The panel's home page and first real control surface:
- **Dashboard.** It replaces Filament's default dashboard. Three widgets poll every 5 s: module
  status cards, online players and recent commands. Status comes from `filter_status`, and
  "online" means a heartbeat less than 15 s old by the **database** clock.
- **Commands.** Start/Stop/Restart (per module, All agents, All), the gateway mode switch and
  Cancel all go through one `CommandQueue` service. It authorises the user, takes a per-target
  lock against duplicates, and inserts the command and its audit entry in one transaction.
  Cancel is a conditional `pending → cancelled` update.
- **Modules page.** A Filament resource on top of 002's `ConfigStore`, with add/remove-last-agent
  and a guard-port clash rule.
- **Connection health.** A read-only page for VFILTER and the optional account/shard/log
  connections, with a 5 s budget each.
- **Shared contract.** Moves to **0.3.0** in both repos: the `cancelled` status, the claim/cancel
  rule, `download` as a target, and the mode payload.

## Technical Context

**Language/Version**: PHP 8.5

**Primary Dependencies**: Laravel 13, Filament 5 (widgets, actions, resources), Livewire polling

**Storage**: SQL Server `VFILTER` (`filter` connection). New optional read-only connections `account`, `shard`, `log`. No migrations.

**Testing**: Pest + Livewire/Filament testing helpers on SQLite in-memory. `@group sqlsrv` covers `DatabaseClock` and the health timeouts.

**Target Platform**: same as 001 (Windows Server + IIS/Nginx or Linux reaching SQL Server)

**Project Type**: Laravel web app (Filament admin panel)

**Performance Goals**: dashboard first paint < 3 s (SC-001). Each 5 s poll costs ≤ 4 small indexed queries (clock, modules, status, 20 commands).

**Constraints**: no sockets to the filter (constitution II). Times stored in UTC and shown in `APP_DISPLAY_TIMEZONE`. 5 s per DB in the health check.

**Scale/Scope**: ≤ 7 modules, a handful of panel users, `filter_commands` grows slowly (indexed `(status, created_at)`, list limited to 20)

## Constitution Check

| Principle | Status | Notes |
| --- | --- | --- |
| I. Shared contract is law | Pass | Contract 0.3.0 edit applied to `docs/shared-contract.md` in **both** repos in this change set ([contracts/shared-contract-0.3.0.md](contracts/shared-contract-0.3.0.md)). No schema change needed (`status` has no check constraint). |
| II. Database-only control | Pass | Control only through `filter_commands` and module config through `ConfigStore`. Game DB connections are read-only and run only `SELECT 1`. |
| III. Security first | Pass | Policies on every page/action, plus a `Gate::authorize` inside `CommandQueue`. Every command, cancel and module change is audited in the same transaction. Health results carry no credentials. Real module addresses are masked for viewers. |
| IV. Test-first | Pass | Pest tests per user story written before each implementation: queueing, duplicates, cancel race, permissions, audit, module rules, health. |
| V. Catalog-driven settings | N/A | No settings pages in this feature. |
| VI. Bilingual, responsive | Pass | All strings in `lang/{en,ar}/panel.php` and the audit labels. Cards use a responsive grid and are checked in RTL and at phone width. |
| VII. Simplicity | Pass | Polling instead of websockets, a cache lock instead of driver-specific SQL locks, read models as plain value objects. |

Post-design re-check: pass. No complexity entries.

## Project Structure

### Documentation (this feature)

```text
specs/003-dashboard-and-control/
├── plan.md, research.md, data-model.md, quickstart.md
├── contracts/
│   ├── shared-contract-0.3.0.md   # exact edit for docs/shared-contract.md (both repos)
│   └── command-queue.md           # CommandQueue / overview / health API + page access matrix
└── checklists/requirements.md
```

### Source Code (repository root)

```text
app/
├── Enums/Filter/CommandStatus.php        # + Cancelled
├── Enums/Filter/CommandGroup.php         # power | gateway_mode (new)
├── Filter/                               # new: dashboard/control domain
│   ├── CommandQueue.php                  # queue / queueForAllAgents / setGatewayMode / cancel
│   ├── AllAgentsResult.php
│   ├── DatabaseClock.php
│   ├── FilterOverview.php                # builds ModuleCard / CommandRow, onlinePlayers
│   ├── ModuleCard.php, CommandRow.php
│   ├── ConnectionHealthCheck.php, HealthResult.php
│   └── Exceptions/                       # CommandInProgress, CommandAlreadyStarted,
│                                         # GatewayModeUnchanged, FilterDatabaseUnavailable
├── Config/ModuleRules.php                # + guard address/port clash rule
├── Config/ModuleSlots.php                # addAgent() / removeLastAgent() via ConfigStore (new)
├── Policies/FilterCommandPolicy.php, FilterModulePolicy.php
└── Filament/
    ├── Pages/Dashboard.php               # replaces Filament\Pages\Dashboard in AdminPanelProvider
    ├── Pages/ConnectionHealth.php
    ├── Widgets/ModuleStatusWidget.php    # + resources/views/filament/widgets/module-status.blade.php
    ├── Widgets/OnlinePlayersWidget.php
    ├── Widgets/RecentCommandsWidget.php
    └── Resources/FilterModules/          # FilterModuleResource, Pages/{List,Edit}, Schemas, Tables
config/database.php                       # + optional account/shard/log connections
lang/{en,ar}/panel.php, audit.php         # new keys (same keys in both)
docs/shared-contract.md                   # 0.3.0 (and ../VFilter/docs/shared-contract.md)
tests/Feature/Dashboard/                  # StatusTest, CommandQueueTest, CancelTest,
                                          # AllAgentsTest, GatewayModeTest, DashboardPageTest
tests/Feature/Modules/                    # ModulesPageTest, ModuleSlotsTest, ModuleRulesClashTest
tests/Feature/Health/ConnectionHealthTest.php
tests/Feature/Security/RouteAuthorizationTest.php   # + new routes
```

**Structure Decision**: the dashboard/control domain gets its own `app/Filter` namespace, next to 002's
`app/Config` (the write path) and `app/Models/Filter` (the tables). The Filament classes stay thin and call
only `CommandQueue`, `FilterOverview`, `ModuleSlots`/`ConfigStore` and `ConnectionHealthCheck`.

## Complexity Tracking

None.
