{{--
    The catalog Add Stock button is injected by mobile-inventory-hotfixes.
    That older bridge tried to mount a table action while the card catalog was
    active, so nothing opened. Capture the click first and mount the dedicated
    ListInventoryItems page action instead.
--}}
<script>
(() => {
    if (window.__vxInventoryCardQuickStockFix) return;
    window.__vxInventoryCardQuickStockFix = true;

    const recordIdFromCard = (card) => {
        const viewLink = card?.querySelector('a[href*="/admin/inventory-items/"]');
        const match = viewLink?.getAttribute('href')?.match(/\/admin\/inventory-items\/(\d+)(?:$|[/?#])/);
        return match ? Number(match[1]) : null;
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest?.('.vx-product-card .vx-card-action.add-stock');
        if (!button) return;

        const card = button.closest('.vx-product-card');
        const recordId = recordIdFromCard(card);
        if (!recordId) return;

        const root = card.closest('[wire\\:id]');
        const componentId = root?.getAttribute('wire:id');
        const component = componentId && window.Livewire ? window.Livewire.find(componentId) : null;
        if (!component) return;

        // Stop the legacy mountTableAction listener on the injected button.
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        try {
            await component.call('mountAction', 'quickAddStock', { product: recordId });
        } catch (error) {
            console.error('[VortexOps] Could not open quick Add Stock action', error);
            window.toast?.error?.('Could not open Add Stock. Refresh the page and try again.');
        }
    }, true);
})();
</script>
