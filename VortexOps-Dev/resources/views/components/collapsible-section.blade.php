@props([
    'title' => '',
    'defaultOpen' => false,
])

{{-- Mobile Collapsible Section Component --}}
<div class="mobile-collapsible {{ $defaultOpen ? 'open' : '' }}" data-collapsible>
    <div class="mobile-collapsible-header" role="button" tabindex="0" aria-expanded="{{ $defaultOpen ? 'true' : 'false' }}">
        <span class="mobile-collapsible-title">{{ $title }}</span>
        <svg class="mobile-collapsible-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
        </svg>
    </div>
    <div class="mobile-collapsible-content">
        <div class="mobile-collapsible-body">
            {{ $slot }}
        </div>
    </div>
</div>
