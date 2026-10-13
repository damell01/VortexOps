<x-filament-panels::page>
@php
    [$from, $to, $label] = $this->range();
    $t = $this->totals();
    $prev = $this->previousTotals();
    $trend = $this->trend();
    $opts = $this->options();
    $money = fn ($v, $dp = 0) => ($v < 0 ? '−$' : '$') . number_format(abs((float) $v), $dp);
    $delta = function ($now, $before) {
        if ($before == 0) return null;
        return round(($now - $before) / abs($before) * 100);
    };
    $maxBar = max(1, $trend->max(fn ($b) => abs($b['profit'])));
    $kpis = [
        ['Shows', number_format($t['shows']), $delta($t['shows'], $prev['shows']), false],
        ['Sales', $money($t['sales']), $delta($t['sales'], $prev['sales']), false],
        ['Earnings', $money($t['earnings']), $delta($t['earnings'], $prev['earnings']), false],
        ['COGS', $money($t['cogs']), $delta($t['cogs'], $prev['cogs']), true],
        ['Streamer pay', $money($t['payouts']), $delta($t['payouts'], $prev['payouts']), true],
        ['Profit after pay', $money($t['profit']), $delta($t['profit'], $prev['profit']), false],
    ];
    $breakdownLabels = ['streamer' => 'Streamer', 'channel' => 'Channel', 'business' => 'Business', 'weekday' => 'Weekday', 'slot' => 'Time slot'];
