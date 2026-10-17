# Contract: CommandQueue and dashboard services (003)

The PHP API used by the dashboard widgets. Tests target these, and pages call nothing else to write.

## App\Filter\CommandQueue

```php
queue(CommandName $command, string $target, ?array $payload = null): FilterCommand
queueForAllAgents(CommandName $command): AllAgentsResult   // ->queued: list<FilterCommand>, ->skipped: list<string>
setGatewayMode(GatewayMode $mode): FilterCommand
cancel(FilterCommand $command): FilterCommand
```

| rule | behaviour |
| --- | --- |
| auth | `Gate::authorize('create', FilterCommand::class)` / `('cancel', $command)`, so a viewer gets `AuthorizationException` |
| targets | `queue` accepts only `start`/`stop`/`restart`, with target `all` or an **enabled** configured module's lower-case name. Anything else throws `InvalidArgumentException` |
| duplicate | the same group already pending/running for the target throws `CommandInProgressException` (target, group) |
| mode no-op | `setGatewayMode(m)` while the gateway reports `m` and nothing is pending throws `GatewayModeUnchangedException` |
| atomicity | insert + `command.queued` audit run in one `AuditLogger::transaction`, inside a `Cache::lock("filter-command:{target}")` (3 s wait). Lock timeout throws `CommandInProgressException` |
| all agents | one transaction. Each enabled agent with no power command in progress gets a command; the rest are listed in `skipped`. Throws `CommandInProgressException` only if every agent is skipped |
| cancel | conditional update `WHERE status='pending'`. 0 rows throws `CommandAlreadyStartedException` and nothing is audited |

## App\Filter\DatabaseClock
`now(): CarbonImmutable` returns the DB server's UTC time (SQL Server `SYSUTCDATETIME()`), or app time on SQLite.

## App\Filter\FilterOverview
`modules(): list<ModuleCard>`, `onlinePlayers(): ?int`, `recentCommands(int $limit = 20): list<CommandRow>`.
It is read-only and reads the clock once per call. When VFILTER is unreachable it throws
`FilterDatabaseUnavailableException`, and widgets show the banner (FR-006).

## App\Filter\ConnectionHealthCheck
`run(): array<string, HealthResult>` with keys `filter`, `account`, `shard`, `log`. It runs no writes,
takes at most 5 s per connection and never puts a credential in the result.

## UI contract (pages)

| page / widget | route | viewer | admin / owner |
| --- | --- | --- | --- |
| Dashboard: ModuleStatusWidget, OnlinePlayersWidget, RecentCommandsWidget (poll 5 s) | `/admin` | read | + Start/Stop/Restart per module, All agents, All, gateway mode, Cancel |
| Modules (FilterModuleResource) list + edit | `/admin/filter-modules`, `/admin/filter-modules/{id}/edit` | list, real addresses masked, no edit route (403) | edit, Add agent, Remove last agent, "Restart now" after an address change |
| Connection health | `/admin/connection-health` | Run check | Run check |

Confirmation modals: Stop and Restart (they name the target and warn that players are disconnected),
gateway mode (Public adds an "everyone can log in" warning), All agents, and Cancel. Start needs no
confirmation.

Every new route is added to `tests/Feature/Security/RouteAuthorizationTest.php`.
