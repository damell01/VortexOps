<x-filament-panels::page>
@include('filament.pages.partials.ivx-styles')
@php
    $d = $this->data();
    $c = $d['counts'];
    $labels = ['in' => ['In stock', 'green'], 'low' => ['Low stock', 'amber'], 'out' => ['Out of stock', 'red']];
@endphp
<div class="ivx">
    <nav class="ivx-tabs" aria-label="Stock filter">
        @foreach(['all' => 'All Items', 'low' => 'Low Stock', 'out' => 'Out of Stock'] as $k => $l)
            <button type="button" wire:click="setTab('{{ $k }}')" @class(['on' => $tab === $k])>{{ $l }}</button>
        @endforeach
    </nav>

    <div class="ivx-kpis k4">
        @foreach([['all', 'Total Items', '', 'heroicon-o-cube'], ['in', 'In Stock', 'green', 'heroicon-o-check-circle'], ['low', 'Low Stock', 'amber', 'heroicon-o-exclamation-triangle'], ['out', 'Out of Stock', 'red', 'heroicon-o-x-circle']] as [$k, $l, $tone, $icon])
            <button type="button" wire:click="setTab('{{ $k }}')" @class(['ivx-kpi center', 'on' => $tab === $k])>
                <span class="ivx-ic {{ $tone }}"><x-dynamic-component :component="$icon" /></span>
                <span class="v">{{ number_format($c[$k]) }}</span>
                <span class="l" @if($tone) style="color:var(--{{ $tone === 'green' ? 'green' : ($tone === 'amber' ? 'amber' : 'red') }})" @endif>{{ $l }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex gap-2">
        <div class="ivx-search"><x-heroicon-o-magnifying-glass /><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search items, SKU, or name…"></div>
    </div>
    <select wire:model.live="sort" class="ivx-input" aria-label="Sort">
        <option value="status">Status (High to Low)</option>
        <option value="name">Name A–Z</option>
        <option value="qty">On hand (most first)</option>
        <option value="value">Value (highest first)</option>
    </select>

    <section class="ivx-card ivx-list" style="overflow:hidden">
        @forelse($d['rows'] as $r)
            @php [$label, $tone] = $labels[$r['state']]; @endphp
            <a href="{{ $this->itemUrl($r['id']) }}" class="ivx-row" wire:key="ss-{{ $r['id'] }}">
                @if($r['image'])<img src="{{ $r['image'] }}" alt="" class="ivx-thumb" loading="lazy">@else<span class="ivx-thumb"><x-heroicon-o-cube /></span>@endif
                <span class="min-w-0 flex-1">
                    <span class="ivx-name block">{{ $r['name'] }}</span>
                    <span class="ivx-meta block">{{ $r['sku'] }}</span>
                    <span class="mt-1 flex items-center gap-1.5 text-xs"><span class="ivx-dot {{ $tone }}"></span><b>{{ number_format($r['qty']) }}</b> <span style="color:var(--muted)">on hand</span></span>
                </span>
                <span class="ivx-right">
                    <span class="ivx-pill {{ $tone }}">{{ $label }}</span>
                    <b class="text-sm">${{ number_format($r['cost'], 2) }}</b>
                    <span class="ivx-meta">Avg cost</span>
                </span>
                <x-heroicon-m-chevron-right class="ivx-chev" />
            </a>
        @empty
            <div class="ivx-empty">No items{{ $search ? ' match “'.$search.'”' : ' here' }}.</div>
        @endforelse
    </section>
    @if($d['matching'] > $d['rows']->count())
        <button type="button" class="ivx-btn" wire:click="more">Show more ({{ $d['matching'] - $d['rows']->count() }} left)</button>
    @endif
</div>
</x-filament-panels::page>
