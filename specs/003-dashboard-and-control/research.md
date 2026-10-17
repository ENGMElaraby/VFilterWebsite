# Research: Dashboard and Filter Control (003)

No open NEEDS CLARIFICATION remained after `/speckit-clarify`. These are the design decisions and
the facts they rest on.

## R1. Live refresh

- **Decision**: Filament widgets with `$pollingInterval = '5s'` (Livewire `wire:poll`). The status,
  player count and recent commands widgets poll; the health check does not (it runs on demand).
- **Rationale**: FR-005/SC-002 need ≤ 5 s. Polling is built into Filament widgets, needs no extra
  service, and Livewire skips polls while the tab is hidden, so a page left open for hours stays cheap.
- **Alternatives**: Laravel Reverb / websockets (rejected: an extra long-running process on the SRO
  box for 3 small queries every 5 s; YAGNI). SSE (no first-party Filament support).

## R2. "Online" judged by the database clock

- **Decision**: `App\Filter\DatabaseClock::now()` returns the database server's current UTC time:
  `SELECT SYSUTCDATETIME()` on SQL Server. On SQLite (tests, local preview on one machine) it returns
  `now('UTC')`, so tests can freeze time with `Carbon::setTestNow`. One query per poll, shared by
  every card. `ModuleHealth` = online when `heartbeat_at > dbNow − 15 s`.
- **Rationale**: The edge case "clock skew must not flip badges". The filter writes `heartbeat_at`
  using its own clock on the SRO box, which is usually the DB box, so DB time is the closest reference.
- **Alternatives**: Compare in SQL (`WHERE heartbeat_at > DATEADD(...)`): correct, but it makes every
  status query driver-specific. Web server clock: wrong under skew.

## R3. Duplicate guard under simultaneous clicks (FR-011, SC-005)

- **Decision**: `CommandQueue` takes an atomic cache lock per target
  (`Cache::lock("filter-command:{target}", 10)->block(3)`). Inside the lock and an
  `AuditLogger::transaction`, it checks for a pending/running command of the same group for that target,
  then inserts the command and its audit entry.
  The groups are `power` (start/stop/restart) and `gateway_mode`.
- **Rationale**: The production cache store is `database` and tests use `array`. Both support atomic
  locks, so it works the same on SQL Server and SQLite. A plain "check then insert" in a transaction
  is not enough on SQL Server: under READ COMMITTED two requests can both see "none" and both insert.
- **Alternatives**: SQL Server `sp_getapplock`, which is driver-specific and untestable on SQLite.
  A filtered unique index on `(target) WHERE status IN (...)` is a schema change that the filter
  would also have to respect, and it would not cover the command group. `UPDLOCK, HOLDLOCK`
  range locks are driver-specific and lock more than needed.

## R4. Cancel only while pending (FR-014a, SC-004a)

- **Decision**: one conditional update:
  `UPDATE filter_commands SET status='cancelled', result=?, processed_at=? WHERE id=? AND status='pending'`.
  If 1 row is affected, the cancel is audited in the same transaction. If 0 rows are affected,
  `CommandAlreadyStartedException` is thrown, the transaction rolls back and nothing is audited.
- **Rationale**: Single-statement atomicity. The filter claims commands with the mirror statement
  (`SET status='running' WHERE id=? AND status='pending'`), so a claim and a cancel cannot both win.
  That claim rule goes into contract 0.3.0.
- **Contract**: `status` is already `nvarchar(16)` with no check constraint, so `cancelled` needs no
  migration. Only the contract text, enum and docs change (MINOR 0.3.0, both repos).

## R5. Health check timeouts (FR-023, SC-007)

- **Decision**: `ConnectionHealthCheck` runs `SELECT 1` per connection with a 5 s budget. On SQL Server
  this uses the connector's `login_timeout` (→ `LoginTimeout` in the DSN) plus
  `PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 5` on dedicated `*_health` connection copies. It purges each
  connection after use. Errors become a short, credential-free reason: `timeout`, `login failed`,
  `unreachable`, `database not found`, or `error` + SQLSTATE. The raw message is never shown.
  For VFILTER, it also lists missing contract tables via `Schema::hasTable`.
- **Rationale**: Laravel's `SqlServerConnector` maps `login_timeout`, and PDO_SQLSRV supports the
  query timeout attribute. Copying the connection config avoids changing timeouts for normal traffic.
- **Game connections**: `account`, `shard` and `log` are new **optional** connections in
  `config/database.php`. They are configured only when `DB_ACCOUNT_DATABASE` / `DB_SHARD_DATABASE` /
  `DB_LOG_DATABASE` is set. Host and credentials default to the VFILTER ones (`DB_GAME_HOST`, … can
  override them). When the database name is unset, the result is "Not configured" (FR-022).
  CLAUDE.md already names these connections. 003 is the first feature to define them, read-only.

## R6. Page structure in Filament 5

- **Decision**:
  - `App\Filament\Pages\Dashboard` extends `Filament\Pages\Dashboard` and replaces the default
    dashboard. Its widgets are `OnlinePlayersWidget` (stats), `ModuleStatusWidget` (a custom
    widget with a Blade card grid plus Filament Actions for Start/Stop/Restart/mode/All agents) and
    `RecentCommandsWidget` (a table widget with a Cancel row action). `AccountWidget` is dropped
    from the dashboard.
  - `FilterModuleResource` provides the list and edit pages. "Add agent" is a header action, and
    "Remove" appears on the last agent only. There is no generic create page, because types are fixed.
  - `ConnectionHealth` page (Dashboard nav group) with a "Run check" action.
- **Rationale**: These are first-party Filament building blocks. Actions give confirmation modals
  (FR-008/009) and per-user `visible()`/`authorize()` for free.

## R7. Authorization

- **Decision**: `FilterCommandPolicy` (`viewAny` = any user; `create`/`cancel` = admin+) and
  `FilterModulePolicy` (`viewAny`/`view` = any; `update`/`create`/`delete` = admin+). `CommandQueue`
  also checks `Gate::authorize`, so a crafted Livewire call is refused server-side (FR-013).
- **Masking**: a viewer sees `module_ip`/`module_port` as `••••` (FR-020). The real values are never
  rendered into their HTML.

## R8. Target names

- **Decision**: target = `all` or `strtolower(module name)` (`gateway`, `download`, `agent1`…).
  "All agents" = one command per enabled agent (clarified). Contract 0.3.0 states `download`
  explicitly (wording, folded into the MINOR bump).
