{{--
    The manifest's inventory picker is our own Alpine modal, not a Filament Select.
    On iOS Safari the mobile `items-end` layout puts the search/results sheet behind
    the software keyboard. Detect that one picker by its search placeholder and pin
    its panel to the visual viewport instead of the bottom of the layout viewport.
--}}
<style>
@media (max-width: 768px) {
    [data-vx-manifest-picker-overlay="1"] {
        align-items: flex-start !important;
        justify-content: center !important;
        padding: 0 10px 10px !important;
        overflow: hidden !important;
    }

    [data-vx-manifest-picker-panel="1"] {
        position: fixed !important;
        left: 10px !important;
        right: 10px !important;
        bottom: auto !important;
        width: auto !important;
        min-width: 0 !important;
        max-width: none !important;
        margin: 0 !important;
        border-radius: 16px !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        background: #111827 !important;
        border: 1px solid #334155 !important;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .55) !important;
    }

    html:not(.dark) [data-vx-manifest-picker-panel="1"] {
        background: #ffffff !important;
        border-color: #cbd5e1 !important;
    }

    [data-vx-manifest-picker-panel="1"] > :first-child {
        flex: 0 0 auto !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 3 !important;
        background: inherit !important;
    }

    [data-vx-manifest-picker-results="1"] {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        -webkit-overflow-scrolling: touch;
    }

    [data-vx-manifest-picker-panel="1"] input[type="search"] {
        min-height: 46px !important;
        font-size: 16px !important;
    }
}
</style>

<script>
(() => {
    const findPicker = () => {
        if (window.innerWidth > 768) return;

        const input = [...document.querySelectorAll('input[type="search"]')].find((el) =>
            (el.getAttribute('placeholder') || '').toLowerCase().includes('search name, sku, upc or barcode') &&
            el.getClientRects().length > 0
        );

        if (!input) return;

        const panel = input.closest('.flex.flex-col') || input.parentElement?.parentElement;
        if (!panel) return;

        const overlay = panel.parentElement;
        if (!overlay) return;

        overlay.dataset.vxManifestPickerOverlay = '1';
        panel.dataset.vxManifestPickerPanel = '1';

        const children = [...panel.children];
        if (children.length >= 2) {
            children[1].dataset.vxManifestPickerResults = '1';
        }

        const viewport = window.visualViewport;
        const viewportTop = viewport ? viewport.offsetTop : 0;
        const viewportHeight = viewport ? viewport.height : window.innerHeight;

        // Keep the picker below the VortexOps top bar but completely inside the
        // actually visible area above the software keyboard.
        const top = Math.max(viewportTop + 72, viewportTop + 12);
        const available = Math.max(220, viewportHeight - 92);
        const height = Math.min(520, available);

        panel.style.setProperty('top', `${Math.round(top)}px`, 'important');
        panel.style.setProperty('max-height', `${Math.round(height)}px`, 'important');
        panel.style.setProperty('height', `${Math.round(height)}px`, 'important');
    };

    const refresh = () => requestAnimationFrame(findPicker);

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('livewire:navigated', refresh);
    document.addEventListener('focusin', refresh);
    document.addEventListener('input', refresh);
    window.visualViewport?.addEventListener('resize', refresh);
    window.visualViewport?.addEventListener('scroll', refresh);

    new MutationObserver(refresh).observe(document.documentElement, {
        childList: true,
        subtree: true,
    });

    refresh();
})();
</script>
