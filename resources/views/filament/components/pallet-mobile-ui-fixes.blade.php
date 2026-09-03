{{--
    Pallet / receiving mobile polish.

    Filament's searchable select is rendered through a floating/teleported
    panel on phones. iOS can expand that panel into a near full-screen list
    once the search results refresh. The rules and JS below keep the inventory
    picker compact, readable and scrollable without taking over the page.
--}}
<style>
@media (max-width: 768px) {
    body.vx-pallet-screen .fi-fo-select-ctn {
        position: relative !important;
        overflow: visible !important;
    }

    /* Panels that are still rendered directly under the select. */
    body.vx-pallet-screen .fi-fo-select-ctn > .fi-dropdown-panel,
    body.vx-pallet-screen .fi-fo-select-ctn > [role="listbox"] {
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        top: calc(100% + 6px) !important;
        bottom: auto !important;
        width: 100% !important;
        max-height: min(360px, 48dvh) !important;
        transform: none !important;
        z-index: 90 !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        border-radius: 12px !important;
        border: 1px solid rgba(148, 163, 184, .24) !important;
        background: #111827 !important;
        box-shadow: 0 18px 45px rgba(0, 0, 0, .42) !important;
    }

    /* Teleported Filament select popup — JS marks the actual wrapper. */
    body.vx-pallet-screen [data-vx-pallet-select-popup="1"] {
        position: fixed !important;
        left: 12px !important;
        right: 12px !important;
        top: max(96px, 18dvh) !important;
        bottom: auto !important;
        width: auto !important;
        min-width: 0 !important;
        max-width: none !important;
        height: auto !important;
        max-height: min(430px, 62dvh) !important;
        transform: none !important;
        z-index: 9999 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        border-radius: 14px !important;
        border: 1px solid rgba(148, 163, 184, .28) !important;
        background: #111827 !important;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .5) !important;
    }

    html:not(.dark) body.vx-pallet-screen [data-vx-pallet-select-popup="1"] {
        background: #ffffff !important;
        border-color: rgba(15, 23, 42, .14) !important;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .22) !important;
    }

    body.vx-pallet-screen [data-vx-pallet-select-popup="1"] [data-vx-pallet-search-wrap="1"] {
        position: sticky !important;
        top: 0 !important;
        z-index: 3 !important;
        flex: 0 0 auto !important;
        padding: 10px !important;
        background: #111827 !important;
        border-bottom: 1px solid rgba(148, 163, 184, .18) !important;
    }

    html:not(.dark) body.vx-pallet-screen [data-vx-pallet-select-popup="1"] [data-vx-pallet-search-wrap="1"] {
        background: #ffffff !important;
    }

    body.vx-pallet-screen [data-vx-pallet-select-popup="1"] [data-vx-pallet-results="1"] {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        max-height: min(330px, 46dvh) !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        -webkit-overflow-scrolling: touch;
        padding: 6px !important;
    }

    body.vx-pallet-screen [data-vx-pallet-select-popup="1"] [data-vx-pallet-footer="1"] {
        flex: 0 0 auto !important;
        position: static !important;
        padding: 9px 12px !important;
        background: #0f172a !important;
        border-top: 1px solid rgba(148, 163, 184, .16) !important;
        color: #94a3b8 !important;
        font-size: 12px !important;
    }

    html:not(.dark) body.vx-pallet-screen [data-vx-pallet-select-popup="1"] [data-vx-pallet-footer="1"] {
        background: #f8fafc !important;
        color: #64748b !important;
    }

    body.vx-pallet-screen .fi-fo-select-search-ctn {
        position: sticky !important;
        top: 0 !important;
        z-index: 2 !important;
        padding: 10px !important;
        background: inherit !important;
        border-bottom: 1px solid rgba(148, 163, 184, .18) !important;
    }

    body.vx-pallet-screen .fi-fo-select-search-ctn .fi-input,
    body.vx-pallet-screen .fi-fo-select-search-ctn input,
    body.vx-pallet-screen [data-vx-pallet-select-popup="1"] input[type="search"],
    body.vx-pallet-screen [data-vx-pallet-select-popup="1"] input[role="combobox"] {
        min-height: 46px !important;
        width: 100% !important;
        font-size: 16px !important;
        color: #f8fafc !important;
        background: #1e293b !important;
        border-radius: 9px !important;
        padding-inline: 12px !important;
    }

    html:not(.dark) body.vx-pallet-screen .fi-fo-select-search-ctn .fi-input,
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-search-ctn input,
    html:not(.dark) body.vx-pallet-screen [data-vx-pallet-select-popup="1"] input[type="search"],
    html:not(.dark) body.vx-pallet-screen [data-vx-pallet-select-popup="1"] input[role="combobox"] {
        color: #0f172a !important;
        background: #f8fafc !important;
    }

    body.vx-pallet-screen .fi-dropdown-list {
        padding: 6px !important;
    }

    body.vx-pallet-screen .fi-dropdown-list-item.fi-fo-select-option,
    body.vx-pallet-screen .fi-fo-select-option,
    body.vx-pallet-screen [data-vx-pallet-results="1"] [role="option"] {
        min-height: 50px !important;
        display: flex !important;
        align-items: center !important;
        padding: 10px 12px !important;
        margin: 2px 0 !important;
        border-radius: 9px !important;
        color: #f8fafc !important;
        font-size: 14px !important;
        line-height: 1.35 !important;
        white-space: normal !important;
    }

    html:not(.dark) body.vx-pallet-screen .fi-dropdown-list-item.fi-fo-select-option,
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-option,
    html:not(.dark) body.vx-pallet-screen [data-vx-pallet-results="1"] [role="option"] {
        color: #0f172a !important;
    }

    body.vx-pallet-screen .fi-fo-select-option:hover,
    body.vx-pallet-screen .fi-fo-select-option:focus,
    body.vx-pallet-screen .fi-fo-select-option[aria-selected="true"],
    body.vx-pallet-screen [data-vx-pallet-results="1"] [role="option"][aria-selected="true"] {
        background: rgba(124, 58, 237, .18) !important;
        color: #ffffff !important;
    }

    html:not(.dark) body.vx-pallet-screen .fi-fo-select-option:hover,
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-option:focus,
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-option[aria-selected="true"],
    html:not(.dark) body.vx-pallet-screen [data-vx-pallet-results="1"] [role="option"][aria-selected="true"] {
        color: #4c1d95 !important;
        background: #ede9fe !important;
    }

    /* Pallet/receiving header actions: compact 2-column grid with readable labels. */
    body.vx-pallet-screen .fi-header-actions,
    body.vx-pallet-screen .fi-header-actions-ctn {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        width: 100% !important;
    }

    body.vx-pallet-screen .fi-header-actions > *,
    body.vx-pallet-screen .fi-header-actions-ctn > * {
        width: 100% !important;
        min-width: 0 !important;
    }

    body.vx-pallet-screen .fi-header-actions .fi-btn,
    body.vx-pallet-screen .fi-header-actions-ctn .fi-btn {
        width: 100% !important;
        min-height: 50px !important;
        padding: 10px 12px !important;
        border-radius: 10px !important;
        justify-content: center !important;
        gap: 7px !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        line-height: 1.2 !important;
        text-align: center !important;
    }

    body.vx-pallet-screen .fi-btn.fi-color-primary,
    body.vx-pallet-screen .fi-btn.fi-color-success,
    body.vx-pallet-screen .fi-btn.fi-color-info,
    body.vx-pallet-screen .fi-btn-color-primary,
    body.vx-pallet-screen .fi-btn-color-success,
    body.vx-pallet-screen .fi-btn-color-info {
        color: #ffffff !important;
    }

    body.vx-pallet-screen .fi-btn.fi-color-gray,
    body.vx-pallet-screen .fi-btn-color-gray {
        color: #f1f5f9 !important;
        background: #182235 !important;
        border: 1px solid #334155 !important;
    }

    html:not(.dark) body.vx-pallet-screen .fi-btn.fi-color-gray,
    html:not(.dark) body.vx-pallet-screen .fi-btn-color-gray {
        color: #172033 !important;
        background: #ffffff !important;
        border-color: #cbd5e1 !important;
    }

    /* Cleaner pallet cards and less dead vertical space on phones. */
    html.dark body.vx-pallet-screen .fi-page .rounded-xl.border.bg-white,
    html.dark body.vx-pallet-screen .fi-page .rounded-xl.border.dark\:bg-gray-900 {
        border-color: #293548 !important;
        background: #111827 !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .16) !important;
    }

    html:not(.dark) body.vx-pallet-screen .fi-page .rounded-xl.border.bg-white {
        border-color: #dbe3ee !important;
        background: #ffffff !important;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .07) !important;
    }

    body.vx-pallet-screen .fi-page .px-6.py-4 {
        padding: 16px !important;
    }

    body.vx-pallet-screen .fi-page .gap-6 {
        gap: 14px !important;
    }

    html.dark body.vx-pallet-screen .fi-page .text-gray-400 {
        color: #aeb9ca !important;
    }

    html.dark body.vx-pallet-screen .fi-page .text-gray-500,
    html.dark body.vx-pallet-screen .fi-page .dark\:text-gray-400 {
        color: #c2cad7 !important;
    }

    html.dark body.vx-pallet-screen .fi-page h1,
    html.dark body.vx-pallet-screen .fi-page h2,
    html.dark body.vx-pallet-screen .fi-page .font-semibold {
        color: #f8fafc;
    }
}
</style>

