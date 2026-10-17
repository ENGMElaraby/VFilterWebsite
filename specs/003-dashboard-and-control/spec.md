# Feature Specification: Dashboard and Filter Control

**Feature Branch**: `003-dashboard-and-control`

**Created**: 2026-10-17

**Status**: Draft

**Input**: User description: "Dashboard like MaxiGuard Filter Control: per-module status cards from filter_status (online if heartbeat < 15s), online player count, Start/Restart/Stop for Gateway/Agent/All and gateway mode switch GM/Inspection/Public, all via filter_commands with live command status. Includes Modules page (filter_modules: 1 gateway, 1 download, 1-5 agents, module IP/port, guard IP/port) and a read-only DB connection health check."

## Overview

This is the panel's home screen and the first page that actually controls the filter. It replaces
MaxiGuard's "Filter Control", "Filter IP/Port" and the read side of "SQL Settings" pages
(`docs/maxiguard-feature-map.md`, Core and connection). The panel never talks to the filter
directly: it reads what the filter reports about itself and leaves instructions in the shared
database (`docs/shared-contract.md`), which the filter picks up.

## Clarifications

### Session 2026-10-17

- Q: Can an admin cancel a command that is still pending? → A: Yes. The panel may change a command from pending to a new status `cancelled`, only while it is still pending. The filter never runs cancelled commands. Shared contract MINOR change (0.3.0) in both repos.
- Q: How does MaxiGuard's "Agent" (all agents) button work here? → A: An "All agents" action queues one separate command per enabled agent (`agent1`, `agent2`, …), each with its own live status. No contract change.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See at a glance whether the filter is up (Priority: P1)

A panel user signs in and lands on the dashboard. They see one card per configured module
(Gateway, Download, Agent1…AgentN) showing whether it is online, its state (stopped, starting,
running, error), its connection count, how long it has been up, the filter version and the last
error if any. The Gateway card also shows the current gateway mode. A headline figure shows how
many players are online. The page refreshes itself without a reload.

**Why this priority**: Knowing whether the server is reachable is the first thing an operator
needs every time they open the panel, and it is useful even before any control works.

**Independent Test**: Put status rows for each module in the shared database (fresh and stale
heartbeats, different states), open the dashboard as a viewer, and confirm each card shows the
right online/offline badge, state, counts and mode, and that changing a row is reflected without
reloading the page.

**Acceptance Scenarios**:

1. **Given** a module whose last heartbeat is under 15 seconds old, **When** the dashboard loads, **Then** its card shows "Online" with the reported state, connection count and uptime.
2. **Given** a module whose last heartbeat is 15 seconds old or older, or that has never reported, **When** the dashboard loads, **Then** its card shows "Offline" with the time of the last heartbeat (or "never"), and its counts are shown as unknown rather than the stale numbers.
3. **Given** a module reports state "error" with an error message, **When** the dashboard loads, **Then** the card is highlighted as an error and shows the message.
4. **Given** the dashboard is open, **When** a module's status changes in the shared database, **Then** the card reflects it within 5 seconds without a page reload.
5. **Given** two agents are online with 40 and 25 players, **When** the dashboard loads, **Then** the online players figure shows 65; offline agents do not count.
6. **Given** a configured module has no status row at all, **When** the dashboard loads, **Then** it still has a card, shown as "Offline – never reported".

---

### User Story 2 - Start, stop and restart the filter, and see the command go through (Priority: P1)

An admin clicks Start, Restart or Stop for the Gateway, for one Agent, or for All. They confirm
the action, and a command appears in a "Recent commands" list as Pending. When the filter picks it
up it moves to Running, then Done or Failed with the filter's result message, live on the page.

**Why this priority**: This is the core of phase 1's definition of done ("click Restart Agent and
see the command move from pending to done") and the main reason to open the panel.

**Independent Test**: As an admin, click Restart on Agent1 and confirm; check one pending command
row exists with the right command and target, one audit entry records it, and the recent commands
list shows it. Update the row's status in the database to running and then done and see the list
follow within 5 seconds.

**Acceptance Scenarios**:

