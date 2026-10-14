@props(['label', 'value', 'icon' => 'heroicon-o-chart-bar', 'tone' => 'accent', 'note' => null, 'href' => null, 'valueClass' => '', 'compact' => false])
{{-- One stat card: round icon, label, big value, optional note (or a slot for a delta). --}}
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['vxr-stat', 'vxr-tone-'.$tone, 'vxr-stat--compact' => $compact]) }}>
    <span class="vxr-stat-icon"><x-filament::icon :icon="$icon" /></span>
    <span style="min-width:0">
        <span class="vxr-stat-label">{{ $label }}</span>
        <span class="vxr-stat-value {{ $valueClass }}" style="display:block">{{ $value }}</span>
        @if($note || trim($slot) !== '')<span class="vxr-stat-note" style="display:block">{{ $note }}{{ $slot }}</span>@endif
    </span>
</{{ $tag }}>
