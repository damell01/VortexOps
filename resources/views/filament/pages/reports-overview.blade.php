<x-filament-panels::page>
<x-reports.shell>
    @php
        $t = $this->totals();
        $links = $this->links();
        $money = fn ($v) => ($v < 0 ? '−$' : '$').number_format(abs($v), abs($v) >= 100 ? 0 : 2);
        $businesses = $this->businesses();
        $financials = (bool) auth()->user()?->canSeeBusinessFinancials();
        $kpis = $this->kpis();
        if (! $financials) unset($kpis['profit']);
        $flow = $this->flow();
        $channels = $this->channels();
        $chColors = ['var(--vxc-1)', 'var(--vxc-2)', 'var(--vxc-3)', 'var(--vxc-4)', 'var(--vxw-muted)'];
        $streamers = $this->streamers();
        $maxNet = max(1, collect($streamers)->max('net'));
        $recent = $this->recent();
        $goals = $this->goals();
        $pace = app(\App\Services\GoalProgress::class)->pace('whatnot_net');
        $avColors = ['#7c3aed', '#2563eb', '#db2777', '#0891b2', '#ea580c', '#16a34a', '#9333ea', '#4f46e5'];
        $first = \Illuminate\Support\Str::before(auth()->user()?->name ?? '', ' ');
        $prevLabel = 'vs previous '.((int) $this->period()[0]->diffInDays($this->period()[1]) + 1).' days';
        $compareUrl = \App\Filament\Pages\ReportCompare::canAccess() ? \App\Filament\Pages\ReportCompare::getUrl() : null;
    @endphp

    <div class="vxr" data-vx-page="reports-overview">
        <div class="vxr-head">
            <div>
                <h1 class="vxr-greet">{{ $first ? 'Welcome back, '.$first : 'Executive overview' }}</h1>
                <p class="vxr-subtitle">How the business is doing {{ strtolower(\App\Filament\Pages\ReportsOverview::RANGES[$range] ?? '') }} · {{ $this->periodLabel() }}</p>
            </div>
            <div class="vxr-actions">
                <div class="vxr-seg" role="group" aria-label="Period">
                    @foreach (['this_week' => 'Week', 'this_month' => 'Month', 'last_30' => '30D', 'this_year' => 'Year'] as $k => $label)
                        <button type="button" wire:click="$set('range', '{{ $k }}')" @class(['is-active' => $range === $k])>{{ $label }}</button>
                    @endforeach
                </div>
                <label class="vxr-control">
                    <x-filament::icon icon="heroicon-o-calendar" />
                    <select wire:model.live="range" aria-label="More periods">
                        @foreach (\App\Filament\Pages\ReportsOverview::RANGES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
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

        <div class="vxr-stats" style="grid-template-columns:repeat({{ count($kpis) }},minmax(0,1fr))" wire:key="kpis-{{ $range }}-{{ $business }}">
            @foreach ($kpis as $key => [$label, $value, $delta, $spark, $icon, $isMoney])
                <x-vxr.stat :label="$label" :value="$isMoney ? $money($value) : number_format($value)" :icon="$icon"
                    :delta="$delta" :delta-note="$delta === null ? null : $prevLabel" :spark="$spark" :featured="$key === 'net'"
                    :value-class="$value < 0 ? 'vxr-val-bad' : ''">
                    @if ($delta === null)<span class="vxr-val-muted">No earlier figure</span>@endif
                </x-vxr.stat>
            @endforeach
        </div>

        <x-reports.pace :pace="$pace" :goals-url="\App\Filament\Pages\Goals::canAccess() ? \App\Filament\Pages\Goals::getUrl() : null" />

        <div class="vxr-dash">
            <section class="vxr-card span-8">
                <div class="vxr-card-head">
                    <div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-chart-bar" /> Money flow</h2><p class="vxr-card-sub">Gross sales against what Whatnot pays out, by {{ $flow['unit'] }}. Hover a {{ $flow['unit'] }} for both.</p></div>
                    <div class="vxr-legend"><span><i style="height:8px;width:8px;border-radius:2px;background:var(--vxw-accent)"></i>Gross sales</span><span><i style="height:8px;width:8px;border-radius:2px;background:color-mix(in srgb,var(--vxw-accent) 35%,var(--vxw-surface))"></i>Whatnot net</span></div>
                </div>
                <div class="vxr-card-body">
                    <x-vxr.grouped :labels="$flow['labels']" :height="250" aria-label="Gross sales and Whatnot net"
                        :series="[['name' => 'Gross sales', 'values' => $flow['sales'], 'color' => 'var(--vxw-accent)'], ['name' => 'Whatnot net', 'values' => $flow['net'], 'color' => 'color-mix(in srgb,var(--vxw-accent) 35%,var(--vxw-surface))']]" />
                </div>
            </section>

            <section class="vxr-card span-4">
                <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-signal" /> Channel mix</h2><p class="vxr-card-sub">Share of Whatnot net.</p></div>
                    @if ($compareUrl && count($channels) >= 2)<a class="vxr-btn vxr-btn--sm vxr-btn--outline" href="{{ $compareUrl }}?kind=channel&items={{ collect($channels)->pluck('id')->filter()->take(2)->implode(',') }}">Compare</a>@endif
                </div>
                <div class="vxr-card-body">
                    <x-vxr.donut center-label="Whatnot net"
                        :segments="collect($channels)->values()->map(fn ($c, $i) => ['label' => $c['label'], 'value' => max(0, $c['value']), 'color' => $chColors[min($i, 4)]])->all()" />
                </div>
            </section>

            <section class="vxr-card span-5">
                <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-trophy" /> Top streamers</h2><p class="vxr-card-sub">By Whatnot net this period.</p></div></div>
                <div class="vxr-card-body vxr-lead">
                    @forelse ($streamers as $i => $s)
                        <div class="vxr-lead-row vxrs-hover-add">
                            <span @class(['rk', 'm'.($i + 1) => $i < 3])>{{ $i + 1 }}</span>
                            <span class="vxr-av" style="background:linear-gradient(135deg,{{ $avColors[$s['id'] % 8] }},{{ $avColors[($s['id'] + 3) % 8] }})">{{ strtoupper(mb_substr($s['name'], 0, 2)) }}</span>
                            <span class="nm">{{ $s['name'] }}<small>{{ $s['shows'] }} {{ \Illuminate\Support\Str::plural('show', $s['shows']) }}@if($s['nph']) · ${{ number_format($s['nph']) }}/hr @endif</small><span class="vxr-mini-bar"><i style="width:{{ max(2, $s['net'] / $maxNet * 100) }}%"></i></span></span>
                            <span class="v">{{ $money($s['net']) }}</span>
                            <x-reports.compare-add kind="streamer" :id="$s['id']" :name="$s['name']" :small="true" />
                        </div>
                    @empty
                        <div class="vxr-empty"><b>No streamer shows yet</b>Shows appear here once they air.</div>
                    @endforelse
                </div>
            </section>

            <section class="vxr-card span-4">
                <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-flag" /> {{ now()->format('F') }} goals</h2><p class="vxr-card-sub">The tick marks a steady pace for today.</p></div>
                    @if (\App\Filament\Pages\Goals::canAccess())<a class="vxr-btn vxr-btn--sm vxr-btn--outline" href="{{ \App\Filament\Pages\Goals::getUrl() }}">{{ $goals->isEmpty() ? 'Set goals' : 'Edit' }}</a>@endif
                </div>
                <div class="vxr-card-body">
                    @if ($goals->isEmpty())
                        <div class="vxr-empty"><b>No goals this month</b>Set a target for Whatnot net or gross sales and track it here.</div>
                    @else
                        <x-vxr.progress :rows="$goals->map(fn ($g) => [
                            'label' => $g['label'], 'sub' => $g['scope'],
                            'value' => ($g['money'] ? '$'.number_format($g['actual'], 0) : number_format($g['actual'])).' / '.($g['money'] ? '$'.number_format($g['target'], 0) : number_format($g['target'])),
                            'pct' => $g['pct'], 'expected' => $g['expected'],
                            'tone' => in_array($g['status'], ['done', 'on_track'], true) ? 'ok' : ($g['status'] === 'missed' || $g['status'] === 'behind' ? 'bad' : 'accent'),
                        ])->all()" />
                    @endif
                </div>
            </section>

            <section class="vxr-card span-3">
                <div class="vxr-card-head"><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-exclamation-triangle" style="color:var(--vxw-bad)" /> Needs attention</h2></div>
                <div class="vxr-card-body vxr-links">
                    <a class="vxr-linkrow vxr-tone-bad" href="{{ $links['losses'] }}"><span class="vxr-linkrow-icon"><x-filament::icon icon="heroicon-o-exclamation-circle" /></span><span><b>Negative Whatnot earnings</b><span>{{ $t['whatnot_negative'] }} {{ \Illuminate\Support\Str::plural('show', $t['whatnot_negative']) }}@if($t['whatnot_negative']) · {{ $money($t['whatnot_negative_sum']) }}@endif</span></span><x-filament::icon icon="heroicon-m-chevron-right" /></a>
                    @if ($financials)<a class="vxr-linkrow vxr-tone-bad" href="{{ $links['finalized_losses'] }}"><span class="vxr-linkrow-icon"><x-filament::icon icon="heroicon-o-exclamation-circle" /></span><span><b>Finalized losses</b><span>{{ $t['finalized_losing'] }} {{ \Illuminate\Support\Str::plural('show', $t['finalized_losing']) }}</span></span><x-filament::icon icon="heroicon-m-chevron-right" /></a>@endif
                    <a class="vxr-linkrow vxr-tone-warn" href="{{ $links['pending'] }}"><span class="vxr-linkrow-icon"><x-filament::icon icon="heroicon-o-clock" /></span><span><b>Pending costs &amp; pay</b><span>{{ $t['incomplete'] }} {{ \Illuminate\Support\Str::plural('show', $t['incomplete']) }}</span></span><x-filament::icon icon="heroicon-m-chevron-right" /></a>
                </div>
            </section>

            <section class="vxr-card span-12">
                <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-video-camera" /> Latest shows</h2><p class="vxr-card-sub">The most recent shows in this period.</p></div>
                    @if ($financials)<a class="vxr-btn vxr-btn--sm vxr-btn--outline" href="{{ $links['pl'] }}">All shows</a>@endif
                </div>
                <div class="vxr-card-body" style="overflow-x:auto">
                    @if ($recent->isEmpty())
                        <div class="vxr-empty"><b>No shows in this period</b>Pick a longer period.</div>
                    @else
                        <table class="vxr-table2">
                            <thead><tr><th>Show</th><th>Streamer</th><th>Channel</th><th class="n">Gross</th><th class="n">Whatnot net</th>@if($financials)<th class="n">Profit</th>@endif<th>Status</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($recent as $r)
                                <tr class="vxrs-hover-add">
                                    <td><a class="t1" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $r['id']]) }}">{{ \Illuminate\Support\Str::limit($r['title'], 42) }}</a><div class="t2">{{ $r['date']?->format('D, M j') }}@if($r['start']) · {{ $r['start']->format('g:ia') }}@endif</div></td>
                                    <td><span style="display:inline-flex;align-items:center;gap:8px"><span class="vxr-av" style="width:24px;height:24px;font-size:9.5px;background:linear-gradient(135deg,{{ $avColors[($r['streamer_id'] ?? 0) % 8] }},{{ $avColors[(($r['streamer_id'] ?? 0) + 3) % 8] }})">{{ strtoupper(mb_substr($r['streamer'], 0, 2)) }}</span>{{ $r['streamer'] }}</span></td>
                                    <td>{{ $r['channel'] }}</td>
                                    <td class="n">{{ $money($r['sales']) }}</td>
                                    <td class="n" @if($r['whatnot_net'] < 0) style="color:var(--vxw-bad)" @endif>{{ $money($r['whatnot_net']) }}</td>
                                    @if($financials)<td class="n" @if($r['profit'] < 0) style="color:var(--vxw-bad)" @endif>{{ $money($r['profit']) }}</td>@endif
                                    <td>@if ($r['whatnot_net'] < 0)<span class="vxr-chip bad">Loss</span>@elseif ($r['incomplete'])<span class="vxr-chip warn" title="{{ implode(', ', $r['incomplete']) }}">Pending</span>@else<span class="vxr-chip ok">Final</span>@endif</td>
                                    <td><x-reports.compare-add kind="show" :id="$r['id']" :name="\Illuminate\Support\Str::limit($r['title'], 30)" :small="true" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
        </div>

        @if ($financials)<p class="vxr-foot-note"><x-filament::icon icon="heroicon-o-information-circle" /> Profit (est.) counts every aired show with the costs and pay recorded so far; finalized profit is on Business Profitability.</p>@endif
    </div>
</x-reports.shell>
</x-filament-panels::page>
