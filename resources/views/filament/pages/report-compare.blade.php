<x-filament-panels::page>
@php
    $c = $this->comparison();
    $items = $c['items'];
    $ids = $this->ids();
    $opts = $this->options();
    $M = \App\Filament\Pages\ReportCompare::METRICS;
    $colors = ['var(--vxc-1)', 'var(--vxc-2)', 'var(--vxc-3)', 'var(--vxc-4)'];
    $fmt = function ($k, $v) use ($M) {
        if ($v === null) return '—';
        if ($k === 'margin') return number_format($v, 1).'%';
        if ($k === 'hours') return number_format($v, 1).' h';
        return $M[$k][1] ? (($v < 0 ? '−$' : '$').number_format(abs($v), 0)) : number_format($v);
    };
    $leader = collect($items)->sortByDesc(fn ($it) => $it['f']['net'])->keys()->first();
@endphp
<x-reports.shell>
<div class="vxr">
    <div class="vxr-head">
        <div>
            <h1 class="vxr-title">Compare</h1>
            <p class="vxr-subtitle">Pick what to compare, then up to four of them. “+ Compare” on any report sends them here.</p>
        </div>
    </div>

    <div class="vxr-toolbar vxr-toolbar--card">
        <div class="vxr-seg" role="group" aria-label="Compare what">
            @foreach (\App\Filament\Pages\ReportCompare::KINDS as $k => $label)
                <button type="button" wire:click="setKind('{{ $k }}')" @class(['is-active' => $this->kind === $k]) aria-pressed="{{ $this->kind === $k ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        @if (! in_array($this->kind, ['period', 'show'], true))
            <div class="vxr-seg" role="group" aria-label="Date range">
                @foreach (\App\Filament\Pages\ReportCompare::RANGES as $k => $label)
                    <button type="button" wire:click="setRange('{{ $k }}')" @class(['is-active' => $this->range === (string) $k])>{{ $label }}</button>
                @endforeach
            </div>
        @endif
        <span class="vxr-val-muted" style="margin-left:auto;font-size:12.5px">
            {{ $this->kind === 'period' ? 'Each period uses its own dates' : ($this->kind === 'show' ? 'Pick any shows' : 'Same dates for each: '.$this->rangeDates()[0]->format('M j').' – '.$this->rangeDates()[1]->format('M j')) }}
        </span>
    </div>

    <div class="vxcp-pick">
        @foreach ($ids as $i => $id)
            @if ($i)<span class="vxcp-vs">VS</span>@endif
            <label class="vxcp-slot">
                <i style="background:{{ $colors[$i] }}"></i>
                <select aria-label="Pick {{ $i + 1 }}" wire:change="setItem({{ $i }}, $event.target.value)">
                    @foreach ($opts as $v => $label)
                        <option value="{{ $v }}" @selected((string) $v === (string) $id)>{{ \Illuminate\Support\Str::limit($label, 60) }}</option>
                    @endforeach
                </select>
                @if (count($ids) > 2)<button type="button" class="vxcp-rm" wire:click="removeItem({{ $i }})" aria-label="Remove">×</button>@endif
            </label>
        @endforeach
        @if (count($ids) < 4)<button type="button" class="vxr-btn vxr-btn--outline" wire:click="addItem">+ Add another</button>@endif
        @if (count($ids) === 2)<button type="button" class="vxr-btn vxr-btn--outline" wire:click="swap" title="Swap sides">⇄ Swap</button>@endif
    </div>

    <div class="vxcp-verdict" wire:key="verdict-{{ $this->kind }}-{{ $this->items }}-{{ $this->range }}">
        <i><x-filament::icon icon="heroicon-o-scale" /></i>
        <div><b>{{ $c['verdict'][0] }}</b><span>{{ $c['verdict'][1] }}</span></div>
    </div>

    <div class="vxcp-cards">
        @foreach ($items as $it)
            <div class="vxcp-card" style="--c:{{ $colors[$it['i']] }}" wire:key="card-{{ $it['id'] }}-{{ $it['i'] }}">
                @if ($it['i'] === $leader && $it['f']['shows'])<span class="vxcp-crown">★ Leader</span>@endif
                <h4>{{ $it['name'] }}</h4>
                <div class="sub">{{ $it['sub'] }}</div>
                <div class="big">{{ $fmt('net', $it['f']['net']) }}</div>
                <div class="sub">Whatnot net · {{ $fmt('net_hour', $it['f']['net_hour']) }}/hr · {{ $fmt('margin', $it['f']['margin']) }} margin</div>
            </div>
        @endforeach
    </div>

    <div class="vxr-grid vxr-grid--2" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr)">
        <section class="vxr-card">
            <div class="vxr-card-head"><div><h3 class="vxr-card-title"><x-filament::icon icon="heroicon-o-arrows-right-left" /> Head to head</h3><p class="vxr-card-sub">The best figure on each line is marked. The bar is each one’s share.</p></div></div>
            <div class="vxr-card-body" style="overflow-x:auto">
                <table class="vxcp-h2h">
                    <thead><tr><th>Metric</th>
                        @foreach ($items as $it)<th><span class="vxcp-dot" style="background:{{ $colors[$it['i']] }}"></span>{{ \Illuminate\Support\Str::limit($it['name'], 18) }}</th>@endforeach
                        @if (count($items) === 2)<th>Difference</th>@endif
                        <th style="text-align:left">Share</th></tr></thead>
                    <tbody>
                    @foreach ($M as $k => [$label, $isMoney, $higher])
                        @php
                            $vals = array_map(fn ($it) => $it['f'][$k], $items);
                            $sum = array_sum(array_map(fn ($v) => max(0, (float) $v), $vals));
                            $diff = count($items) === 2 && $vals[1] ? (($vals[0] ?? 0) - $vals[1]) / abs($vals[1]) * 100 : null;
                        @endphp
                        <tr>
                            <td>{{ $label }}</td>
                            @foreach ($vals as $i => $v)<td @class(['is-win' => $c['best'][$k] === $i])>{{ $fmt($k, $v) }}</td>@endforeach
                            @if (count($items) === 2)
                                <td>@if ($diff !== null && $vals[0] !== null)<span @class(['vxr-delta', 'is-up' => $higher ? $diff > 0 : $diff < 0, 'is-down' => $higher ? $diff < 0 : $diff > 0])>{{ ($diff >= 0 ? '+' : '').number_format($diff, 0) }}%</span>@else — @endif</td>
                            @endif
                            <td style="text-align:left"><div class="vxcp-tug">@foreach ($vals as $i => $v)<i style="width:{{ $sum ? max(0, (float) $v) / $sum * 100 : 0 }}%;background:{{ $colors[$i] }}" title="{{ $items[$i]['name'] }}: {{ $fmt($k, $v) }}"></i>@endforeach</div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section class="vxr-card">
            <div class="vxr-card-head"><div><h3 class="vxr-card-title"><x-filament::icon icon="heroicon-o-banknotes" /> Where each dollar went</h3><p class="vxr-card-sub">Share of gross sales.</p></div></div>
            <div class="vxr-card-body">
                @foreach ($items as $it)
                    @php
                        $g = max(0.01, (float) $it['f']['sales']);
                        $parts = [['Profit', max(0, $it['f']['profit']), 'var(--vxw-accent)', '#fff'], ['Fees', $it['f']['fees'], 'var(--vxw-muted)', '#fff'], ['Goods', $it['f']['cogs'], 'var(--vxw-border-strong, #c4c0d0)', 'var(--vxw-text)'], ['Pay', $it['f']['pay'], 'var(--vxw-sunken)', 'var(--vxw-text)']];
                        $tot = max(0.01, array_sum(array_column($parts, 1)));
                    @endphp
                    <div class="vxcp-split">
                        <span title="{{ $it['name'] }}"><span class="vxcp-dot" style="background:{{ $colors[$it['i']] }}"></span>{{ $it['name'] }}</span>
                        <div class="vxcp-bar">
                            @foreach ($parts as [$n, $v, $bg, $fg])
                                <i style="width:{{ $v / $tot * 100 }}%;background:{{ $bg }};color:{{ $fg }}" title="{{ $n }}: ${{ number_format($v, 0) }} ({{ round($v / $g * 100) }}% of gross)">{{ $v / $tot > .12 ? round($v / $g * 100).'%' : '' }}</i>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div class="vxr-legend" style="margin-top:10px">
                    <span><i style="height:10px;width:10px;border-radius:3px;background:var(--vxw-accent)"></i>Profit</span>
                    <span><i style="height:10px;width:10px;border-radius:3px;background:var(--vxw-muted)"></i>Whatnot fees</span>
                    <span><i style="height:10px;width:10px;border-radius:3px;background:var(--vxw-border-strong, #c4c0d0)"></i>Cost of goods</span>
                    <span><i style="height:10px;width:10px;border-radius:3px;background:var(--vxw-sunken)"></i>Streamer pay</span>
                </div>
            </div>
        </section>
    </div>

    @if ($c['race'])
        @php
            $race = $c['race']; $n = count($race['labels']);
            $all = array_merge(...$race['lines']); $max = max(array_merge([0], $all)); $min = min(array_merge([0], $all));
            if ($max == $min) $max = $min + 1;
            $span = $max - $min; $mag = pow(10, floor(log10($span))); $step = ceil($span / 4 / $mag * 2) / 2 * $mag ?: 1;
            $top = ceil($max / $step) * $step; $bot = floor($min / $step) * $step;
            $W = 1000; $H = 240; $L = 0; $T = 8; $B = 8;
            $x = fn ($i) => $n > 1 ? $i * $W / ($n - 1) : $W / 2;
            $y = fn ($v) => $T + ($top - $v) / ($top - $bot) * ($H - $T - $B);
            $short = function ($v) use ($race) { $a = abs($v); $s = $v < 0 ? '−' : ''; $t = $a >= 1e6 ? round($a / 1e6, 1).'M' : ($a >= 1000 ? round($a / 1000, $a >= 1e4 ? 0 : 1).'k' : (string) round($a)); return $s.($race['money'] ? '$' : '').$t; };
            $every = max(1, (int) ceil($n / 8));
            $tip = array_map(fn ($i) => ['l' => $race['labels'][$i], 'x' => round($x($i) / $W * 100, 3), 'v' => array_map(fn ($ln) => $race['money'] ? '$'.number_format($ln[$i], 0) : number_format($ln[$i]), $race['lines'])], range(0, $n - 1));
        @endphp
        <section class="vxr-card" wire:key="race-{{ $this->kind }}-{{ $this->items }}-{{ $this->metric }}-{{ (int) $this->running }}-{{ $this->range }}">
            <div class="vxr-card-head" style="flex-wrap:wrap;gap:8px">
                <div><h3 class="vxr-card-title"><x-filament::icon icon="heroicon-o-chart-bar" /> {{ \App\Filament\Pages\ReportCompare::CHART_METRICS[$this->metric] }} over time</h3>
                    <p class="vxr-card-sub">{{ $this->running ? 'Running total: see who pulls ahead and when.' : 'Each day (or week) on its own.' }}{{ $this->kind === 'period' ? ' Lined up by day of each period.' : '' }}</p></div>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <div class="vxr-seg"><button type="button" wire:click="setRunning(true)" @class(['is-active' => $this->running])>Running total</button><button type="button" wire:click="setRunning(false)" @class(['is-active' => ! $this->running])>Each period</button></div>
                    <div class="vxr-seg">@foreach (\App\Filament\Pages\ReportCompare::CHART_METRICS as $k => $label)<button type="button" wire:click="setMetric('{{ $k }}')" @class(['is-active' => $this->metric === $k])>{{ $label }}</button>@endforeach</div>
                </div>
            </div>
            <div class="vxr-card-body">
                <div class="vxr-legend" style="margin-bottom:8px">@foreach ($items as $it)<span><i style="background:{{ $colors[$it['i']] }}"></i>{{ $it['name'] }}</span>@endforeach</div>
                <div class="vxcp-race" style="display:grid;grid-template-columns:52px minmax(0,1fr);gap:6px">
                    <div style="position:relative;height:{{ $H }}px">
                        @for ($g = $bot; $g <= $top + 0.0001; $g += $step)<span style="position:absolute;right:4px;top:{{ $y($g) / $H * 100 }}%;transform:translateY(-50%);font-size:11px;color:var(--vxw-muted)">{{ $short($g) }}</span>@endfor
                    </div>
                    <div class="vxr-chart-wrap" x-data="{ i: null, d: @js($tip), pick(e) { const r = this.$el.getBoundingClientRect(); const px = (e.clientX - r.left) / r.width * 100; let b = 0, bd = 1e9; this.d.forEach((p, k) => { const dd = Math.abs(p.x - px); if (dd < bd) { bd = dd; b = k; } }); this.i = b; } }" x-on:pointermove="pick($event)" x-on:pointerleave="i = null">
                        <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" style="height:{{ $H }}px" role="img" aria-label="Comparison over time">
                            @for ($g = $bot; $g <= $top + 0.0001; $g += $step)<line class="grid" x1="0" x2="{{ $W }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}" vector-effect="non-scaling-stroke" />@endfor
                            @foreach ($race['lines'] as $li => $ln)
                                <polyline class="vxr-chart-line" pathLength="1" points="{{ implode(' ', array_map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1), $ln, array_keys($ln))) }}" fill="none" stroke="{{ $colors[$li] }}" stroke-width="2.4" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                            @endforeach
                        </svg>
                        <div style="position:relative;height:18px">
                            @foreach ($race['labels'] as $i => $label)@if (($i % $every === 0 && $n - 1 - $i >= $every / 2) || $i === $n - 1)<span style="position:absolute;left:{{ $x($i) / $W * 100 }}%;transform:translateX(-{{ $i === $n - 1 ? 100 : ($i === 0 ? 0 : 50) }}%);font-size:11px;color:var(--vxw-muted);white-space:nowrap">{{ $label }}</span>@endif @endforeach
                        </div>
                        <span class="vxr-chart-x" x-show="i !== null" x-cloak :style="i !== null && ('left:' + d[i].x + '%;bottom:18px;top:0')"></span>
                        <div class="vxr-chart-tip" x-show="i !== null" x-cloak :style="i !== null && (d[i].x > 60 ? 'right:' + (100 - d[i].x + 1.5) + '%' : 'left:' + (d[i].x + 1.5) + '%')">
                            <template x-if="i !== null"><div>
                                <div class="t" x-text="d[i].l"></div>
                                @foreach ($items as $li => $it)<div class="r"><span><i class="sw" style="background:{{ $colors[$li] }}"></i>{{ \Illuminate\Support\Str::limit($it['name'], 22) }}</span><b x-text="d[i].v[{{ $li }}]"></b></div>@endforeach
                            </div></template>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>
</x-reports.shell>
</x-filament-panels::page>
