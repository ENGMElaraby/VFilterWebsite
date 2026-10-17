# Feature Specification: Logs and Live Sessions

**Feature Branch**: `004-logs`

**Created**: 2026-10-17

**Status**: Draft

**Input**: User description: "Logs viewer for filter_logs: filters by level, category, module, IP, username, charname, opcode and time range; live tail; export CSV; retention setting logs.retention_days with a scheduled prune job; live sessions page from filter_sessions with kick/ban actions via filter_commands."

## Overview

The filter records every notable event (connections, logins, blocked exploits, floods, limits,
restrictions, GM actions) and keeps a row for every connected player. This feature lets panel users
search those events, watch them arrive live, export them, keep the log table from growing forever,
and act on connected players (kick or ban) through the same command queue as the dashboard. It
completes phase 1 ("browse logs").

## Clarifications

### Session 2026-10-17

- Q: What does "Ban" on a connected player do? → A: Bans the game account (username) for a chosen duration (1 hour, 1 day, 7 days, 30 days, or permanent) with a required reason; the filter applies it and kicks the player. Needs a username; contract PATCH documents the ban payload.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Search the filter's logs (Priority: P1)

A panel user opens Logs and sees the newest events first: time, level, category, module, IP, HWID,
username, character name, opcode and message. They narrow the list by level, category, module, IP,
username, character name, opcode and a time range, and open one event to see its full details.

**Why this priority**: When a player complains ("I got disconnected", "I can't log in"), the logs are
the only way to see what the filter did and why.

**Independent Test**: Put events of different levels, categories, modules, IPs, names and times in
the log table; apply each filter alone and combined; confirm only matching events show, newest first,
paged.

**Acceptance Scenarios**:

1. **Given** many events, **When** a user opens Logs, **Then** the newest 50 are shown newest first with paging to older ones.
2. **Given** events of several levels and categories, **When** the user picks level "security" and category "exploit", **Then** only events matching both are shown.
3. **Given** events from several IPs and players, **When** the user filters by IP, username, character name or opcode, **Then** only exact matches are shown (username and character name ignore letter case).
4. **Given** events over several days, **When** the user picks a time range in the panel's display time zone, **Then** only events inside that range are shown.
5. **Given** an event with extra detail, **When** the user opens it, **Then** every field and the extra detail are shown, safely escaped.
6. **Given** the log holds millions of events, **When** the user filters by any supported field, **Then** results appear within 2 seconds.

---

### User Story 2 - Watch events live (Priority: P1)

A user turns on Live and sees new events appear at the top within a few seconds, with the current
filters still applied. They pause it to read, and resume.

**Why this priority**: During an attack or an event, operators need to watch what the filter is
blocking right now.

**Independent Test**: Turn Live on with a category filter, add matching and non-matching events, and
confirm only matching ones appear within 5 seconds; pause, add more, confirm the list does not move
until resumed.

**Acceptance Scenarios**:

1. **Given** Live is on, **When** a new event is logged, **Then** it appears at the top within 5 seconds.
2. **Given** Live is on with filters, **When** events are logged, **Then** only matching ones appear.
3. **Given** Live is paused, **When** events are logged, **Then** the list does not change until Live is resumed.
4. **Given** the user moves to an older page, **When** Live is on, **Then** Live pauses automatically so the page they are reading does not shift.

---

### User Story 3 - Export logs (Priority: P2)

A user exports the currently filtered events to a CSV file to share with staff or keep as evidence.

**Why this priority**: Useful for investigations and reports, but not needed day to day.

**Independent Test**: Apply filters, export, and confirm the file has a header row and exactly the
filtered events (up to the export limit), with times in the display time zone.

**Acceptance Scenarios**:

1. **Given** filters are applied, **When** the user exports, **Then** the file contains exactly the matching events, newest first, with a header row.
2. **Given** more than 100,000 matching events, **When** the user exports, **Then** the newest 100,000 are exported and the user is told the export was cut and to narrow the filters.
3. **Given** an event field starting with `=`, `+`, `-` or `@`, **When** it is exported, **Then** it is written so spreadsheet programs do not run it as a formula.
4. **Given** any export, **When** it completes, **Then** an audit entry records who exported and which filters were used.

