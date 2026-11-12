@props(['pace' => null, 'goalsUrl' => null])
{{-- "On pace for $X this month": the goal, what is in, and where the month lands at this rate. --}}
@if ($pace)
    @php
        $m = fn ($v) => $pace['money'] ? '$'.number_format($v, 0) : number_format($v);
        $pct = min(100, $pace['pct']);
        $exp = min(100, $pace['expected'] / max(1, $pace['target']) * 100);
        $ring = 2 * M_PI * 19;
    @endphp
    <div class="vxr-pace" role="status">
        <svg class="vxr-pace-ring" viewBox="0 0 46 46" aria-hidden="true">
            <circle cx="23" cy="23" r="19" fill="none" stroke="var(--vxw-sunken)" stroke-width="5" />
            <circle cx="23" cy="23" r="19" fill="none" stroke="var(--vxw-accent)" stroke-width="5" stroke-linecap="round" stroke-dasharray="{{ round($pct / 100 * $ring, 1) }} {{ round($ring, 1) }}" transform="rotate(-90 23 23)" />
            <text x="23" y="27" text-anchor="middle" style="font-size:11px;font-weight:800;fill:var(--vxw-text)">{{ round($pace['pct']) }}%</text>
        </svg>
        <div style="min-width:0">
            <b>On pace for {{ $m($pace['projected']) }} this month</b>
            <div class="sub">{{ $pace['label'] }} goal {{ $m($pace['target']) }}{{ $pace['scope'] !== 'Everyone' ? ' for '.$pace['scope'] : '' }} · {{ $m($pace['actual']) }} in so far · {{ $pace['days_left'] }} {{ \Illuminate\Support\Str::plural('day', $pace['days_left']) }} left</div>
        </div>
        <div class="bar" title="Marker: where a straight line to the goal would be today"><i style="width:{{ $pct }}%"></i><em style="left:{{ $exp }}%"></em></div>
        <span @class(['tag', 'is-ahead' => $pace['status'] !== 'behind', 'is-behind' => $pace['status'] === 'behind'])>{{ $pace['status'] === 'done' ? 'Goal hit' : ($pace['status'] === 'ahead' ? 'Ahead of goal' : 'Behind goal') }}</span>
        @if ($goalsUrl)<a href="{{ $goalsUrl }}" class="vxr-btn vxr-btn--sm vxr-btn--outline">Goals</a>@endif
    </div>
@endif
