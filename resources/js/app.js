
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
    if (!location.pathname.startsWith('/admin')) return;

    document.getElementById('vx-nav-loader')?.remove();
    document.getElementById('vx-nav-loader-styles')?.remove();

    const asset = (name) => `/images/${name}.webp`;
    const style = document.createElement('style');
    style.id = 'vx-nav-loader-styles';
    style.textContent = `
      #vx-nav-loader{position:fixed;inset:0;z-index:2147483000;display:grid;place-items:center;background:rgba(5,5,18,.86);backdrop-filter:blur(12px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .14s ease,visibility .14s ease;overflow:hidden}
      #vx-nav-loader.vx-show{opacity:1;visibility:visible;pointer-events:auto}
      .vx-loader-stage{position:relative;width:min(96vw,780px);height:min(86vh,720px);display:grid;place-items:center;overflow:hidden}
      .vx-loader-stage:before{content:"";position:absolute;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle,rgba(168,85,247,.24),rgba(88,28,135,.1) 42%,transparent 70%);filter:blur(14px);animation:vxPulse 2.4s ease-in-out infinite}
      .vx-loader-shelf{position:absolute;inset:5% 0 17%;opacity:.32;filter:blur(2.5px)}
      .vx-loader-bgslab{position:absolute;width:126px;height:178px;border:3px solid rgba(236,232,255,.26);border-radius:12px;background:linear-gradient(160deg,rgba(255,255,255,.1),rgba(88,28,135,.18));box-shadow:0 0 32px rgba(168,85,247,.16);transform:rotate(var(--r));left:var(--x);top:var(--y)}
      .vx-loader-bgslab:before{content:"GEM 10";position:absolute;top:8px;left:8px;right:8px;height:26px;border-radius:4px;background:rgba(245,245,255,.22);color:rgba(255,255,255,.52);font:900 9px/26px ui-sans-serif;text-align:center;letter-spacing:.08em}
      .vx-loader-scene{position:relative;width:390px;height:460px;transform:translateY(-18px);filter:drop-shadow(0 30px 60px rgba(0,0,0,.58))}
      .vx-loader-img{position:absolute;left:50%;top:50%;object-fit:contain;transform-origin:center;will-change:transform,opacity;user-select:none;pointer-events:none}
      .vx-pack-closed{width:285px;height:335px;margin:-168px 0 0 -143px;animation:vxClosed 3.2s ease-in-out infinite}
      .vx-pack-open{width:350px;height:330px;margin:-165px 0 0 -175px;opacity:0;animation:vxOpen 3.2s ease-in-out infinite}
      .vx-pack-cards{width:295px;height:360px;margin:-195px 0 0 -148px;opacity:0;animation:vxCards 3.2s ease-in-out infinite}
      .vx-hit-card{width:235px;height:320px;margin:-174px 0 0 -118px;opacity:0;animation:vxHit 3.2s ease-in-out infinite}
      .vx-hit-slab{width:245px;height:345px;margin:-188px 0 0 -123px;opacity:0;animation:vxSlab 3.2s ease-in-out infinite;filter:drop-shadow(0 0 28px rgba(168,85,247,.8))}
      .vx-loader-spark{position:absolute;left:50%;top:43%;width:320px;height:320px;margin:-160px;border-radius:50%;opacity:0;background:radial-gradient(circle,rgba(255,255,255,.8),rgba(192,132,252,.35) 25%,transparent 66%);filter:blur(7px);animation:vxSpark 3.2s ease-in-out infinite}
      .vx-loader-copy{position:absolute;left:50%;bottom:2%;transform:translateX(-50%);width:min(90vw,520px);text-align:center;color:#fff;font:800 20px/1.3 ui-sans-serif;text-shadow:0 2px 18px rgba(0,0,0,.55)}
      .vx-loader-status{display:block;min-height:27px}.vx-loader-sub{display:block;margin-top:8px;color:#c4b5fd;font-size:9px;letter-spacing:.24em;text-transform:uppercase}
      .vx-loader-progress{height:8px;margin:15px auto 0;width:min(76vw,360px);border:1px solid rgba(216,180,254,.9);border-radius:999px;padding:2px;background:rgba(9,5,22,.72);box-shadow:0 0 18px rgba(168,85,247,.28)}.vx-loader-progress:before{content:"";display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#7c3aed,#d946ef,#fff);box-shadow:0 0 12px #a855f7;animation:vxProgress 3.2s linear infinite}
      .vx-loader-dots{display:flex;gap:9px;justify-content:center;margin-top:12px}.vx-loader-dots i{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.2);animation:vxDot 3.2s linear infinite}.vx-loader-dots i:nth-child(2){animation-delay:.2s}.vx-loader-dots i:nth-child(3){animation-delay:.4s}.vx-loader-dots i:nth-child(4){animation-delay:.6s}.vx-loader-dots i:nth-child(5){animation-delay:.8s}
      @keyframes vxClosed{0%,17%{opacity:1;transform:scale(.96)}24%,100%{opacity:0;transform:scale(1.04)}}
      @keyframes vxOpen{0%,14%{opacity:0;transform:scale(.94)}21%,33%{opacity:1;transform:scale(1.02)}40%,100%{opacity:0;transform:scale(1.08)}}
      @keyframes vxCards{0%,25%{opacity:0;transform:translateY(45px) scale(.9)}34%,48%{opacity:1;transform:translateY(-10px) scale(1.03)}57%,100%{opacity:0;transform:translateY(-35px) scale(1.08)}}
      @keyframes vxHit{0%,42%{opacity:0;transform:translateY(45px) rotate(-5deg) scale(.88)}52%,66%{opacity:1;transform:translateY(-6px) rotate(2deg) scale(1.08)}74%,100%{opacity:0;transform:translateY(-12px) scale(.98)}}
      @keyframes vxSlab{0%,61%{opacity:0;transform:scale(.88) rotate(-2deg)}70%,94%{opacity:1;transform:scale(1.08) rotate(0)}100%{opacity:0;transform:scale(.98)}}
      @keyframes vxSpark{0%,45%{opacity:0;transform:scale(.3)}55%{opacity:.9;transform:scale(1.2)}75%{opacity:.25;transform:scale(1.55)}100%{opacity:0;transform:scale(1.8)}}
      @keyframes vxProgress{0%{width:6%}20%{width:25%}40%{width:48%}62%{width:72%}84%{width:92%}100%{width:100%}}
      @keyframes vxDot{0%,12%,100%{background:rgba(255,255,255,.2);box-shadow:none}18%,55%{background:#a855f7;box-shadow:0 0 10px #a855f7}}
      @keyframes vxPulse{0%,100%{transform:scale(.92);opacity:.7}50%{transform:scale(1.08);opacity:1}}
      @media(max-width:640px){.vx-loader-stage{transform:scale(.84);width:118vw}.vx-loader-copy{bottom:1%;font-size:18px}.vx-loader-shelf{opacity:.18}}
      @media(prefers-reduced-motion:reduce){.vx-loader-img,.vx-loader-spark,.vx-loader-progress:before,.vx-loader-dots i{animation:none}.vx-hit-slab{opacity:1;transform:scale(1)}.vx-pack-closed,.vx-pack-open,.vx-pack-cards,.vx-hit-card{display:none}}
    `;
    document.head.appendChild(style);

    const loader=document.createElement('div');
    loader.id='vx-nav-loader'; loader.setAttribute('role','status'); loader.setAttribute('aria-live','polite');
    loader.innerHTML=`<div class="vx-loader-stage">
      <div class="vx-loader-shelf" aria-hidden="true">
        <div class="vx-loader-bgslab" style="--x:4%;--y:15%;--r:-9deg"></div>
        <div class="vx-loader-bgslab" style="--x:78%;--y:8%;--r:8deg"></div>
        <div class="vx-loader-bgslab" style="--x:10%;--y:59%;--r:7deg"></div>
        <div class="vx-loader-bgslab" style="--x:75%;--y:58%;--r:-7deg"></div>
      </div>
      <div class="vx-loader-scene">
        <div class="vx-loader-spark"></div>
        <img class="vx-loader-img vx-pack-closed" src="${asset('pack-closed')}" alt="">
        <img class="vx-loader-img vx-pack-open" src="${asset('pack-open')}" alt="">
        <img class="vx-loader-img vx-pack-cards" src="${asset('pack-cards')}" alt="">
        <img class="vx-loader-img vx-hit-card" src="${asset('hit-card')}" alt="">
        <img class="vx-loader-img vx-hit-slab" src="${asset('hit-slab')}" alt="">
      </div>
      <div class="vx-loader-copy"><span class="vx-loader-status">Loading VortexOps…</span><small class="vx-loader-sub">Rip • Reveal • Grade • Inventory</small><div class="vx-loader-progress"></div><div class="vx-loader-dots"><i></i><i></i><i></i><i></i><i></i></div></div>
    </div>`;
    document.body.appendChild(loader);

    const status = loader.querySelector('.vx-loader-status');
    let statusTimers = [];
    const resetStatus = () => { statusTimers.forEach(clearTimeout); statusTimers=[]; if(status) status.textContent='Loading VortexOps…'; };
    const runStatus = () => {
      resetStatus();
      [['Ripping Pack…',520],['Revealing Cards…',1050],['Found a Hit…',1600],['Grading Hit…',2150],['Almost Ready…',2700]].forEach(([copy,ms]) => statusTimers.push(setTimeout(()=>{if(status)status.textContent=copy},ms)));
    };

    let timer=null, watchdog=null;
    const show=()=>{clearTimeout(timer);clearTimeout(watchdog);resetStatus();timer=setTimeout(()=>{runStatus();loader.classList.add('vx-show');watchdog=setTimeout(()=>{loader.classList.remove('vx-show');resetStatus()},12000)},75)};
    const hide=()=>{clearTimeout(timer);clearTimeout(watchdog);resetStatus();loader.classList.remove('vx-show')};

    if (!window.__vortexNavLoaderBound) {
      window.__vortexNavLoaderBound=true;
      document.addEventListener('livewire:navigating',()=>window.__vortexNavLoader?.show());
      document.addEventListener('livewire:navigated',()=>window.__vortexNavLoader?.hide());
    }
    window.__vortexNavLoader={show,hide};
    window.addEventListener('pageshow',hide,{once:true});
    document.addEventListener('click',(event)=>{
      const link=event.target.closest?.('a[href]');
      if(!link||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||link.target==='_blank'||link.hasAttribute('download'))return;
      const url=new URL(link.href,location.href);
      if(url.origin!==location.origin||url.href===location.href||(url.hash&&url.pathname===location.pathname&&url.search===location.search))return;
      show();
    },{capture:true});
    requestAnimationFrame(hide);
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
