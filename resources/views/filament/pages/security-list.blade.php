<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if (method_exists($this, 'cafeSummary'))
        {{ $this->cafeSummary() }}
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