1. **Given** an admin is on the dashboard, **When** they click Restart on the Agent1 card and confirm, **Then** one command "restart" for target "agent1" is queued as pending, an audit entry records who queued it, and it appears at the top of Recent commands.
2. **Given** an admin clicks Stop or Restart, **When** the confirmation appears, **Then** it names the module and warns that connected players on it will be disconnected; cancelling queues nothing.
3. **Given** a command is pending, **When** the filter marks it running and then done (or failed with a result), **Then** the Recent commands list shows each change within 5 seconds, including the result text for failures.
4. **Given** a start/stop/restart command for a target is still pending or running, **When** an admin tries to queue another start/stop/restart for the same target, **Then** it is refused with a message that a command for that target is already in progress.
5. **Given** the filter is offline, **When** an admin queues a command, **Then** it is accepted and the list marks it "Waiting – filter offline", so the admin knows it will run when the filter comes back.
6. **Given** a viewer is on the dashboard, **When** the page renders, **Then** no Start/Stop/Restart or mode buttons are shown, and any direct attempt to queue a command is refused.
7. **Given** a pending command is older than 2 minutes and still not picked up, **When** the list renders, **Then** it is flagged as not picked up.
8. **Given** a command is still pending, **When** an admin clicks Cancel and confirms, **Then** it becomes "Cancelled", the cancellation is audited, and the filter never runs it.
9. **Given** the filter has already moved a command to running, **When** an admin tries to cancel it (including at the same moment the filter picks it up), **Then** the cancel is refused with "already started" and the command is left as the filter set it.
10. **Given** an admin clicks "All agents → Restart" with Agent1–3 enabled, **When** they confirm, **Then** three separate restart commands are queued (agent1, agent2, agent3), each shown with its own status; agents that already have a command in progress are skipped and named in the message.

---

### User Story 3 - Switch the gateway mode (Priority: P2)

An admin switches the gateway between GM (only allowed IPs may log in), Inspection (everyone else
gets the inspection error) and Public. The Gateway card shows the current mode as reported by the
filter, and the switch goes through the same command queue with live status.

**Why this priority**: Used for every maintenance window and server opening, but less often than
checking status or restarting.

**Independent Test**: As an admin, switch the mode from GM to Public and confirm; check a
"set_gateway_mode" command with the mode in its payload is queued and audited; set the gateway's
reported mode to public in the status table and see the card update.

**Acceptance Scenarios**:

1. **Given** the gateway reports mode GM, **When** an admin chooses Public and confirms, **Then** a gateway-mode command with mode "public" is queued for the gateway and audited.
2. **Given** a mode change is queued, **When** the filter has not yet applied it, **Then** the card shows the reported (current) mode plus "changing to Public…" until the reported mode matches or the command fails.
3. **Given** the admin chooses the mode that is already reported, **When** they confirm, **Then** nothing is queued and they are told the gateway is already in that mode.
4. **Given** the switch is to Public, **When** the confirmation appears, **Then** it warns that all players will be able to log in.

---

### User Story 4 - Manage module addresses (Priority: P2)

An admin opens the Modules page and sees the Gateway, the Download module and 1–5 Agents, each
with its real (hidden) address and port and its public guard address and port, and whether it is
enabled. They can edit addresses, enable or disable a module, add the next Agent (up to Agent5)
and remove the last Agent. Invalid setups are refused with a clear reason.

**Why this priority**: Needed once at install time and rarely afterwards; the defaults from
`vfilter:install-defaults` already give a working shape.

**Independent Test**: As an admin, change Agent1's guard port, add Agent2, then try to remove
Agent1 while Agent2 exists; check the first two save (audited, modules change counter increased)
and the third is refused.

**Acceptance Scenarios**:

1. **Given** the default modules exist, **When** an admin opens the Modules page, **Then** they see Gateway, Download and each Agent in order with real address/port, guard address/port and enabled state.
2. **Given** an admin edits a module with a valid address and port, **When** they save, **Then** the change is stored, audited with old → new values, the filter is told modules changed, and the page notes that the filter must be restarted for address changes to take effect.
3. **Given** 1–4 agents exist, **When** an admin adds an agent, **Then** it is created with the next name (e.g. Agent3); with 5 agents, adding is not offered.
4. **Given** several agents exist, **When** an admin removes an agent, **Then** only the highest-numbered agent can be removed, and the last remaining agent cannot be removed.
5. **Given** an admin enters a port outside 1–65535 or an address that is not an IP or host name, **When** they save, **Then** the save is refused with a message on that field and nothing changes.
6. **Given** two modules would use the same guard address and port, **When** an admin saves, **Then** it is refused because the filter cannot listen twice on one port.
7. **Given** a viewer opens the Modules page, **When** it renders, **Then** they see guard addresses and ports but real module addresses are masked, and there are no edit actions.

---

### User Story 5 - Check the database connections (Priority: P3)

A panel user opens a Connection health section and sees, for the VFILTER database and for each
configured game database (account, shard, log), whether the panel can reach it, how long a
simple check took, and for VFILTER whether all shared tables exist. Connection settings are not
editable here; they come from the server's environment file.

