<?php

namespace App\Filter;

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Enums\Filter\GatewayMode;
use App\Enums\Filter\ModuleState;
use App\Enums\Filter\ModuleType;
use App\Filter\Exceptions\FilterDatabaseUnavailableException;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use App\Models\Filter\FilterStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PDOException;

/**
 * Read side of the dashboard: module cards and the online player count, judged against the
 * database clock (contracts/command-queue.md). Never writes.
 */
class FilterOverview
{
    public const ONLINE_WINDOW_SECONDS = 15;

    public const NOT_PICKED_UP_SECONDS = 120;

    public const RECENT_COMMANDS = 20;

    private const TYPE_ORDER = [ModuleType::Gateway->value => 0, ModuleType::Download->value => 1, ModuleType::Agent->value => 2];

    public function __construct(private readonly DatabaseClock $clock) {}

    /**
     * @return list<ModuleCard>
     */
    public function modules(): array
    {
        return $this->guard(function (): array {
            $now = $this->clock->now();
            $statuses = FilterStatus::query()->toBase()->get()
                ->keyBy(fn (object $row): string => strtolower((string) $row->module));
            $pendingMode = $this->pendingGatewayMode();

            return FilterModule::query()->get()
                ->sortBy(fn (FilterModule $module): string => self::TYPE_ORDER[$module->type->value]
                    .str_pad((string) (int) preg_replace('/\D/', '', $module->name), 3, '0', STR_PAD_LEFT))
                ->map(fn (FilterModule $module): ModuleCard => $this->card(
                    $module,
                    $statuses->get(strtolower($module->name)),
                    $now,
                    $pendingMode,
                ))
                ->values()
                ->all();
        });
    }

    /**
     * Players in game = sum over online agents; null when no agent is online (FR-004).
     */
    public function onlinePlayers(): ?int
    {
        $agents = array_filter($this->modules(), fn (ModuleCard $card): bool => $card->isAgent() && $card->online);

        return $agents === [] ? null : array_sum(array_map(fn (ModuleCard $card): int => (int) $card->onlineCount, $agents));
    }

    /**
     * @return list<CommandRow>
     */
    public function recentCommands(int $limit = self::RECENT_COMMANDS): array
    {
        $cards = $this->modules();

        return $this->guard(function () use ($cards, $limit): array {
            $now = $this->clock->now();
            $online = collect($cards)->filter(fn (ModuleCard $card): bool => $card->online)->pluck('target')->all();
            $commands = FilterCommand::query()->knownValues()->latest('id')->limit($limit)->get();
            $names = User::query()->whereIn('id', $commands->pluck('created_by')->unique())->pluck('name', 'id');

            return $commands
                ->map(fn (FilterCommand $command): CommandRow => $this->row($command, $names, $online, $now))
                ->all();
        });
    }

    public static function isFresh(?CarbonImmutable $heartbeat, CarbonImmutable $now): bool
    {
        if ($heartbeat === null) {
            return false;
        }

        $age = $heartbeat->diffInMilliseconds($now, false);
        $window = self::ONLINE_WINDOW_SECONDS * 1000;

        // A heartbeat far in the future means a wrong filter clock, not a live module.
        return $age < $window && $age > -$window;
    }

    private function card(FilterModule $module, ?object $status, CarbonImmutable $now, ?GatewayMode $pendingMode): ModuleCard
    {
        $heartbeat = $this->time($status?->heartbeat_at);
        $online = self::isFresh($heartbeat, $now);
        $startedAt = $this->time($status?->started_at);
        $rawMode = $status?->gateway_mode;
        $mode = $rawMode === null ? null : GatewayMode::tryFrom((string) $rawMode);
        $isGateway = $module->type === ModuleType::Gateway;

        return new ModuleCard(
            name: $module->name,
            type: $module->type,
            target: strtolower($module->name),
            enabled: $module->enabled,
            online: $online,
            state: $online ? ModuleState::tryFrom((string) $status?->state) : null,
            connections: $online ? (int) $status?->connections : null,
            onlineCount: $online ? (int) $status?->online_count : null,
            uptimeSeconds: $online && $startedAt !== null ? (int) max(0, $startedAt->diffInSeconds($now, false)) : null,
            version: $status?->version,
            lastError: $status?->last_error,
            gatewayMode: $isGateway ? $mode : null,
            unknownGatewayMode: $isGateway && $rawMode !== null && $mode === null ? (string) $rawMode : null,
            lastHeartbeat: $heartbeat,
            pendingMode: $isGateway ? $pendingMode : null,
        );
    }

    /**
     * @param  Collection<int|string, string>  $names
     * @param  list<string>  $online  targets of online modules
     */
    private function row(FilterCommand $command, Collection $names, array $online, CarbonImmutable $now): CommandRow
    {
        $pending = $command->status === CommandStatus::Pending;
        $targetOnline = $command->target === CommandQueue::ALL ? $online !== [] : in_array($command->target, $online, true);
        $createdAt = CarbonImmutable::instance($command->created_at)->utc();

        return new CommandRow(
            id: $command->id,
            command: $command->command,
            target: $command->target,
            mode: GatewayMode::tryFrom((string) ($command->payload['mode'] ?? '')),
            requestedBy: $names->get($command->created_by) ?? '#'.$command->created_by,
            requestedAt: $createdAt,
            status: $command->status,
            processedAt: $command->processed_at === null ? null : CarbonImmutable::instance($command->processed_at)->utc(),
            result: $command->result,
            shortResult: $command->result === null ? null : Str::limit($command->result, CommandRow::RESULT_PREVIEW_LENGTH, '…'),
            waitingOffline: $pending && ! $targetOnline,
            notPickedUp: $pending && $createdAt->diffInSeconds($now, false) > self::NOT_PICKED_UP_SECONDS,
        );
    }

    private function pendingGatewayMode(): ?GatewayMode
    {
        $command = FilterCommand::query()
            ->where('command', CommandName::SetGatewayMode)
            ->whereIn('status', CommandStatus::inProgress())
            ->latest('id')
            ->first();

        return GatewayMode::tryFrom((string) ($command?->payload['mode'] ?? ''));
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value, 'UTC');
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $read
     * @return TReturn
     */
    private function guard(Closure $read): mixed
    {
        try {
            return $read();
        } catch (QueryException|PDOException $exception) {
            throw new FilterDatabaseUnavailableException($exception);
        }
    }
}
