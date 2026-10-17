<?php

namespace App\Filament\Widgets;

use App\Enums\Filter\CommandName;
use App\Enums\Filter\GatewayMode;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandInProgressException;
use App\Filter\Exceptions\FilterDatabaseUnavailableException;
use App\Filter\Exceptions\GatewayModeUnchangedException;
use App\Filter\FilterOverview;
use App\Filter\ModuleCard;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Module status cards with Start/Stop/Restart, All agents, All and the gateway mode switch
 * (spec 003, US1-US3). All writes go through CommandQueue.
 */
class ModuleStatusWidget extends Widget implements HasActions, HasSchemas
{
    use CanPoll;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public const ALL_AGENTS = 'all_agents';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.module-status';

    /** @var list<string>|null lower-case names of enabled modules, per request */
    private ?array $enabledTargets = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        try {
            return ['cards' => app(FilterOverview::class)->modules(), 'unavailable' => false];
        } catch (FilterDatabaseUnavailableException) {
            return ['cards' => [], 'unavailable' => true];
        }
    }

    public function startAction(): Action
    {
        return $this->powerAction(CommandName::Start, 'success');
    }

    public function stopAction(): Action
    {
        return $this->powerAction(CommandName::Stop, 'danger')
            ->requiresConfirmation()
            ->modalDescription(fn (array $arguments): string => __('panel.commands.confirm_stop', ['target' => $this->targetLabel($arguments['target'] ?? '')]));
    }

    public function restartAction(): Action
    {
        return $this->powerAction(CommandName::Restart, 'warning')
            ->requiresConfirmation()
            ->modalDescription(fn (array $arguments): string => __('panel.commands.confirm_restart', ['target' => $this->targetLabel($arguments['target'] ?? '')]));
    }

    public function gatewayModeAction(): Action
    {
        return Action::make('gatewayMode')
            ->label(fn (array $arguments): string => __('panel.gateway_modes.'.($arguments['mode'] ?? 'gm')))
            ->size(Size::Small)
            ->color(fn (array $arguments): string => ($arguments['mode'] ?? null) === GatewayMode::Public->value ? 'danger' : 'gray')
            ->requiresConfirmation()
            ->modalHeading(__('panel.module_card.gateway_mode'))
            ->modalDescription(fn (array $arguments): string => ($arguments['mode'] ?? null) === GatewayMode::Public->value
                ? __('panel.commands.confirm_public')
                : __('panel.commands.confirm_mode', ['mode' => __('panel.gateway_modes.'.($arguments['mode'] ?? 'gm'))]))
            ->visible(fn (): bool => $this->canControl('gateway'))
            ->action(function (array $arguments): void {
                $mode = GatewayMode::tryFrom((string) ($arguments['mode'] ?? ''));

                if ($mode === null) {
                    return;
                }

                $this->attempt(function () use ($mode): string {
                    app(CommandQueue::class)->setGatewayMode($mode);

                    return __('panel.commands.queued', [
                        'command' => __('panel.commands.names.set_gateway_mode').' → '.__('panel.gateway_modes.'.$mode->value),
                        'target' => $this->targetLabel('gateway'),
                    ]);
                });
            });
    }

    private function powerAction(CommandName $command, string $color): Action
    {
        return Action::make($command->value)
            ->label(__('panel.commands.names.'.$command->value))
            ->size(Size::Small)
            ->color($color)
            ->visible(fn (array $arguments): bool => $this->canControl((string) ($arguments['target'] ?? '')))
            ->action(fn (array $arguments) => $this->attempt(
                fn (): string => $this->queue($command, (string) ($arguments['target'] ?? '')),
            ));
    }

    private function queue(CommandName $command, string $target): string
    {
        $label = __('panel.commands.names.'.$command->value);

        if ($target !== self::ALL_AGENTS) {
            app(CommandQueue::class)->queue($command, $target);

            return __('panel.commands.queued', ['command' => $label, 'target' => $this->targetLabel($target)]);
        }

        $result = app(CommandQueue::class)->queueForAllAgents($command);
        $message = __('panel.commands.queued_many', [
            'command' => $label,
            'targets' => implode(', ', array_map(fn (FilterCommand $row): string => ucfirst($row->target), $result->queued)),
        ]);

        return $result->skipped === []
            ? $message
            : $message.' '.__('panel.commands.skipped', ['targets' => implode(', ', $result->skipped)]);
    }

    /**
     * @param  Closure(): string  $command
     */
    private function attempt(Closure $command): void
    {
        try {
            Notification::make()->success()->title($command())->send();
        } catch (CommandInProgressException|GatewayModeUnchangedException|FilterDatabaseUnavailableException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        } catch (InvalidArgumentException) {
            Notification::make()->danger()->title(__('panel.commands.errors.invalid_target'))->send();
        }
    }

    private function canControl(string $target): bool
    {
        if (! Gate::allows('create', FilterCommand::class)) {
            return false;
        }

        if (in_array($target, [CommandQueue::ALL, self::ALL_AGENTS], true)) {
            return true;
        }

        return in_array($target, $this->enabledTargets(), true);
    }

    /**
     * @return list<string>
     */
    private function enabledTargets(): array
    {
        return $this->enabledTargets ??= FilterModule::query()->where('enabled', true)->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->values()
            ->all();
    }

    public function targetLabel(string $target): string
    {
        return match ($target) {
            CommandQueue::ALL => __('panel.commands.targets.all'),
            self::ALL_AGENTS => __('panel.commands.targets.all_agents'),
            default => ucfirst($target),
        };
    }

    public function stateColor(ModuleCard $card): string
    {
        return match (true) {
            ! $card->enabled => 'gray',
            ! $card->online => 'danger',
            $card->state?->value === 'error' => 'danger',
            $card->state?->value === 'running' => 'success',
            default => 'warning',
        };
    }
}