@endphp
<div class="vxw" style="grid-template-columns:minmax(0,1fr)" data-vx-page="profit-loss">
    {{-- Period and filters --}}
    <section class="vxw-card">
        <div class="vxw-card-body flex flex-wrap items-center gap-3">
            <div class="vxw-chips" role="tablist" aria-label="View">
                @foreach (['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'] as $key => $text)
                    <button type="button" role="tab" wire:click="setView('{{ $key }}')" @class(['vxw-chip', 'is-active' => $period === $key])>{{ $text }}</button>
                @endforeach
            </div>
            <div class="flex items-center gap-1">
                <button type="button" wire:click="shift(-1)" class="vxw-btn vxw-btn--sm" aria-label="Previous"><x-filament::icon icon="heroicon-m-chevron-left" /></button>
                <div class="vxw-h2 px-2 text-center" style="min-width:11rem">{{ $label }}</div>
                <button type="button" wire:click="shift(1)" class="vxw-btn vxw-btn--sm" aria-label="Next"><x-filament::icon icon="heroicon-m-chevron-right" /></button>
                <button type="button" wire:click="today" class="vxw-btn vxw-btn--ghost vxw-btn--sm">Today</button>
            </div>
            <div class="ml-auto flex flex-wrap gap-2">
                <select wire:model.live="channel" class="vxw-select" style="width:auto" aria-label="Channel"><option value="">All channels</option>@foreach ($opts['channels'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                <select wire:model.live="business" class="vxw-select" style="width:auto" aria-label="Business"><option value="">All businesses</option>@foreach ($opts['businesses'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                <select wire:model.live="streamer" class="vxw-select" style="width:auto" aria-label="Streamer"><option value="">All streamers</option>@foreach ($opts['streamers'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
            </div>
        </div>
    </section>

    {{-- Headline numbers --}}
    <section class="vxw-card">
        <div class="vxw-stats" style="grid-template-columns:repeat(auto-fit,minmax(min(150px,100%),1fr));border-top:0">
            @foreach ($kpis as [$name, $value, $d, $costLike])
                <div class="vxw-stat">
                    <div class="vxw-stat-label">{{ $name }}</div>
                    <div class="vxw-stat-value" @if($name === 'Profit after pay') style="color:{{ $t['profit'] < 0 ? '#dc2626' : '#059669' }}" @endif>{{ $value }}</div>
                    @if ($d !== null)
                        @php $good = $costLike ? $d <= 0 : $d >= 0; @endphp
                        <div class="text-xs" style="color:{{ $good ? '#059669' : '#dc2626' }}">{{ $d >= 0 ? '▲' : '▼' }} {{ abs($d) }}% vs previous</div>
                    @else
                        <div class="vxw-meta text-xs">—</div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap px-5 pb-4 text-sm" style="color:var(--vxw-muted);column-gap:24px;row-gap:4px">
            <span>Margin <b style="color:var(--vxw-text)">{{ number_format($t['margin_pct'], 1) }}%</b> of earnings</span>
            <span>Profit per show <b style="color:var(--vxw-text)">{{ $money($t['profit_per_show']) }}</b></span>
            @if ($t['profit_per_hour'] !== null)<span>Profit per hour worked <b style="color:var(--vxw-text)">{{ $money($t['profit_per_hour']) }}</b></span>@endif
            <span>Units <b style="color:var(--vxw-text)">{{ number_format($t['units']) }}</b></span>
            <button type="button" wire:click="$set('showFilter', 'losing')" class="vxw-link">Losing shows <b>{{ $t['losing'] }}</b></button>
            <button type="button" wire:click="$set('showFilter', 'incomplete')" class="vxw-link">Incomplete <b>{{ $t['incomplete'] }}</b></button>
        </div>
    </section>

    {{-- Trend --}}
    @if ($period !== 'day')
        <section class="vxw-card">
            <div class="vxw-card-head"><div><h3 class="vxw-h2">Profit after pay, {{ $period === 'year' ? 'by month' : 'by day' }}</h3><p class="vxw-sub">Green made money, red lost it. Hover a bar for sales and show count.</p></div></div>
            <div class="vxw-card-body">
                <div class="flex items-stretch gap-1" style="height:180px" role="img" aria-label="Profit trend">
                    @foreach ($trend as $b)
                        @php $h = round(abs($b['profit']) / $maxBar * 50, 1); @endphp
                        <div class="flex min-w-0 flex-1 flex-col" title="{{ $b['label'] }}: profit {{ $money($b['profit']) }} · sales {{ $money($b['sales']) }} · {{ $b['shows'] }} {{ \Illuminate\Support\Str::plural('show', $b['shows']) }}">
                            <div class="flex flex-1 flex-col justify-end"><div style="height:{{ $b['profit'] > 0 ? $h : 0 }}%;background:#10b981;border-radius:3px 3px 0 0"></div></div>
                            <div style="height:1px;background:var(--vxw-border-strong)"></div>
                            <div class="flex flex-1 flex-col justify-start"><div style="height:{{ $b['profit'] < 0 ? $h : 0 }}%;background:#ef4444;border-radius:0 0 3px 3px"></div></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-1 text-[10px]" style="color:var(--vxw-muted)">
                    @foreach ($trend as $i => $b)<div class="min-w-0 flex-1 truncate text-center">{{ ($trend->count() <= 14 || $i % 3 === 0) ? $b['label'] : '' }}</div>@endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- How streamers are doing --}}
    @php $streamers = $this->streamerTrend(); @endphp
    <section class="vxw-card">
        <div class="vxw-card-head"><div><h3 class="vxw-h2">How streamers are doing</h3><p class="vxw-sub">Shows they hosted as primary in this period, compared with the period before.</p></div></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="min-width:820px">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wide" style="color:var(--vxw-muted)">
                    <th class="px-4 py-2">Streamer</th><th class="px-4 py-2 text-right">Shows</th><th class="px-4 py-2 text-right">Sales</th><th class="px-4 py-2 text-right">Pay</th><th class="px-4 py-2 text-right">Profit</th><th class="px-4 py-2 text-right">Per show</th><th class="px-4 py-2 text-right">Per hour</th><th class="px-4 py-2 text-right">Losing</th><th class="px-4 py-2 text-right">vs before</th>
                </tr></thead>
                <tbody>
                    @forelse ($streamers as $s)
                        @php $chg = $s['prev_profit'] !== null ? $s['profit'] - $s['prev_profit'] : null; @endphp
                        <tr style="border-top:1px solid var(--vxw-border)">
                            <td class="px-4 py-2.5 font-medium">{{ $s['label'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $s['shows'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($s['sales']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($s['payouts']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-semibold" style="color:{{ $s['profit'] < 0 ? '#dc2626' : 'inherit' }}">{{ $money($s['profit']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($s['profit_per_show']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $s['profit_per_hour'] !== null ? $money($s['profit_per_hour']) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $s['losing'] ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums" style="color:{{ $chg === null ? 'inherit' : ($chg >= 0 ? '#059669' : '#dc2626') }}">{{ $chg === null ? 'new' : ($chg >= 0 ? '+' : '−').'$'.number_format(abs($chg)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center vxw-meta">No shows in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Breakdowns --}}
    @php $rowsBy = $this->breakdown(); $maxAbs = max(1, $rowsBy->max(fn ($r) => abs($r['profit']))); @endphp
    <section class="vxw-card">
        <div class="vxw-card-head">
            <div><h3 class="vxw-h2">Breakdown</h3><p class="vxw-sub">What's winning and what's losing.</p></div>
            <div class="vxw-chips" role="tablist" aria-label="Break down by">
                @foreach ($breakdownLabels as $key => $text)
                    <button type="button" role="tab" wire:click="$set('breakdownBy', '{{ $key }}')" @class(['vxw-chip', 'is-active' => $breakdownBy === $key])>{{ $text }}</button>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="min-width:760px">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wide" style="color:var(--vxw-muted)">
                    <th class="px-4 py-2">{{ $breakdownLabels[$breakdownBy] ?? 'Group' }}</th><th class="px-4 py-2 text-right">Shows</th><th class="px-4 py-2 text-right">Sales</th><th class="px-4 py-2 text-right">Earnings</th><th class="px-4 py-2 text-right">COGS</th><th class="px-4 py-2 text-right">Pay</th><th class="px-4 py-2 text-right">Profit</th><th class="px-4 py-2" style="width:18%"></th>
                </tr></thead>
                <tbody>
                    @foreach ($rowsBy as $r)
                        <tr style="border-top:1px solid var(--vxw-border)">
                            <td class="px-4 py-2.5 font-medium">{{ $r['label'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $r['shows'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($r['sales']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($r['earnings']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($r['cogs']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($r['payouts']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-semibold" style="color:{{ $r['profit'] < 0 ? '#dc2626' : 'inherit' }}">{{ $money($r['profit']) }}</td>
                            <td class="px-4 py-2.5"><div style="height:8px;border-radius:4px;width:{{ max(2, round(abs($r['profit']) / $maxAbs * 100)) }}%;background:{{ $r['profit'] < 0 ? '#ef4444' : '#10b981' }}"></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Every show --}}
    @php $shows = $this->showRows(); @endphp
    <section class="vxw-card">
        <div class="vxw-card-head">
            <div><h3 class="vxw-h2">Shows</h3><p class="vxw-sub">{{ $shows->count() }} of {{ $t['shows'] }} · Incomplete shows are counted but their profit may change.</p></div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="vxw-chips">
                    @foreach (['all' => 'All', 'losing' => 'Losing', 'incomplete' => 'Incomplete', 'complete' => 'Complete'] as $key => $text)
                        <button type="button" wire:click="$set('showFilter', '{{ $key }}')" @class(['vxw-chip', 'is-active' => $showFilter === $key])>{{ $text }}</button>
                    @endforeach
                </div>
                <select wire:model.live="sort" class="vxw-select" style="width:auto" aria-label="Sort"><option value="date">Newest</option><option value="profit">Biggest loss first</option><option value="profit_desc">Most profit first</option><option value="sales">Most sales first</option></select>
                <button type="button" wire:click="exportCsv" class="vxw-btn vxw-btn--sm"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> CSV</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="min-width:1000px">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wide" style="color:var(--vxw-muted)">
                    <th class="px-4 py-2">Date</th><th class="px-4 py-2">Show</th><th class="px-4 py-2">Streamer</th><th class="px-4 py-2 text-right">Sales</th><th class="px-4 py-2 text-right">Earnings</th><th class="px-4 py-2 text-right">COGS</th><th class="px-4 py-2 text-right">Pay</th><th class="px-4 py-2 text-right">Profit</th><th class="px-4 py-2 text-right">Margin</th>
                </tr></thead>
                <tbody>
                    @forelse ($shows->take($shown) as $r)
                        <tr wire:key="pl-{{ $r['id'] }}" style="border-top:1px solid var(--vxw-border)">
                            <td class="px-4 py-2.5 whitespace-nowrap">{{ $r['date']?->format('D M j') }}@if($r['start'])<div class="vxw-meta">{{ $r['start']->format('g:i A') }}</div>@endif</td>
                            <td class="px-4 py-2.5">
                                <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $r['id']]) }}" class="font-medium hover:underline">{{ \Illuminate\Support\Str::limit($r['title'], 60) }}</a>
                                <div class="vxw-meta">{{ $r['channel'] }}</div>
                                @if ($r['incomplete'])<div class="mt-1"><span class="vxw-pill vxw-pill--warn" title="{{ implode(' · ', $r['incomplete']) }}">Incomplete — may change</span> <span class="vxw-meta text-xs">{{ implode(' · ', $r['incomplete']) }}</span></div>@endif
                            </td>
                            <td class="px-4 py-2.5">{{ $r['streamer'] }}@if($r['cohosts'])<div class="vxw-meta">+ {{ implode(', ', $r['cohosts']) }}</div>@endif</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($r['sales'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($r['earnings'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($r['cogs'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $money($r['payouts'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-semibold" style="color:{{ $r['profit'] < 0 ? '#dc2626' : '#059669' }}">{{ $money($r['profit'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($r['margin_pct'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center vxw-meta">No shows match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($shows->count() > $shown)<div class="flex items-center justify-between px-4 py-3" style="border-top:1px solid var(--vxw-border)"><span class="vxw-meta">Showing {{ $shown }} of {{ $shows->count() }}</span><button type="button" wire:click="showMore" class="vxw-btn vxw-btn--sm">Show more</button></div>@endif
    </section>
</div>
</x-filament-panels::page>
