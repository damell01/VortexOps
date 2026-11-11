<x-filament-panels::page>
@php
    $money = fn ($v) => $v === null ? '—' : (((float) $v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 2));
@endphp
<div class="vxw" data-vx-page="giveaway-roi">
    <section class="vxw-card">
        <div class="vxw-card-body" style="display:flex;flex-wrap:wrap;gap:12px 16px;align-items:flex-end;justify-content:space-between">
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                <label class="vxw-meta" for="gr-range" style="display:grid;gap:4px">Period
                    <select id="gr-range" class="vxw-input" wire:model.live="range">@foreach($ranges as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                </label>
                <label class="vxw-meta" for="gr-channel" style="display:grid;gap:4px">Channel
                    <select id="gr-channel" class="vxw-input" wire:model.live="channel"><option value="">All channels</option>@foreach($channels as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </label>
            </div>
            <x-report-toolbar :exports="['export' => 'Export CSV']" />
        </div>
        <div class="vxw-stats">
            <div class="vxw-stat"><div class="vxw-stat-label">Spent on giveaways</div><div class="vxw-stat-value">{{ $money($totals['cost']) }}</div><div class="vxw-meta">{{ $money($totals['product_cost']) }} product · {{ $money($totals['whatnot_spend']) }} Whatnot</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">New buyers on those shows</div><div class="vxw-stat-value">{{ number_format($totals['new_buyers']) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Cost per new buyer</div><div class="vxw-stat-value">{{ $money($totals['cost_per_new_buyer']) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Avg net, with vs without</div><div class="vxw-stat-value">{{ $money($with['avg_net']) }} <span class="vxw-meta">vs {{ $money($without['avg_net']) }}</span></div><div class="vxw-meta">{{ $with['shows'] }} with · {{ $without['shows'] }} without · new buyers {{ $with['avg_new_buyers'] }} vs {{ $without['avg_new_buyers'] }}</div></div>
        </div>
    </section>

    <section class="vxw-card" style="margin-top:16px">
        @if ($shows->isEmpty())
            <div style="padding:24px;text-align:center">
                <p style="margin:0 0 6px;font-weight:600">No shows in this period</p>
                <p class="vxw-meta" style="margin:0 0 12px">Pick a longer period, or import shows from Whatnot first.</p>
                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="$set('range', '365')">Show the last 12 months</button>
            </div>
        @else
            <div style="overflow-x:auto">
                <table class="vxw-table" style="width:100%">
                    <thead><tr>
                        <th scope="col" style="text-align:left">Show</th>
                        <th scope="col" style="text-align:right">Giveaways</th><th scope="col" style="text-align:right">Cost</th>
                        <th scope="col" style="text-align:right">Whatnot net</th><th scope="col" style="text-align:right">Buyers</th><th scope="col" style="text-align:right">New buyers</th>
                        <th scope="col" style="text-align:right">Cost / new buyer</th><th scope="col" style="text-align:right">Net per $1</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($shows as $s)
                            <tr wire:key="gr-{{ $s['id'] }}">
                                <td><a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $s['id']]) }}" class="vxw-link"><strong>{{ $s['title'] }}</strong></a><div class="vxw-meta">{{ \Illuminate\Support\Carbon::parse($s['date'])->format('M j, Y') }}{{ $s['channel'] ? ' · ' . $s['channel'] : '' }}</div></td>
                                <td style="text-align:right">{{ $s['giveaways'] ?: '—' }}</td>
                                <td style="text-align:right" title="{{ $money($s['product_cost']) }} product + {{ $money($s['whatnot_spend']) }} Whatnot">{{ $s['cost'] > 0 ? $money($s['cost']) : '—' }}</td>
                                <td style="text-align:right">{{ $money($s['net']) }}</td>
                                <td style="text-align:right">{{ $s['buyers'] ?? '—' }}</td>
                                <td style="text-align:right">{{ $s['new_buyers'] ?? '—' }}</td>
                                <td style="text-align:right">{{ $money($s['cost_per_new_buyer']) }}</td>
                                <td style="text-align:right" class="{{ $s['net_per_dollar'] === null ? '' : ($s['net_per_dollar'] >= 5 ? 'vxw-tone-ok' : ($s['net_per_dollar'] < 1 ? 'vxw-tone-bad' : 'vxw-tone-warn')) }}">{{ $s['net_per_dollar'] === null ? '—' : $money($s['net_per_dollar']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
</x-filament-panels::page>
