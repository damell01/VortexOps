{{-- Targeted inventory / receiving UI fixes that survive Filament SPA navigation. --}}
<style>
/* Inventory cards: five actions need room to breathe. */
.vx-card-actions {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    align-items: stretch !important;
}
.vx-card-action {
    width: 100% !important;
    min-width: 0 !important;
    padding-inline: 8px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.vx-card-action.add-stock {
    color: #047857 !important;
    background: #ecfdf5 !important;
    border-color: #a7f3d0 !important;
}
.dark .vx-card-action.add-stock {
    color: #6ee7b7 !important;
    background: #052e2b !important;
    border-color: #065f46 !important;
}

@media (max-width: 768px) {
    .vx-card-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
    }
    .vx-card-action {
        min-height: 44px !important;
        font-size: 12px !important;
        padding-inline: 10px !important;
    }

    /* Keep the live inventory picker above the iOS keyboard instead of at the bottom. */
    body.vx-pallet-screen [data-vx-active-inventory-picker="1"] {
        position: fixed !important;
        left: 10px !important;
        right: 10px !important;
        top: calc(env(safe-area-inset-top, 0px) + 78px) !important;
        bottom: auto !important;
        inset-inline: 10px !important;
        width: auto !important;
        max-width: none !important;
        min-width: 0 !important;
        height: auto !important;
        max-height: min(430px, 58dvh) !important;
        transform: none !important;
        z-index: 10000 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        border-radius: 14px !important;
        background: #111827 !important;
        border: 1px solid #334155 !important;
        box-shadow: 0 24px 70px rgba(0,0,0,.55) !important;
    }

    html:not(.dark) body.vx-pallet-screen [data-vx-active-inventory-picker="1"] {
        background: #fff !important;
        border-color: #cbd5e1 !important;
    }

    body.vx-pallet-screen [data-vx-active-inventory-picker="1"] [role="listbox"] {
        position: static !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
        max-height: min(320px, 42dvh) !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }

    body.vx-pallet-screen [data-vx-active-inventory-picker="1"] input[type="search"],
    body.vx-pallet-screen [data-vx-active-inventory-picker="1"] input[role="combobox"] {
        position: relative !important;
        z-index: 2 !important;
        min-height: 48px !important;
        width: 100% !important;
        font-size: 16px !important;
    }

    body.vx-pallet-screen [data-vx-active-inventory-picker="1"] [role="option"] {
        min-height: 50px !important;
        padding: 10px 12px !important;
        white-space: normal !important;
    }

    body.vx-pallet-screen [data-vx-active-inventory-picker="1"] [data-vx-picker-helper="1"] {
        position: static !important;
        flex: 0 0 auto !important;
        margin: 0 !important;
        padding: 8px 12px !important;
        border-top: 1px solid rgba(148,163,184,.16) !important;
        background: #0f172a !important;
        color: #94a3b8 !important;
        font-size: 12px !important;
    }

    html:not(.dark) body.vx-pallet-screen [data-vx-active-inventory-picker="1"] [data-vx-picker-helper="1"] {
        background: #f8fafc !important;
        color: #64748b !important;
    }

    /* View Pallet: make the Filament header behave like a real mobile action panel. */
    body.vx-pallet-view-screen .fi-page-header {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
        width: 100% !important;
    }

    body.vx-pallet-view-screen .fi-page-header-main-ctn,
    body.vx-pallet-view-screen .fi-page-header-main,
    body.vx-pallet-view-screen .fi-page-header > div {
        width: 100% !important;
        min-width: 0 !important;
    }

    body.vx-pallet-view-screen .fi-header-actions,
    body.vx-pallet-view-screen .fi-header-actions-ctn {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 9px !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        align-items: stretch !important;
    }

    body.vx-pallet-view-screen .fi-header-actions > *,
    body.vx-pallet-view-screen .fi-header-actions-ctn > * {
        width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
    }

    body.vx-pallet-view-screen .fi-header-actions .fi-btn,
    body.vx-pallet-view-screen .fi-header-actions-ctn .fi-btn,
    body.vx-pallet-view-screen .fi-header-actions a,
    body.vx-pallet-view-screen .fi-header-actions button {
        width: 100% !important;
        min-width: 0 !important;
        min-height: 50px !important;
        padding: 10px 12px !important;
        border-radius: 10px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 7px !important;
        font-size: 13px !important;
        font-weight: 750 !important;
        line-height: 1.2 !important;
        text-align: center !important;
        white-space: normal !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }

    body.vx-pallet-view-screen [data-vx-pallet-wide="1"] {
        grid-column: 1 / -1 !important;
    }

    body.vx-pallet-view-screen [data-vx-pallet-kind="receive"] {
        color: #fff !important;
        background: #059669 !important;
        border-color: #059669 !important;
    }

    body.vx-pallet-view-screen [data-vx-pallet-kind="scan"] {
        color: #fff !important;
        background: #2563eb !important;
        border-color: #2563eb !important;
    }

    body.vx-pallet-view-screen [data-vx-pallet-kind="review"] {
        color: #fff !important;
        background: #059669 !important;
        border-color: #059669 !important;
    }

    body.vx-pallet-view-screen [data-vx-pallet-kind="secondary"],
    body.vx-pallet-view-screen [data-vx-pallet-kind="more"] {
        color: #e5e7eb !important;
        background: #111827 !important;
        border: 1px solid #334155 !important;
    }

    html:not(.dark) body.vx-pallet-view-screen [data-vx-pallet-kind="secondary"],
    html:not(.dark) body.vx-pallet-view-screen [data-vx-pallet-kind="more"] {
        color: #172033 !important;
        background: #fff !important;
        border-color: #cbd5e1 !important;
    }

    /* Tighten the page cards and make labels readable on a phone. */
    body.vx-pallet-view-screen .fi-page-content > .space-y-6 > * + * {
        margin-top: 12px !important;
    }

    body.vx-pallet-view-screen .fi-page-content .rounded-xl.border {
        border-radius: 14px !important;
    }

    body.vx-pallet-view-screen .fi-page-content .px-6.py-4 {
        padding: 14px !important;
    }

    body.vx-pallet-view-screen .fi-page-content .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: repeat(2, minmax(0,1fr)) !important;
        gap: 12px !important;
    }

    body.vx-pallet-view-screen .fi-page-content .text-xs.text-gray-400.uppercase {
        color: #94a3b8 !important;
        font-size: 10px !important;
        letter-spacing: .06em !important;
    }

    html.dark body.vx-pallet-view-screen .fi-page-content .rounded-xl.border {
        background: #101827 !important;
        border-color: #263248 !important;
        box-shadow: 0 8px 24px rgba(0,0,0,.16) !important;
    }

    html.dark body.vx-pallet-view-screen .fi-page-content .text-gray-900,
    html.dark body.vx-pallet-view-screen .fi-page-content .dark\:text-gray-100 {
        color: #f8fafc !important;
    }
}
</style>

