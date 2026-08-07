{{-- Floating Action Button Component with Submenu --}}
<button class="floating-action-button" aria-label="{{ $label ?? 'Actions' }}" title="{{ $label ?? 'Actions' }}">
    {!! $icon ?? '➕' !!}
</button>

@if($slot->isNotEmpty())
    <div class="floating-action-overlay"></div>
    <div class="floating-action-menu">
        {{ $slot }}
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fab = document.querySelector('.floating-action-button');
    const menu = document.querySelector('.floating-action-menu');
    const overlay = document.querySelector('.floating-action-overlay');

    if (!fab || !menu) return;

    fab.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        menu.classList.toggle('open');
        overlay.classList.toggle('open');
    });

    if (overlay) {
        overlay.addEventListener('click', () => {
            menu.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    // Close menu when clicking items
    const items = document.querySelectorAll('.floating-action-menu-item');
    items.forEach(item => {
        item.addEventListener('click', () => {
            menu.classList.remove('open');
            overlay.classList.remove('open');
        });
    });
});
</script>
