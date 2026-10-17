@php($other = \App\Support\PanelLocale::other(app()->getLocale()))
<div class="text-center text-sm">
    <x-filament::link :href="route('panel.locale', $other)">
        {{ \App\Support\PanelLocale::name($other) }}
    </x-filament::link>
</div>
