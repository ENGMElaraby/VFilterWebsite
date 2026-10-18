# Implementation Plan: Notices and Ban Durations

**Branch**: `006-notices` | **Date**: 2026-10-19 | **Spec**: [spec.md](spec.md)

## Summary
- `CommandQueue::notice(string $message)` queues `notice` for target `all` with payload `{"message": trimmed}`
  (contract 0.3.4). It authorises (`create` FilterCommand), validates the message (1–200 characters, one line),
  refuses a message identical to a notice still pending/running, and saves the row with its `command.queued`
  audit entry in one transaction (the existing `insert()` path).
- A Notices page (`/admin/notices`): send form for admin/owner, and a table of the 20 newest notices polled every
  5 s with Cancel while pending (`CommandQueue::cancel()`, already audited `command.cancelled`).
- `config('vfilter.timed_bans')` (env `VFILTER_TIMED_BANS`, default false). `CommandQueue::banDurations()` returns
  the allowed timed lengths (none while off). `ban()` and the Sessions ban dialog use it.

## Technical Context
**Language/Version**: PHP 8.5 · **Primary Dependencies**: Laravel 13, Filament 5
**Storage**: VFILTER `filter_commands` (existing); no migrations · **Testing**: Pest + Livewire
**Performance Goals**: status visible within 5 s (poll) · **Constraints**: AegisCore notice limit 200 runes, no NUL
**Scale/Scope**: a handful of notices per day

## Constitution Check
| Principle | Status | Notes |
| --- | --- | --- |
| I. Contract | Pass | Uses the `notice` payload from contract 0.3.4 (PR #7); the website is only stricter (200 chars, one line), so no contract change. |
| II. DB-only | Pass | Only `filter_commands`, via CommandQueue. |
| III. Security | Pass | `create`/`cancel` FilterCommand policy (admin, owner); validated server-side; audited; output escaped. |
| IV. Test-first | Pass | Queue, validation, page, authorization and ban switch tests. |
| V. Catalog settings | N/A | `timed_bans` is a deployment setting in `.env` (it follows which filter side is deployed), not a filter setting. |
| VI. Bilingual | Pass | EN/AR keys; message input `dir="auto"`. |
| VII. Simplicity | Pass | No new table; one page, one queue method, one config key. |

## Project Structure
```text
app/Filter/CommandQueue.php                     notice(), noticeError(), banDurations(), ban() check
app/Filter/Exceptions/NoticeAlreadyQueuedException.php
app/Filament/Pages/Notices.php + resources/views/filament/pages/notices.blade.php
app/Filament/Resources/FilterSessions/Tables/FilterSessionsTable.php   durations from banDurations()
config/vfilter.php, .env.example                timed_bans
lang/{en,ar}/panel.php                          notices.*, sessions.errors.timed_bans_off
tests/Feature/Notices/{NoticeQueueTest,NoticesPageTest}.php
tests/Feature/Sessions/TimedBansTest.php
tests/Feature/Security/RouteAuthorizationTest.php
```

## Complexity Tracking
None.
