@props(['labels' => [], 'series' => [], 'money' => true, 'height' => 240])
{{--
    Grouped bars: one group per label, one bar per series, hover for every value.
    series: [['name' => 'Gross sales', 'values' => [...], 'color' => 'var(--vxw-accent)'], ...]
--}}
@php
    $labels = array_values($labels); $n = max(1, count($labels)); $k = max(1, count($series));
    $all = []; foreach ($series as $s) { foreach ($s['values'] as $v) { $all[] = (float) $v; } }
    $max = max(array_merge([0], $all)); $min = min(array_merge([0], $all)); if ($max == $min) $max = $min + 1;
    $span = $max - $min; $mag = pow(10, floor(log10($span))); $step = ceil($span / 4 / $mag * 2) / 2 * $mag ?: 1;
    $top = ceil($max / $step) * $step; $bot = floor($min / $step) * $step;
    $H = $height; $T = 8; $B = 4; $W = 1000;
    $y = fn ($v) => $T + ($top - $v) / ($top - $bot) * ($H - $T - $B);
    $slot = $W / $n; $gw = min($slot * .72, 26 * $k + 6 * ($k - 1)); $bw = ($gw - 4 * ($k - 1)) / $k;
    $short = function ($v) use ($money) { $a = abs($v); $s = $v < 0 ? '−' : ''; $t = $a >= 1e6 ? round($a / 1e6, 1).'M' : ($a >= 1000 ? round($a / 1000, $a >= 1e4 ? 0 : 1).'k' : (string) round($a)); return $s.($money ? '$' : '').$t; };
    $full = fn ($v) => $money ? (($v < 0 ? '−$' : '$').number_format(abs($v), 0)) : number_format($v);
    $every = max(1, (int) ceil($n / 10));
    $tip = array_map(fn ($i) => ['l' => $labels[$i], 'x' => round(($i + .5) / $n * 100, 3), 'v' => array_map(fn ($s) => $full((float) ($s['values'][$i] ?? 0)), $series)], array_keys($labels));
@endphp
<div class="vxg">
    <div class="vxg-y" style="height:{{ $H }}px">
        @for ($g = $bot; $g <= $top + 0.0001; $g += $step)<span style="top:{{ $y($g) / $H * 100 }}%">{{ $short($g) }}</span>@endfor
    </div>
    <div class="vxr-chart-wrap" x-data="{ i: null, d: @js($tip), pick(e) { const r = this.$el.getBoundingClientRect(); this.i = Math.max(0, Math.min(this.d.length - 1, Math.floor((e.clientX - r.left) / r.width * this.d.length))); } }" x-on:pointermove="pick($event)" x-on:pointerleave="i = null">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" style="height:{{ $H }}px;width:100%;display:block" role="img" {{ $attributes }}>
            @for ($g = $bot; $g <= $top + 0.0001; $g += $step)<line x1="0" x2="{{ $W }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}" stroke="var(--vxw-border)" vector-effect="non-scaling-stroke" @if($g != 0) stroke-dasharray="3 4" @endif />@endfor
            <rect x="0" y="0" width="0" height="{{ $H }}" fill="var(--vxw-sunken)" class="vxg-band" />
            @foreach ($labels as $i => $label)
                @foreach ($series as $si => $s)
                    @php $v = (float) ($s['values'][$i] ?? 0); $x = $slot * $i + ($slot - $gw) / 2 + $si * ($bw + 4); $y0 = $y(0); $y1 = $y($v); $h = max(abs($y0 - $y1), $v != 0 ? 2 : 0); @endphp
                    <rect class="vxg-bar" x="{{ round($x, 2) }}" y="{{ round($v < 0 ? $y0 : $y0 - $h, 2) }}" width="{{ round($bw, 2) }}" height="{{ round($h, 2) }}" rx="3" fill="{{ $s['color'] }}" style="animation-delay:{{ min(600, $i * 25) }}ms" />
                @endforeach
            @endforeach
        </svg>
        <div class="vxg-x">
            @foreach ($labels as $i => $label)@if ($i % $every === 0)<span style="left:{{ ($i + .5) / $n * 100 }}%">{{ $label }}</span>@endif @endforeach
        </div>
        <span class="vxg-hl" x-show="i !== null" x-cloak :style="i !== null && ('left:' + (i / d.length * 100) + '%;width:' + (100 / d.length) + '%')"></span>
        <div class="vxr-chart-tip" x-show="i !== null" x-cloak :style="i !== null && (d[i].x > 60 ? 'right:' + (100 - d[i].x + 3) + '%' : 'left:' + (d[i].x + 3) + '%')">
            <template x-if="i !== null"><div>
                <div class="t" x-text="d[i].l"></div>
                @foreach ($series as $si => $s)<div class="r"><span><i class="sw" style="background:{{ $s['color'] }};height:8px;width:8px;border-radius:2px"></i>{{ $s['name'] }}</span><b x-text="d[i].v[{{ $si }}]"></b></div>@endforeach
            </div></template>
        </div>
    </div>
</div>
