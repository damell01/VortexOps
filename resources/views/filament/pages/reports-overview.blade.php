<x-filament-panels::page>
    @php
        $t = $this->totals();
        $links = $this->links();
        $money = fn ($v) => ($v < 0 ? '-$' : '$').number_format(abs($v), abs($v) >= 100 ? 0 : 2);
        $businesses = $this->businesses();
    @endphp

    <div class="vxr" data-vx-page="reports-overview">
        <div class="vxr-head">
            <div>
                <h1 class="vxr-title">Reports Overview<img class="vx-sprite vx-mew" src="/img/pokemon/mew.gif" alt="" aria-hidden="true"></h1>
                <p class="vxr-subtitle">Business performance and financial reporting in one place.</p>
            </div>
            <div class="vxr-actions">
                <label class="vxr-control">
                    <x-filament::icon icon="heroicon-o-calendar" />
                    <span style="display:grid;line-height:1.15">
                        <select wire:model.live="range" aria-label="Period" style="font-weight:600">
                            @foreach (\App\Filament\Pages\ReportsOverview::RANGES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                        <span style="font-size:12px;color:var(--vxw-muted)">{{ $this->periodLabel() }}</span>
                    </span>
                </label>
                @if ($businesses)
                    <label class="vxr-control">
                        <x-filament::icon icon="heroicon-o-building-office-2" />
                        <select wire:model.live="business" aria-label="Business">
                            <option value="">All businesses</option>
                            @foreach ($businesses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </label>
                @endif
            </div>
        </div>

        <div class="vxr-stats">
            <x-vxr.stat label="Gross Sales" :value="$money($t['sales'])" icon="heroicon-o-chart-bar" :note="$t['shows'].' '.\Illuminate\Support\Str::plural('show', $t['shows'])" />
            <x-vxr.stat label="Whatnot Est. Earnings" :value="$money($t['whatnot_net'])" icon="heroicon-o-tag" tone="accent" note="After Whatnot fees, before our costs" />
            <x-vxr.stat label="Finalized Business Profit" :value="$t['finalized'] ? $money($t['finalized_profit']) : '—'" icon="heroicon-o-currency-dollar" tone="ok" :value-class="$t['finalized_profit'] < 0 ? 'vxr-val-bad' : ''" :note="$t['finalized'].' finalized '.\Illuminate\Support\Str::plural('show', $t['finalized']).' only'" :href="$links['pl']" />
            <x-vxr.stat label="Pending Financials" :value="$t['incomplete'].' '.\Illuminate\Support\Str::plural('show', $t['incomplete'])" icon="heroicon-o-clock" tone="warn" note="Awaiting costs or pay runs" :href="$links['pending']" />
        </div>

        <div class="vxr-grid vxr-grid--wide">
            <section class="vxr-card">
                <div class="vxr-card-head">
                    <h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-arrow-trending-up" /> Whatnot Earnings Trend</h2>
                    <span class="vxr-hint" style="margin:0">Estimated earnings by {{ $range === 'this_year' ? 'month' : 'day' }}</span>
                </div>
                <div class="vxr-card-body"><x-vxr.chart :points="$this->trend()" type="area" :height="260" /></div>
            </section>

            <section class="vxr-card">
                <div class="vxr-card-head"><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-exclamation-triangle" style="color:var(--vxw-bad)" /> Needs Attention</h2></div>
                <div class="vxr-card-body vxr-links">
                    <a class="vxr-linkrow vxr-tone-bad" href="{{ $links['losses'] }}"><span class="vxr-linkrow-icon"><x-filament::icon icon="heroicon-o-exclamation-circle" /></span><span><b>Negative Whatnot Earnings</b><span>{{ $t['whatnot_negative'] }} {{ \Illuminate\Support\Str::plural('show', $t['whatnot_negative']) }}@if($t['whatnot_negative']) · {{ $money($t['whatnot_negative_sum']) }}@endif</span></span><x-filament::icon icon="heroicon-m-chevron-right" /></a>
                    <a class="vxr-linkrow vxr-tone-bad" href="{{ $links['finalized_losses'] }}"><span class="vxr-linkrow-icon"><x-filament::icon icon="heroicon-o-exclamation-circle" /></span><span><b>Finalized Business Losses</b><span>{{ $t['finalized_losing'] }} {{ \Illuminate\Support\Str::plural('show', $t['finalized_losing']) }}</span></span><x-filament::icon icon="heroicon-m-chevron-right" /></a>
                    <a class="vxr-linkrow vxr-tone-warn" href="{{ $links['pending'] }}"><span class="vxr-linkrow-icon"><x-filament::icon icon="heroicon-o-clock" /></span><span><b>Pending Costs &amp; Payroll</b><span>{{ $t['incomplete'] }} {{ \Illuminate\Support\Str::plural('show', $t['incomplete']) }}</span></span><x-filament::icon icon="heroicon-m-chevron-right" /></a>
                </div>
            </section>
        </div>

        <section style="display:grid;gap:12px">
            <h2 class="vxr-card-title" style="font-size:19px"><x-filament::icon icon="heroicon-o-squares-2x2" /> Report Library</h2>
            <div class="vxr-tiles">
                @foreach ($this->library() as $tile)
                    <a class="vxr-tile" href="{{ $tile['url'] }}">
                        <span class="vxr-stat-icon vxr-tone-{{ $tile['tone'] }}"><x-filament::icon :icon="$tile['icon']" /></span>
                        <span><b>{{ $tile['title'] }}</b><p>{{ $tile['text'] }}</p><span class="vxr-link">Open Report <x-filament::icon icon="heroicon-m-arrow-right" /></span></span>
                    </a>
                @endforeach
            </div>
        </section>

        <p class="vxr-foot-note"><x-filament::icon icon="heroicon-o-information-circle" /> Business profit includes finalized shows only — earnings, approved COGS and final pay all in. Pending shows are excluded.</p>
    </div>
</x-filament-panels::page>
