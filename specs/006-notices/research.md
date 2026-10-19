# Research: Notices and Ban Durations (006)

## R1. Notice length and characters
- **Decision**: 1–200 characters after trimming, counted with `mb_strlen`; refuse any control character
  (`\p{Cc}`, includes CR/LF/TAB/NUL).
- **Why**: AegisCore (`agent-server/service/management_notice.go`) validates the notice with `MaxRunes: 200`,
  `RequireTrimmed`, no NUL, and sends it as a one-line chat notice (type 7). A longer message would be queued
  and then fail on the filter side.
- **Alternatives**: 255 (fails on AegisCore); allowing new lines (client shows one line).

## R2. Duplicate notices
- **Decision**: refuse a notice whose message equals one that is pending or running; allow repeats once it
  finished. Checked inside a cache lock (`filter-command:notice`) and the insert transaction, like other commands.
- **Why**: stops a double click from broadcasting twice; repeat announcements stay possible.
- **Alternatives**: one notice in flight at a time (blocks two different messages in a row, rejected).

## R3. Timed bans switch
- **Decision**: `config('vfilter.timed_bans')` from `VFILTER_TIMED_BANS` (bool, default false).
  `CommandQueue::banDurations()` = `BAN_DURATIONS` when on, `[]` when off. The dialog lists those plus Permanent and
  defaults to 1 day when on (as before) and Permanent when off. `ban()` refuses durations outside `banDurations()`.
- **Why**: the user chose a config switch (2026-10-19): AegisCore fails timed bans, the legacy VFilter treats
  them as permanent; neither applies them. The panel cannot tell which filter side runs, so the operator sets it.
- **Alternatives**: detect AegisCore from `filter_status.version` (fragile); remove timed bans (loses them when
  AegisCore adds expiry).

## R4. Page shape
- **Decision**: a Filament `Page` with `InteractsWithTable` + a form (`statePath('data')`); a `send` action
  under the form, visible only when `Gate::allows('create', FilterCommand::class)`. The table queries
  `FilterCommand` where `command = notice`, newest 20, `poll('5s')`, Cancel as in `RecentCommandsWidget`.
