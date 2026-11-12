@props(['points' => [], 'type' => 'bar', 'money' => true, 'height' => 240, 'signed' => false,
    'compare' => null, 'compareLabel' => 'Previous period', 'seriesLabel' => 'This period', 'goal' => null, 'goalLabel' => 'Goal pace'])
{{--
    A small dependency-free chart. $points: [['label' => 'Mon', 'value' => 12.5, 'muted' => false, 'title' => 'tooltip']].
    type: bar | area. signed bars colour negatives red and draw a zero line.
    compare: values lined up with points, drawn as a dashed line.
    goal: one value per point (the target for that day/week), drawn as a flat dotted line.
    Hovering shows every value for that point.
--}}
@php
    $pts = array_values($points);
    $n = max(count($pts), 1);
    $vals = array_map(fn ($p) => (float) ($p['value'] ?? 0), $pts);
    $cmp = is_array($compare) ? array_values(array_map(fn ($v) => $v === null ? null : (float) $v, $compare)) : null;
    $goalV = $goal !== null && (float) $goal > 0 ? (float) $goal : null;
    $extra = array_merge($cmp ? array_filter($cmp, fn ($v) => $v !== null) : [], $goalV !== null ? [$goalV * 1.08] : []);
    $max = max(array_merge([0], $vals, $extra)); $min = min(array_merge([0], $vals, $extra));
    if ($max == $min) { $max = $min + 1; }
    // Round the top up to a tidy step so the gridlines read well.
    $span = $max - $min; $mag = pow(10, floor(log10($span))); $step = ceil($span / 4 / $mag * 2) / 2 * $mag ?: 1;
    $top = ceil($max / $step) * $step; $bottom = floor($min / $step) * $step; if ($top == $bottom) { $top = $bottom + $step; }
    $W = 1000; $H = $height; $L = 64; $R = 10; $T = 12; $B = 34;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $y = fn ($v) => $T + ($top - $v) / ($top - $bottom) * $ph;
    $fmt = function ($v) use ($money) {
        $a = abs($v); $s = $v < 0 ? '-' : '';
        $txt = $a >= 1000000 ? round($a / 1000000, 1).'M' : ($a >= 1000 ? round($a / 1000, $a >= 10000 ? 0 : 1).'k' : (string) round($a));
        return $s.($money ? '$' : '').$txt;
    };
    $every = max(1, (int) ceil($n / 12));
    $slot = $pw / $n;
@endphp
@php
    $fmtFull = fn ($v) => $v === null ? '—' : ($money ? (($v < 0 ? '−$' : '$').number_format(abs($v), 2)) : number_format($v, 0));
    $tipData = array_map(fn ($i) => [
        'l' => str_replace("\n", ' ', $pts[$i]['label'] ?? ''), 'v' => $fmtFull($vals[$i]),
        'c' => $cmp !== null ? $fmtFull($cmp[$i] ?? null) : null,
        'd' => $cmp !== null && ($cmp[$i] ?? 0) != 0 ? round(($vals[$i] - $cmp[$i]) / abs($cmp[$i]) * 100, 1) : null,
        'x' => round(($L + $slot * ($i + .5)) / $W * 100, 3),
    ], array_keys($pts));
@endphp
<div class="vxr-chart-wrap" x-data="{ i: null, d: @js($tipData), pick(e) { const r = this.$el.getBoundingClientRect(); const px = (e.clientX - r.left) / r.width * 100; let best = 0, bd = 1e9; this.d.forEach((p, k) => { const dd = Math.abs(p.x - px); if (dd < bd) { bd = dd; best = k; } }); this.i = best; } }"
    x-on:pointermove="pick($event)" x-on:pointerleave="i = null">
<svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" class="vxr-chart" style="height:{{ $H }}px" role="img" {{ $attributes }}>
    @for ($g = $bottom; $g <= $top + 0.0001; $g += $step)
        <line class="grid" x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}" />
        <text x="{{ $L - 8 }}" y="{{ $y($g) + 4 }}" text-anchor="end">{{ $fmt($g) }}</text>
    @endfor
    @if ($bottom < 0)<line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $y(0) }}" y2="{{ $y(0) }}" stroke="var(--vxw-border-strong)" />@endif
    @if ($cmp !== null && count($cmp) > 1)
        @php $cxy = []; foreach ($cmp as $i => $v) { if ($v !== null) $cxy[] = round($L + $slot * ($i + .5), 1).','.round($y($v), 1); } @endphp
        <polyline points="{{ implode(' ', $cxy) }}" fill="none" class="vxr-chart-cmp" vector-effect="non-scaling-stroke" />
    @endif
    @if ($type === 'area' && count($pts) > 0)
        @php
            $xy = []; foreach ($vals as $i => $v) { $xy[] = [round($L + $slot * ($i + .5), 1), round($y($v), 1)]; }
            $line = implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $xy));
            $area = $line.' '.end($xy)[0].','.$y(max($bottom, 0)).' '.$xy[0][0].','.$y(max($bottom, 0));
        @endphp
        <defs><linearGradient id="vxrArea{{ $uid = substr(md5(json_encode($vals).$H), 0, 8) }}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--vxw-accent)" stop-opacity=".28"/><stop offset="1" stop-color="var(--vxw-accent)" stop-opacity="0"/></linearGradient></defs>
        <polygon points="{{ $area }}" fill="url(#vxrArea{{ $uid }})" />
        <polyline points="{{ $line }}" pathLength="1" fill="none" stroke="var(--vxw-accent)" stroke-width="3" vector-effect="non-scaling-stroke" class="vxr-chart-line" />
        @foreach ($xy as $i => $p)
            <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="5" fill="var(--vxw-surface)" stroke="var(--vxw-accent)" stroke-width="2.5" vector-effect="non-scaling-stroke"><title>{{ $pts[$i]['title'] ?? ($pts[$i]['label'].': '.$fmt($vals[$i])) }}</title></circle>
        @endforeach
    @else
        @foreach ($pts as $i => $p)
            @php
                $v = $vals[$i]; $bw = min(64, $slot * .62); $x = $L + $slot * $i + ($slot - $bw) / 2;
                $y0 = $y(0); $y1 = $y($v); $hgt = max(abs($y0 - $y1), ($p['muted'] ?? false) ? 6 : 1);
                $fill = ($p['muted'] ?? false) ? 'var(--vxw-sunken)' : ($signed ? ($v < 0 ? 'var(--vxw-bad)' : 'var(--vxw-ok)') : 'var(--vxw-accent)');
            @endphp
            <rect x="{{ $x }}" y="{{ $v < 0 ? $y0 : $y0 - $hgt }}" width="{{ $bw }}" height="{{ $hgt }}" rx="4" fill="{{ $fill }}" @if(!($p['muted'] ?? false) && !$signed) opacity=".85" @endif><title>{{ $p['title'] ?? ($p['label'].': '.$fmt($v)) }}</title></rect>
        @endforeach
    @endif
    @if ($goalV !== null)
        <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $y($goalV) }}" y2="{{ $y($goalV) }}" class="vxr-chart-goal" vector-effect="non-scaling-stroke" />
    @endif
    @foreach ($pts as $i => $p)
        @if ($i % $every === 0)
            @foreach (explode("\n", $p['label']) as $li => $part)
                <text x="{{ $L + $slot * ($i + .5) }}" y="{{ $H - $B + 16 + $li * 13 }}" text-anchor="middle">{{ $part }}</text>
            @endforeach
        @endif
    @endforeach
</svg>
    @if ($goalV !== null)<span class="vxr-goal-tag" style="top:{{ round($y($goalV) / $H * 100, 2) }}%">{{ $goalLabel }} {{ $fmt($goalV) }}</span>@endif
    <span class="vxr-chart-x" x-show="i !== null" x-cloak :style="i !== null && ('left:' + d[i].x + '%')"></span>
    <div class="vxr-chart-tip" x-show="i !== null" x-cloak :style="i !== null && (d[i].x > 60 ? 'right:' + (100 - d[i].x + 1.5) + '%' : 'left:' + (d[i].x + 1.5) + '%')">
        <template x-if="i !== null">
            <div>
                <div class="t" x-text="d[i].l"></div>
                <div class="r"><span><i class="sw"></i>{{ $seriesLabel }}</span><b x-text="d[i].v"></b></div>
                @if ($cmp !== null)
                    <div class="r"><span><i class="sw is-cmp"></i>{{ $compareLabel }}</span><b x-text="d[i].c"></b></div>
                    <div class="r" x-show="d[i].d !== null"><span>Change</span><b :class="d[i].d >= 0 ? 'is-up' : 'is-down'" x-text="(d[i].d >= 0 ? '+' : '') + d[i].d + '%'"></b></div>
                @endif
                @if ($goalV !== null)<div class="r"><span><i class="sw is-goal"></i>{{ $goalLabel }}</span><b>{{ $fmtFull($goalV) }}</b></div>@endif
            </div>
        </template>
    </div>
</div>
