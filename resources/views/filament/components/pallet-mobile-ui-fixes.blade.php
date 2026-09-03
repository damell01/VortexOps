{{--
    Pallet / receiving mobile polish.

    Filament positions searchable Select dropdowns with fixed viewport
    coordinates. On iOS that can leave the search/results tray pinned to the
    bottom of the screen after the keyboard/search result list changes height.
    For pallet/manifest workflows we keep the dropdown anchored to the field
    that opened it instead.
--}}
<style>
@media (max-width: 768px) {
    /* Keep searchable inventory results attached to the select that opened them. */
    body.vx-pallet-screen .fi-fo-select-ctn {
        position: relative !important;
        overflow: visible !important;
    }

    body.vx-pallet-screen .fi-fo-select-ctn > .fi-dropdown-panel,
    body.vx-pallet-screen .fi-fo-select-ctn > [role="listbox"] {
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        top: calc(100% + 6px) !important;
        bottom: auto !important;
        inset: auto 0 auto 0 !important;
        width: 100% !important;
        min-width: 100% !important;
        max-width: none !important;
        max-height: min(320px, 45vh) !important;
        transform: none !important;
        z-index: 90 !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        border-radius: 12px !important;
        border: 1px solid rgba(148, 163, 184, .24) !important;
        background: #111827 !important;
        box-shadow: 0 18px 45px rgba(0, 0, 0, .42) !important;
    }

    html:not(.dark) body.vx-pallet-screen .fi-fo-select-ctn > .fi-dropdown-panel,
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-ctn > [role="listbox"] {
        background: #ffffff !important;
        border-color: rgba(15, 23, 42, .14) !important;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .18) !important;
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
    body.vx-pallet-screen .fi-fo-select-search-ctn input {
        min-height: 46px !important;
        font-size: 16px !important;
        color: #f8fafc !important;
        background: #1e293b !important;
        border-radius: 9px !important;
        padding-inline: 12px !important;
    }

    html:not(.dark) body.vx-pallet-screen .fi-fo-select-search-ctn .fi-input,
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-search-ctn input {
        color: #0f172a !important;
        background: #f8fafc !important;
    }

    body.vx-pallet-screen .fi-dropdown-list {
        padding: 6px !important;
    }

    body.vx-pallet-screen .fi-dropdown-list-item.fi-fo-select-option,
    body.vx-pallet-screen .fi-fo-select-option {
        min-height: 52px !important;
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
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-option {
        color: #0f172a !important;
    }

    body.vx-pallet-screen .fi-fo-select-option:hover,
    body.vx-pallet-screen .fi-fo-select-option:focus,
    body.vx-pallet-screen .fi-fo-select-option[aria-selected="true"] {
        background: rgba(124, 58, 237, .18) !important;
        color: #ffffff !important;
    }

    html:not(.dark) body.vx-pallet-screen .fi-fo-select-option:hover,
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-option:focus,
    html:not(.dark) body.vx-pallet-screen .fi-fo-select-option[aria-selected="true"] {
        color: #4c1d95 !important;
        background: #ede9fe !important;
    }

    /* Do not dim the whole manifest just because a searchable select is open. */
    body.vx-pallet-screen .fi-fo-select-ctn + .fi-modal-close-overlay,
    body.vx-pallet-screen .fi-fo-select-ctn ~ .fi-modal-close-overlay {
        display: none !important;
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
    const markPalletScreens = () => {
        const path = window.location.pathname.toLowerCase();
        const isPallet = /\/admin\/(pallets|receive-inventory)(\/|$)/.test(path)
            || path.includes('/admin/pallet')
            || document.querySelector('[wire\\:click*="confirmCase"], [wire\\:click*="linkLine"], input[placeholder*="Search or browse inventory" i]');

        document.body.classList.toggle('vx-pallet-screen', Boolean(isPallet));
    };

    const keepSelectVisible = () => {
        if (!document.body.classList.contains('vx-pallet-screen') || window.innerWidth > 768) return;

        document.querySelectorAll('.fi-fo-select-ctn').forEach((container) => {
            const panel = container.querySelector(':scope > .fi-dropdown-panel, :scope > [role="listbox"]');
            if (!panel || panel.offsetParent === null) return;

            // Popper writes fixed viewport coordinates inline. Clearing them lets
            // the mobile CSS anchor the result list under the active field.
            panel.style.removeProperty('left');
            panel.style.removeProperty('right');
            panel.style.removeProperty('top');
            panel.style.removeProperty('bottom');
            panel.style.removeProperty('transform');
            panel.style.removeProperty('width');
            panel.style.removeProperty('position');
        });
    };

    const refresh = () => {
        markPalletScreens();
        requestAnimationFrame(keepSelectVisible);
    };

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('livewire:navigated', refresh);
    document.addEventListener('focusin', (event) => {
        if (event.target.closest?.('.fi-fo-select-ctn')) setTimeout(keepSelectVisible, 0);
    });
    document.addEventListener('input', (event) => {
        if (event.target.closest?.('.fi-fo-select-ctn')) setTimeout(keepSelectVisible, 0);
    });

    const observer = new MutationObserver(() => refresh());
    observer.observe(document.documentElement, { childList: true, subtree: true });

    refresh();
})();
</script>
