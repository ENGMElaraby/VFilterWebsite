---
description: "Tasks for 003 Dashboard and Filter Control"
---

# Tasks: Dashboard and Filter Control

**Input**: `specs/003-dashboard-and-control/` · **Tests**: required (constitution IV), written first.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

- [X] T001 Add `case Cancelled = 'cancelled';` to app/Enums/Filter/CommandStatus.php and update its docblock to "pending -> running -> done | failed, or pending -> cancelled (website)"
- [X] T002 [P] Create app/Enums/Filter/CommandGroup.php (`power`, `gateway_mode`) with `static for(CommandName): ?self` (start/stop/restart → power; set_gateway_mode → gateway_mode; others null) and `commands(): list<CommandName>`
- [X] T003 [P] Add optional read-only connections `account`, `shard`, `log` to config/database.php: sqlsrv, `database` from `DB_ACCOUNT_DATABASE` / `DB_SHARD_DATABASE` / `DB_LOG_DATABASE` (null when unset). Host/port/username/password come from `DB_GAME_HOST`/`DB_GAME_PORT`/`DB_GAME_USERNAME`/`DB_GAME_PASSWORD`, falling back to the `DB_*` VFILTER values. Same encrypt/trust settings. Document the keys (commented) in .env.example (research R5)
- [X] T004 Apply contracts/shared-contract-0.3.0.md to docs/shared-contract.md **and** ../VFilter/docs/shared-contract.md (identical files: version 0.3.0, rule 2, filter_commands target/status rows, the claim/cancel note, the changelog). Verify with `diff docs/shared-contract.md ../VFilter/docs/shared-contract.md`

---

## Phase 2: Foundational (blocks all stories)

- [X] T005 [P] tests/Feature/Dashboard/DatabaseClockTest.php: on SQLite `DatabaseClock::now()` equals `Carbon::setTestNow` time in UTC. A `@group sqlsrv` test checks that it is within 2 s of `SYSUTCDATETIME()`
- [X] T006 [P] Create app/Filter/DatabaseClock.php: `now(): CarbonImmutable`. On SQL Server it returns `SELECT SYSUTCDATETIME() AS now` on the `filter` connection, parsed as UTC. Any other driver returns `CarbonImmutable::now('UTC')`
- [X] T007 [P] Create the exceptions in app/Filter/Exceptions/: `CommandInProgressException` (target, group, user-facing translated message), `CommandAlreadyStartedException`, `GatewayModeUnchangedException`, `FilterDatabaseUnavailableException`
- [X] T008 [P] tests/Feature/Dashboard/PoliciesTest.php: `FilterCommandPolicy` lets any user `viewAny`; `create` and `cancel` are admin+ only. `FilterModulePolicy` lets any user `viewAny`/`view`; `update`/`create`/`delete` are admin+ only. Viewers are denied each of these
- [X] T009 [P] Create app/Policies/FilterCommandPolicy.php and app/Policies/FilterModulePolicy.php using `UserRole::atLeast(UserRole::Admin)`. Register them if they are not auto-discovered (models live in `App\Models\Filter`, so map them in AppServiceProvider with `Gate::policy`)
- [X] T010 Add the panel string keys used by this feature to lang/en/panel.php and lang/ar/panel.php (the same keys in both): dashboard, module card, states, command names, statuses incl. cancelled, confirmations, errors, modules page, health page. Add `command.queued` and `command.cancelled` to lang/{en,ar}/audit.php and append them to specs/001-auth-and-shell/contracts/audit-actions.md

**Checkpoint**: `php artisan test` is green and the shared contract is 0.3.0 in both repos.

---

## Phase 3: User Story 1 – See whether the filter is up (P1) 🎯 MVP

**Independent Test**: seed `filter_status` rows (fresh, stale, missing, error, unknown mode). The viewer dashboard shows the correct badges, counts and player total, and the widgets poll every 5 s.

