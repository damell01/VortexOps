@props(['points' => [], 'type' => 'bar', 'money' => true, 'height' => 240, 'signed' => false])
{{--
    A small dependency-free chart. $points: [['label' => 'Mon', 'value' => 12.5, 'muted' => false, 'title' => 'tooltip']].
    type: bar | area. signed bars colour negatives red and draw a zero line.
--}}
@php
    $pts = array_values($points);
    $n = max(count($pts), 1);
    $vals = array_map(fn ($p) => (float) ($p['value'] ?? 0), $pts);
    $max = max(array_merge([0], $vals)); $min = min(array_merge([0], $vals));
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
<svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" class="vxr-chart" style="height:{{ $H }}px" role="img" {{ $attributes }}>
    @for ($g = $bottom; $g <= $top + 0.0001; $g += $step)
        <line class="grid" x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}" />
        <text x="{{ $L - 8 }}" y="{{ $y($g) + 4 }}" text-anchor="end">{{ $fmt($g) }}</text>
    @endfor
    @if ($bottom < 0)<line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $y(0) }}" y2="{{ $y(0) }}" stroke="var(--vxw-border-strong)" />@endif
    @if ($type === 'area' && count($pts) > 0)
        @php
            $xy = []; foreach ($vals as $i => $v) { $xy[] = [round($L + $slot * ($i + .5), 1), round($y($v), 1)]; }
            $line = implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $xy));
            $area = $line.' '.end($xy)[0].','.$y(max($bottom, 0)).' '.$xy[0][0].','.$y(max($bottom, 0));
        @endphp
        <polygon points="{{ $area }}" fill="var(--vxw-accent)" opacity=".12" />
        <polyline points="{{ $line }}" pathLength="1" fill="none" stroke="var(--vxw-accent)" stroke-width="3" vector-effect="non-scaling-stroke" />
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
    @foreach ($pts as $i => $p)
        @if ($i % $every === 0)
            @foreach (explode("\n", $p['label']) as $li => $part)
                <text x="{{ $L + $slot * ($i + .5) }}" y="{{ $H - $B + 16 + $li * 13 }}" text-anchor="middle">{{ $part }}</text>
            @endforeach
        @endif
    @endforeach
</svg>