<script>
(() => {
    const visible = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';

    const markPalletScreens = () => {
        const path = window.location.pathname.toLowerCase();
        const isPallet = /\/admin\/(pallets|receive-inventory)(\/|$)/.test(path)
            || path.includes('/admin/pallet')
            || document.querySelector('[wire\\:click*="confirmCase"], [wire\\:click*="linkLine"], input[placeholder*="Search or browse inventory" i]');

        document.body.classList.toggle('vx-pallet-screen', Boolean(isPallet));
    };

    const markTeleportedSelect = () => {
        if (!document.body.classList.contains('vx-pallet-screen') || window.innerWidth > 768) return;

        document.querySelectorAll('[role="listbox"]').forEach((listbox) => {
            if (!visible(listbox)) return;

            /* Ignore an inline listbox that is already inside its select. */
            if (listbox.closest('.fi-fo-select-ctn')) return;

            let popup = listbox;
            let cursor = listbox.parentElement;

            /* Find the smallest floating wrapper that contains the list plus
               the select search input/helper text. Filament's exact wrapper
               classes vary between releases, so this deliberately uses the DOM
               relationship instead of one brittle class name. */
            for (let i = 0; cursor && cursor !== document.body && i < 7; i++, cursor = cursor.parentElement) {
                const hasSearch = cursor.querySelector('input[type="search"], input[role="combobox"], .fi-fo-select-search-ctn');
                const rect = cursor.getBoundingClientRect();
                if (hasSearch && rect.width > 200) {
                    popup = cursor;
                    break;
                }
            }

            popup.dataset.vxPalletSelectPopup = '1';
            listbox.dataset.vxPalletResults = '1';

            const search = popup.querySelector('input[type="search"], input[role="combobox"], .fi-fo-select-search-ctn input');
            if (search) {
                const wrap = search.closest('.fi-fo-select-search-ctn') || search.parentElement;
                if (wrap) wrap.dataset.vxPalletSearchWrap = '1';
            }

            /* Filament's mobile helper is the line that says things such as
               “Showing up to 50 — keep typing to narrow it down.” Keep that
               outside the scrolling results so it never pushes the field away. */
            [...popup.querySelectorAll('div, p, span')].forEach((node) => {
                const text = (node.textContent || '').trim();
                if (text && text.length < 120 && /showing up to|keep typing to narrow/i.test(text)) {
                    node.dataset.vxPalletFooter = '1';
                }
            });

            /* Remove the viewport-sized coordinates written by the floating
               positioning library. CSS now owns the compact mobile geometry. */
            ['left','right','top','bottom','inset','transform','width','height','max-height','position'].forEach((prop) => {
                popup.style.removeProperty(prop);
            });
        });
    };

    const refresh = () => {
        markPalletScreens();
        requestAnimationFrame(markTeleportedSelect);
    };

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('livewire:navigated', refresh);
    document.addEventListener('focusin', () => setTimeout(refresh, 0));
    document.addEventListener('input', () => setTimeout(refresh, 0));

    const observer = new MutationObserver(() => refresh());
    observer.observe(document.documentElement, { childList: true, subtree: true });

    refresh();
})();
</script>
