<x-filament-panels::page>
@php
    [$from, $to, $label] = $this->range();
    $t = $this->totals();
    $prev = $this->previousTotals();
    $opts = $this->options();
    $money = fn ($v, $dp = 0) => ($v < 0 ? '-$' : '$') . number_format(abs((float) $v), $dp);
    $delta = function ($now, $before) {
        if ($before == 0) return null;
        return round(($now - $before) / abs($before) * 100);
    };
    $deltaNote = function ($now, $before, $costLike = false) use ($delta) {
        $d = $delta($now, $before);
        if ($d === null) return null;
        $good = $costLike ? $d <= 0 : $d >= 0;
        return new \Illuminate\Support\HtmlString('<span class="vxr-delta '.($good ? 'vxr-delta--up' : 'vxr-delta--down').'">'.($d >= 0 ? '▲' : '▼').' '.abs($d).'% vs previous</span>');
    };
    $breakdownLabels = ['streamer' => 'Streamer', 'channel' => 'Channel', 'business' => 'Business', 'weekday' => 'Weekday', 'slot' => 'Time slot'];
    $counts = ['all' => $t['shows'], 'finalized' => $t['finalized'], 'incomplete' => $t['incomplete'], 'losses' => $t['finalized_losing'], 'whatnot' => $t['whatnot_negative']];
    $tab = $showFilter === 'complete' ? 'finalized' : $showFilter;
    $detail = $this->detail();
@endphp

