{{-- Dedicated catalog Add Stock bridge.

    The custom inventory catalog has repeatedly proven unreliable when it tries
    to mount Filament page actions from card markup. This bridge deliberately
    bypasses the list page action layer entirely: it intercepts the card click,
    extracts the product id, and opens a standalone Livewire modal component.
--}}
<livewire:inventory-quick-stock-modal />

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
