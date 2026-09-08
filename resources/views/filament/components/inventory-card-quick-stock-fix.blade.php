{{--
    Legacy fallback for older inventory card markup.

    The catalog now renders its own native Livewire Add Stock button. Never
    intercept that button: letting Livewire process wire:click is the reliable
    path and mirrors the working Filament buttons elsewhere in VortexOps.
--}}
<script>
(() => {
    if (window.__vxInventoryCardQuickStockFixV3) return;
    window.__vxInventoryCardQuickStockFixV3 = true;

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

        // Native card buttons are rendered by Blade with wire:click. Leave
        // those entirely to Livewire; this listener only supports any stale
        // legacy button that may have been injected into an old DOM snapshot.
        if (button.hasAttribute('wire:click') || button.hasAttribute('wire:click.stop')) {
            return;
        }

        const card = button.closest('.vx-product-card');
        const recordId = recordIdFromCard(card);
        const component = inventoryComponentFor(card);
        if (!recordId || !component) return;

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