<script>
(() => {
    const isVisible = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';

    const markPage = () => {
        const path = location.pathname.toLowerCase();
        const onPallet = path.includes('/admin/pallet') || path.includes('/admin/receive-inventory') || !!document.querySelector('input[placeholder*="Search or browse inventory" i]');
        const onPalletView = /^\/admin\/pallets\/\d+\/?$/.test(path);
        document.body.classList.toggle('vx-pallet-screen', onPallet);
        document.body.classList.toggle('vx-pallet-view-screen', onPalletView);
        return { onPallet, onPalletView };
    };

    const findPickerWrapper = (input) => {
        if (!input) return null;
        let node = input;
        for (let i = 0; node && node !== document.body && i < 12; i++, node = node.parentElement) {
            if (node.querySelector?.('[role="listbox"]')) return node;
        }
        return null;
    };

    const pinActivePicker = () => {
        const state = markPage();
        if (innerWidth > 768 || !state.onPallet) return;

        document.querySelectorAll('[data-vx-active-inventory-picker="1"]').forEach(el => delete el.dataset.vxActiveInventoryPicker);

        const active = document.activeElement;
        let wrapper = findPickerWrapper(active);

        if (!wrapper) {
            const visibleSearch = [...document.querySelectorAll('input[type="search"], input[role="combobox"]')]
                .find(input => isVisible(input) && findPickerWrapper(input));
            wrapper = findPickerWrapper(visibleSearch);
        }

        if (!wrapper) return;

        wrapper.dataset.vxActiveInventoryPicker = '1';

        ['position','left','right','top','bottom','inset','transform','width','height','max-height'].forEach(prop => {
            wrapper.style.removeProperty(prop);
        });

        [...wrapper.querySelectorAll('div,p,span')].forEach(node => {
            const text = (node.textContent || '').trim();
            if (text.length < 120 && /showing up to|keep typing to narrow/i.test(text)) {
                node.dataset.vxPickerHelper = '1';
            }
        });

        const viewport = window.visualViewport;
        if (viewport) {
            const top = Math.max(72, Math.round(viewport.offsetTop + 72));
            const maxHeight = Math.max(220, Math.min(430, Math.round(viewport.height - 92)));
            wrapper.style.setProperty('top', top + 'px', 'important');
            wrapper.style.setProperty('max-height', maxHeight + 'px', 'important');
        }
    };

    const injectAddStockButtons = () => {
        document.querySelectorAll('.vx-product-card .vx-card-actions').forEach(actions => {
            if (actions.querySelector('.vx-card-action.add-stock')) return;

            const card = actions.closest('.vx-product-card');
            const viewLink = card?.querySelector('a[href*="/admin/inventory-items/"]');
            const match = viewLink?.getAttribute('href')?.match(/\/admin\/inventory-items\/(\d+)(?:$|[/?#])/);
            if (!match) return;
            const recordId = Number(match[1]);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vx-card-action add-stock';
            btn.title = 'Add stock';
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg><span>Add Stock</span>';
            btn.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                const root = card.closest('[wire\\:id]');
                const id = root?.getAttribute('wire:id');
                if (!id || !window.Livewire) return;
                const component = window.Livewire.find(id);
                if (!component) return;
                await component.call('mountTableAction', 'add_stock', recordId);
            });

            actions.prepend(btn);
        });
    };

    const markPalletHeaderActions = () => {
        const { onPalletView } = markPage();
        if (!onPalletView) return;

        const header = document.querySelector('.fi-page-header');
        if (!header) return;

        const actions = header.querySelectorAll('.fi-header-actions a, .fi-header-actions button, .fi-header-actions-ctn a, .fi-header-actions-ctn button');
        actions.forEach(action => {
            const text = (action.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            let kind = 'secondary';
            let wide = false;

            if (text.includes('receiving')) { kind = 'receive'; wide = true; }
            else if (text.includes('scan item')) { kind = 'scan'; }
            else if (text.includes('review') && text.includes('receive')) { kind = 'review'; wide = true; }
            else if (text === 'more' || text.endsWith(' more')) { kind = 'more'; wide = true; }

            action.dataset.vxPalletKind = kind;
            if (wide) action.dataset.vxPalletWide = '1';

            const container = action.parentElement;
            if (container && container.closest('.fi-header-actions, .fi-header-actions-ctn')) {
                container.dataset.vxPalletKind = kind;
                if (wide) container.dataset.vxPalletWide = '1';
            }
        });
    };

    const refresh = () => {
        markPage();
        injectAddStockButtons();
        markPalletHeaderActions();
        requestAnimationFrame(pinActivePicker);
    };

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('livewire:navigated', refresh);
    document.addEventListener('focusin', () => setTimeout(pinActivePicker, 0));
    document.addEventListener('input', () => setTimeout(pinActivePicker, 0));
    window.visualViewport?.addEventListener('resize', pinActivePicker);
    window.visualViewport?.addEventListener('scroll', pinActivePicker);

    new MutationObserver(() => requestAnimationFrame(refresh)).observe(document.documentElement, { childList: true, subtree: true });
    refresh();
})();
</script>
