@props([
    'label',            // "Business" / "Channel"
    'allLabel',         // "All Businesses"
    'model',            // Livewire property the choice is written to
    'options' => [],    // list of ['value' => int, 'label' => string, 'color' => '#hex']
    'current' => null,  // selected value or null for "all"
    'icon' => 'channel',
])
@php
    $selected = collect($options)->firstWhere('value', $current);
    $color = $selected['color'] ?? null;
@endphp
<div class="vx-picker" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false"
     @if($color) style="--vx-pick: {{ $color }}" data-active @endif>
    <button type="button" class="vx-picker-btn" @click="open = !open" :aria-expanded="open" aria-haspopup="listbox" aria-label="{{ $label }}: {{ $selected['label'] ?? $allLabel }}">
        @if($icon === 'business')
            <svg class="vx-picker-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 16.5v-13h-.25a.75.75 0 0 1 0-1.5h12.5a.75.75 0 0 1 0 1.5H16v13h.25a.75.75 0 0 1 0 1.5h-3.5a.75.75 0 0 1-.75-.75v-2.5a.75.75 0 0 0-.75-.75h-2.5a.75.75 0 0 0-.75.75v2.5a.75.75 0 0 1-.75.75h-3.5a.75.75 0 0 1 0-1.5H4Zm3-11a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1ZM7.5 9a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1ZM11 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1Zm.5 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1Z" clip-rule="evenodd"/></svg>
        @else
            <svg class="vx-picker-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3.25 4A2.25 2.25 0 0 0 1 6.25v7.5A2.25 2.25 0 0 0 3.25 16h7.5A2.25 2.25 0 0 0 13 13.75v-7.5A2.25 2.25 0 0 0 10.75 4h-7.5ZM19 4.75a.75.75 0 0 0-1.28-.53l-3 3a.75.75 0 0 0-.22.53v4.5c0 .2.08.39.22.53l3 3a.75.75 0 0 0 1.28-.53V4.75Z"/></svg>
        @endif
        <span class="vx-picker-text">
            <span class="vx-picker-kicker">{{ $label }}</span>
            <span class="vx-picker-value">@if($color)<i class="vx-picker-dot" aria-hidden="true"></i>@endif{{ $selected['label'] ?? $allLabel }}</span>
        </span>
        <svg class="vx-picker-chevron" :class="open && 'is-open'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
    </button>
    <div class="vx-picker-menu" x-cloak x-show="open" x-transition.origin.top role="listbox" aria-label="{{ $label }}">
        <div class="vx-picker-menu-head">{{ $label }}</div>
        @php $choices = array_merge([['value' => null, 'label' => $allLabel, 'color' => null]], $options); @endphp
        @foreach($choices as $choice)
            @php $isOn = $choice['value'] === $current; @endphp
            <button type="button" role="option" @if($isOn) aria-selected="true" @endif
                    @class(['vx-picker-option', 'is-on' => $isOn])
                    @click="open = false; $wire.set('{{ $model }}', {{ $choice['value'] === null ? 'null' : (int) $choice['value'] }})">
                <i class="vx-picker-dot" @if($choice['color']) style="background: {{ $choice['color'] }}" @else data-all @endif aria-hidden="true"></i>
                <span>{{ $choice['label'] }}</span>
                @if($isOn)<svg class="vx-picker-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>@endif
            </button>
        @endforeach
    </div>
</div>
@once
<style>
.vx-picker{position:relative;width:100%}
.vx-picker-btn{display:flex;align-items:center;gap:8px;width:100%;min-height:40px;padding:5px 10px;border-radius:11px;border:1px solid var(--vx-border,#e5e7eb);background:#fff;color:#1f2937;text-align:left;transition:border-color .15s,background .15s,box-shadow .15s}
.vx-picker-btn:hover{border-color:#c4b5fd}
.vx-picker-btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(139,92,246,.35)}
.dark .vx-picker-btn{background:#111827;color:#e5e7eb;border-color:#374151}
.vx-picker[data-active] .vx-picker-btn{border-color:color-mix(in srgb,var(--vx-pick) 70%,transparent);background:color-mix(in srgb,var(--vx-pick) 10%,#fff)}
.dark .vx-picker[data-active] .vx-picker-btn{background:color-mix(in srgb,var(--vx-pick) 20%,#111827)}
.vx-picker-icon{width:16px;height:16px;flex:none;color:#9ca3af}
.vx-picker[data-active] .vx-picker-icon{color:var(--vx-pick)}
.vx-picker-text{display:grid;min-width:0;flex:1;line-height:1.1}
.vx-picker-kicker{font-size:9.5px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#9ca3af}
.vx-picker-value{display:flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vx-picker-dot{display:inline-block;width:8px;height:8px;border-radius:999px;flex:none;background:var(--vx-pick,#9ca3af)}
.vx-picker-dot[data-all]{background:conic-gradient(#7c3aed 0 33%,#0d9488 0 66%,#f59e0b 0)}
.vx-picker-chevron{width:15px;height:15px;flex:none;color:#9ca3af;transition:transform .15s}
.vx-picker-chevron.is-open{transform:rotate(180deg)}
.vx-picker-menu{position:absolute;z-index:60;top:calc(100% + 6px);right:0;min-width:100%;width:max-content;max-width:280px;max-height:60vh;overflow-y:auto;padding:6px;border-radius:14px;border:1px solid #e5e7eb;background:#fff;color:#1f2937;box-shadow:0 18px 40px -12px rgba(15,23,42,.35)}
.dark .vx-picker-menu{background:#111827;color:#e5e7eb;border-color:#1f2937}
.vx-picker-menu-head{padding:6px 10px 4px;font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af}
.vx-picker-option{display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border-radius:9px;font-size:13px;font-weight:600;text-align:left}
.vx-picker-option:hover{background:#f5f3ff}
.dark .vx-picker-option:hover{background:#1e1b3a}
.vx-picker-option.is-on{background:#ede9fe;color:#5b21b6}
.dark .vx-picker-option.is-on{background:#2e1065;color:#ddd6fe}
.vx-picker-option span{flex:1;white-space:nowrap}
.vx-picker-check{width:15px;height:15px;flex:none}
/* In the dark top bar */
.vx-desktop-mega-nav .vx-mega-business + .vx-mega-channel{margin-left:6px!important}
.vx-desktop-mega-nav .vx-mega-channel.vx-mega-business{min-width:150px!important;max-width:205px!important;flex-basis:205px!important}
.vx-mobile-channel .vx-picker-menu{left:0;right:auto;max-width:100%}
.vx-desktop-mega-nav .vx-picker-btn{min-height:38px;background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.14);color:#f3f4f6}
.vx-desktop-mega-nav .vx-picker-btn:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.28)}
.vx-desktop-mega-nav .vx-picker[data-active] .vx-picker-btn{background:color-mix(in srgb,var(--vx-pick) 28%,transparent);border-color:color-mix(in srgb,var(--vx-pick) 75%,#fff 0%)}
.vx-desktop-mega-nav .vx-picker-kicker{color:rgba(229,231,235,.6)}
.vx-desktop-mega-nav .vx-picker[data-active] .vx-picker-icon{color:#fff}
.vx-desktop-mega-nav .vx-picker-chevron{color:rgba(229,231,235,.7)}
</style>
@endonce
