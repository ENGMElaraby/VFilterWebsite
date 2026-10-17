@php
    use App\Filter\CommandQueue;
    use App\Filament\Widgets\ModuleStatusWidget;
    use App\Enums\Filter\GatewayMode;
    use Carbon\CarbonInterval;

    $zone = (string) config('vfilter.display_timezone');
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget>
    <div @if ($pollingInterval) wire:poll.{{ $pollingInterval }} @endif>
        @if ($unavailable)
            <x-filament::section>
                <div role="alert" style="color: var(--danger-600); font-weight: 600;">
                    {{ __('panel.dashboard.database_unavailable') }}
                </div>
            </x-filament::section>
        @else
            <x-filament::section :heading="__('panel.dashboard.modules')">
                <x-slot name="afterHeader">
                    <div style="display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;">
                        @foreach ([ModuleStatusWidget::ALL_AGENTS, CommandQueue::ALL] as $group)
                            <span style="font-size: .875rem; opacity: .75;">
                                {{ $group === CommandQueue::ALL ? __('panel.dashboard.all') : __('panel.dashboard.all_agents') }}:
                            </span>
                            {{ ($this->startAction)(['target' => $group]) }}
                            {{ ($this->stopAction)(['target' => $group]) }}
                            {{ ($this->restartAction)(['target' => $group]) }}
                        @endforeach
                    </div>
                </x-slot>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: 1rem;">
                    @foreach ($cards as $card)
                        <x-filament::section compact wire:key="module-{{ $card->target }}" :style="$card->enabled ? null : 'opacity: .6'">
                            <x-slot name="heading">{{ $card->name }}</x-slot>
                            <x-slot name="afterHeader">
                                <x-filament::badge :color="$this->stateColor($card)">
                                    @if (! $card->enabled)
                                        {{ __('panel.module_card.disabled') }}
                                    @elseif ($card->online)
                                        {{ __('panel.module_card.online') }}
                                    @else
                                        {{ __('panel.module_card.offline') }}
                                    @endif
                                </x-filament::badge>
                            </x-slot>

                            <dl style="display: grid; grid-template-columns: auto 1fr; gap: .25rem .75rem; font-size: .875rem;">
                                @if ($card->online)
                                    <dt>{{ __('panel.module_card.state') }}</dt>
                                    <dd>{{ $card->state ? __('panel.module_states.'.$card->state->value) : __('panel.module_card.unknown') }}</dd>
                                    <dt>{{ __('panel.module_card.connections') }}</dt>
                                    <dd>{{ $card->connections }}</dd>
                                    @if ($card->isAgent())
                                        <dt>{{ __('panel.module_card.players') }}</dt>
                                        <dd>{{ $card->onlineCount }}</dd>
                                    @endif
                                    @if ($card->uptimeSeconds !== null)
                                        <dt>{{ __('panel.module_card.uptime') }}</dt>
                                        <dd>{{ CarbonInterval::seconds($card->uptimeSeconds)->cascade()->forHumans(['short' => true, 'parts' => 2]) }}</dd>
                                    @endif
                                @else
                                    <dt>{{ __('panel.module_card.last_heartbeat') }}</dt>
                                    <dd>{{ $card->lastHeartbeat?->setTimezone($zone)->format('Y-m-d H:i:s') ?? __('panel.module_card.never_reported') }}</dd>
                                @endif
                                @if ($card->version)
                                    <dt>{{ __('panel.module_card.version') }}</dt>
                                    <dd>{{ $card->version }}</dd>
                                @endif
                                @if ($card->isGateway())
                                    <dt>{{ __('panel.module_card.gateway_mode') }}</dt>
                                    <dd>
                                        @if ($card->gatewayMode)
                                            {{ __('panel.gateway_modes.'.$card->gatewayMode->value) }}
                                        @elseif ($card->unknownGatewayMode)
                                            {{ __('panel.module_card.unknown_mode', ['value' => $card->unknownGatewayMode]) }}
                                        @else
                                            {{ __('panel.module_card.unknown') }}
                                        @endif
                                        @if ($card->pendingMode && $card->pendingMode !== $card->gatewayMode)
                                            <span style="opacity: .75;">({{ __('panel.module_card.changing_to', ['mode' => __('panel.gateway_modes.'.$card->pendingMode->value)]) }})</span>
                                        @endif
                                    </dd>
                                @endif
                                @if ($card->lastError)
                                    <dt>{{ __('panel.module_card.last_error') }}</dt>
                                    <dd style="color: var(--danger-600); overflow-wrap: anywhere;" title="{{ $card->lastError }}">{{ \Illuminate\Support\Str::limit($card->lastError, 160, '…') }}</dd>
                                @endif
                            </dl>

                            <div style="display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem;">
                                {{ ($this->startAction)(['target' => $card->target]) }}
                                {{ ($this->stopAction)(['target' => $card->target]) }}
                                {{ ($this->restartAction)(['target' => $card->target]) }}
                            </div>

                            @if ($card->isGateway())
                                <div style="display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem;">
                                    @foreach (GatewayMode::cases() as $mode)
                                        {{ ($this->gatewayModeAction)(['mode' => $mode->value]) }}
                                    @endforeach
                                </div>
                            @endif
                        </x-filament::section>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
