<x-filament-panels::page>
<x-reports.shell>
@php
    $money = fn ($v) => $v === null ? '—' : (((float) $v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 2));
@endphp
<div class="vxr" data-vx-page="giveaway-roi">
    <div class="vxr-head">
        <div><h1 class="vxr-title">Giveaway ROI</h1><p class="vxr-subtitle">What giveaways cost, the new buyers they bring and how those shows earn against shows without them.</p></div>
        <div class="vxr-actions">
            <label class="vxr-control"><x-filament::icon icon="heroicon-o-calendar" /><select id="gr-range" wire:model.live="range" aria-label="Period">@foreach($ranges as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
            <label class="vxr-control"><x-filament::icon icon="heroicon-o-signal" /><select id="gr-channel" wire:model.live="channel" aria-label="Channel"><option value="">All channels</option>@foreach($channels as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
            <x-report-toolbar :exports="['export' => 'Export CSV']" />
        </div>
    </div>

    @php
        $lift = $with['shows'] > 0 && $without['avg_net'] > 0 ? round(($with['avg_net'] - $without['avg_net']) / $without['avg_net'] * 100, 1) : null;
        $pairs = [
            ['Avg Whatnot net per show', $with['avg_net'], $without['avg_net'], true],
            ['Avg new buyers per show', $with['avg_new_buyers'], $without['avg_new_buyers'], false],
        ];
    @endphp
    <div class="vxr-stats" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-vxr.stat label="Spent on giveaways" :value="$money($totals['cost'])" icon="heroicon-o-gift" tone="bad" :note="$money($totals['product_cost']).' product · '.$money($totals['whatnot_spend']).' Whatnot'" />
        <x-vxr.stat label="New buyers on those shows" :value="number_format($totals['new_buyers'])" icon="heroicon-o-user-plus" :note="number_format($totals['giveaways']).' giveaways run'" />
        <x-vxr.stat label="Cost per new buyer" :value="$money($totals['cost_per_new_buyer'])" icon="heroicon-o-calculator" featured note="Giveaway spend ÷ first-time buyers" />
        <x-vxr.stat label="Net lift with giveaways" :value="$lift === null ? '—' : (($lift >= 0 ? '+' : '').number_format($lift, 1).'%')" icon="heroicon-o-arrow-trending-up" :tone="$lift !== null && $lift < 0 ? 'bad' : 'ok'" :note="$with['shows'].' with · '.$without['shows'].' without'" />
    </div>

    <section class="vxr-card">
        <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-scale" /> With vs without giveaways</h2><p class="vxr-card-sub">The average show of each kind in this period.</p></div>
            <div class="vxr-legend"><span><i style="height:8px;width:8px;border-radius:2px;background:var(--vxw-accent)"></i>With giveaways</span><span><i style="height:8px;width:8px;border-radius:2px;background:var(--vxw-border-strong,#c4c0d0)"></i>Without</span></div></div>
        <div class="vxr-card-body" style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
            @foreach ($pairs as [$label, $a, $b, $isMoney])
                @php $mx = max(0.01, $a, $b); $f = fn ($v) => $isMoney ? $money($v) : number_format($v, 1); @endphp
                <div>
                    <div style="font-size:13px;font-weight:650;margin-bottom:8px">{{ $label }}</div>
                    <div class="vxr-lead">
                        <div class="vxr-lead-row" style="grid-template-columns:70px minmax(0,1fr) auto"><span class="nm" style="font-weight:600">With</span><span class="vxr-mini-bar" style="height:12px;margin:0"><i style="width:{{ $a / $mx * 100 }}%"></i></span><span class="v">{{ $f($a) }}</span></div>
                        <div class="vxr-lead-row" style="grid-template-columns:70px minmax(0,1fr) auto"><span class="nm" style="font-weight:600">Without</span><span class="vxr-mini-bar" style="height:12px;margin:0"><i style="width:{{ $b / $mx * 100 }}%;background:var(--vxw-border-strong,#c4c0d0)"></i></span><span class="v">{{ $f($b) }}</span></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="vxr-card">
        @if ($shows->isEmpty())
            <div style="padding:24px;text-align:center">
                <p style="margin:0 0 6px;font-weight:600">No shows in this period</p>
                <p class="vxw-meta" style="margin:0 0 12px">Pick a longer period, or import shows from Whatnot first.</p>
                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="$set('range', '365')">Show the last 12 months</button>
            </div>
        @else
            <div style="overflow-x:auto">
                <table class="vxr-table2">
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
</x-reports.shell>
</x-filament-panels::page>