---

### User Story 4 - Keep logs from growing forever (Priority: P2)

An admin sets how many days of logs to keep. Every day, events older than that are removed
automatically in small batches, without slowing the filter down.

**Why this priority**: The log table can reach millions of rows; without pruning it fills the disk
and slows every search.

**Independent Test**: Set retention to 7 days, put events aged 6 and 8 days, run the daily prune,
confirm only the 8-day-old events are gone and the run is recorded.

**Acceptance Scenarios**:

1. **Given** retention is 30 days, **When** the daily prune runs, **Then** events older than 30 days are removed and newer ones are kept.
2. **Given** an admin changes retention, **When** they save, **Then** the value is validated (1–3650 days), stored, and audited with old → new.
3. **Given** millions of old events, **When** the prune runs, **Then** it deletes in batches so no single step holds the table for more than a few seconds, and it stops after a time budget, finishing on the next run.
4. **Given** a prune run, **When** it finishes, **Then** how many events were removed is recorded where staff can see it.
5. **Given** a viewer, **When** they open the retention setting, **Then** they can see it but not change it.

---

### User Story 5 - See who is connected and kick or ban (Priority: P1)

A panel user opens Sessions and sees every connection the filter currently holds: module, IP, HWID,
username, character name, connected since and last activity. They filter by module, IP, username or
character name. An admin can kick a session, or ban the player, with a reason; this queues a
command for the filter and shows its status like the dashboard does.

**Why this priority**: Removing a cheater or a stuck player right now is a core operator task.

**Independent Test**: Put sessions in the session table; as admin kick one and ban one; confirm the
right commands are queued with the right target, details and audit entries; as viewer confirm no
kick/ban is possible.

**Acceptance Scenarios**:

1. **Given** sessions exist, **When** a user opens Sessions, **Then** each is listed with its fields and the list refreshes every 5 seconds.
2. **Given** an admin clicks Kick on a session and confirms, **When** the command is queued, **Then** it targets that session's player (character name, else username, else IP) and carries the session id, and an audit entry is written.
3. **Given** an admin clicks Ban on a logged-in session, chooses a duration (1 hour, 1 day, 7 days, 30 days, permanent) and enters a reason, **When** they confirm, **Then** one ban command is queued for that game account (username) with the duration and reason; the filter bans the account and kicks the player.
7. **Given** a session with no username yet (not logged in), **When** an admin looks at it, **Then** Ban is not offered (Kick still is).
4. **Given** a kick or ban for the same player is already pending or running, **When** an admin tries again, **Then** it is refused as already in progress.
5. **Given** a viewer, **When** they open Sessions, **Then** they see the list but no Kick or Ban.
6. **Given** a session ends while an admin is looking at it, **When** the list refreshes, **Then** it disappears; a kick on a session that no longer exists is still queued and the filter reports the result.

---

### Edge Cases

- A session or event with no username/character (before login): it shows "—"; kick targets the IP.
- Text from the filter (messages, names, extra detail) may contain markup or very long values: always escaped, long values shortened in the list and full in the detail view.
- Unknown level or category values written by a newer filter: shown as-is, not an error; they can still be filtered by the known values.
- Time range with start after end: refused with a message.
- The VFILTER database is unreachable: Logs and Sessions show the same "cannot reach VFILTER" banner as the dashboard.
- Prune runs while the filter is writing: only rows older than the cut-off are touched, so new events are never blocked for long.
- Retention changed to a smaller value: the next prune removes the extra history; the save asks for confirmation when it shortens retention.
- Export while Live is on: exports the filtered set as of the moment of export.

## Requirements *(mandatory)*

### Functional Requirements

**Logs**

- **FR-001**: The system MUST list log events newest first, 50 per page, with time (display time zone), level, category, module, IP, HWID, username, character name, opcode and message.
- **FR-002**: Users MUST be able to filter by level (multi), category (multi), module, IP (exact), username and character name (exact, case-insensitive), opcode (exact) and a time range; filters combine with AND.
- **FR-003**: Filtered queries MUST use only fields the log table is indexed for as the leading condition, or be bounded by a time range, so that no filter scans the whole table; a time range MUST default to the last 24 hours.
- **FR-004**: Users MUST be able to open an event and see all fields and its extra detail (formatted), escaped.
- **FR-005**: A Live mode MUST show new matching events within 5 seconds, MUST be pausable, and MUST pause itself when the user leaves the first page.
- **FR-006**: Users MUST be able to export the current filtered set to CSV, at most 100,000 rows (newest first), with a header row, display-time-zone times, formula-safe cells, and a notice when the cap is hit. Each export MUST be audited (`logs.exported` with the filters).

