{{-- Dedicated catalog Add Stock bridge.

    The custom inventory catalog has repeatedly proven unreliable when it tries
    to mount Filament page actions from card markup. This bridge deliberately
    bypasses the list page action layer entirely: it intercepts the card click,
    extracts the product id, and opens a standalone Livewire modal component.
--}}
<livewire:inventory-quick-stock-modal />

{{-- The global camera scanner normally sits at z-100. Quick Stock intentionally
     uses a very high modal layer, so without this override the scanner opens
     behind the Add Stock dialog and looks like it did nothing. Keep the scanner
     above Quick Stock so users can see the live camera, scan, then return to the
     still-open stock modal with the barcode populated. --}}
<style>
    [x-data="cameraScanner()"] > [x-show="isOpen"] {
        z-index: 10050 !important;
    }
</style>

<script>
(() => {
    if (window.__vxInventoryCardQuickStockFixV4) return;
    window.__vxInventoryCardQuickStockFixV4 = true;

    const recordIdFromCard = (card) => {
        const viewLink = card?.querySelector('a[href*="/admin/inventory-items/"]');
        const match = viewLink?.getAttribute('href')?.match(/\/admin\/inventory-items\/(\d+)(?:$|[/?#])/);
        return match ? Number(match[1]) : null;
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('.vx-product-card .vx-card-action.add-stock');
        if (!button) return;

        const card = button.closest('.vx-product-card');
        const recordId = recordIdFromCard(card);
        if (!recordId) {
            console.error('[VortexOps] Add Stock card is missing a record id');
            return;
        }

        // Stop every older wire:click / mountAction / injected handler. The
        // standalone modal component owns the entire flow now.
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        window.dispatchEvent(new CustomEvent('inventory-quick-stock', {
            detail: { productId: recordId },
        }));
    }, true);
})();
</script>
