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
use App\Models\Filter\FilterStatus;
use App\Models\User;
use App\Services\AuditLogger;
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

        $result = $this->audit->transaction(function () use ($agents, $command): AllAgentsResult {
            $queued = [];
            $skipped = [];

            foreach ($agents as $name) {
                try {
                    $queued[] = $this->locked(strtolower($name), fn (): FilterCommand => $this->insert($command, strtolower($name)));
                } catch (CommandInProgressException) {
                    $skipped[] = $name;
                }
            }

            return new AllAgentsResult($queued, $skipped);
        });

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
            if ($this->reportedGatewayMode() === $mode && ! $this->inProgress(CommandGroup::GatewayMode, self::GATEWAY)) {
                throw new GatewayModeUnchangedException($mode);
            }

            return $this->insert(CommandName::SetGatewayMode, self::GATEWAY, ['mode' => $mode->value]);
        }));
    }

    public function cancel(FilterCommand $command): FilterCommand
    {
        Gate::authorize('create', FilterCommand::class);

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

    private function reportedGatewayMode(): ?GatewayMode
    {
        $mode = FilterStatus::query()->whereRaw('LOWER(module) = ?', [self::GATEWAY])->toBase()->value('gateway_mode');

        return GatewayMode::tryFrom((string) $mode);
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
