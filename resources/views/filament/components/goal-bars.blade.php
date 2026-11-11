@props(['progress', 'removable' => false])
@php
    $fmt = fn ($row, $v) => $row['money'] ? '$' . number_format($v, 0) : number_format($v, 0);
    $tone = ['done' => ['#16a34a', 'Done'], 'on_track' => ['#2563eb', 'On track'], 'behind' => ['#d97706', 'Behind'], 'missed' => ['#dc2626', 'Missed']];
@endphp
<ul class="vx-goal-bars" style="list-style:none;margin:0;padding:0;display:grid;gap:14px">
    @foreach ($progress as $row)
        @php([$color, $word] = $tone[$row['status']])
        <li wire:key="goal-{{ $row['id'] }}">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:baseline;flex-wrap:wrap">
                <span style="font-weight:600">{{ $row['label'] }} <span class="vxw-meta" style="font-weight:400">· {{ $row['scope'] }}</span></span>
                <span style="display:flex;gap:10px;align-items:baseline">
                    <span style="font-variant-numeric:tabular-nums">{{ $fmt($row, $row['actual']) }} <span class="vxw-meta">of {{ $fmt($row, $row['target']) }}</span></span>
                    <span style="font-size:12px;font-weight:600;color:{{ $color }}">{{ $word }} · {{ number_format($row['pct'], 0) }}%</span>
                    @if ($removable)
                        <button type="button" class="vxw-btn vxw-btn--sm" wire:click="remove({{ $row['id'] }})" wire:confirm="Remove this goal?" aria-label="Remove goal">Remove</button>
                    @endif
                </span>
            </div>
            <div style="position:relative;height:10px;border-radius:999px;background:rgba(148,163,184,.25);margin-top:6px;overflow:hidden"
                 role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, $row['pct']) }}" aria-label="{{ $row['label'] }} {{ $row['scope'] }}">
                <div style="height:100%;width:{{ min(100, $row['pct']) }}%;background:{{ $color }};border-radius:999px;transition:width .4s"></div>
                @if ($row['expected'] > 0 && $row['expected'] < 100)
                    <div title="Where a steady pace would be today" style="position:absolute;top:-2px;bottom:-2px;left:{{ $row['expected'] }}%;width:2px;background:currentColor;opacity:.45"></div>
                @endif
            </div>
        </li>
    @endforeach
</ul>
