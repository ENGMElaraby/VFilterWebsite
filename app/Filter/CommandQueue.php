<?php

namespace App\Filter;

use App\Enums\Filter\CommandGroup;
use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Enums\Filter\GatewayMode;
use App\Enums\Filter\ModuleType;
use App\Filter\Exceptions\CommandAlreadyStartedException;
use App\Filter\Exceptions\CommandInProgressException;
use App\Filter\Exceptions\GatewayModeUnchangedException;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use App\Models\Filter\FilterSession;
use App\Models\Filter\FilterStatus;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * The only way the panel instructs the filter (contracts/command-queue.md). Each command is
 * authorised, guarded against duplicates per target, and saved with its audit entry in one
 * transaction. Cancel only wins while the filter has not claimed the command.
 */
class CommandQueue
{
    public const ALL = 'all';

    public const LOCK_SECONDS = 10;

    public const LOCK_WAIT_SECONDS = 3;

    private const GATEWAY = 'gateway';

    /** Allowed ban lengths in minutes; null = permanent (contract 0.3.1). */
    public const BAN_DURATIONS = [60, 1440, 10080, 43200];

    public const REASON_MAX_LENGTH = 200;

    private const TARGET_MAX_LENGTH = 64;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DatabaseClock $clock,
    ) {}

    public function queue(CommandName $command, string $target): FilterCommand
    {
        Gate::authorize('create', FilterCommand::class);
        $this->assertPower($command);
        $this->assertTarget($target);

        return $this->locked($target, fn (): FilterCommand => $this->audit->transaction(
            fn (): FilterCommand => $this->insert($command, $target),
        ));
    }

    public function queueForAllAgents(CommandName $command): AllAgentsResult
    {
        Gate::authorize('create', FilterCommand::class);
        $this->assertPower($command);

        $agents = FilterModule::query()
            ->where('type', ModuleType::Agent)
            ->where('enabled', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        if ($agents === []) {
            throw new InvalidArgumentException('No enabled agents.');
        }

        // Same order as queue(): hold the per-target locks until the transaction has committed.
        $locks = [];
        $skipped = [];

        foreach ($agents as $name) {
            $lock = Cache::lock('filter-command:'.strtolower($name), self::LOCK_SECONDS);

            try {
                $lock->block(self::LOCK_WAIT_SECONDS);
                $locks[$name] = $lock;
            } catch (LockTimeoutException) {
                $skipped[] = $name;
            }
        }

        try {
            $result = $this->audit->transaction(function () use ($locks, $command, $skipped): AllAgentsResult {
                $queued = [];

                foreach (array_keys($locks) as $name) {
                    try {
                        $queued[] = $this->insert($command, strtolower($name));
                    } catch (CommandInProgressException) {
                        $skipped[] = $name;
                    }
                }

                sort($skipped);

                return new AllAgentsResult($queued, $skipped);
            });
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
        }

        if ($result->queued === []) {
            throw new CommandInProgressException(__('panel.commands.targets.all_agents'), CommandGroup::Power);
        }

        return $result;
    }

    public function setGatewayMode(GatewayMode $mode): FilterCommand
    {
        Gate::authorize('create', FilterCommand::class);
        $this->assertTarget(self::GATEWAY);

        return $this->locked(self::GATEWAY, fn (): FilterCommand => $this->audit->transaction(function () use ($mode): FilterCommand {
            if ($this->onlineGatewayMode() === $mode && ! $this->inProgress(CommandGroup::GatewayMode, self::GATEWAY)) {
                throw new GatewayModeUnchangedException($mode);
            }

            return $this->insert(CommandName::SetGatewayMode, self::GATEWAY, ['mode' => $mode->value]);
        }));
    }

    /**
     * Disconnect one session: target = character, else username, else IP (contract 0.3.1).
     */
    public function kick(FilterSession $session, ?string $reason = null): FilterCommand
    {
        Gate::authorize('create', FilterCommand::class);

        $target = $session->charname ?: ($session->username ?: $session->ip);
        $reason = $reason === null || trim($reason) === '' ? null : $this->assertReason($reason);

        return $this->queuePlayer(CommandName::Kick, $target, [
            'session_id' => $session->id,
            'ip' => $session->ip,
            'reason' => $reason,
        ]);
    }

    /**
     * Ban the session's game account for a fixed time (null = permanent) and disconnect it.
     */
    public function ban(FilterSession $session, string $reason, ?int $durationMinutes): FilterCommand
    {
        Gate::authorize('create', FilterCommand::class);

        if (blank($session->username)) {
            throw new InvalidArgumentException('Only a logged-in account can be banned.');
        }

        if ($durationMinutes !== null && ! in_array($durationMinutes, self::BAN_DURATIONS, true)) {
            throw new InvalidArgumentException("Ban duration {$durationMinutes} is not allowed.");
        }

        return $this->queuePlayer(CommandName::Ban, (string) $session->username, [
            'session_id' => $session->id,
            'reason' => $this->assertReason($reason),
            'duration_minutes' => $durationMinutes,
        ]);
    }

    public function cancel(FilterCommand $command): FilterCommand
    {
        Gate::authorize('cancel', $command);

        return $this->audit->transaction(function () use ($command): FilterCommand {
            $changed = FilterCommand::query()
                ->whereKey($command->getKey())
                ->where('status', CommandStatus::Pending->value)
                ->toBase()
                ->update([
                    'status' => CommandStatus::Cancelled->value,
                    'result' => __('panel.commands.cancelled_by', ['user' => $this->actor()->email], 'en'),
                    'processed_at' => $this->clock->now(),
                ]);

            if ($changed !== 1) {
                throw new CommandAlreadyStartedException;
            }

            $command->refresh();
            $this->audit->log('command.cancelled', $command, ['status' => CommandStatus::Pending->value], ['status' => CommandStatus::Cancelled->value]);

            return $command;
        });
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function insert(CommandName $command, string $target, ?array $payload = null): FilterCommand
    {
        $group = CommandGroup::for($command);

        if ($group !== null && $this->inProgress($group, $target)) {
            throw new CommandInProgressException($target, $group);
        }

        $row = FilterCommand::query()->create([
            'command' => $command,
            'target' => $target,
            'payload' => $payload,
            'status' => CommandStatus::Pending,
            'created_by' => $this->actor()->getKey(),
            'created_at' => $this->clock->now(),
        ]);

        $this->audit->log('command.queued', $row, [], ['command' => $command->value, 'target' => $target, 'payload' => $payload]);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function queuePlayer(CommandName $command, string $target, array $payload): FilterCommand
    {
        if ($target === '' || mb_strlen($target) > self::TARGET_MAX_LENGTH) {
            throw new InvalidArgumentException('Player target must be 1-'.self::TARGET_MAX_LENGTH.' characters.');
        }

        return $this->locked($target, fn (): FilterCommand => $this->audit->transaction(
            fn (): FilterCommand => $this->insert($command, $target, $payload),
        ));
    }

    private function assertReason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw new InvalidArgumentException('Reason must be 1-'.self::REASON_MAX_LENGTH.' characters.');
        }

        return $reason;
    }

    private function inProgress(CommandGroup $group, string $target): bool
    {
        return FilterCommand::query()
            ->where('target', $target)
            ->whereIn('command', $group->commands())
            ->whereIn('status', CommandStatus::inProgress())
            ->exists();
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function locked(string $target, Closure $callback): mixed
    {
        try {
            return Cache::lock('filter-command:'.$target, self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, $callback);
        } catch (LockTimeoutException) {
            throw new CommandInProgressException($target, CommandGroup::Power);
        }
    }

    private function assertPower(CommandName $command): void
    {
        if (CommandGroup::for($command) !== CommandGroup::Power) {
            throw new InvalidArgumentException("Command {$command->value} is not queued from the dashboard.");
        }
    }

    private function assertTarget(string $target): void
    {
        if ($target === self::ALL) {
            return;
        }

        $enabled = FilterModule::query()->where('enabled', true)->pluck('name')
            ->map(fn (string $name): string => strtolower($name));

        if (! $enabled->contains($target)) {
            throw new InvalidArgumentException("Unknown or disabled target [{$target}].");
        }
    }

    /**
     * The mode the gateway reports, or null while it is offline (a stale mode must not block a change).
     */
    private function onlineGatewayMode(): ?GatewayMode
    {
        $status = FilterStatus::query()->whereRaw('LOWER(module) = ?', [self::GATEWAY])->toBase()->first(['gateway_mode', 'heartbeat_at']);
        $heartbeat = $status?->heartbeat_at === null ? null : CarbonImmutable::parse((string) $status->heartbeat_at, 'UTC');

        return FilterOverview::isFresh($heartbeat, $this->clock->now())
            ? GatewayMode::tryFrom((string) $status?->gateway_mode)
            : null;
    }

    private function actor(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new InvalidArgumentException('Commands need a signed-in panel user.');
        }

        return $user;
    }
}
