# Data Model: Logs and Live Sessions (004)

There are no new tables. 004 reads `filter_logs` and `filter_sessions`, deletes old `filter_logs` rows,
writes `filter_commands` (kick/ban) and `filter_settings.logs.retention_days`.

## LogFilters (value object, `App\Logs\LogFilters`)
| field | type | rule |
| --- | --- | --- |
| from, until | CarbonImmutable (UTC) | required. Default until = now and from = now − 24 h. Refused if from > until |
| levels | list<LogLevel> | optional, IN |
| categories | list<LogCategory> | optional, IN |
| module | ?string | exact |
| ip | ?string | exact, max 45 |
| username | ?string | exact, case-insensitive, max 32 |
| charname | ?string | exact, case-insensitive, max 64 |
| opcode | ?string | exact, upper-cased, max 8 |

`LogQuery::apply(Builder, LogFilters)` is the one place that builds the WHERE. The table, Live and
export all use it.

## Kick / ban commands (contract 0.3.1)
| command | target | payload |
| --- | --- | --- |
| `kick` | charname, else username, else ip | `{"session_id":int,"ip":string,"reason":string\|null}` |
| `ban` | username (required) | `{"session_id":int,"reason":string (1–200),"duration_minutes":60\|1440\|10080\|43200\|null}` |

`CommandGroup::Player` = {kick, ban}: at most one pending/running per target.

## Retention
`logs.retention_days`: int, 1–3650, default 30 (002). The prune cutoff is the DB clock minus that
many days. Batch size is 5000 and the time budget is 120 s.

## Audit actions (added)
| action | actor | target | values |
| --- | --- | --- | --- |
| `logs.exported` | user | – | new: {filters, rows, truncated} |
| `logs.pruned` | system | – | new: {cutoff, deleted, complete} |
| `command.queued` (kick/ban) | user | filter_command | as in 003 |
| `setting.updated` (retention) | user | filter_setting | as in 002 |
