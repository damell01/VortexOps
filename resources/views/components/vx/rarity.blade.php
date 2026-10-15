@props(['cost' => null])
@php($tier = \App\Support\Rarity::tier($cost !== null ? (float) $cost : null))
@if ($tier > 0)
    {{-- ★ by unit cost (App\Support\Rarity); the top tier is holo. --}}
    <span {{ $attributes->class(['vx-rarity', 'tier-'.$tier]) }} title="{{ \App\Support\Rarity::label($tier) }}">{{ str_repeat('★', $tier) }}</span>
@endif
