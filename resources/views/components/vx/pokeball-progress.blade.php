@props(['pct' => 0, 'done' => false])
@php($pct = max(0, min(100, (int) $pct)))
@php($uid = 'vxpb'.substr(md5(uniqid('', true)), 0, 8))
{{-- A Poké Ball that fills from the bottom as the work gets done, and clicks shut with a star once it is. --}}
<span {{ $attributes->class(['vx-pb', 'is-caught' => $done]) }} role="img" aria-label="{{ $pct }}% received">
    <svg viewBox="0 0 32 32" width="30" height="30" aria-hidden="true">
        <defs><clipPath id="{{ $uid }}"><rect x="0" y="{{ 32 - 32 * $pct / 100 }}" width="32" height="32" /></clipPath></defs>
        <circle cx="16" cy="16" r="14" fill="#eef0f5" stroke="#cbd2e0" stroke-width="2" />
        <g clip-path="url(#{{ $uid }})">
            <path d="M2 16a14 14 0 0 1 28 0z" fill="#ef4444" />
            <path d="M2 16a14 14 0 0 0 28 0z" fill="#fff" />
            <circle cx="16" cy="16" r="14" fill="none" stroke="#1f2937" stroke-width="2" />
            <rect x="2" y="14.6" width="28" height="2.8" fill="#1f2937" />
        </g>
        <circle cx="16" cy="16" r="4.4" fill="#fff" stroke="#1f2937" stroke-width="2" />
    </svg>
    <span class="vx-pb-pct">{{ $pct }}%</span>
</span>
