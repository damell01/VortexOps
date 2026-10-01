
// Keep each user's timezone aligned with the browser they actually sign in
// from. This removes timezone setup from admin user creation.
async function syncBrowserTimezone() {
    if (!location.pathname.startsWith('/admin')) return;

    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!timezone || !csrf) return;

    const cacheKey = 'vortexops-timezone';
    if (localStorage.getItem(cacheKey) === timezone) return;

    try {
        const response = await fetch('/admin/timezone', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ timezone }),
        });

        if (response.ok) localStorage.setItem(cacheKey, timezone);
    } catch (_) {
        // Non-blocking: timezone will retry on a later page load.
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncBrowserTimezone, { once: true });
} else {
    syncBrowserTimezone();
}



// Global page-navigation loader. Keep this in the core bundle (not the idle
// enhancement bundle) so feedback starts immediately on every Filament visit.
function installVortexNavigationLoader() {
    if (!location.pathname.startsWith('/admin') || document.getElementById('vx-nav-loader')) return;

    const style = document.createElement('style');
    style.id = 'vx-nav-loader-styles';
    style.textContent = `
        #vx-nav-loader{position:fixed;inset:0;z-index:2147483000;display:grid;place-items:center;background:rgba(7,10,27,.72);backdrop-filter:blur(5px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .14s ease,visibility .14s ease}
        #vx-nav-loader.vx-show{opacity:1;visibility:visible;pointer-events:auto}
        .vx-loader-stage{display:flex;flex-direction:column;align-items:center;gap:14px}
        .vx-loader-card{position:relative;width:94px;height:132px;perspective:700px}
        .vx-loader-card-inner{position:absolute;inset:0;border-radius:14px;background:linear-gradient(145deg,#7c3aed,#4f46e5);border:3px solid rgba(255,255,255,.88);box-shadow:0 18px 55px rgba(0,0,0,.42),0 0 0 5px rgba(124,58,237,.16);display:grid;place-items:center;animation:vxCardFlip 1.15s ease-in-out infinite}
        .vx-loader-card-inner:before,.vx-loader-card-inner:after{content:"";position:absolute;inset:8px;border:1px solid rgba(255,255,255,.28);border-radius:9px}.vx-loader-card-inner:after{inset:15px;border-radius:7px}
        .vx-loader-card img{width:64px;height:64px;object-fit:contain;filter:drop-shadow(0 4px 8px rgba(0,0,0,.28))}
        .vx-loader-shine{position:absolute;inset:0;overflow:hidden;border-radius:12px}.vx-loader-shine:after{content:"";position:absolute;top:-40%;left:-90%;width:55%;height:180%;transform:rotate(20deg);background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);animation:vxCardShine 1.15s ease-in-out infinite}
        .vx-loader-text{color:#fff;font:700 13px/1.2 ui-sans-serif,system-ui,sans-serif;letter-spacing:.02em}.vx-loader-sub{margin-top:-8px;color:rgba(255,255,255,.65);font:500 10px/1.2 ui-sans-serif,system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase}
        @keyframes vxCardFlip{0%,100%{transform:rotateY(0) translateY(0)}45%{transform:rotateY(180deg) translateY(-5px)}55%{transform:rotateY(180deg) translateY(-5px)}}
        @keyframes vxCardShine{0%{left:-90%}65%,100%{left:150%}}
        @media(prefers-reduced-motion:reduce){.vx-loader-card-inner,.vx-loader-shine:after{animation:none}}
    `;
    document.head.appendChild(style);

    const loader = document.createElement('div');
    loader.id = 'vx-nav-loader';
    loader.setAttribute('role', 'status');
    loader.setAttribute('aria-live', 'polite');
    loader.innerHTML = `<div class="vx-loader-stage"><div class="vx-loader-card"><div class="vx-loader-card-inner"><img src="/images/vb-logo.svg" alt=""><span class="vx-loader-shine"></span></div></div><div class="vx-loader-text">Loading VortexOps</div><div class="vx-loader-sub">Opening the next card</div></div>`;
    document.body.appendChild(loader);

    let timer = null;
    const show = () => {
        clearTimeout(timer);
        // Tiny delay avoids flashing the overlay for genuinely instant visits.
        timer = setTimeout(() => loader.classList.add('vx-show'), 90);
    };
    const hide = () => {
        clearTimeout(timer);
        loader.classList.remove('vx-show');
    };

    document.addEventListener('livewire:navigating', show);
    document.addEventListener('livewire:navigated', hide);
    window.addEventListener('pageshow', hide);
    window.addEventListener('popstate', hide);
    // Normal non-Livewire links / full document navigations.
    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.href === location.href || url.hash && url.pathname === location.pathname && url.search === location.search) return;
        show();
    }, { capture: true });
    window.addEventListener('beforeunload', () => {
        clearTimeout(timer);
        loader.classList.add('vx-show');
    });
    setTimeout(hide, 8000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', installVortexNavigationLoader, { once: true });
} else {
    installVortexNavigationLoader();
}

// Shared lazy script loader for heavy, optional operational tools (camera,
// scanners, etc.). The promise is cached so repeat opens never redownload it.
const vxScriptLoads = new Map();
window.vxLoadScriptOnce = function (src, id) {
    if (id && document.getElementById(id)) return Promise.resolve();
    if (vxScriptLoads.has(src)) return vxScriptLoads.get(src);

    const promise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        if (id) script.id = id;
        script.src = src;
        script.defer = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    }).catch((error) => {
        vxScriptLoads.delete(src);
        throw error;
    });

    vxScriptLoads.set(src, promise);
    return promise;
};
// Some Filament/Livewire pages attach beforeunload guards after interactive
// actions. In VortexOps those guards were sticking around after the action had
// already completed, so ordinary navigation could trigger Chrome's misleading
// "Changes you made may not be saved" dialog. VortexOps saves operational
// actions immediately; do not block normal navigation with a stale page guard.
window.addEventListener('beforeunload', (event) => {
    event.stopImmediatePropagation();
}, { capture: true });