- [X] T011 [P] [US1] tests/Feature/Dashboard/FilterOverviewTest.php covers these cases (frozen time):
  - heartbeat 14 s old → online; 15 s old → offline; null → offline "never".
  - When offline, connections and onlineCount are null.
  - Order is Gateway, Download, Agent1…N.
  - A status row for an unconfigured module is ignored; a missing status row still gives a card.
  - Module names match case-insensitively.
  - Error state carries lastError; an unknown gateway_mode becomes "Unknown (x)" without throwing.
  - onlinePlayers = 40 + 25 for two online agents; an offline agent is excluded; null when no agent is online.
  - uptime = dbNow − started_at.
- [X] T012 [P] [US1] tests/Feature/Dashboard/DashboardPageTest.php covers:
  - `/admin` renders the new Dashboard for a viewer, with module names, Online/Offline badges and the player count.
  - Each widget has a polling interval of `5s`.
  - With the `filter` connection broken (mock `FilterOverview` to throw `FilterDatabaseUnavailableException`), the page shows the "cannot reach VFILTER" banner and no actions.
  - A disabled module shows "Disabled".
- [X] T013 [P] [US1] Create app/Filter/ModuleCard.php (readonly value object with the fields in data-model.md, incl. `target`, `online`, `pendingMode`)
- [X] T014 [US1] Create app/Filter/FilterOverview.php `modules()` and `onlinePlayers()` per data-model.md and contracts/command-queue.md. Read the clock once per call. Wrap `QueryException`/`PDOException` from the `filter` connection in `FilterDatabaseUnavailableException`. Unknown gateway_mode: read the raw attribute and don't let the enum cast throw. In FilterStatus, use `getRawOriginal('gateway_mode')` with `GatewayMode::tryFrom`
- [X] T015 [US1] Create app/Filament/Pages/Dashboard.php extending `Filament\Pages\Dashboard` (`getWidgets()` returns OnlinePlayersWidget, ModuleStatusWidget, RecentCommandsWidget). Replace `Filament\Pages\Dashboard::class` and `AccountWidget` in app/Providers/Filament/AdminPanelProvider.php
- [X] T016 [P] [US1] Create app/Filament/Widgets/OnlinePlayersWidget.php (StatsOverviewWidget, `$pollingInterval = '5s'`, shows "—" when null)
- [X] T017 [US1] Create app/Filament/Widgets/ModuleStatusWidget.php + resources/views/filament/widgets/module-status.blade.php:
  - `$pollingInterval = '5s'`, `columnSpan = 'full'`.
  - A responsive card grid that works in RTL: badge (Online/Offline/Disabled/Error), state, connections, uptime, version, last heartbeat, an escaped and shortened last error, and the gateway mode plus "changing to X…".
  - The banner when `FilterDatabaseUnavailableException` is thrown.
  - No actions yet.

**Checkpoint**: US1 tests green. The dashboard is useful read-only.

---

## Phase 4: User Story 2 – Start/Stop/Restart and live command status (P1)

**Independent Test**: as admin, Restart Agent1 → one pending row + `command.queued` audit, shown in Recent commands. DB status changes appear within 5 s. A duplicate is refused. A viewer can't. Cancel works only while pending.

- [X] T018 [P] [US2] tests/Feature/Dashboard/CommandQueueTest.php:
  - `queue(Restart,'agent1')` inserts status `pending`, `created_by` = user and `created_at` = now UTC, plus exactly one `command.queued` audit with new `{command,target,payload}`.
  - A viewer gets `AuthorizationException` and nothing is written.
  - A target that is unknown, disabled, or not `all`/a module throws `InvalidArgumentException`.
  - Command `kick` is rejected by `queue`.
  - A second power command for the same target while the first is pending or running throws `CommandInProgressException`. After it is done/failed/cancelled, a new one is allowed.
  - `all` doesn't block `agent1`.
  - When the audit write fails (mock `AuditLogger::log` throws), no command row remains.
  - When the lock is already held (`Cache::lock('filter-command:agent1')->get()` before the call), it throws `CommandInProgressException`.
