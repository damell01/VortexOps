<style>
    /* Shared form/dialog behavior. Inventory uses forms heavily, so keep
       confirmations compact while allowing real work to use the screen. */
    .fi-modal-window { max-height: min(92vh, 920px); }
    .fi-modal-content { overflow-y: auto; }

    @media (min-width: 641px) {
        .fi-modal-window:not(.fi-width-xs):not(.fi-width-sm) { width: min(92vw, 72rem) !important; max-width: 72rem !important; }
        .vx-inventory-edit .fi-section,
        .vx-inventory-item .fi-section { border-radius: .85rem !important; }
        .vx-tour-launcher:hover { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(15,23,42,.16); }
        .vx-mobile-proxy-bar { display: none !important; }
    }

    @media (max-width: 640px) {
        .fi-main { padding-inline: .75rem !important; }
        .fi-page-header { gap: .75rem !important; }
        .fi-page-header-heading { font-size: 1.35rem !important; line-height: 1.2 !important; }
        .fi-page-header-subheading { margin-top: .25rem !important; font-size: .75rem !important; line-height: 1.25rem !important; }
        .fi-btn:not(.fi-icon-btn) { min-height: 42px; }

        input:not([type="checkbox"]):not([type="radio"]):not([type="color"]), select, textarea {
            font-size: 16px !important;
        }

        /* Complex Filament forms should feel like a page on a phone, not a
           tiny desktop dialog floating in the middle of the screen. */
        .fi-modal-window:not(.fi-width-xs):not(.fi-width-sm) {
            position: fixed !important;
            inset: .35rem !important;
            width: auto !important;
            max-width: none !important;
            max-height: calc(100dvh - .7rem) !important;
            border-radius: .85rem !important;
        }
        .fi-modal-header { padding: .9rem 1rem !important; }
        .fi-modal-content { padding: .85rem 1rem !important; }
        .fi-modal-footer { padding: .75rem 1rem max(.75rem, env(safe-area-inset-bottom)) !important; }

        .vx-end-stream section.rounded-2xl,
        .vx-end-stream div.rounded-2xl { border-radius: .75rem !important; }
        .vx-end-stream section.p-5,
        .vx-end-stream div.p-5 { padding: .875rem !important; }
        .vx-end-stream .grid.gap-5 { gap: .75rem !important; }
        .vx-end-stream .space-y-5 > :not([hidden]) ~ :not([hidden]) { margin-top: .75rem !important; }
        .vx-end-stream h2.text-xl { font-size: 1.05rem !important; line-height: 1.4 !important; }
        .vx-end-stream ol button { min-height: 46px; padding: .6rem .35rem !important; gap: .35rem !important; font-size: .72rem !important; }
        .vx-end-stream ol button span:first-child { width: 1.35rem !important; height: 1.35rem !important; font-size: .68rem !important; }
        .vx-end-stream aside { order: 2; }

        .vx-fulfillment-list .fi-ta-header,
        .vx-fulfillment-list .fi-ta-header-toolbar { padding-inline: .75rem !important; }
        .vx-fulfillment-list .fi-ta-cell { padding-top: .7rem !important; padding-bottom: .7rem !important; }
        .vx-show-detail .fi-section,
        .vx-show-detail .fi-wi { border-radius: .75rem !important; }

        /* Inventory item/detail/edit pages are used standing up with a phone.
           Reduce dead space, keep tabs/actions horizontally scrollable and
           keep sections readable rather than turning every field into a card. */
        .vx-inventory-item .fi-page-header-actions,
        .vx-inventory-edit .fi-page-header-actions,
        .vx-inventory-stock .fi-page-header-actions {
            display: flex !important;
            width: 100% !important;
            gap: .4rem !important;
            overflow-x: auto !important;
            padding-bottom: .15rem !important;
        }
        .vx-inventory-item .fi-page-header-actions > *,
        .vx-inventory-edit .fi-page-header-actions > *,
        .vx-inventory-stock .fi-page-header-actions > * { flex: 0 0 auto; }
        .vx-inventory-item .fi-section,
        .vx-inventory-edit .fi-section,
        .vx-inventory-stock .fi-section { border-radius: .75rem !important; }
        .vx-inventory-edit .fi-section-header,
        .vx-inventory-edit .fi-section-content { padding-inline: .9rem !important; }
        .vx-inventory-edit .fi-section-header { padding-top: .85rem !important; padding-bottom: .65rem !important; }
        .vx-inventory-edit .fi-section-content { padding-bottom: .9rem !important; }
        .vx-inventory-edit .fi-fo-repeater-item { border-radius: .7rem !important; }
        .vx-inventory-item table { min-width: 42rem; }
        .vx-inventory-item .overflow-x-auto { -webkit-overflow-scrolling: touch; }

        /* Receiving should prioritize the work queue and scanner. */
        .vx-inventory-receive .fi-section,
        .vx-inventory-pallet .fi-section { border-radius: .75rem !important; }
        .vx-inventory-receive .fi-btn,
        .vx-inventory-pallet .fi-btn { min-height: 44px; }

        .vx-mobile-proxy-bar {
            position: fixed;
            inset-inline: 0;
            bottom: 0;
            z-index: 42;
            display: flex;
            gap: .5rem;
            padding: .625rem .75rem max(.625rem, env(safe-area-inset-bottom));
            border-top: 1px solid rgb(229 231 235);
            background: rgba(255,255,255,.96);
            box-shadow: 0 -8px 24px rgba(15,23,42,.08);
            backdrop-filter: blur(12px);
        }
        .dark .vx-mobile-proxy-bar { border-color: rgb(55 65 81); background: rgba(17,24,39,.96); }
        .vx-mobile-proxy-bar button {
            min-height: 44px;
            flex: 1;
            border-radius: .6rem;
            padding: .55rem .7rem;
            font-size: .8rem;
            font-weight: 700;
        }
        .vx-mobile-proxy-primary { background: rgb(22 163 74); color: white; }
        .vx-mobile-proxy-secondary { border: 1px solid rgb(252 165 165); color: rgb(185 28 28); background: white; }
        .dark .vx-mobile-proxy-secondary { border-color: rgb(127 29 29); color: rgb(252 165 165); background: rgb(31 41 55); }
        body.vx-has-mobile-action-bar .vx-tour-launcher { bottom: calc(66px + env(safe-area-inset-bottom)); }
    }
