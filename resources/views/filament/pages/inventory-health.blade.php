<x-filament-panels::page>
@include('filament.pages.partials.ivx-styles')
@php
    $d = $this->data();
    $t = max(1, $d['total']);
    $st = $d['states'];
    // Donut: one circle per state, each offset by the ones before it.
    $r = 15.915; $off = 25; $segs = [];
    foreach ([['in', '#16a34a'], ['low', '#f59e0b'], ['out', '#ef4444']] as [$k, $col]) {
        $pct = $st[$k] / $t * 100;
        $segs[] = ['pct' => $pct, 'off' => $off, 'col' => $col];
        $off -= $pct;
    }
    $maxCat = max(1, (int) ($d['categories']->max('items') ?? 0));
@endphp
<div class="ivx">
    <nav class="ivx-tabs" aria-label="Health sections">
        @foreach(['health' => 'Health', 'categories' => 'Categories', 'vendors' => 'Vendors'] as $k => $l)
            <button type="button" wire:click="setTab('{{ $k }}')" @class(['on' => $tab === $k])>{{ $l }}</button>
        @endforeach
    </nav>

    @if($tab === 'health')
        <div class="ivx-kpis">
            <a href="{{ $this->ageUrl() }}" class="ivx-kpi" style="gap:6px">
                <span class="l" style="color:var(--text);font-weight:700">Fresh inventory</span>
                <span class="v" style="font-size:1.6rem">{{ number_format($d['fresh']) }}</span>
                <span class="flex items-center justify-between"><span class="ivx-sub">In last 30 days</span><x-heroicon-m-chevron-right class="ivx-chev" /></span>
            </a>
            <a href="{{ $this->ageUrl() }}" class="ivx-kpi" style="gap:6px;border-color:#fecaca">
                <span class="l" style="color:var(--text);font-weight:700">Needs attention</span>
                <span class="v" style="font-size:1.6rem;color:var(--red)">{{ number_format($d['attention']) }}</span>
                <span class="flex items-center justify-between"><span class="ivx-sub">61+ days in stock</span><x-heroicon-m-chevron-right class="ivx-chev" /></span>
            </a>
        </div>

        <section class="ivx-card ivx-pad">
            <h3 class="ivx-h">Health Breakdown</h3>
            <div class="mt-3 flex flex-wrap items-center gap-5">
                <svg viewBox="0 0 42 42" width="150" height="150" role="img" aria-label="Health breakdown of {{ $d['total'] }} items" style="flex:none">
                    <circle cx="21" cy="21" r="{{ $r }}" fill="none" stroke="var(--line)" stroke-width="5.5"/>
                    @foreach($segs as $s)
                        @if($s['pct'] > 0)<circle cx="21" cy="21" r="{{ $r }}" fill="none" stroke="{{ $s['col'] }}" stroke-width="5.5" stroke-dasharray="{{ round($s['pct'], 2) }} {{ round(100 - $s['pct'], 2) }}" stroke-dashoffset="{{ round($s['off'], 2) }}"/>@endif
                    @endforeach
                    <text x="21" y="21" text-anchor="middle" font-size="7" font-weight="800" fill="currentColor">{{ number_format($d['total']) }}</text>
                    <text x="21" y="26.5" text-anchor="middle" font-size="2.8" fill="var(--muted)">Total Items</text>
                </svg>
                <div class="grid flex-1 gap-2.5 text-sm" style="min-width:160px">
                    @foreach([['in', 'Fresh', 'green'], ['low', 'Low Stock', 'amber'], ['out', 'Out of Stock', 'red']] as [$k, $l, $tone])
                        <a href="{{ $this->stockUrl($k) }}" class="flex items-center gap-2"><span class="ivx-dot {{ $tone }}" style="width:12px;height:12px;border-radius:4px"></span><b>{{ number_format($st[$k]) }}</b><span style="color:var(--muted)">{{ $l }} ({{ round($st[$k] / $t * 100) }}%)</span></a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ivx-card ivx-pad">
            <div class="ivx-head"><h3 class="ivx-h">Top Categories <span class="ivx-sub font-normal">(by item count)</span></h3><button type="button" class="ivx-link" wire:click="setTab('categories')">View all</button></div>
            <div class="grid gap-3">
                @forelse($d['categories']->take(5) as $c)
                    <div class="grid items-center gap-3" style="grid-template-columns:minmax(0,1fr) 1.4fr">
                        <span class="truncate text-sm">{{ $c['name'] }}</span>
                        <span class="ivx-bar"><i style="width:{{ $c['items'] / $maxCat * 100 }}%;background:var(--p)"></i></span>
                    </div>
                @empty
                    <div class="ivx-empty">No items yet.</div>
                @endforelse
            </div>
        </section>
    @else
        @php $rows = $tab === 'categories' ? $d['categories'] : $d['vendors']; @endphp
        <section class="ivx-card ivx-list" style="overflow:hidden">
            @forelse($rows as $row)
                <div class="ivx-row">
                    <span class="ivx-ic" style="width:34px;height:34px">@if($tab === 'categories')<x-heroicon-o-tag />@else<x-heroicon-o-building-storefront />@endif</span>
                    <span class="min-w-0 flex-1">
                        <span class="ivx-name block">{{ $row['name'] }}</span>
                        <span class="ivx-meta block">{{ number_format($row['items']) }} items · {{ number_format($row['units']) }} units @if($row['out'])· <span style="color:var(--red)">{{ $row['out'] }} out of stock</span>@endif</span>
                    </span>
                    <span class="ivx-right"><b class="text-sm">${{ number_format($row['value'], 0) }}</b><span class="ivx-meta">on hand</span></span>
                </div>
            @empty
                <div class="ivx-empty">Nothing to show yet.</div>
            @endforelse
        </section>
    @endif
</div>
</x-filament-panels::page>
