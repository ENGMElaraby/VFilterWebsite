---
description: "Tasks for 004 Logs and Live Sessions"
---

# Tasks: Logs and Live Sessions

**Input**: `specs/004-logs/` · **Tests**: required (constitution IV), written first.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

- [ ] T001 Apply contracts/shared-contract-0.3.1.md to docs/shared-contract.md and ../VFilter/docs/shared-contract.md (identical, version 0.3.1, changelog)
- [ ] T002 [P] Add `case Player = 'player';` to app/Enums/Filter/CommandGroup.php (`for()`: Kick, Ban → Player; `commands()`: [Kick, Ban])
- [ ] T003 [P] Add EN/AR keys to lang/{en,ar}/panel.php (`logs.*`, `sessions.*`, `retention.*`) and `logs.exported`, `logs.pruned` to lang/{en,ar}/audit.php + specs/001-auth-and-shell/contracts/audit-actions.md
- [ ] T004 [P] Create app/Policies/FilterLogPolicy.php and FilterSessionPolicy.php (viewAny/view = any signed-in user; create/update/delete/deleteAny = false); register in AppServiceProvider

---

## Phase 2: Foundational

- [ ] T005 [P] tests/Feature/Logs/LogQueryTest.php:
  - Time bounds are inclusive, and from > until throws `InvalidArgumentException`.
  - Levels and categories match with IN.
  - Module, IP and opcode are exact; opcode is upper-cased.
  - Username and charname are exact and case-insensitive.
  - Results are ordered ts desc, id desc.
  - Filters combine with AND.
  - Defaults are now−24h..now.
- [ ] T006 Create app/Logs/LogFilters.php (readonly; `fromArray(array, CarbonImmutable $now, string $zone)` parses the display-zone inputs to UTC and validates max lengths; `toArray()` for audit) and app/Logs/LogQuery.php (`apply()`, `whereName()` with `COLLATE NOCASE` on sqlite)

---

## Phase 3: US1 – Search logs (P1) 🎯 MVP

- [ ] T007 [P] [US1] tests/Feature/Logs/LogsPageTest.php:
  - Every role can open the list; it shows the newest first, 50 per page, with simple pagination.
  - Each table filter narrows the rows. Rows older than 24 h are hidden by default and shown with a wider range.
  - The view page shows all fields and pretty-printed `data`, escaped (`<script>` text is not raw HTML).
  - An unknown level value in the DB doesn't break the page.
  - The DB-unavailable banner shows.
- [ ] T008 [US1] Create app/Filament/Resources/FilterLogs/: FilterLogResource (nav group Logs, icon, no create/edit), Pages/ListFilterLogs, Pages/ViewFilterLog, Tables/FilterLogsTable and Schemas/FilterLogInfolist:
  - Columns: ts in the display tz; level and category badges (raw value when unknown); module, ip, hwid, username, charname, opcode; message limited to 120 chars with a tooltip.
  - Filters: date range (default 24 h, not removable), levels, categories, module select, and text inputs for ip, username, charname and opcode. Each applies `LogQuery` through `LogFilters`.
  - `paginationMode(Simple)`, `defaultPaginationPageOption(50)`.
- [ ] T009 [US1] Make level/category display tolerant: read raw attributes (`getRawOriginal`) for badges so `ValueError` can't occur. Or add a `knownValues` guard like FilterCommand. Choose the raw-attribute display.

## Phase 4: US2 – Live tail (P1)

- [ ] T010 [P] [US2] tests/Feature/Logs/LiveTest.php:
  - Toggling Live sets the poll interval to `5s`.
  - Off means no poll.
  - Changing to page 2 turns Live off.
  - A new matching row appears after a refresh while non-matching rows don't.
- [ ] T011 [US2] Add `public bool $live = false`, a "Live/Pause" header action, `->poll(fn () => $this->live ? '5s' : null)` and an `updatedPaginators()` hook that sets `live = false` when the page is not 1, in ListFilterLogs

## Phase 5: US5 – Sessions, kick, ban (P1)

- [ ] T012 [P] [US5] tests/Feature/Sessions/KickBanTest.php:
  - `kick()` targets the charname, else the username, else the IP, with payload {session_id, ip, reason}, plus a `command.queued` audit entry.
  - `ban()` targets the username with payload {session_id, reason, duration_minutes}. It refuses: no username, an empty or longer-than-200 reason, and a duration outside {60, 1440, 10080, 43200, null}.
  - A duplicate kick/ban for the same target is refused. Kick then ban on the same player is also refused, because they share the Player group.
  - A viewer is refused.
