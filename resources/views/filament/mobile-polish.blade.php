<style>
    @media (max-width: 640px) {
        .fi-main { padding-inline: .75rem !important; }
        .fi-page-header { gap: .75rem !important; }
        .fi-page-header-heading { font-size: 1.35rem !important; line-height: 1.2 !important; }
        .fi-page-header-subheading { margin-top: .25rem !important; font-size: .75rem !important; line-height: 1.25rem !important; }
        .fi-btn:not(.fi-icon-btn) { min-height: 42px; }

        input:not([type="checkbox"]):not([type="radio"]):not([type="color"]), select, textarea {
            font-size: 16px !important;
        }

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

    @media (min-width: 641px) {
        .vx-tour-launcher:hover { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(15,23,42,.16); }
        .vx-mobile-proxy-bar { display: none !important; }
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

            body.classList.toggle('vx-end-stream', path.includes('end-of-stream'));
            body.classList.toggle('vx-fulfillment-list', path.includes('fulfillment-center'));
            body.classList.toggle('vx-show-detail', /\/shows\/[^/]+/.test(path));

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
