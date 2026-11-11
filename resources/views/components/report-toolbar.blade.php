@props(['exports' => ['exportCsv' => 'Export CSV']])
{{-- The same Print / Export pair, in the same order, at the top right of every report. --}}
<div {{ $attributes->merge(['class' => 'vx-report-toolbar']) }} role="group" aria-label="Print and export">
    <button type="button" class="vx-report-toolbar__btn" onclick="window.print()"><x-filament::icon icon="heroicon-m-printer" /> Print</button>
    @if (count($exports) === 1)
        @foreach ($exports as $method => $label)
            <button type="button" class="vx-report-toolbar__btn" wire:click="{{ $method }}" wire:loading.attr="disabled" wire:target="{{ $method }}"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> {{ $label }}</button>
        @endforeach
    @elseif (count($exports) > 1)
        <div class="vx-report-toolbar__menu" x-data="{ open: false }" @keydown.escape="open = false">
            <button type="button" class="vx-report-toolbar__btn" @click="open = !open" :aria-expanded="open" aria-haspopup="menu"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> Export <x-filament::icon icon="heroicon-m-chevron-down" /></button>
            <div class="vx-report-toolbar__list" role="menu" x-cloak x-show="open" @click.outside="open = false">
                @foreach ($exports as $method => $label)
                    <button type="button" role="menuitem" wire:click="{{ $method }}" @click="open = false">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    @endif
</div>