- [ ] T013 [P] [US5] tests/Feature/Sessions/SessionsPageTest.php:
  - Every role sees the sessions and the count, with a 5 s poll; filters work.
  - Admin: Kick (confirm) queues the command; Ban (form: duration select + reason) queues.
  - Ban is hidden when there is no username.
  - Viewer: there are no actions.
  - A kick/ban appears in RecentCommandsWidget.
- [ ] T014 [US5] Add `kick()` and `ban()` to app/Filter/CommandQueue.php (reuse lock/transaction/insert; player target validation non-empty ≤ 64; constants `BAN_DURATIONS`, `REASON_MAX_LENGTH = 200`)
- [ ] T015 [US5] Create app/Filament/Resources/FilterSessions/: Resource (nav group Logs), Pages/ListFilterSessions and Tables/FilterSessionsTable:
  - Columns: module, ip, hwid, username, charname, connected_at, last_packet_at.
  - Filters: module, ip, username, charname. Poll 5 s, count in the heading.
  - Row actions Kick and Ban, admin+ only, calling CommandQueue and catching the domain exceptions in notifications.
- [ ] T016 [US5] Show kick/ban targets as-is in RecentCommandsWidget (no `ucfirst` for player targets) and add command labels for kick/ban with the duration in app/Filament/Widgets/RecentCommandsWidget.php

## Phase 6: US3 – Export (P2)

- [ ] T017 [P] [US3] tests/Feature/Logs/ExportTest.php:
  - The streamed CSV has a BOM, the header, and exactly the filtered rows newest first, with times in the display tz.
  - Cells starting with `=`, `+`, `-` or `@` get a `'` prefix.
  - The cap gives N rows plus the truncated flag (test with the cap overridden to 3).
  - A `logs.exported` audit entry is written with the filters.
  - The Livewire header action returns a download.
- [ ] T018 [US3] Create app/Logs/LogExporter.php (`MAX_ROWS = 100_000`, injectable cap for tests; `lazyById` desc) and add the "Export CSV" header action to ListFilterLogs, with a warning notification when truncated

## Phase 7: US4 – Retention + prune (P2)

- [ ] T019 [P] [US4] tests/Feature/Logs/PruneTest.php:
  - With retention 7, rows aged 6 days are kept and rows aged 8 days are deleted.
  - Batching: with the batch size 2 and 5 old rows, all are deleted.
  - When the budget runs out, `complete` is false.
  - A `logs.pruned` audit entry is written with the counts.
  - The command `vfilter:prune-logs` prints the result.
  - The schedule contains the command daily.
- [ ] T020 [P] [US4] tests/Feature/Logs/RetentionTest.php:
  - Admin saves 7 through the header action, which gives a `setting.updated` audit entry and a settings version bump.
  - 0 and 3651 are refused.
  - A smaller value requires confirmation.
  - The viewer sees the value but not the action.
  - The page shows the last prune summary.
- [ ] T021 [US4] Create app/Logs/PruneResult.php, app/Logs/LogPruner.php and app/Console/Commands/PruneLogs.php, and the schedule in routes/console.php (`dailyAt('03:30')->withoutOverlapping()`)
- [ ] T022 [US4] Add a "Retention" header action (form: days, current value prefilled; confirmation text when it gets smaller; admin+) and a "last prune" subheading to ListFilterLogs

## Phase 8: Polish

- [ ] T023 Add the new routes to tests/Feature/Security/RouteAuthorizationTest.php (filter-logs index/view, filter-sessions index)
- [ ] T024 [P] `@group sqlsrv` tests: case-insensitive username on CI collation, and the `DELETE TOP` prune
- [ ] T025 Run Pint, PHPStan and the full suite; check the browser preview in EN/AR at phone width; update HANDOFF.md (004 done, phase 1 complete, scheduler note) and CLAUDE.md conventions; mark tasks done

## Dependencies

Setup → Foundational → US1 → US2. US5 needs only Setup. US3 and US4 need US1's page. Polish runs last.

## Implementation strategy

MVP = US1 + US2 + US5 (phase-1 "browse logs" plus acting on players). Then export and retention.
