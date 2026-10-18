# Feature Specification: Notices and Ban Durations

**Feature Branch**: `006-notices`

**Created**: 2026-10-19

**Status**: Draft

**Input**: User description: "Notice page (006-notices): owner and admin send a system notice to every in-world player. Queued as filter command `notice` (target `all`, payload {"message": trimmed}) through App\Filter\CommandQueue; message trimmed, 1-200 characters (AegisCore limit), single line. Page shows live status/result of recent notices and Cancel while pending; audited command.queued/command.cancelled; EN/AR. Story 2: config switch vfilter.timed_bans (env VFILTER_TIMED_BANS, default false): off = ban dialog offers Permanent only and CommandQueue rejects timed bans (AegisCore fails timed bans); on = 1h/1d/7d/30d/permanent as today."

## Overview

The production filter side is now AegisCore (contract 0.3.4). It can broadcast a system notice to every
in-world player, but the panel has no way to ask for one. This feature adds a Notices page that queues a
`notice` command and shows what happened to it. It also stops the ban dialog from offering ban lengths
the filter side cannot apply: AegisCore fails every timed ban, and today the dialog defaults to one day.

## Clarifications

### Session 2026-10-19

- Q: How should the ban dialog handle timed bans while AegisCore is the filter side? → A: A server
  setting (off by default). Off: only Permanent is offered and timed bans are refused. On: the 1 hour /
  1 day / 7 days / 30 days choices come back.
- Q: Who can open the Notices page? → A: Every panel user can open it and see recent notices
  (viewers are read-only everywhere else, and the dashboard already shows them every command); only
  admins and the owner see the send form and Cancel.
- Q: What is the longest notice? → A: 200 characters, the longest notice AegisCore will send. One line
  only: line breaks and other control characters are refused.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Send a notice to all players (Priority: P1)

An admin opens Notices, types a message ("Server restarts in 10 minutes"), and sends it. The message
appears at the top of the recent notices list as pending, then changes to done with the filter's result
("notice sent to 42 player(s)") or to failed with the reason. While it is still pending, the admin can
cancel it.

**Why this priority**: Announcing restarts, maintenance and events to players is a daily operator task
and the main reason for this feature.

**Independent Test**: As an admin, send a notice; confirm one pending `notice` command for all players
with the trimmed message and an audit entry exist, and that it shows in the list. As a viewer, confirm
the page opens without the form and that sending is refused.

**Acceptance Scenarios**:

1. **Given** an admin or the owner, **When** they send "  Server restarts in 10 minutes  ", **Then** one pending notice for all players is queued with the message "Server restarts in 10 minutes", the action is audited, and the notice appears at the top of the list.
2. **Given** an empty message or one of only spaces, **When** they send it, **Then** nothing is queued and the form says the message is required.
3. **Given** a message longer than 200 characters (after trimming), **When** they send it, **Then** nothing is queued and the form says the limit.
4. **Given** a message with a line break or another control character, **When** they send it, **Then** nothing is queued and the form says it must be one line.
5. **Given** a queued notice, **When** the filter marks it done or failed, **Then** the list shows the new status and result within 5 seconds without reloading.
6. **Given** a pending notice, **When** an admin cancels it, **Then** it becomes cancelled, the cancel is audited, and the filter never sends it. **Given** the filter has already claimed it, **Then** the admin is told it already started.
7. **Given** a viewer, **When** they open Notices, **Then** they see the recent notices but no send form and no Cancel; a crafted send request is refused and nothing is queued.
8. **Given** the same message is already pending, **When** it is sent again (e.g. a double click), **Then** the second one is refused with a clear message.

---

### User Story 2 - Offer only the ban lengths the filter can apply (Priority: P2)

An admin bans a connected player from Sessions or from a log entry. While timed bans are switched off
(the default), the dialog offers only Permanent and uses it by default. When the server operator turns
timed bans on (once the filter side supports them), the dialog offers 1 hour, 1 day, 7 days, 30 days and
Permanent again, as before.