- [X] T019 [P] [US2] tests/Feature/Dashboard/CancelCommandTest.php:
  - Cancelling a pending command sets `cancelled`, `processed_at` and result `Cancelled by <email>`, plus a `command.cancelled` audit with old `{status:pending}`.
  - Cancelling a running/done/failed/cancelled command throws `CommandAlreadyStartedException`, with no audit and the row unchanged.
  - A race where the row is set to running between load and cancel is refused (load the model, update the row in the DB, then cancel).
  - A viewer is refused.
- [X] T020 [P] [US2] tests/Feature/Dashboard/AllAgentsTest.php: Agent1–3 enabled → 3 commands, `agent1..agent3`. Agent2 has a pending restart → 2 queued and `skipped = ['Agent2']`. All agents busy → `CommandInProgressException`. Disabled agents are not targeted. Everything is in one transaction (a failure leaves no rows).
- [X] T021 [P] [US2] tests/Feature/Dashboard/RecentCommandsTest.php:
  - `FilterOverview::recentCommands()` returns the 20 newest by id desc, with the requester name (or `#id` when the user is gone).
  - A pending command with an offline target has `waitingOffline` = true. For `all`, this needs no module online.
  - A command pending for 121 s has `notPickedUp`; at 120 s it doesn't.
  - Result is cut to 200 chars.
- [X] T022 [P] [US2] tests/Feature/Dashboard/DashboardActionsTest.php (Livewire):
  - Admin: Restart on Agent1 needs confirmation and queues; cancelling the modal queues nothing.
  - Start needs no confirmation.
  - Stop/Restart modal text names the module and warns about disconnects.
  - "All agents → Restart" and "All → Stop" queue as specified.
  - A duplicate shows the in-progress notification.
  - Viewer: no action buttons are rendered, and calling the action through Livewire is refused.
  - A disabled module has no actions.
  - The Cancel row action shows only for pending rows and only for admins.
- [X] T023 [US2] Create app/Filter/AllAgentsResult.php and app/Filter/CommandQueue.php: `queue`, `queueForAllAgents` and `cancel` exactly per contracts/command-queue.md:
  - `Gate::authorize`.
  - Target validation against enabled `FilterModule` names (lower-case) or `all`.
  - `Cache::lock("filter-command:{$target}", 10)->block(3, …)`, then `AuditLogger::transaction`. Inside it: the duplicate check by `CommandGroup` (status in pending/running), the insert, and `AuditLogger::log('command.queued', …)`.
  - Cancel is a conditional `update` `where id = ? and status = 'pending'` with `status = cancelled`, `processed_at = DatabaseClock::now()`, `result = 'Cancelled by '.email`. When 0 rows change it throws `CommandAlreadyStartedException`, otherwise it logs `command.cancelled`.
- [X] T024 [P] [US2] Create app/Filter/CommandRow.php and add `recentCommands(int $limit = 20)` to app/Filter/FilterOverview.php (one query + one user lookup `whereIn`, no N+1)
- [X] T025 [US2] Add Filament Actions to app/Filament/Widgets/ModuleStatusWidget.php:
  - Per card: Start (no confirm), Stop and Restart (`requiresConfirmation`, with the module name and the disconnect warning).
  - Header: "All agents" (Start/Stop/Restart group, confirm) and "All" (Start/Stop/Restart, confirm).
  - Each action is `visible` only to admin+ and enabled modules. It calls `CommandQueue`, catches the domain exceptions and shows a translated danger notification. All agents names the skipped agents.
- [X] T026 [US2] Create app/Filament/Widgets/RecentCommandsWidget.php (TableWidget, `$pollingInterval = '5s'`, query = latest 20, `paginated(false)`):
  - Columns: command, target, requested by, requested at (display timezone), a status badge (pending/running/done/failed/cancelled colors), processed at, result (escaped, limited, with a modal for the full text).
  - Pending-row labels "Waiting – filter offline" / "Not picked up" come from `FilterOverview`.
  - Row action Cancel (confirm, admin+, pending only) calls `CommandQueue::cancel`.

**Checkpoint**: phase-1 DoD "click Restart Agent, see it pending" works. US1 + US2 tests are green.

---

## Phase 5: User Story 3 – Gateway mode switch (P2)