// Keep Whatnot financial terminology consistent across Filament, including
// server-rendered resource labels and Livewire-rendered report tables. The
// underlying field remains shows.whatnot_net; this only clarifies what Whatnot
// actually calls it: Total Estimated Earnings.
const WHATNOT_FINANCIAL_LABELS = new Map([
    ['Whatnot Net', 'Estimated Net Earnings'],
    ['Net Revenue', 'Estimated Net Earnings'],
]);

function normalizeWhatnotFinancialLabels(root = document.body) {
    if (! root) return;

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const changes = [];

    while (walker.nextNode()) {
        const node = walker.currentNode;
        const current = node.nodeValue?.trim();
        if (! current) continue;

        const replacement = WHATNOT_FINANCIAL_LABELS.get(current);
        if (replacement) {
            changes.push([node, replacement]);
            continue;
        }

        // Reports / streamer analytics historically shortened this metric to
        // just "Net". Only rewrite that exact table/card label on those pages;
        // do not globally replace ordinary uses of the word "net" such as Net
        // Margin, net_revenue_basis, or unrelated accounting language.
        if (
            current === 'Net' &&
            (/\/admin\/reports(?:\/|$)/.test(location.pathname) ||
             /\/admin\/streamer-analytics(?:\/|$)/.test(location.pathname))
        ) {
            changes.push([node, 'Estimated Net Earnings']);
        }
    }

    for (const [node, replacement] of changes) {
        node.nodeValue = node.nodeValue.replace(node.nodeValue.trim(), replacement);
    }
}

function startFinancialTerminologyObserver() {
    normalizeWhatnotFinancialLabels();

    let pending = false;
    const observer = new MutationObserver(() => {
        if (pending) return;
        pending = true;

        requestAnimationFrame(() => {
            pending = false;
            normalizeWhatnotFinancialLabels(document.body);
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startFinancialTerminologyObserver, { once: true });
} else {
    startFinancialTerminologyObserver();
}

// The barcode scanner and its decoding library are 469KB — more than a fifth
// of everything the panel ships — and were imported here at the top level, so
// every page paid for them: the dashboard, the payouts table, the settings
// screen. Three pages actually scan.
//
// They load on first use instead, behind ensureBarcodeScanner(). The import is
// cached by the browser and by this promise, so opening the camera twice costs
// one download and the pages that never scan cost none.
let scannerPromise = null;

window.ensureBarcodeScanner = function () {
    if (! scannerPromise) {
        scannerPromise = Promise.all([
            import('./barcode-scanner.js'),
            import('@zxing/browser'),
        ]).then(([scanner, zxing]) => {
            window.barcodeScanner = {
                init: scanner.initScanner,
                stop: scanner.stopScanner,
                toggleFlashlight: scanner.toggleFlashlight,
                BrowserMultiFormatReader: zxing.BrowserMultiFormatReader,
            };

            return window.barcodeScanner;
        }).catch((e) => {
            scannerPromise = null;
            console.warn('[app.js] barcode scanner failed to load:', e.message);

            return null;
        });
    }

    return scannerPromise;
};

// Livewire v4 uses a hash-based update endpoint. After a deploy an already-open
// SPA tab can briefly keep the old endpoint and Livewire's default behavior is
// to render the server's 404 page inside a large diagnostic iframe. Recover by
// refreshing once, and suppress the iframe if the server still returns 404 so
// users are never trapped behind a fake-looking modal.
document.addEventListener('livewire:init', () => {
    if (! window.Livewire?.interceptRequest) return;

    window.Livewire.interceptRequest(({ request, onError }) => {
        onError(({ response, preventDefault }) => {
            if (response?.status !== 404) return;

            const uri = String(request?.uri || response?.url || '');
            if (! uri.includes('/livewire-')) return;

            preventDefault();

            const storageKey = 'vortexops-livewire-404-reloaded-at';
            const lastReload = Number(sessionStorage.getItem(storageKey) || 0);
            const now = Date.now();

            if (now - lastReload > 15000) {
                sessionStorage.setItem(storageKey, String(now));
                window.location.reload();
                return;
            }

            console.warn('[VortexOps] Livewire update endpoint returned 404 after refresh:', uri);
        });
    });
});

// Keep the SPA shell lean. These enhancements are progressive, not prerequisites
// for first paint or navigation. Load them only once the browser is idle so a
// click to another Filament page wins the network/main-thread race.
const loadOptionalUi = () => Promise.allSettled([
    import('./feedback-annotation.js'),
    import('./animations.js'),
    import('./ui-enhancements.js'),
    import('./ux-enhancements.js'),
    import('./mobile-enhancements.js'),
    import('./sidebar-full-collapse.js'),
    import('./ui-improvements.js'),
    import('./responsive-data-tables.js'),
    import('./modal-visibility.js'),
    import('./modal-lifecycle.js'),
]);

if ('requestIdleCallback' in window) {
    window.requestIdleCallback(loadOptionalUi, { timeout: 2500 });
} else {
    window.setTimeout(loadOptionalUi, 1200);
}