**Why this priority**: Without it every ban queued with the default length fails on AegisCore, which
looks like the panel is broken. It is a small change next to the main story.

**Independent Test**: With the switch off, open the ban dialog and confirm only Permanent is offered;
queue a ban and confirm it is permanent; confirm a timed ban is refused. With the switch on, confirm the
timed lengths are offered and accepted.

**Acceptance Scenarios**:

1. **Given** timed bans are off, **When** an admin opens the ban dialog, **Then** only Permanent is offered and it is selected.
2. **Given** timed bans are off, **When** a timed ban is requested anyway (crafted request), **Then** it is refused and nothing is queued.
3. **Given** timed bans are on, **When** an admin opens the ban dialog, **Then** 1 hour, 1 day, 7 days, 30 days and Permanent are offered, as today.
4. **Given** either setting, **When** a permanent ban is queued, **Then** it is queued exactly as today (no duration).

---

### Edge Cases

- The filter is offline: the notice stays pending and the list flags it as waiting/not picked up, the same as other commands; it expires on the filter side after 10 minutes (contract 0.3.3).
- The legacy VFilter proxy is the filter side: it fails notices with "not supported yet"; the page shows that result.
- Arabic text: the 200-character limit counts characters, not bytes, so a 200-letter Arabic message is accepted.
- A message containing HTML or script text is shown escaped in the list and sent as plain text.
- The same message sent again after the first one finished is allowed (repeat announcements).
- The game DB time zone and the panel display time zone differ: times show in the panel's display time zone.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The panel MUST have a Notices page that every signed-in panel user can open.
- **FR-002**: Admins and the owner MUST be able to send a notice; viewers MUST NOT (no form shown, and the server refuses).
- **FR-003**: A notice MUST be queued as a `notice` command for all players with the message trimmed of leading and trailing whitespace (contract 0.3.4).
- **FR-004**: The trimmed message MUST be 1–200 characters, counted as characters, and MUST NOT contain line breaks or other control characters. Invalid messages MUST be refused with a clear, translated message and nothing queued.
- **FR-005**: A message identical to a notice that is still pending or running MUST be refused.
- **FR-006**: Each queued notice and each cancel MUST be written to the audit log (`command.queued`, `command.cancelled`) in the same transaction as the change.
- **FR-007**: The page MUST list the 20 most recent notices, newest first, with message, requested by, requested at, status, processed at and result, refreshing at least every 5 seconds.
- **FR-008**: Admins and the owner MUST be able to cancel a notice while it is pending; once the filter has claimed it, cancel MUST be refused with a clear message.
- **FR-009**: All new text MUST exist in English and Arabic.
- **FR-010**: There MUST be a server setting for timed bans, off by default.
- **FR-011**: With timed bans off, the ban dialog MUST offer only Permanent (selected by default) and the panel MUST refuse any timed ban.
- **FR-012**: With timed bans on, the ban dialog MUST offer 1 hour, 1 day, 7 days, 30 days and Permanent, and accept them, as before this feature.

### Key Entities

- **Notice**: a filter command with name `notice`, target "all players", a message, who asked and when, and the filter's status and result. No new table.
- **Timed bans setting**: a server-level on/off switch read from the panel's environment, not stored in the database.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can send a notice in under 15 seconds from opening the page.
- **SC-002**: A notice's status change made by the filter shows on the page within 5 seconds.
- **SC-003**: 100% of queued and cancelled notices have a matching audit entry.
- **SC-004**: With timed bans off, 0 timed bans can be queued from the panel.
- **SC-005**: Viewers cannot queue or cancel a notice by any route (verified by tests).

## Assumptions

- The filter side (AegisCore) sends the notice and writes the result; the website only queues and shows it.
- The 200-character limit matches AegisCore's notice limit; the contract only requires a non-empty message, so the website being stricter needs no contract change.
- The switch is a deployment setting in `.env`, not a panel setting, because it follows which filter side is deployed.
- Notices are not scheduled or repeated by the panel; that can come later with an events feature.
