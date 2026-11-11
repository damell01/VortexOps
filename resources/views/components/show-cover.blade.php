@props(['show', 'size' => 40])
{{-- The show's Whatnot thumbnail, or its first letter on a tile when there is none. --}}
@php($src = $show?->cover_image_url)
@if ($src)
    <img src="{{ $src }}" alt="" loading="lazy" decoding="async" width="{{ $size }}" height="{{ $size }}"
         {{ $attributes->merge(['class' => 'vx-show-cover', 'style' => "width:{$size}px;height:{$size}px;flex:none;border-radius:" . max(6, (int) round($size / 5)) . "px;object-fit:cover;background:rgba(148,163,184,.2)"]) }}>
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => 'vx-show-cover vx-show-cover--empty', 'style' => "width:{$size}px;height:{$size}px;flex:none;border-radius:" . max(6, (int) round($size / 5)) . "px;display:inline-grid;place-items:center;font-weight:700;font-size:" . max(11, (int) round($size * 0.4)) . "px;background:rgba(99,102,241,.14);color:rgb(79,70,229)"]) }}>{{ mb_strtoupper(mb_substr(trim((string) $show?->title) ?: '?', 0, 1)) }}</span>
@endif