**Independent Test**: as admin, switch GM→Public. A `set_gateway_mode {"mode":"public"}` command is queued and audited, and the card shows "changing to Public…" until the status matches.

- [X] T027 [P] [US3] tests/Feature/Dashboard/GatewayModeTest.php:
  - `setGatewayMode(Public)` queues target `gateway` with payload `{"mode":"public"}` and an audit entry.
  - The same mode as reported, with nothing pending, throws `GatewayModeUnchangedException`.
  - A second mode change while one is pending throws `CommandInProgressException`, but a power command for gateway is still allowed alongside.
  - A viewer is refused.
  - ModuleCard `pendingMode` is set while the command is pending/running and cleared once it is done.
  - Livewire: the Public modal contains the "everyone can log in" warning.
- [X] T028 [US3] Add `setGatewayMode(GatewayMode $mode)` to app/Filter/CommandQueue.php (it reuses the queue path with group `gateway_mode`), and `pendingMode` lookup to FilterOverview `modules()`
- [X] T029 [US3] Add a "Gateway mode" action to the Gateway card in app/Filament/Widgets/ModuleStatusWidget.php: a select of GM/Inspection/Public with the current mode preselected and a confirmation. Public adds the warning. Admin+ only.

---

## Phase 6: User Story 4 – Manage module addresses (P2)

**Independent Test**: as admin, edit Agent1's guard port (audited, modules counter +1), add Agent2, and try to remove Agent1 while Agent2 exists (refused). Invalid port, invalid address and a guard clash are each refused. A viewer sees masked addresses.

- [X] T030 [P] [US4] tests/Feature/Modules/ModuleRulesClashTest.php: two modules with the same `(guard_ip, guard_port)` cause `InvalidModuleSetupException`. The same port on a different guard_ip is OK.
- [X] T031 [P] [US4] tests/Feature/Modules/ModuleSlotsTest.php covers:
  - `addAgent()` with 1 agent creates `Agent2`, type agent, `module_port`/`guard_port` = Agent1's + 1, `enabled` = false. It is audited `modules.created` and the modules version is bumped.
  - With 5 agents it throws.
  - `removeLastAgent()` with Agent1–3 deletes Agent3. With 1 agent it throws.
  - Removing a non-last agent is impossible through the API.
  - A viewer is refused.
- [X] T032 [P] [US4] tests/Feature/Modules/ModulesPageTest.php (Livewire):
  - The admin list shows Gateway, Download, Agent1… in order.
  - Edit with a valid change saves through ConfigStore: `modules.updated` audit with old→new, modules version +1, and a "restart needed" notification with a "Restart now" action that queues `restart` for that module.
  - Port 0/70000 gives a field error. A bad host like `a..b` gives a field error.
  - A guard clash gives a form error, and nothing changes.
  - "Add agent" is hidden at 5 agents. "Remove" is visible only on the last agent and only when there are more than 1.
  - Viewer: the list shows guard ip:port, module ip/port render as `••••` (assert the real IP is not in the HTML), there is no edit action, and the edit URL returns 403.
- [X] T033 [US4] Add the guard clash check to app/Config/ModuleRules.php (`guardClashProblems`, translated message)
- [X] T034 [US4] Create app/Config/ModuleSlots.php: `addAgent()` and `removeLastAgent()` via `ConfigStore::batch`, with `Gate::authorize` on FilterModulePolicy and the rules above
- [X] T035 [US4] Create app/Filament/Resources/FilterModules/ with these parts:
  - `FilterModuleResource`: model FilterModule, nav group Dashboard, label "Modules", `canCreate` false.
  - Pages/ListFilterModules, with header action "Add agent" and record action "Remove" (last agent only, confirm).
  - Pages/EditFilterModule: save goes through `ConfigStore::update`, not `$record->save()`. Override `handleRecordUpdate` and turn `InvalidModuleSetupException` into form errors. After an address/port change, notify with a "Restart now" action that calls `CommandQueue::queue(Restart, target)`.
  - Schemas/FilterModuleForm: type and name are read-only. `module_ip`/`guard_ip` are required, max 64, IP or host name. The ports are integers 1–65535. `enabled` is a toggle.
  - Tables/FilterModulesTable: module ip/port are masked for users who can't update.
  - Policies are applied.

