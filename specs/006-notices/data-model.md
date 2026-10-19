# Data Model: Notices and Ban Durations (006)

No new tables or migrations.

## filter_commands row for a notice (contract 0.3.4)
| Field | Value |
| --- | --- |
| command | `notice` |
| target | `all` |
| payload | `{"message": "<trimmed, 1–200 chars, no control chars>"}` |
| status | `pending` → `running` → `done`/`failed` (filter), or `pending` → `cancelled` (website) |
| result | filter's text, e.g. `notice sent to 42 player(s)`, `failed: not supported yet`, `expired` |
| created_by / created_at | panel user / DB clock |

## Config
`vfilter.timed_bans` (bool, env `VFILTER_TIMED_BANS`, default `false`).

## Audit
`command.queued` (new: `command`, `target`, `payload`) and `command.cancelled` — existing action ids.