**Retention**

- **FR-007**: Admins and the owner MUST be able to set `logs.retention_days` (whole days, 1–3650), saved through the audited settings path; viewers see it read-only. Shortening retention MUST ask for confirmation.
- **FR-008**: A prune job MUST run daily, delete events older than the retention period in batches, stop after a time budget, and record the number removed and when it ran (visible on the Logs page).
- **FR-009**: The prune MUST touch only the log table and only rows older than the cut-off.

**Sessions**

- **FR-010**: The system MUST list current sessions with module, IP, HWID, username, character name, connected since and last activity, filterable by module, IP, username and character name, refreshing every 5 seconds, with a total count.
- **FR-011**: Admins and the owner MUST be able to Kick a session (confirmation) and Ban the session's game account (confirmation, required reason up to 200 characters, duration 1 hour / 1 day / 7 days / 30 days / permanent); Ban is offered only when the session has a username. Viewers MUST NOT, enforced on the server.
- **FR-012**: Kick and Ban MUST be queued as commands for the filter through the same queue as the dashboard, each with an audit entry in one unit. Kick: target = character name, else username, else IP; details = session id, IP, optional reason. Ban: target = username; details = session id, reason, duration in minutes (none = permanent).
- **FR-013**: The system MUST refuse a new kick/ban for a target while one for that target is pending or running.
- **FR-014**: Kick and Ban commands MUST appear in the dashboard's recent commands with live status.

**General**

- **FR-015**: All new text MUST be available in English and Arabic; pages MUST work in RTL and at phone width.
- **FR-016**: Logs and Sessions MUST be visible to every signed-in role; the retention setting is editable by admin and owner only.

### Key Entities

- **Log event**: something the filter recorded: time, level, category, module, IP, HWID, username, character name, opcode, message, extra detail. Written by the filter; read and pruned by the panel.
- **Session**: one current connection held by the filter: module, IP, HWID, username, character name, connected since, last activity. Written by the filter; read by the panel.
- **Command (kick/ban)**: as in feature 003, with the player as target and session/reason details.
- **Prune run**: when the prune ran and how many events it removed.
- **Retention setting**: `logs.retention_days`, part of the filter settings.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With 5 million log events, any supported filter combination returns the first page in under 2 seconds.
- **SC-002**: In Live mode a new matching event is visible within 5 seconds, 100% of the time in testing.
- **SC-003**: An export of 100,000 events completes in under 30 seconds and opens correctly in a spreadsheet program in both English and Arabic text.
- **SC-004**: After a prune run, no event older than the retention period remains (unless the run reported it stopped on its time budget), and no event inside it was removed.
- **SC-005**: An admin can kick a connected player in 3 clicks or fewer.
- **SC-006**: 100% of kicks, bans, exports and retention changes have an audit entry naming the user.
- **SC-007**: No viewer can kick, ban or change retention, verified for every action.

## Assumptions

- Roles as in features 001/003: every role reads logs and sessions; admin and owner act.
- Log rows are written by the filter; the panel only reads them and deletes old ones (contract: retention via `logs.retention_days`, website job prunes).
- Kick/ban targets follow the contract (`target` = username/charname); a kick on a session with neither uses its IP as target. The kick and ban payload shapes are written into the shared contract as a PATCH (0.3.1) in both repos.
- Exact-match filters (not "contains") keep searches fast on millions of rows; partial search may come later.
- Live mode uses periodic refresh (≤ 5 s), like the dashboard.
- The prune runs through the app's scheduler (one daily run); the server must run the scheduler (documented in HANDOFF).
- Depends on 001 (roles, audit, EN/AR), 002 (tables, settings path), 003 (command queue, recent commands, DB-unavailable banner).