<div class="vxr" data-vx-page="profit-loss">
    <div class="vxr-head">
        <div>
            <h1 class="vxr-title">Business Profitability</h1>
            <p class="vxr-subtitle">Profit &amp; loss: Whatnot earnings plus tips, minus COGS and recorded worker payouts.</p>
        </div>
        <div class="vxr-actions">
            <button type="button" class="vxr-btn" wire:click="exportCsv"><x-filament::icon icon="heroicon-o-arrow-down-tray" /> Export</button>
            <button type="button" class="vxr-btn vxr-btn--primary" wire:click="openSend"><x-filament::icon icon="heroicon-o-envelope" /> Send Summary</button>
        </div>
    </div>

    <div class="vxr-toolbar">
        <div class="vxr-seg" role="tablist" aria-label="View">
            @foreach (['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'] as $key => $text)
                <button type="button" role="tab" wire:click="setView('{{ $key }}')" @class(['is-active' => $period === $key])>{{ $text }}</button>
            @endforeach
        </div>
        <div class="vxr-pager">
            <button type="button" wire:click="shift(-1)" aria-label="Previous"><x-filament::icon icon="heroicon-m-chevron-left" /></button>
            <span><x-filament::icon icon="heroicon-o-calendar" style="width:18px;height:18px;color:var(--vxw-muted)" /> {{ $label }}</span>
            <button type="button" wire:click="shift(1)" aria-label="Next"><x-filament::icon icon="heroicon-m-chevron-right" /></button>
        </div>
        <button type="button" wire:click="today" class="vxr-btn vxr-btn--sm" style="height:40px">Today</button>
        <select wire:model.live="channel" class="vxr-select" aria-label="Channel"><option value="">All channels</option>@foreach ($opts['channels'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        <select wire:model.live="business" class="vxr-select" aria-label="Business"><option value="">All businesses</option>@foreach ($opts['businesses'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        <select wire:model.live="streamer" class="vxr-select" aria-label="Streamer"><option value="">All streamers</option>@foreach ($opts['streamers'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
    </div>

    @if ($t['incomplete'] > 0)
        <div class="vxr-banner vxr-banner--warn">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" />
            <span>
                <b>{{ $t['incomplete'] }} {{ $t['incomplete'] === 1 ? 'show has' : 'shows have' }} incomplete costs or payouts.</b>
                {{ $t['finalized'] === 0 ? 'Final business profit is not available yet.' : 'Finalized profit covers the other '.$t['finalized'].' '.\Illuminate\Support\Str::plural('show', $t['finalized']).' only.' }}
            </span>
            <button type="button" class="vxr-btn vxr-btn--sm" wire:click="setTab('incomplete')">See them</button>
        </div>
    @endif

    <div class="vxr-stats" style="grid-template-columns:repeat(auto-fit,minmax(175px,1fr))">
        <x-vxr.stat compact label="Shows" :value="number_format($t['shows'])" icon="heroicon-o-calendar-days">{{ $deltaNote($t['shows'], $prev['shows']) }}</x-vxr.stat>
        <x-vxr.stat compact label="Sales" :value="$money($t['sales'])" icon="heroicon-o-shopping-cart">{{ $deltaNote($t['sales'], $prev['sales']) }}</x-vxr.stat>
        <x-vxr.stat compact label="Whatnot Earnings" :value="$money($t['earnings'])" icon="heroicon-o-banknotes" title="Whatnot's earnings after its fees, plus tips">{{ $deltaNote($t['earnings'], $prev['earnings']) }}</x-vxr.stat>
        <x-vxr.stat compact label="Recorded COGS" :value="$t['shows'] && $t['missing_cogs'] === $t['shows'] ? 'Missing' : $money($t['cogs'])" icon="heroicon-o-cube" :tone="$t['missing_cogs'] ? 'warn' : 'accent'" :value-class="$t['shows'] && $t['missing_cogs'] === $t['shows'] ? 'vxr-val-warn' : ''" :note="$t['missing_cogs'] ? $t['missing_cogs'].' '.\Illuminate\Support\Str::plural('show', $t['missing_cogs']).' missing' : null" />
        <x-vxr.stat compact label="Worker Payouts" :value="$t['shows'] && $t['pay_pending'] === $t['shows'] && $t['payouts'] == 0 ? 'Pending' : $money($t['payouts'])" icon="heroicon-o-users" :tone="$t['pay_pending'] ? 'warn' : 'accent'" :value-class="$t['shows'] && $t['pay_pending'] === $t['shows'] && $t['payouts'] == 0 ? 'vxr-val-warn' : ''" :note="$t['pay_pending'] ? $t['pay_pending'].' not final' : null" />
        <x-vxr.stat compact label="Finalized Profit" :value="$t['finalized'] ? $money($t['finalized_profit']) : '—'" icon="heroicon-o-chart-bar" tone="ok" :value-class="$t['finalized'] ? ($t['finalized_profit'] < 0 ? 'vxr-val-bad' : 'vxr-val-ok') : 'vxr-val-muted'" :note="$t['finalized'].' finalized · '.$money($t['profit']).' incl. incomplete'" />
    </div>

    <div class="vxr-tabs" role="tablist">
        @foreach (\App\Filament\Pages\ProfitLoss::TABS as $key => $text)
            <button type="button" role="tab" wire:click="setTab('{{ $key }}')" @class(['vxr-tab', 'is-active' => $tab === $key])>{{ $text }}@if(in_array($key, ['incomplete', 'losses', 'whatnot'], true) && $counts[$key])<span class="vxr-count">{{ $counts[$key] }}</span>@endif</button>
        @endforeach
    </div>

    <div class="vxr-grid vxr-grid--main">
        @if ($period !== 'day')
            <section class="vxr-card">
                <div class="vxr-card-head">
                    <div><h2 class="vxr-card-title" style="font-size:18px">Whatnot estimated earnings by {{ $period === 'year' ? 'month' : 'day' }}</h2></div>
                    <div class="vxr-legend"><span><i style="background:var(--vxw-accent)"></i>Earnings (actual)</span><span><i style="background:var(--vxw-sunken);border:1px solid var(--vxw-border)"></i>Future {{ $period === 'year' ? 'month' : 'day' }} (no data yet)</span></div>
                </div>
                <div class="vxr-card-body">
                    <x-vxr.chart :points="$this->earningsChart()" type="bar" :height="230" />
                    <p class="vxr-hint">Does not include business expenses (COGS, worker payouts, or other costs).</p>
                </div>
            </section>
        @else
            <section class="vxr-card">
                <div class="vxr-card-head"><h2 class="vxr-card-title" style="font-size:18px">{{ $label }}</h2></div>
                <div class="vxr-card-body"><dl class="vxr-dl">
                    <div><dt>Whatnot earnings + tips</dt><dd>{{ $money($t['earnings'], 2) }}</dd></div>
                    <div><dt>COGS</dt><dd>{{ $money(-$t['cogs'], 2) }}</dd></div>
                    <div><dt>Worker payouts</dt><dd>{{ $money(-$t['payouts'], 2) }}</dd></div>
                    <div class="is-total"><dt>Profit after pay</dt><dd class="{{ $t['profit'] < 0 ? 'vxr-val-bad' : 'vxr-val-ok' }}">{{ $money($t['profit'], 2) }}</dd></div>
                </dl></div>
            </section>
        @endif

        <section class="vxr-card">
            <div class="vxr-card-head" style="align-items:flex-start">
                <div style="display:flex;gap:12px"><x-filament::icon icon="heroicon-o-clipboard-document-check" style="width:28px;height:28px;color:var(--vxw-muted);flex:none" />
                    <div><h2 class="vxr-card-title" style="font-size:18px">Finalization Checklist</h2><p class="vxr-card-sub">All items must be complete to finalize profit for this period.</p></div></div>
            </div>
            <div class="vxr-card-body" style="display:grid;gap:16px">
                @foreach ($this->checklist() as [$name, $text, $done, $go])
                    <div style="display:flex;gap:12px;align-items:flex-start">
                        @if ($done)
                            <x-filament::icon icon="heroicon-s-check-circle" style="width:26px;height:26px;color:var(--vxw-ok);flex:none" />
                        @else
                            <span style="width:24px;height:24px;margin:1px;border-radius:999px;border:2.5px solid #f59e0b;flex:none"></span>
                        @endif
                        <div style="min-width:0">
                            <div style="font-weight:650;font-size:15px">{{ $name }}</div>
                            <div class="vxr-hint" style="margin-top:2px">
                                @if ($go === 'incomplete')<button type="button" class="vxr-link" style="background:none;border:0;padding:0;font-weight:500;cursor:pointer" wire:click="setTab('incomplete')">{{ $text }}</button>
                                @elseif ($go === 'time' && \App\Filament\Pages\TimeAttendance::canAccess())<a class="vxr-link" style="font-weight:500" href="{{ \App\Filament\Pages\TimeAttendance::getUrl() }}">{{ $text }}</a>
                                @elseif ($go === 'payroll' && \App\Filament\Pages\PayrollOverview::canAccess())<a class="vxr-link" style="font-weight:500" href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}">{{ $text }}</a>
                                @else {{ $text }} @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Every show --}}
    @php $shows = $this->showRows(); @endphp
    <section class="vxr-card">
        <div class="vxr-card-head">
            <div><h2 class="vxr-card-title" style="font-size:17px">{{ \App\Filament\Pages\ProfitLoss::TABS[$tab] ?? 'Shows' }}</h2><p class="vxr-card-sub">{{ $shows->count() }} of {{ $t['shows'] }} {{ \Illuminate\Support\Str::plural('show', $t['shows']) }} · Click a show for its breakdown.</p></div>
            <select wire:model.live="sort" class="vxr-select" aria-label="Sort"><option value="date">Newest</option><option value="profit">Biggest loss first</option><option value="profit_desc">Most profit first</option><option value="sales">Most sales first</option></select>
        </div>
        <div class="vxr-table-wrap">
            <table class="vxr-table" style="min-width:980px">
                <thead><tr>
                    <th>Show</th><th>Channel</th><th>Streamer</th><th class="num">Sales</th><th class="num">Earnings</th><th class="num">COGS</th><th class="num">Worker Payouts</th><th class="num">Profit / Loss</th><th>Financial Status</th>
                </tr></thead>
                <tbody>
                    @forelse ($shows->take($shown) as $r)
                        @php $final = $r['incomplete'] === []; @endphp
                        <tr wire:key="pl-{{ $r['id'] }}" wire:click="select({{ $r['id'] }})" @class(['is-click', 'is-selected' => $selected === $r['id']])>
                            <td><span class="strong">{{ \Illuminate\Support\Str::limit($r['title'], 48) }}</span><span class="sub">{{ $r['date']?->format('M j, Y') }}@if($r['start']) {{ $r['start']->format('g:i A') }}@endif</span></td>
                            <td>{{ $r['channel'] }}</td>
                            <td>{{ $r['streamer'] }}@if($r['cohosts'])<span class="sub">+ {{ implode(', ', $r['cohosts']) }}</span>@endif</td>
                            <td class="num">{{ $money($r['sales'], 2) }}</td>
                            <td class="num {{ $r['whatnot_net'] < 0 ? 'vxr-val-bad' : '' }}">{{ $money($r['earnings'], 2) }}</td>
                            <td class="num">@if($r['cogs_recorded']){{ $money($r['cogs'], 2) }}@else<span class="vxr-val-warn">Missing</span>@endif</td>
                            <td class="num">@if($r['pay_final'] || $r['payouts'] != 0){{ $money($r['payouts'], 2) }}@if(! $r['pay_final'])<span class="sub vxr-val-warn">not final</span>@endif @else<span class="vxr-val-warn">Pending</span>@endif</td>
                            <td class="num strong {{ $r['profit'] < 0 ? 'vxr-val-bad' : 'vxr-val-ok' }}">{{ $money($r['profit'], 2) }}@unless($final)<span class="sub">may change</span>@endunless</td>
                            <td>
                                @if ($final)<span class="vxr-pill vxr-pill--ok">Finalized</span>
                                @else<span class="vxr-pill vxr-pill--warn" title="{{ implode(' · ', $r['incomplete']) }}"><x-filament::icon icon="heroicon-o-exclamation-triangle" /> Incomplete</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="vxr-empty"><b>No shows here</b>Nothing matches this tab and these filters.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($shows->count() > $shown)<div class="vxr-card-foot"><span>Showing {{ $shown }} of {{ $shows->count() }}</span><button type="button" wire:click="showMore" class="vxr-btn vxr-btn--sm">Show more</button></div>@endif
    </section>

    {{-- How streamers are doing --}}
    @php $streamers = $this->streamerTrend(); @endphp
    <section class="vxr-card">
        <div class="vxr-card-head"><div><h2 class="vxr-card-title" style="font-size:17px">How streamers are doing</h2><p class="vxr-card-sub">Shows they hosted as primary in this period, compared with the period before.</p></div></div>
        <div class="vxr-table-wrap">
            <table class="vxr-table" style="min-width:820px">
                <thead><tr><th>Streamer</th><th class="num">Shows</th><th class="num">Sales</th><th class="num">Pay</th><th class="num">Profit</th><th class="num">Per show</th><th class="num">Per hour</th><th class="num">Losing</th><th class="num">vs before</th></tr></thead>
                <tbody>
                    @forelse ($streamers as $s)
                        @php $chg = $s['prev_profit'] !== null ? $s['profit'] - $s['prev_profit'] : null; @endphp
                        <tr>
                            <td class="strong">{{ $s['label'] }}</td>
                            <td class="num">{{ $s['shows'] }}</td>
                            <td class="num">{{ $money($s['sales']) }}</td>
                            <td class="num">{{ $money($s['payouts']) }}</td>
                            <td class="num strong {{ $s['profit'] < 0 ? 'vxr-val-bad' : '' }}">{{ $money($s['profit']) }}</td>
                            <td class="num">{{ $money($s['profit_per_show']) }}</td>
                            <td class="num">{{ $s['profit_per_hour'] !== null ? $money($s['profit_per_hour']) : '—' }}</td>
                            <td class="num">{{ $s['losing'] ?: '—' }}</td>
                            <td class="num {{ $chg === null ? '' : ($chg >= 0 ? 'vxr-val-ok' : 'vxr-val-bad') }}">{{ $chg === null ? 'new' : ($chg >= 0 ? '+' : '-').'$'.number_format(abs($chg)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="vxr-empty">No shows in this period.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Breakdowns --}}
    @php $rowsBy = $this->breakdown(); $maxAbs = max(1, $rowsBy->max(fn ($r) => abs($r['profit']))); @endphp
    <section class="vxr-card">
        <div class="vxr-card-head">
            <div><h2 class="vxr-card-title" style="font-size:17px">Breakdown</h2><p class="vxr-card-sub">What's winning and what's losing.</p></div>
            <div class="vxr-seg" role="tablist" aria-label="Break down by">
                @foreach ($breakdownLabels as $key => $text)
                    <button type="button" role="tab" wire:click="$set('breakdownBy', '{{ $key }}')" @class(['is-active' => $breakdownBy === $key])>{{ $text }}</button>
                @endforeach
            </div>
        </div>
        <div class="vxr-table-wrap">
            <table class="vxr-table" style="min-width:760px">
                <thead><tr><th>{{ $breakdownLabels[$breakdownBy] ?? 'Group' }}</th><th class="num">Shows</th><th class="num">Sales</th><th class="num">Earnings</th><th class="num">COGS</th><th class="num">Pay</th><th class="num">Profit</th><th style="width:22%"></th></tr></thead>
                <tbody>
                    @foreach ($rowsBy as $r)
                        <tr>
                            <td class="strong">{{ $r['label'] }}</td>
                            <td class="num">{{ $r['shows'] }}</td>
                            <td class="num">{{ $money($r['sales']) }}</td>
                            <td class="num">{{ $money($r['earnings']) }}</td>
                            <td class="num">{{ $money($r['cogs']) }}</td>
                            <td class="num">{{ $money($r['payouts']) }}</td>
                            <td class="num strong {{ $r['profit'] < 0 ? 'vxr-val-bad' : '' }}">{{ $money($r['profit']) }}</td>
                            <td><div style="height:8px;border-radius:4px;width:{{ max(2, round(abs($r['profit']) / $maxAbs * 100)) }}%;background:{{ $r['profit'] < 0 ? 'var(--vxw-bad)' : 'var(--vxw-ok)' }}"></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="vxr-card" style="display:flex;gap:14px;align-items:center;padding:16px 20px">
        <x-filament::icon icon="heroicon-o-bell" style="width:28px;height:28px;color:var(--vxw-accent);flex:none" />
        <div><b style="font-size:15px">Use notifications for daily or weekly loss summaries.</b>
            <div class="vxr-hint" style="margin-top:2px">Finalized business losses and negative Whatnot earnings can be sent on a schedule — set it up in <a class="vxr-link" href="{{ \App\Filament\Pages\NotificationSettings::getUrl(['configure' => 'show_lost_money']) }}">notification settings</a>.</div></div>
    </div>
</div>

{{-- Show detail panel --}}
@if ($detail)
    <div class="vxr-drawer-backdrop" wire:click="select({{ $detail['id'] }})"></div>
    <aside class="vxr-drawer" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.select({{ $detail['id'] }})">
        <div class="vxr-drawer-head">
            <div style="min-width:0">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><h2 class="vxr-drawer-title">{{ $detail['title'] }}</h2>
                    @if ($detail['incomplete'] === [])<span class="vxr-pill vxr-pill--ok">Finalized</span>@else<span class="vxr-pill vxr-pill--warn">Incomplete</span>@endif</div>
                <div class="vxr-subtitle" style="margin-top:6px">{{ $detail['channel'] }} &nbsp;|&nbsp; {{ $detail['date']?->format('M j, Y') }}@if($detail['hours'] !== null) &nbsp;|&nbsp; {{ intdiv((int) round($detail['hours'] * 60), 60) }}h {{ str_pad((string) ((int) round($detail['hours'] * 60) % 60), 2, '0', STR_PAD_LEFT) }}m @endif</div>
            </div>
            <button type="button" class="vxr-x" wire:click="select({{ $detail['id'] }})" aria-label="Close"><x-filament::icon icon="heroicon-o-x-mark" /></button>
        </div>
        <div class="vxr-drawer-body">
            @if ($detail['incomplete'] !== [])
                <div class="vxr-banner vxr-banner--warn" style="font-size:13.5px"><x-filament::icon icon="heroicon-o-exclamation-triangle" /><span>Still missing: {{ implode(' · ', $detail['incomplete']) }}</span></div>
            @endif
            <div>
                <p class="vxr-section-label">Pay runs</p>
                @forelse ($detail['pay_runs'] as $p)
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--vxw-border);border-radius:12px;background:var(--vxw-surface-2);margin-bottom:8px">
                        <div style="min-width:0"><div style="font-weight:650">{{ $p['who'] }} · {{ $money($p['amount'], 2) }}</div><div class="vxr-hint" style="margin:2px 0 0">{{ $p['run'] }}</div></div>
                        <span class="vxr-pill {{ $p['status'] === 'Paid' ? 'vxr-pill--ok' : 'vxr-pill--warn' }}">{{ $p['status'] }}</span>
                    </div>
                @empty
                    <p class="vxr-hint" style="margin:0">No pay calculated for this show yet.</p>
                @endforelse
            </div>
            <div>
                <p class="vxr-section-label">Financial breakdown</p>
                <dl class="vxr-dl">
                    <div><dt>Gross sales</dt><dd>{{ $money($detail['sales'], 2) }}</dd></div>
                    <div><dt>Whatnot earnings</dt><dd class="{{ $detail['whatnot_net'] < 0 ? 'vxr-val-bad' : '' }}">{{ $money($detail['whatnot_net'], 2) }}</dd></div>
                    @if ($detail['tips'] != 0)<div><dt>Tips</dt><dd>{{ $money($detail['tips'], 2) }}</dd></div>@endif
                    <div><dt>COGS</dt><dd>@if($detail['cogs_recorded']){{ $money($detail['cogs'], 2) }}@else<span class="vxr-val-warn">Missing</span>@endif</dd></div>
                    <div><dt>Worker payouts</dt><dd>{{ $money($detail['payouts'], 2) }}</dd></div>
                    <div class="is-total"><dt>{{ $detail['profit'] < 0 ? 'Business loss' : 'Business profit' }}</dt><dd class="{{ $detail['profit'] < 0 ? 'vxr-val-bad' : 'vxr-val-ok' }}">{{ $money($detail['profit'], 2) }}</dd></div>
                </dl>
                <p class="vxr-foot-note" style="margin-top:10px"><x-filament::icon icon="heroicon-o-information-circle" /> Fees included in Whatnot earnings are not deducted twice.</p>
            </div>
        </div>
        <div class="vxr-drawer-foot">
            <a class="vxr-btn vxr-btn--primary vxr-btn--block" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $detail['id']]) }}" style="height:48px">View Show <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" /></a>
        </div>
    </aside>
@endif

@include('filament.components.vxr-send-summary', [
    'title' => 'Send Business Loss Summary',
    'digest' => $this->sendOpen ? $this->lossDigest() : ['label' => '', 'count' => 0, 'total' => 0],
    'countLabel' => 'finalized shows that lost money',
    'amountLabel' => 'lost after COGS and pay',
])
</x-filament-panels::page>
