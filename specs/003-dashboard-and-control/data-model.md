# Data Model: Dashboard and Filter Control (003)

No new tables and no migrations. The tables come from feature 002 (`docs/shared-contract.md`). This
feature adds one enum value, a few read models (value objects, not stored), and new audit actions.

## Changed: CommandStatus (contract 0.3.0)

```
pending ──filter claims (WHERE status='pending')──► running ──► done | failed
   │
   └──panel cancels (WHERE status='pending')──► cancelled   (terminal; filter never runs it)
```

| value | written by | terminal |
| --- | --- | --- |
| `pending` | panel (insert) | no |
| `running` | filter | no |
| `done`, `failed` | filter | yes |
| `cancelled` | panel, only from `pending` | yes |

When it cancels, the panel also sets `processed_at` = now (UTC) and `result` = `Cancelled by <email>`.

## Command groups (duplicate guard)

| group | commands | rule |
| --- | --- | --- |
| `power` | `start`, `stop`, `restart` | at most one pending/running per target |
| `gateway_mode` | `set_gateway_mode` | at most one pending/running (target always `gateway`) |

`all` is its own target. An `all` command doesn't block `agent1`, and the filter serialises them.

## filter_commands rows written by this feature

| command | target | payload |
| --- | --- | --- |
| `start` / `stop` / `restart` | `all`, `gateway`, `download`, `agent1`…`agent5` | null |
| `set_gateway_mode` | `gateway` | `{"mode": "gm" \| "inspection" \| "public"}` |

`created_by` = panel user id, `created_at` = now (UTC), `status` = `pending`.

## Read models (in `app/Filter/`, not stored)

### ModuleCard
Built per configured module, joined with its `filter_status` row (matched case-insensitively on the
module name).

| field | from | rule |
| --- | --- | --- |
| name, type, enabled | filter_modules | |
| target | name | lower-case |
| online | heartbeat_at | `heartbeat_at !== null && heartbeat_at > dbNow − 15 s` |
| state | status.state | shown only when online; else `offline` |
| connections, onlineCount | status | `null` (unknown) when offline |
| uptime | status.started_at | `dbNow − started_at`, when online |
| version, lastError | status | |
| gatewayMode | status.gateway_mode | gateway only; unknown string → "Unknown (x)" |
| lastHeartbeat | status.heartbeat_at | `null` → "never" |
| pendingMode | latest pending/running `set_gateway_mode` | gateway only |

Order: Gateway, Download, Agent1…AgentN. Status rows with no configured module are ignored.

### OnlinePlayers
`sum(onlineCount)` over agents that are online, or `null` (shown as "—") when none are online.

### CommandRow (recent commands list, 20 newest by `id desc`)
command, target, payload mode, requested by (user name, or "#id" if the user was deleted), created_at,
status, processed_at, result (escaped, cut to 200 chars, full text in a modal). Flags:
- `waitingOffline`: status pending and the target is offline. For `all`, that means no module is online.
- `notPickedUp`: status pending and `dbNow − created_at > 120 s`.

### HealthResult (per connection: `filter`, `account`, `shard`, `log`)
| field | values |
| --- | --- |
| state | `ok` \| `unreachable` \| `not_configured` |
| millis | int, when it was attempted |
| reason | `timeout` \| `login_failed` \| `unreachable` \| `database_not_found` \| `error:<SQLSTATE>` |
| missingTables | `filter` only: list of contract tables not found |

## Module validation (on top of 002's ModuleRules)

- New in `ModuleRules`: no two modules may share the same `(guard_ip, guard_port)` (FR-017).
- Add agent: name = `Agent{n+1}`, type `agent`. The defaults are copied from the last agent:
  `module_port` + 1, `guard_port` + 1, `enabled` = false. The admin must review it and enable it.
- Remove: only the highest-numbered agent, and only when more than 1 agent exists. ModuleRules
  enforces this again on commit.

## Audit actions (added to the 001 catalog, labels in `lang/{en,ar}/audit.php`)

| action | target | old → new |
| --- | --- | --- |
| `command.queued` | filter_command | – → {command, target, payload} |
| `command.cancelled` | filter_command | {status: pending} → {status: cancelled} |
| `modules.created` / `modules.updated` / `modules.deleted` | filter_module | already emitted by ConfigStore (002) |
