<x-filament-panels::page>
@include('filament.pages.partials.ivx-styles')
@php
    $rows = $this->entries();
    $tz = auth()->user()?->timezone ?: config('app.timezone');
    $look = [
        'received' => ['Received', 'green', 'heroicon-o-inbox-arrow-down'],
        'transferred' => ['Transferred', '', 'heroicon-o-arrows-right-left'],
        'adjusted' => ['Adjusted', 'amber', 'heroicon-o-adjustments-horizontal'],
        'damaged' => ['Damaged', 'red', 'heroicon-o-exclamation-triangle'],
        'sold' => ['Sold', 'amber', 'heroicon-o-shopping-bag'],
        'created' => ['Created', '', 'heroicon-o-sparkles'],
    ];
    $fmt = fn (float $n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
@endphp
<div class="ivx">
    <nav class="ivx-tabs" aria-label="Activity type">
        @foreach(\App\Filament\Pages\InventoryActivity::TABS as $k => $l)
            <button type="button" wire:click="setTab('{{ $k }}')" @class(['on' => $tab === $k])>{{ $l }}</button>
        @endforeach
    </nav>
    <div class="ivx-search"><x-heroicon-o-magnifying-glass /><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search activity by item or SKU…"></div>

    <section class="ivx-card ivx-list" style="overflow:hidden">
        @forelse($rows as $r)
            @php [$label, $tone, $icon] = $look[$r['kind']]; $url = $this->itemUrl($r['item_id']); @endphp
            @if($url)<a href="{{ $url }}" class="ivx-row" style="align-items:flex-start">@else<div class="ivx-row" style="align-items:flex-start">@endif
                <span class="ivx-ic {{ $tone }}" style="width:36px;height:36px"><x-dynamic-component :component="$icon" /></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2 text-sm"><b>{{ $label }}</b>
                        @if($r['kind'] === 'transferred')<span class="font-bold" style="color:var(--muted)">{{ $fmt($r['qty']) }}</span>
                        @elseif($r['kind'] !== 'created' && $r['change'] != 0)<span class="font-bold" style="color:{{ $r['change'] > 0 ? 'var(--green)' : 'var(--red)' }}">{{ $r['change'] > 0 ? '+' : '−' }}{{ $fmt(abs($r['change'])) }}</span>@endif
                    </span>
                    <span class="ivx-name block" style="font-weight:600">{{ $r['item'] }}</span>
                    <span class="ivx-meta block">
                        @if($r['kind'] === 'transferred'){{ $r['from'] ?? '—' }} → {{ $r['to'] ?? '—' }}
                        @elseif($r['kind'] === 'created')New SKU
                        @else{{ $r['to'] ?? $r['from'] ?? '—' }}@endif
                    </span>
                </span>
                <span class="ivx-right"><span class="ivx-meta">{{ $r['at']?->timezone($tz)->format('M j') }}</span><span class="ivx-meta">{{ $r['at']?->timezone($tz)->format('g:i A') }}</span></span>
            @if($url)</a>@else</div>@endif
        @empty
            <div class="ivx-empty">No activity{{ $search ? ' matches “'.$search.'”' : ' yet' }}.</div>
        @endforelse
    </section>
    @if($rows->count() >= $limit)
        <button type="button" class="ivx-btn" wire:click="more">Show more</button>
    @endif
</div>
</x-filament-panels::page>