**Why this priority**: Diagnoses "the dashboard shows everything offline" quickly, but the
dashboard already works without it.

**Independent Test**: Open the health section with a working VFILTER connection and an
unreachable game database configured; confirm VFILTER shows OK with timing and tables present,
the game database shows unreachable with a short reason, and no credentials appear anywhere.

**Acceptance Scenarios**:

1. **Given** VFILTER is reachable, **When** a user runs the health check, **Then** it shows "OK", the response time, and that all 11 shared tables are present (or which are missing).
2. **Given** a game database is configured but unreachable, **When** the check runs, **Then** it shows "Unreachable" with a short reason that contains no password, user name or connection string.
3. **Given** a game database is not configured, **When** the check runs, **Then** it shows "Not configured" rather than an error.
4. **Given** a database does not answer, **When** the check runs, **Then** the result appears within 5 seconds as a timeout instead of the page hanging.
5. **Given** any user runs the check, **When** it completes, **Then** nothing in any database has been changed.

---

### Edge Cases

- The panel's clock and the database server's clock differ: online/offline is judged against the database server's current time, not the web server's, so skew cannot flip a module's badge.
- The shared database itself is down: the dashboard shows a clear "cannot reach VFILTER" banner instead of an error page, and command buttons are disabled.
- A status row exists for a module that is no longer configured (e.g. Agent3 removed): it is not shown as a card.
- A module is configured but disabled: its card is shown greyed out as "Disabled" and has no control buttons.
- An "All" command is queued while a per-module command for one module is in progress: it is allowed (the filter serialises them); only same-target duplicates are refused.
- The filter reports a gateway mode the panel does not know: the card shows it as "Unknown (<value>)" rather than failing.
- Two admins click Restart on the same module at the same moment: only one command is queued; the other admin sees the in-progress message.
- Filter result text is long or contains markup: it is shown escaped and shortened, with the full text available on demand.
- The page is left open for hours: refreshing continues without piling up requests or slowing the browser.
- Arabic (RTL) and phone-width layouts keep cards and buttons usable.

## Requirements *(mandatory)*

### Functional Requirements

**Dashboard and status**

- **FR-001**: The dashboard MUST be the landing page after sign-in and MUST show one card per configured module, ordered Gateway, Download, Agent1…AgentN.
- **FR-002**: A module MUST be shown "Online" only when its last heartbeat is less than 15 seconds old, measured against the database server's current time; otherwise "Offline" with the last heartbeat time or "never".
- **FR-003**: Each online card MUST show state, connection count, uptime since start, filter version and last error (if any); the Gateway card MUST also show the reported gateway mode.
- **FR-004**: The dashboard MUST show the number of online players as the sum of the online counts of online Agent modules, and MUST show it as unknown when no Agent is online.
- **FR-005**: Status, player count and recent commands MUST refresh automatically at least every 5 seconds while the page is open.
- **FR-006**: If the shared database cannot be reached, the dashboard MUST show a clear banner and disable controls instead of failing.

**Commands**

- **FR-007**: Users with role admin or owner MUST be able to queue Start, Stop and Restart for: the Gateway, the Download module, each individual Agent, All agents, and All. "All agents" MUST queue one separate command per enabled agent, skipping (and naming) agents that already have a start/stop/restart in progress.
- **FR-008**: Stop and Restart MUST ask for confirmation naming the target and warning that players on it will be disconnected. Start MUST be one click.
- **FR-009**: Users with role admin or owner MUST be able to queue a gateway mode change to GM, Inspection or Public, with confirmation; switching to Public MUST warn that everyone can log in.
- **FR-010**: Every queued command MUST be stored as a new pending command with the requesting user, and MUST be recorded in the audit log, in one unit: either both happen or neither does.
- **FR-011**: The system MUST refuse a new start/stop/restart for a target while another start/stop/restart for the same target is pending or running, and MUST refuse a gateway mode change while another mode change is pending or running.
- **FR-012**: The dashboard MUST list the 20 most recent commands with command, target, requested by, requested at, status, processed at and result; it MUST mark pending commands as "Waiting – filter offline" when the target is offline, and as "Not picked up" when pending for more than 2 minutes.
- **FR-013**: Viewers MUST NOT see command controls, and every command request MUST be authorized on the server regardless of what the page shows.
- **FR-014**: Disabled modules MUST NOT offer command controls.
- **FR-014a**: Admins and the owner MUST be able to cancel a command while it is still pending. Cancelling MUST only succeed if the command is still pending at the moment of the change (never overriding running/done/failed), MUST set it to "cancelled", and MUST be audited in the same unit. Cancelled commands are never run by the filter.

