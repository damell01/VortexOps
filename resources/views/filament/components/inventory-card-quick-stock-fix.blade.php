{{--
    Reliable bridge for the custom All Inventory card catalog.

    The visible Add Stock button is injected by mobile-inventory-hotfixes, but
    mounting a Filament action directly from browser JavaScript has proven
    unreliable on this custom ListRecords page. Capture the click first, call a
    normal public Livewire method, and let the PHP page mount its own action.
--}}
<script>
(() => {
    if (window.__vxInventoryCardQuickStockFixV2) return;
    window.__vxInventoryCardQuickStockFixV2 = true;

    const recordIdFromCard = (card) => {
        const viewLink = card?.querySelector('a[href*="/admin/inventory-items/"]');
        const match = viewLink?.getAttribute('href')?.match(/\/admin\/inventory-items\/(\d+)(?:$|[/?#])/);
        return match ? Number(match[1]) : null;
    };

    const inventoryComponentFor = (element) => {
        const root = element?.closest?.('[wire\\:id]');
        const componentId = root?.getAttribute('wire:id');
        return componentId && window.Livewire ? window.Livewire.find(componentId) : null;
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest?.('.vx-product-card .vx-card-action.add-stock');
        if (!button) return;

        const card = button.closest('.vx-product-card');
        const recordId = recordIdFromCard(card);
        const component = inventoryComponentFor(card);
        if (!recordId || !component) return;

        // Stop the legacy injected-button handler before it reaches Filament.
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        try {
            await component.call('openQuickAddStock', recordId);
        } catch (error) {
            console.error('[VortexOps] Could not open quick Add Stock action', error);
            window.toast?.error?.('Could not open Add Stock. Refresh the page and try again.');
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    }, true);

    // The shared camera scanner emits barcode-scanned. When the quick-stock
    // modal owns the scan target, verify the code against that item rather than
    // treating it like the normal "assign/replace barcode" card action.
    window.addEventListener('barcode-scanned', async (event) => {
        const value = String(event.detail?.value || '').trim();
        if (!value || !window.Livewire) return;

        const inventoryRoot = document.querySelector('[data-vx-page="inventory-center"]')?.closest('[wire\\:id]');
        const componentId = inventoryRoot?.getAttribute('wire:id');
        const component = componentId ? window.Livewire.find(componentId) : null;
        if (!component) return;

        try {
            const quickTarget = component.$wire?.quickStockScanTargetId ?? component.quickStockScanTargetId;
            if (quickTarget) {
                await component.call('verifyQuickStockBarcode', value);
            }
        } catch (error) {
            console.error('[VortexOps] Could not verify quick-stock barcode', error);
        }
    });
})();
</script>
