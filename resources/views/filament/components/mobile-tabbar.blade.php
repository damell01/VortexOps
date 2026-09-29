@php
    $tab = function (string $label, string $icon, callable $can, callable $url): ?array {
        try {
            if (! $can()) return null;
            return ['label' => $label, 'icon' => $icon, 'url' => $url()];
        } catch (\Throwable) { return null; }
    };

    $tabs = array_values(array_filter([
        $tab('Shows', 'heroicon-o-tv',
            fn () => \App\Filament\Resources\ShowResource::canViewAny(),
            fn () => \App\Filament\Resources\ShowResource::getUrl('index')),
        $tab('Inventory', 'heroicon-o-cube',
            fn () => \App\Filament\Pages\InventoryOverview::canAccess(),
            fn () => \App\Filament\Pages\InventoryOverview::getUrl()),
        $tab('Payroll', 'heroicon-o-wallet',
            fn () => \App\Filament\Pages\PayrollOverview::canAccess(),
            fn () => \App\Filament\Pages\PayrollOverview::getUrl()),
    ]));
    $current = url()->current();
@endphp
@if (count($tabs) > 0)
<nav class="vx-tabbar" aria-label="Quick navigation">
    @foreach ($tabs as $t)
        @php $active = str_starts_with($current, rtrim($t['url'], '/')); @endphp
        <a href="{{ $t['url'] }}" class="vx-tabbar-item" wire:navigate @if ($active) aria-current="page" @endif>
            <x-filament::icon :icon="$t['icon']" /><span>{{ $t['label'] }}</span>
        </a>
    @endforeach
    <button type="button" class="vx-tabbar-item" x-data x-on:click="$store.sidebar.open()" aria-label="Open full menu">
        <x-filament::icon icon="heroicon-o-bars-3" /><span>More</span>
    </button>
</nav>
@endif