**Modules**

- **FR-015**: The Modules page MUST list all configured modules with type, name, real address and port, guard address and port, and enabled state.
- **FR-016**: Admins and the owner MUST be able to edit addresses, ports and enabled state, add the next Agent (Agent1…Agent5, no gaps) and remove only the highest-numbered Agent; the page MUST keep exactly 1 Gateway, 1 Download and 1–5 Agents.
- **FR-017**: Addresses MUST be an IP address or host name and ports MUST be 1–65535; two modules MUST NOT share the same guard address and port. Invalid input MUST be refused with a field-level message and leave the stored setup unchanged.
- **FR-018**: Every module change MUST be saved, audited (old → new) and signal a modules configuration change to the filter, in one unit.
- **FR-019**: After an address or port change, the page MUST tell the user that the filter must be restarted to apply it, with a shortcut to queue that restart.
- **FR-020**: Viewers MUST see the Modules page read-only, with real module addresses masked.

**Connection health**

- **FR-021**: Any signed-in user MUST be able to run a connection health check for VFILTER and for each configured game database (account, shard, log).
- **FR-022**: For each database the check MUST show OK / Unreachable / Not configured, response time, and, for VFILTER, which of the 11 shared tables are present or missing.
- **FR-023**: The check MUST be read-only, MUST time out within 5 seconds per database, and MUST NOT display credentials, user names or connection strings.

**General**

- **FR-024**: All new text MUST be available in English and Arabic, and all pages MUST be usable in RTL and at phone width.

### Key Entities

- **Module**: one proxied server component (Gateway, Download, Agent1–5) with real address/port, public guard address/port and enabled flag. Configured by the panel.
- **Module status**: what the filter last reported about a module: state, online count, connections, gateway mode, version, start time, heartbeat time, last error. Written by the filter, read by the panel.
- **Command**: one instruction from the panel to the filter (start, stop, restart, set gateway mode) with target, optional details (e.g. the mode), requesting user, status (pending → running → done | failed, or pending → cancelled by the panel), result and timestamps.
- **Audit entry**: who queued which command or changed which module, when, with old → new values.
- **Connection check result**: per database, reachability, response time, missing tables. Not stored.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator can tell whether every module is up, and how many players are online, within 3 seconds of signing in.
- **SC-002**: A status change written by the filter is visible on an open dashboard within 5 seconds, 100% of the time in testing.
- **SC-003**: An admin can restart any single module in 3 clicks or fewer (button, confirm), and see the command's outcome on the same page without navigating.
- **SC-004**: 100% of queued commands and module changes have a matching audit entry naming the user.
- **SC-004a**: A cancelled command is never run, and a command the filter has started can never be marked cancelled, including when both happen at the same moment.
- **SC-005**: No duplicate start/stop/restart for the same target can be queued while one is in progress, including under simultaneous clicks.
- **SC-006**: No viewer can queue a command or change a module, verified for every action.
- **SC-007**: The health check answers within 5 seconds per database even when a database is unreachable, and never shows a credential.
- **SC-008**: An invalid module setup (wrong counts, gaps in agent names, bad ports or addresses, clashing guard ports) can never be saved.

## Assumptions

- Roles follow feature 001: viewer = read-only; admin and owner may queue commands and edit modules.
- Command targets are `all` or a module's name in lower case (`gateway`, `download`, `agent1`…`agent5`); the contract lists `all`, `gateway`, `agent1`… and `download` is read as included. A PATCH note will be added to the shared contract in both repos if that wording needs to be explicit.
- The filter applies commands in the order queued; the panel does not reorder or retry them.
- Online players = sum of Agent online counts, since players are in game on Agents; the Gateway only holds logins briefly.
- Uptime is computed from the module's reported start time.
- Game database connections (account, shard, log) are configured in the server's environment file; the panel never edits connection settings or stores credentials in the database (constitution III). MaxiGuard's editable SQL Settings page and "Refresh cache" button are out of scope.
- Custom domain and firewall allow-list from MaxiGuard's Filter IP/Port page are out of scope here (later feature).
- Live updates use periodic refresh of the page data (every 5 seconds or faster); no push channel is required.
- Until the filter exists, commands stay pending, which is the expected phase-1 behaviour.
- Shared contract 0.3.0 (MINOR, both repos in one change set): `filter_commands.status` gains `cancelled`; rule 2 allows the website to set `pending → cancelled` only; the filter must claim a command with a pending-only conditional update so a cancel and a pick-up can never both win.
- Depends on feature 001 (sign-in, roles, audit log, EN/AR) and feature 002 (contract tables, models, audited configuration writes, module setup rules).
