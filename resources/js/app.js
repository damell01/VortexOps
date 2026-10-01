
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



// Global SPA-style navigation loader. Filament/Livewire already performs SPA
// navigation for wire:navigate links; this gives those transitions an instant,
// branded collectible-card loading state without delaying the destination.
function installVortexNavigationLoader() {
    if (!location.pathname.startsWith('/admin') || document.getElementById('vx-nav-loader')) return;

    const style = document.createElement('style');
    style.id = 'vx-nav-loader-styles';
    style.textContent = `
      #vx-nav-loader{position:fixed;inset:0;z-index:2147483000;display:grid;place-items:center;background:rgba(6,8,24,.78);backdrop-filter:blur(7px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .12s ease,visibility .12s ease}
      #vx-nav-loader.vx-show{opacity:1;visibility:visible;pointer-events:auto}
      .vx-load-scene{position:relative;width:180px;height:245px;filter:drop-shadow(0 24px 42px rgba(0,0,0,.45))}
      .vx-pack{position:absolute;left:36px;top:38px;width:108px;height:158px;border-radius:9px;background:linear-gradient(145deg,#1b103b,#7c3aed 52%,#2e1065);border:1px solid rgba(255,255,255,.45);overflow:hidden;animation:vxPack 2.25s ease-in-out infinite}
      .vx-pack:before,.vx-pack:after{content:"";position:absolute;left:0;right:0;height:10px;background:repeating-linear-gradient(90deg,rgba(255,255,255,.7) 0 3px,rgba(255,255,255,.16) 3px 6px)}.vx-pack:before{top:0}.vx-pack:after{bottom:0}
      .vx-pack img{position:absolute;width:62px;height:62px;left:23px;top:45px;object-fit:contain;filter:drop-shadow(0 0 12px rgba(255,255,255,.4))}
      .vx-rip{position:absolute;left:30px;top:34px;width:120px;height:19px;background:linear-gradient(135deg,#5b21b6,#a855f7);clip-path:polygon(0 20%,100% 0,95% 58%,76% 38%,61% 78%,44% 42%,24% 75%,4% 50%);opacity:0;animation:vxRip 2.25s ease-in-out infinite}
      .vx-card{position:absolute;left:44px;top:49px;width:92px;height:130px;border-radius:8px;background:linear-gradient(145deg,#090d1f,#251148);border:2px solid #d8b45a;box-shadow:0 0 25px rgba(139,92,246,.65);display:grid;place-items:center;opacity:0;animation:vxReveal 2.25s ease-in-out infinite}
      .vx-card img{width:52px;height:52px;object-fit:contain}.vx-card:before{content:"VORTEX • #001";position:absolute;left:7px;right:7px;top:7px;color:#fff;font:700 7px/1 ui-sans-serif;text-align:center;letter-spacing:.08em}.vx-card:after{content:"COLLECT • SELL • GROW";position:absolute;left:7px;right:7px;bottom:8px;color:#d8b45a;font:700 5px/1 ui-sans-serif;text-align:center;letter-spacing:.08em}
      .vx-sleeve{position:absolute;left:39px;top:43px;width:102px;height:143px;border:2px solid rgba(220,235,255,.7);border-radius:7px;background:linear-gradient(110deg,rgba(255,255,255,.02),rgba(255,255,255,.16),rgba(255,255,255,.03));box-shadow:inset 0 0 14px rgba(255,255,255,.12);opacity:0;animation:vxSleeve 2.25s ease-in-out infinite}
      .vx-slab{position:absolute;left:29px;top:18px;width:122px;height:180px;border:3px solid rgba(230,240,255,.9);border-radius:11px;background:rgba(220,235,255,.1);box-shadow:0 0 30px rgba(139,92,246,.75),inset 0 0 0 3px rgba(255,255,255,.16);opacity:0;animation:vxSlab 2.25s ease-in-out infinite}
      .vx-grade{position:absolute;left:36px;right:36px;top:25px;height:31px;border-radius:5px;background:#fff;color:#17172a;display:flex;align-items:center;justify-content:space-between;padding:0 7px;font:800 7px/1 ui-sans-serif;opacity:0;animation:vxGrade 2.25s ease-in-out infinite}.vx-grade b{font-size:19px;color:#7c3aed}.vx-grade span{display:block;font-size:5px;color:#666;margin-top:2px;letter-spacing:.08em}
      .vx-foil{position:absolute;inset:0;overflow:hidden;border-radius:10px;opacity:0;animation:vxFoil 2.25s ease-in-out infinite}.vx-foil:after{content:"";position:absolute;top:-30%;left:-80%;width:48%;height:160%;transform:rotate(18deg);background:linear-gradient(90deg,transparent,rgba(255,255,255,.8),transparent);animation:vxShine 2.25s ease-in-out infinite}
      .vx-load-copy{text-align:center;margin-top:7px;color:#fff;font:700 13px/1.3 ui-sans-serif}.vx-load-copy small{display:block;margin-top:5px;color:rgba(255,255,255,.58);font-size:9px;letter-spacing:.13em;text-transform:uppercase}
      @keyframes vxPack{0%,15%{opacity:1;transform:scale(.92)}25%,100%{opacity:0;transform:scale(1)}}
      @keyframes vxRip{0%,15%{opacity:0;transform:translateY(0) rotate(0)}20%{opacity:1}32%,100%{opacity:0;transform:translate(22px,-35px) rotate(18deg)}}
      @keyframes vxReveal{0%,20%{opacity:0;transform:translateY(48px) scale(.9)}32%,72%{opacity:1;transform:translateY(0) scale(1)}88%,100%{opacity:0;transform:translateY(-3px) scale(.84)}}
      @keyframes vxSleeve{0%,37%{opacity:0;transform:translateY(45px)}48%,70%{opacity:1;transform:translateY(0)}82%,100%{opacity:0}}
      @keyframes vxSlab{0%,50%{opacity:0;transform:scale(.92)}61%,82%{opacity:1;transform:scale(1)}94%,100%{opacity:0;transform:scale(.82)}}
      @keyframes vxGrade{0%,57%{opacity:0;transform:translateY(-8px)}66%,84%{opacity:1;transform:translateY(0)}94%,100%{opacity:0}}
      @keyframes vxFoil{0%,64%{opacity:0}70%,86%{opacity:1}94%,100%{opacity:0}}
      @keyframes vxShine{0%,67%{left:-80%}84%,100%{left:145%}}
      @media(prefers-reduced-motion:reduce){.vx-pack,.vx-rip,.vx-card,.vx-sleeve,.vx-slab,.vx-grade,.vx-foil,.vx-foil:after{animation:none}.vx-card,.vx-slab,.vx-grade{opacity:1}.vx-pack,.vx-rip,.vx-sleeve,.vx-foil{display:none}}
    `;
    document.head.appendChild(style);

    const loader=document.createElement('div');
    loader.id='vx-nav-loader'; loader.setAttribute('role','status'); loader.setAttribute('aria-live','polite');
    loader.innerHTML=`<div><div class="vx-load-scene"><div class="vx-pack"><img src="/images/vb-logo.svg" alt=""></div><div class="vx-rip"></div><div class="vx-card"><img src="/images/vb-logo.svg" alt=""></div><div class="vx-sleeve"></div><div class="vx-slab"></div><div class="vx-grade"><div>VORTEX GRADE<span>GEM MINT</span></div><b>10</b></div><div class="vx-foil"></div></div><div class="vx-load-copy">Loading VortexOps…<small>Rip • Reveal • Grade</small></div></div>`;
    document.body.appendChild(loader);

    let timer=null, watchdog=null;
    const show=()=>{clearTimeout(timer);clearTimeout(watchdog);timer=setTimeout(()=>{loader.classList.add('vx-show');watchdog=setTimeout(()=>loader.classList.remove('vx-show'),12000)},75)};
    const hide=()=>{clearTimeout(timer);clearTimeout(watchdog);loader.classList.remove('vx-show')};

    document.addEventListener('livewire:navigating',show);
    document.addEventListener('livewire:navigated',hide);
    window.addEventListener('pageshow',hide);
    window.addEventListener('popstate',hide);
    document.addEventListener('click',(event)=>{
      const link=event.target.closest?.('a[href]');
      if(!link||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||link.target==='_blank'||link.hasAttribute('download'))return;
      const url=new URL(link.href,location.href);
      if(url.origin!==location.origin||url.href===location.href||(url.hash&&url.pathname===location.pathname&&url.search===location.search))return;
      show();
    },{capture:true});
    window.addEventListener('beforeunload',()=>{clearTimeout(timer);loader.classList.add('vx-show')});
}

if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',installVortexNavigationLoader,{once:true});else installVortexNavigationLoader();

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
