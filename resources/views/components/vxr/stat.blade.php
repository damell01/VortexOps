@props(['label', 'value', 'icon' => 'heroicon-o-chart-bar', 'tone' => 'accent', 'note' => null, 'href' => null, 'valueClass' => '', 'compact' => false,
    'delta' => null, 'invert' => false, 'deltaNote' => null, 'spark' => null, 'featured' => false])
{{--
    One stat card: icon, label, big value, then either a note (or slot) or a
    change pill against the comparison period, with an optional sparkline.
    delta is a percentage (12.5 = +12.5%); invert marks a cost, where down is good.
    featured paints the card in the report's accent (the figure being charted).
--}}
@php
    $tag = $href ? 'a' : 'div';
    $sparkPts = is_array($spark) ? array_values(array_map('floatval', $spark)) : [];
    $sparkPath = null;
    if (count($sparkPts) > 1) {
        $mn = min($sparkPts); $mx = max($sparkPts); $n = count($sparkPts);
        $xy = []; foreach ($sparkPts as $i => $v) { $xy[] = [round(2 + $i * 96 / ($n - 1), 1), round(32 - ($mx == $mn ? .5 : ($v - $mn) / ($mx - $mn)) * 26, 1)]; }
        $sparkPath = 'M'.implode(' L', array_map(fn ($p) => $p[0].' '.$p[1], $xy));
        $sparkEnd = end($xy);
    }
    $good = $delta === null ? null : ($invert ? $delta < 0 : $delta > 0);
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['vxr-stat', 'vxr-tone-'.$tone, 'vxr-stat--compact' => $compact, 'vxr-stat--featured' => $featured, 'has-spark' => $sparkPath]) }}>
    <span class="vxr-stat-icon"><x-filament::icon :icon="$icon" /></span>
    <span style="min-width:0;flex:1">
        <span class="vxr-stat-label">{{ $label }}</span>
        <span class="vxr-stat-value {{ $valueClass }}" style="display:block" data-vx-countup>{{ $value }}</span>
        @if ($delta !== null)
            <span class="vxr-stat-note vxr-delta-row" style="display:flex">
                <span @class(['vxr-delta', 'is-up' => $good === true, 'is-down' => $good === false, 'is-flat' => abs((float) $delta) < 0.05])>{{ abs((float) $delta) < 0.05 ? '0%' : (($delta > 0 ? '▲ ' : '▼ ').number_format(abs((float) $delta), 1).'%') }}</span>
                @if ($deltaNote)<span>{{ $deltaNote }}</span>@endif
            </span>
        @elseif ($note || trim($slot) !== '')
            <span class="vxr-stat-note" style="display:block">{{ $note }}{{ $slot }}</span>
        @endif
    </span>
    @if ($sparkPath)
        <svg class="vxr-spark" viewBox="0 0 100 36" preserveAspectRatio="none" aria-hidden="true">
            <path d="{{ $sparkPath }} L98 36 L2 36Z" class="vxr-spark-fill" />
            <path d="{{ $sparkPath }}" class="vxr-spark-line" vector-effect="non-scaling-stroke" />
        </svg>
    @endif
</{{ $tag }}>