</style>

<script>
    (() => {
        const textOf = el => (el?.textContent || '').replace(/\s+/g, ' ').trim();
        const visible = el => !!el && el.offsetParent !== null && !el.disabled;

        const removeProxy = () => {
            document.getElementById('vx-mobile-proxy-bar')?.remove();
            document.body?.classList.remove('vx-has-mobile-action-bar');
        };

        const findButton = label => [...document.querySelectorAll('button, a')]
            .find(el => visible(el) && textOf(el).toLowerCase() === label.toLowerCase());

        const buildAdminReviewProxy = () => {
            if (!window.matchMedia('(max-width: 640px)').matches) return removeProxy();
            if (document.querySelector('[data-vx-mobile-actions]')) {
                document.body?.classList.add('vx-has-mobile-action-bar');
                return;
            }

            const approve = findButton('Approve');
            const changes = findButton('Request Changes');
            if (!approve || !changes || !document.querySelector('[data-vx-page="show-report-review"]')) return removeProxy();

            let bar = document.getElementById('vx-mobile-proxy-bar');
            if (!bar) {
                bar = document.createElement('div');
                bar.id = 'vx-mobile-proxy-bar';
                bar.className = 'vx-mobile-proxy-bar';
                bar.innerHTML = '<button type="button" class="vx-mobile-proxy-secondary">Request Changes</button><button type="button" class="vx-mobile-proxy-primary">Approve Report</button>';
                document.body.appendChild(bar);
                bar.children[0].addEventListener('click', () => findButton('Request Changes')?.click());
                bar.children[1].addEventListener('click', () => findButton('Approve')?.click());
            }
            document.body?.classList.add('vx-has-mobile-action-bar');
        };

        const applyVortexPageClasses = () => {
            const path = window.location.pathname;
            const body = document.body;
            if (!body) return;

            const inventoryItemMatch = path.match(/\/inventory-items\/(\d+)(?:\/([^/?#]+))?/);
            const inventoryAction = inventoryItemMatch?.[2] || '';

            body.classList.toggle('vx-end-stream', path.includes('end-of-stream'));
            body.classList.toggle('vx-fulfillment-list', path.includes('fulfillment-center'));
            body.classList.toggle('vx-show-detail', /\/shows\/[^/]+/.test(path));
            body.classList.toggle('vx-inventory-item', !!inventoryItemMatch && !['edit', 'stock'].includes(inventoryAction));
            body.classList.toggle('vx-inventory-edit', !!inventoryItemMatch && inventoryAction === 'edit');
            body.classList.toggle('vx-inventory-stock', !!inventoryItemMatch && inventoryAction === 'stock');
            body.classList.toggle('vx-inventory-receive', /\/pallets\/[^/]+\/receive/.test(path));
            body.classList.toggle('vx-inventory-pallet', /\/pallets\/[^/]+/.test(path) && !/\/receive/.test(path));

            setTimeout(buildAdminReviewProxy, 150);
        };

        const observer = new MutationObserver(() => {
            clearTimeout(window.__vxMobileProxyTimer);
            window.__vxMobileProxyTimer = setTimeout(buildAdminReviewProxy, 100);
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                applyVortexPageClasses();
                observer.observe(document.body, { childList: true, subtree: true });
            }, { once: true });
        } else {
            applyVortexPageClasses();
            observer.observe(document.body, { childList: true, subtree: true });
        }

        document.addEventListener('livewire:navigated', applyVortexPageClasses);
        window.addEventListener('resize', buildAdminReviewProxy);
    })();
</script>
