<x-filament-panels::page>
    @if ($results === null)
        <x-filament::section>
            <p>{{ __('panel.health.not_run') }}</p>
        </x-filament::section>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: 1rem;">
            @foreach ($results as $name => $result)
                <x-filament::section compact wire:key="health-{{ $name }}">
                    <x-slot name="heading">{{ __('panel.health.connections.'.$name) }}</x-slot>
                    <x-slot name="afterHeader">
                        <x-filament::badge :color="match ($result['state']) { 'ok' => 'success', 'not_configured' => 'gray', default => 'danger' }">
                            {{ __('panel.health.states.'.$result['state']) }}
                        </x-filament::badge>
                    </x-slot>

                    <dl style="display: grid; grid-template-columns: auto 1fr; gap: .25rem .75rem; font-size: .875rem;">
                        <dt>{{ __('panel.health.time') }}</dt>
                        <dd>{{ $result['millis'] === null ? '—' : $result['millis'].' ms' }}</dd>
                        @if ($result['reason'] !== null)
                            <dt>{{ __('panel.health.reason') }}</dt>
                            <dd>{{ $this->reasonLabel($result['reason']) }}</dd>
                        @endif
                        @if ($result['tableCount'] !== null)
                            <dt>{{ __('panel.health.tables') }}</dt>
                            <dd>
                                {{ __('panel.health.tables_count', ['present' => $result['tableCount'] - count($result['missingTables']), 'total' => $result['tableCount']]) }}
                                @if ($result['missingTables'] !== [])
                                    <div style="color: var(--danger-600); overflow-wrap: anywhere;">{{ __('panel.health.missing', ['tables' => implode(', ', $result['missingTables'])]) }}</div>
                                @endif
                            </dd>
                        @endif
                    </dl>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
