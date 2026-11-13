@props(['segments' => [], 'center' => null, 'centerLabel' => '', 'money' => true, 'size' => 168])
{{--
    Donut with a 2px gap between slices and the total in the middle; the
    legend beside it names every slice with its value and share.
    segments: [['label' => 'Main', 'value' => 1200, 'color' => 'var(--vxc-1)', 'href' => null], ...]
--}}
@php
    $segs = array_values(array_filter($segments, fn ($s) => (float) $s['value'] > 0));
    $tot = array_sum(array_map(fn ($s) => (float) $s['value'], $segs)) ?: 1;
    $R = 46; $r = 31; $cx = 50; $cy = 50; $a0 = -M_PI / 2;
    $fmt = fn ($v) => $money ? '$'.number_format($v, 0) : number_format($v);
    $short = function ($v) use ($money) { $a = abs($v); $t = $a >= 1e6 ? round($a / 1e6, 2).'M' : ($a >= 1000 ? round($a / 1000, $a >= 1e5 ? 0 : 1).'k' : (string) round($a)); return ($money ? '$' : '').$t; };
@endphp
<div class="vxd">
    <svg viewBox="0 0 100 100" class="vxd-svg" style="width:{{ $size }}px;height:{{ $size }}px" role="img" aria-label="{{ $centerLabel }}">
        @if (! $segs)<circle cx="50" cy="50" r="{{ ($R + $r) / 2 }}" fill="none" stroke="var(--vxw-sunken)" stroke-width="{{ $R - $r }}" />@endif
        @foreach ($segs as $i => $s)
            @php
                $a1 = $a0 + (float) $s['value'] / $tot * 2 * M_PI; $large = ($a1 - $a0) > M_PI ? 1 : 0;
                $p = fn ($rad, $a) => round($cx + $rad * cos($a), 3).' '.round($cy + $rad * sin($a), 3);
                $full = count($segs) === 1;
            @endphp
            @if ($full)
                <circle cx="50" cy="50" r="{{ ($R + $r) / 2 }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="{{ $R - $r }}"><title>{{ $s['label'] }}: {{ $fmt($s['value']) }}</title></circle>
            @else
                <path class="vxd-seg" d="M{{ $p($R, $a0) }} A{{ $R }} {{ $R }} 0 {{ $large }} 1 {{ $p($R, $a1) }} L{{ $p($r, $a1) }} A{{ $r }} {{ $r }} 0 {{ $large }} 0 {{ $p($r, $a0) }}Z" fill="{{ $s['color'] }}" stroke="var(--vxw-surface)" stroke-width="1"><title>{{ $s['label'] }}: {{ $fmt($s['value']) }} ({{ round($s['value'] / $tot * 100) }}%)</title></path>
            @endif
            @php $a0 = $a1; @endphp
        @endforeach
        <text x="50" y="50" text-anchor="middle" class="vxd-c">{{ $center ?? $short($tot) }}</text>
        <text x="50" y="60" text-anchor="middle" class="vxd-cl">{{ $centerLabel }}</text>
    </svg>
    <div class="vxd-legend">
        @foreach ($segs as $s)
            @php $tag = ! empty($s['href']) ? 'a' : 'div'; @endphp
            <{{ $tag }} @if(! empty($s['href'])) href="{{ $s['href'] }}" @endif class="vxd-row">
                <i style="background:{{ $s['color'] }}"></i><span class="vxd-n" title="{{ $s['label'] }}">{{ $s['label'] }}</span><b>{{ $short((float) $s['value']) }}</b><span class="vxd-p">{{ round($s['value'] / $tot * 100) }}%</span>
            </{{ $tag }}>
        @endforeach
        @if (! $segs)<p class="vxr-hint" style="margin:0">Nothing in this period yet.</p>@endif
    </div>
</div>