---

## Phase 7: User Story 5 – Connection health (P3)

**Independent Test**: VFILTER OK with timing and 11/11 tables. An unconfigured game DB shows "Not configured". An unreachable one shows "Unreachable" with a short reason and no credentials. Nothing is written.

- [X] T036 [P] [US5] tests/Feature/Health/ConnectionHealthTest.php covers:
  - SQLite `filter` connection → `ok`, millis ≥ 0, `missingTables` = [].
  - Drop `filter_logs` → `missingTables` = ['filter_logs'].
  - `account`/`shard`/`log` with no database configured → `not_configured`.
  - A configured connection whose connect throws (bind the connection to a sqlite path in a non-existent dir) → `unreachable`. The reason is in the allowed set and the result does not contain the username, password or host.
  - No query other than SELECT runs (`DB::listen`).
  - Page: any role can open `/admin/connection-health` and run the check, and the results render.
  - `@group sqlsrv`: an unreachable host returns within 6 s with reason `timeout` or `unreachable`.
- [X] T037 [P] [US5] Create app/Filter/HealthResult.php (readonly: state, millis, reason, missingTables)
- [X] T038 [US5] Create app/Filter/ConnectionHealthCheck.php `run()` per research R5:
  - For sqlsrv, clone the connection config as `{name}_health` with `login_timeout` = 5 and `options[PDO::SQLSRV_ATTR_QUERY_TIMEOUT]` = 5. Then `SELECT 1`, time it with `hrtime`, and `DB::purge` afterwards.
  - Map exceptions to a reason by SQLSTATE/message keywords. Never return the raw message.
  - For `filter`, compute missing tables from the 11 contract table names.
- [X] T039 [US5] Create app/Filament/Pages/ConnectionHealth.php + resources/views/filament/pages/connection-health.blade.php: nav group Dashboard, any signed-in user, a "Run check" action that stores results in a public array, rendered as a table (name, state badge, ms, reason, tables X/11)

---

## Phase 8: Polish & cross-cutting

- [X] T040 Add `/admin` (dashboard), `/admin/filter-modules`, `/admin/filter-modules/{record}/edit` and `/admin/connection-health` with their role expectations to tests/Feature/Security/RouteAuthorizationTest.php
- [X] T041 [P] Run the translations parity test (tests/Feature/Preferences/TranslationsTest.php) and fix any missing EN/AR keys. Check the dashboard in `ar` (RTL) and at 375 px width in the browser preview
- [X] T042 [P] Update CLAUDE.md conventions: commands only via `App\Filter\CommandQueue`; "online" uses `DatabaseClock`; the optional `account`/`shard`/`log` env keys
- [X] T043 Run `./vendor/bin/pint`, `./vendor/bin/phpstan` and `php artisan test`, and make them all green
- [X] T044 Walk through quickstart.md steps 1–8 in the browser preview (SQLite) and fix what fails
- [X] T045 Update HANDOFF.md (003 built, next 004-logs) and mark tasks done

---

## Dependencies

- Setup (T001–T004) → Foundational (T005–T010) → user stories.
- US1 (T011–T017) is the MVP. US2 needs US1's widget/overview (T014, T017).
- US3 needs US2's CommandQueue (T023) and widget actions (T025).
- US4 is independent of US2/US3, except that the "Restart now" shortcut (in T035) uses T023.
- US5 is independent (needs only T003).
- Polish runs last.

## Parallel examples

- Foundational: T005, T007, T008 in parallel. Then T006 and T009.
- US1: T011, T012, T013 in parallel. Then T014 → T015/T016/T017.
- US2: tests T018–T022 in parallel. Then T023 and T024 in parallel → T025, T026.
- US4 and US5 can run side by side with US2 once Foundational is done.

## Implementation strategy

1. MVP = Setup + Foundational + US1 (read-only dashboard).
2. Add US2 to reach the phase-1 definition of done for control.
3. Then US3, US4 and US5. Each is independently testable and committed per story.
