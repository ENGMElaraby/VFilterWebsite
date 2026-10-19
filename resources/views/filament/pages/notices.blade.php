<x-filament-panels::page>
    @if ($this->sendAction->isVisible())
        <x-filament::section>
            <form wire:submit="mountAction('send')" class="grid gap-y-4">
                {{ $this->form }}

                <div>
                    {{ $this->sendAction }}
                </div>
            </form>
        </x-filament::section>
    @endif

    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
