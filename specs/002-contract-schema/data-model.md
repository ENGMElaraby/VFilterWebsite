# Data Model: 002 Contract Schema

Exact definitions created by this feature (SQL Server types; SQLite equivalents in tests).
All `datetime2(3)` values are UTC. `nvarchar(max)` = Laravel `text`. No foreign keys.

## filter_modules  (area `modules`)
| column | type | null | default | notes |
| --- | --- | --- | --- | --- |
| id | int identity PK | no | | |
| type | nvarchar(16) | no | | `gateway` \| `agent` \| `download` · index |
| name | nvarchar(32) | no | | unique · `Gateway`, `Download`, `Agent1`..`Agent5` |
| module_ip | nvarchar(64) | no | | real address, never shown to players |
| module_port | int | no | | 1–65535 |
| guard_ip | nvarchar(64) | no | | public address |
| guard_port | int | no | | 1–65535 |
| enabled | bit | no | 0 | |

## filter_settings  (area `settings`)
| column | type | null | default | notes |
| --- | --- | --- | --- | --- |
| key | nvarchar(128) PK | no | | dotted, e.g. `logs.retention_days` |
| value | nvarchar(max) | no | | string form; `bool` stored `1`/`0` |
| value_type | nvarchar(16) | no | | `bool` \| `int` \| `string` \| `json` \| `color` |
| group | nvarchar(32) | no | | tab name · index |
| requires_restart | nvarchar(16) | no | `none` | `none` \| `filter` \| `gameserver` \| `client` |
| updated_at | datetime2(3) | yes | | |
| updated_by | int | yes | | panel user id (no FK) |

## filter_lists  (area `lists`)
| column | type | null | notes |
| --- | --- | --- | --- |
| id | bigint identity PK | no | |
| list | nvarchar(32) | no | `allowed_ips`, `blocked_hwids`, … |
| value | nvarchar(256) | no | |
| comment | nvarchar(256) | yes | |
Indexes: unique `(list, value)`.

## filter_area_rules  (area `area_rules`)
| column | type | null | default |
| --- | --- | --- | --- |
| id | bigint identity PK | no | |
| rule | nvarchar(48) | no | |
| area | nvarchar(64) | no | | Region/World/ContinentName/`-1` |
| target | nvarchar(128) | yes | |
| params | nvarchar(max) | yes | | JSON |
| enabled | bit | no | 1 |
Indexes: `(rule, enabled)`, `(area)`.

## filter_cooldowns  (area `cooldowns`)
`action nvarchar(32) PK`, `seconds int not null default 0` (0–32000). Actions: `reverse`, `reverse_job`,
`global`, `exit`, `exchange`, `stall`, `ride_pet`, `grab_pet`, `all_chat`, `all_chat_safe`, `teleport`,
`teleport_job`, `berserk_job`, `berserk_fw`, `res_scroll`, `res_scroll_job`, `friend_request`.

## filter_ga_permissions  (area `ga_permissions`)
`username nvarchar(32) PK` + `bit not null default 0` for each of: `make_item`, `zoe`, `load_monster`,
`mobkill0`, `mobkill1`, `notice`, `filter_cmd`, `invisible`, `invincible`, `ban`, `recall_user`,
`recall_guild`, `move_to_user`, `move_to_npc`, `to_town`, `go_town`, `reset_q`, `wp`, `gm_skill`,
`world_status`.

## filter_commands  (website writes new rows; filter writes status/result/processed_at)
| column | type | null | default |
| --- | --- | --- | --- |
| id | bigint identity PK | no | |
| command | nvarchar(32) | no | | `start`, `stop`, `restart`, `set_gateway_mode`, `reload`, `kick`, `notice`, `ban` |
| target | nvarchar(64) | no | | `all`, `gateway`, `agent1`…, or username/charname |
| payload | nvarchar(max) | yes | | JSON |
| status | nvarchar(16) | no | `pending` | `pending` → `running` → `done` \| `failed` |
| result | nvarchar(max) | yes | |
| created_by | int | no | | panel user id |
| created_at | datetime2(3) | no | |
| processed_at | datetime2(3) | yes | |
Indexes: `(status, created_at)`. (`target` widened 32 → 64 so character names fit.)

## filter_status  (filter writes)
| column | type | null | default |
| --- | --- | --- | --- |
| module | nvarchar(32) PK | no | |
| state | nvarchar(16) | no | `stopped` | `stopped`/`starting`/`running`/`error` |
| online_count | int | no | 0 |
| connections | int | no | 0 |
| gateway_mode | nvarchar(16) | yes | | `gm`/`inspection`/`public` (gateway only) |
| version | nvarchar(32) | yes | | filter build version |
| started_at | datetime2(3) | yes | |
| heartbeat_at | datetime2(3) | yes | | > 15 s old = offline |
| last_error | nvarchar(max) | yes | |

## filter_sessions  (filter writes)
| column | type | null |
| --- | --- | --- |
| id | bigint identity PK | no |
| module | nvarchar(32) | no |
| ip | nvarchar(45) | no |
| hwid | nvarchar(64) | yes |
| username | nvarchar(32) | yes |
| charname | nvarchar(64) | yes |
| connected_at | datetime2(3) | no |
| last_packet_at | datetime2(3) | yes |
Indexes: `(module)`, `(username)`, `(ip)`.

## filter_logs  (filter writes)
| column | type | null |
| --- | --- | --- |
| id | bigint identity PK | no |
| ts | datetime2(3) | no |
| level | nvarchar(8) | no |
| module | nvarchar(32) | no |
| category | nvarchar(32) | no |
| ip | nvarchar(45) | yes |
| hwid | nvarchar(64) | yes |
| username | nvarchar(32) | yes |
| charname | nvarchar(64) | yes |
| opcode | nvarchar(8) | yes |
| message | nvarchar(1024) | no |
| data | nvarchar(max) | yes |
Indexes: `(ts)`, `(category, ts)`, `(username)`, `(ip)`.

## filter_config_version  (one row per area — clarified)
`area nvarchar(16) PK` (`settings`, `lists`, `area_rules`, `cooldowns`, `modules`, `ga_permissions`),
`version bigint not null default 0`, `updated_at datetime2(3) null`.
