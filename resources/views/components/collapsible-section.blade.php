{{-- Mobile Collapsible Section Component --}}
<div class="mobile-collapsible {{ $defaultOpen ? 'open' : '' }}">
    <div class="mobile-collapsible-header">
        <span class="mobile-collapsible-title">{{ $title }}</span>
        <svg class="mobile-collapsible-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
document.addEventListener('DOMContentLoaded', () => {
    // Handle collapsible section clicks
    const headers = document.querySelectorAll('.mobile-collapsible-header');
    headers.forEach(header => {
        header.addEventListener('click', (e) => {
            e.preventDefault();
            const section = header.closest('.mobile-collapsible');
            if (section) {
                section.classList.toggle('open');
            }
        });
    });
});
</script>
