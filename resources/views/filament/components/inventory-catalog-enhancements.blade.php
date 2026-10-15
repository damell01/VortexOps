{{-- Inventory catalog density + automatic incremental loading. --}}
<style>
/* Let the grid decide how many cards fit based on the actual content width,
   not the viewport. This stays sane with the Filament sidebar open or closed. */
.vx-catalog-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)) !important;
    gap: 10px !important;
}

/* Card density (image, body, actions) lives with the page: list-inventory-items.blade.php. */

/* The old manual button remains in the DOM as the Livewire trigger, but users
   no longer need to click it. The observer below activates it automatically. */
.vx-catalog-footer .vx-load-more {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}

.vx-catalog-footer {
    min-height: 42px !important;
    padding: 10px 0 18px !important;
}

.vx-catalog-footer::after {
    content: 'Scroll to load more';
    font-size: 11px;
    color: #94a3b8;
}

.vx-catalog-footer[data-vx-loading="1"]::after {
    content: 'Loading more inventory…';
}

@media (max-width: 640px) {
    .vx-catalog-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 9px !important;
    }

}
</style>

<script>
(() => {
    if (window.__vxCatalogInfiniteScrollInstalled) return;
    window.__vxCatalogInfiniteScrollInstalled = true;

    let observer = null;
    let observedFooter = null;
    let clickPending = false;

    const findLoadButton = (footer) => footer?.querySelector('.vx-load-more');

    const loadMore = (footer) => {
        if (!footer || clickPending) return;
        const button = findLoadButton(footer);
        if (!button || button.disabled || button.getAttribute('wire:loading.attr') === null && !button.isConnected) return;

        clickPending = true;
        footer.dataset.vxLoading = '1';
        button.click();

        // Livewire replaces the catalog/footer after the request. This guard is
        // only to prevent repeated IntersectionObserver callbacks in that gap.
        setTimeout(() => {
            clickPending = false;
            if (footer.isConnected) delete footer.dataset.vxLoading;
            bind();
        }, 900);
    };

    const bind = () => {
        const footer = document.querySelector('.vx-catalog-footer');
        if (!footer) {
            if (observer) observer.disconnect();
            observer = null;
            observedFooter = null;
            return;
        }

        const button = findLoadButton(footer);
        if (!button) {
            footer.dataset.vxComplete = '1';
            footer.style.minHeight = '20px';
            return;
        }

        if (footer === observedFooter && observer) return;

        if (observer) observer.disconnect();
        observedFooter = footer;
        observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) loadMore(entry.target);
            }
        }, {
            root: null,
            rootMargin: '500px 0px 500px 0px',
            threshold: 0.01,
        });
        observer.observe(footer);
    };

    document.addEventListener('DOMContentLoaded', bind);
    document.addEventListener('livewire:navigated', () => setTimeout(bind, 0));
    document.addEventListener('livewire:updated', () => setTimeout(bind, 0));

    new MutationObserver(() => requestAnimationFrame(bind))
        .observe(document.documentElement, { childList: true, subtree: true });

    bind();
})();
</script>
