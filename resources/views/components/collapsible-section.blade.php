@props([
    'title' => '',
    'defaultOpen' => false,
])

{{-- Mobile Collapsible Section Component --}}
<div class="mobile-collapsible {{ $defaultOpen ? 'open' : '' }}">
    <div class="mobile-collapsible-header">
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

<script>
function initCollapsibleSections() {
    const headers = document.querySelectorAll('.mobile-collapsible-header');
    headers.forEach(header => {
        // Skip if already initialized (has event listener)
        if (header._collapsibleInitialized) return;

        header.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const section = header.closest('.mobile-collapsible');
            if (section) {
                section.classList.toggle('open');
            }
        });

        // Mark as initialized
        header._collapsibleInitialized = true;
    });
}

// Initialize immediately if DOM is ready, or wait for DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCollapsibleSections);
} else {
    // DOM is already ready, initialize now
    setTimeout(initCollapsibleSections, 0);
}

// Reinitialize when Livewire updates
if (window.Livewire) {
    Livewire.hook('morph.updated', () => {
        setTimeout(initCollapsibleSections, 100);
    });
}
</script>
