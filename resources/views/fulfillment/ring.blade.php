{{-- Progress ring. Params: $pct (0–100), $label (small line under the %), $tone (packing|hold|ready|paused|idle), $size (px). --}}
@php $r = 26; $c = 2 * M_PI * $r; $pct = max(0, min(100, (int) ($pct ?? 0))); @endphp
<span class="vxf-ring vxf-ring--{{ $tone ?? 'packing' }}" style="--size: {{ $size ?? 76 }}px" role="img" aria-label="{{ $pct }}% packed">
    <svg viewBox="0 0 64 64" aria-hidden="true"><circle class="vxf-ring__bg" cx="32" cy="32" r="{{ $r }}"/><circle class="vxf-ring__fg" cx="32" cy="32" r="{{ $r }}" stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $c * (1 - $pct / 100) }}"/></svg>
    <span class="vxf-ring__text"><b>{{ $pct }}%</b>@isset($label)<small>{{ $label }}</small>@endisset</span>
</span>
