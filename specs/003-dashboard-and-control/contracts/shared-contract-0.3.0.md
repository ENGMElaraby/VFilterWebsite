# Shared contract 0.2.0 → 0.3.0 (MINOR)

Apply the same edit to `docs/shared-contract.md` in **VFilterWebsite and VFilter** in one change set.

## 1. Header
`**Version:** 0.3.0 (draft)`

## 2. Rules, item 2: replace with
> 2. The filter never alters schema. It only reads config tables and writes to `filter_status`,
>    `filter_logs`, `filter_sessions`, and `filter_commands.status/result/processed_at`. The website
>    writes new `filter_commands` rows, and may change a command's `status` only from `pending` to
>    `cancelled` (with `result` and `processed_at`).

## 3. filter_commands: replace the `target` and `status` rows and the note under the table
| target | nvarchar(64) | no | | `all`, `gateway`, `download`, `agent1`…`agent5` (lower-case module name), or username/charname |
| status | nvarchar(16) | no | `pending` | `pending` → `running` → `done` \| `failed`; or `pending` → `cancelled` (website) |

Add below the table:
> **Claiming and cancelling.** The filter claims a command with a conditional update
> (`UPDATE … SET status='running' WHERE id=@id AND status='pending'`) and runs it only if one row
> changed. The website cancels with the mirror statement (`… SET status='cancelled' … AND
> status='pending'`). So a command is either run or cancelled, never both. The filter ignores
> `cancelled` rows. The website queues at most one pending/running `start`/`stop`/`restart` per target and
> one `set_gateway_mode` at a time. `set_gateway_mode` payload: `{"mode":"gm"|"inspection"|"public"}`.

## 4. Changelog: add on top
- 0.3.0 (2026-10-17, MINOR): `filter_commands.status` gains `cancelled` (website, only from
  `pending`); the claim/cancel rule; `download` listed as a target; `set_gateway_mode` payload shape
  (feature 003).
