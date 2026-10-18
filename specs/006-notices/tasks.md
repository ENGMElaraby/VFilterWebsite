---
description: "Tasks for 006 Notices and Ban Durations"
---

# Tasks: Notices and Ban Durations

**Input**: `specs/006-notices/` · **Tests**: required (constitution IV), written first.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

- [X] T001 [P] Add `timed_bans` to config/vfilter.php (`(bool) env('VFILTER_TIMED_BANS', false)`) and `VFILTER_TIMED_BANS=false` to .env.example
- [X] T002 [P] Add EN/AR keys to lang/{en,ar}/panel.php: `navigation`-free `notices.*` (title, message, help, send, queued, list heading, errors.required/too_long/one_line/duplicate) and `sessions.errors.timed_bans_off`

---

## Phase 2: US1 – Send a notice (P1) 🎯 MVP

- [X] T003 [P] [US1] tests/Feature/Notices/NoticeQueueTest.php:
  - Admin and owner queue `notice` / `all` / `{"message": trimmed}`, status pending, with a `command.queued` audit entry.
  - Viewer is refused (`AuthorizationException`) and nothing is queued.
  - Empty, whitespace-only, 201 characters, line break, tab → `InvalidArgumentException`, nothing queued.
  - 200 Arabic letters are accepted.
  - The same message while pending/running → `NoticeAlreadyQueuedException`; after done → allowed; a different message → allowed.
- [X] T004 [US1] Add `NOTICE_MAX_LENGTH`, `noticeError()`, `notice()` to app/Filter/CommandQueue.php and app/Filter/Exceptions/NoticeAlreadyQueuedException.php
- [X] T005 [P] [US1] tests/Feature/Notices/NoticesPageTest.php:
  - Every role opens the page; it lists only notices, newest first, at most 20, and polls.
  - Admin sends through the form (trimmed payload); invalid input shows a form error and queues nothing.
  - Viewer sees no form/send action and no Cancel; calling the send action is refused.
  - Admin cancels a pending notice (status cancelled, audit `command.cancelled`); a running one shows "already started".
  - A message with HTML is shown escaped.
- [X] T006 [US1] Create app/Filament/Pages/Notices.php + resources/views/filament/pages/notices.blade.php (form, send action, table, Cancel)
- [X] T007 [US1] Add `filament.admin.pages.notices` (all roles ok) to tests/Feature/Security/RouteAuthorizationTest.php

---

## Phase 3: US2 – Ban lengths follow the switch (P2)

- [X] T008 [P] [US2] tests/Feature/Sessions/TimedBansTest.php:
  - Off: `banDurations()` is empty; `ban(..., 1440)` is refused; permanent works; the dialog offers only Permanent, selected.
  - On: the four durations are offered (default 1 day) and accepted.
- [X] T009 [US2] Add `banDurations()` and use it in `CommandQueue::ban()`; build the dialog options/default from it in app/Filament/Resources/FilterSessions/Tables/FilterSessionsTable.php
- [X] T010 [US2] Turn timed bans on in existing timed-ban tests (tests/Feature/Sessions/KickBanTest.php) where they rely on timed durations

---

## Phase 4: Polish

- [X] T011 Update HANDOFF.md (006 done; settings-catalog → 007, area-rules → 008; VFILTER_TIMED_BANS) and the dashboard docs if needed
- [X] T012 Run `php artisan test`, `./vendor/bin/pint`, `./vendor/bin/phpstan`; fix all
