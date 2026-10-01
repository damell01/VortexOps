
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

    // SPA navigation keeps app.js alive while Livewire swaps the page DOM. Remove
    // any stale loader left by a previous document before creating the current one.
    document.getElementById('vx-nav-loader')?.remove();
    document.getElementById('vx-nav-loader-styles')?.remove();

    const style = document.createElement('style');
    style.id = 'vx-nav-loader-styles';
    style.textContent = `
      #vx-nav-loader{position:fixed;inset:0;z-index:2147483000;display:grid;place-items:center;background:radial-gradient(circle at 50% 42%,rgba(104,36,178,.25),transparent 34%),rgba(4,5,18,.9);backdrop-filter:blur(11px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .14s ease,visibility .14s ease;overflow:hidden}
      #vx-nav-loader.vx-show{opacity:1;visibility:visible;pointer-events:auto}
      .vx-loader-stage{position:relative;width:min(94vw,720px);height:min(78vh,650px);display:flex;align-items:center;justify-content:center}
      .vx-loader-shelf{position:absolute;inset:7% 2% 18%;opacity:.4;filter:blur(2px)}
      .vx-loader-bgslab{position:absolute;width:120px;height:168px;border:2px solid rgba(226,232,240,.34);border-radius:10px;background:linear-gradient(160deg,rgba(255,255,255,.12),rgba(94,34,160,.18));box-shadow:0 0 30px rgba(168,85,247,.18);transform:rotate(var(--r));left:var(--x);top:var(--y)}
      .vx-loader-bgslab:before{content:"10";position:absolute;top:8px;right:10px;font:900 18px/1 ui-sans-serif;color:rgba(255,255,255,.55)}
      .vx-loader-bgslab img{position:absolute;width:55%;height:55%;object-fit:contain;left:22%;top:28%;opacity:.45}
      .vx-loader-scene{position:relative;width:300px;height:390px;filter:drop-shadow(0 30px 55px rgba(0,0,0,.55));transform:translateY(-14px)}
      .vx-loader-pack{position:absolute;left:65px;top:80px;width:170px;height:235px;border-radius:14px;background:linear-gradient(145deg,#12072d,#6d28d9 48%,#1e0a43);border:2px solid rgba(214,177,255,.7);overflow:hidden;box-shadow:0 0 45px rgba(168,85,247,.55),inset 0 0 30px rgba(255,255,255,.07);animation:vxLoaderPack 3.1s ease-in-out infinite}
      .vx-loader-pack:before,.vx-loader-pack:after{content:"";position:absolute;left:0;right:0;height:15px;background:repeating-linear-gradient(90deg,rgba(255,255,255,.7) 0 5px,rgba(255,255,255,.12) 5px 10px)}.vx-loader-pack:before{top:0}.vx-loader-pack:after{bottom:0}
      .vx-loader-pack img{position:absolute;width:100px;height:100px;left:35px;top:60px;object-fit:contain;filter:drop-shadow(0 0 18px rgba(255,255,255,.45))}
      .vx-loader-pack-label{position:absolute;left:0;right:0;bottom:36px;text-align:center;color:#fff;font:800 13px/1 ui-sans-serif;letter-spacing:.04em}.vx-loader-pack-label small{display:block;color:#d8b4fe;font-size:7px;margin-top:7px;letter-spacing:.18em}
      .vx-loader-rip{position:absolute;left:56px;top:73px;width:190px;height:32px;background:linear-gradient(135deg,#3b0764,#a855f7 55%,#ede9fe);clip-path:polygon(0 20%,100% 0,94% 58%,77% 38%,61% 82%,44% 42%,23% 76%,4% 50%);opacity:0;animation:vxLoaderRip 3.1s ease-in-out infinite}
      .vx-loader-burst{position:absolute;left:92px;top:72px;width:116px;height:116px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.7),rgba(168,85,247,.42) 35%,transparent 70%);filter:blur(5px);opacity:0;animation:vxLoaderBurst 3.1s ease-in-out infinite}
      .vx-loader-mini{position:absolute;left:112px;top:105px;width:78px;height:108px;border-radius:8px;background:linear-gradient(145deg,#111827,#4c1d95);border:2px solid #d8b45a;box-shadow:0 0 18px rgba(168,85,247,.5);opacity:0;animation:vxLoaderMini 3.1s ease-in-out infinite;transform:translate(var(--dx),var(--dy)) rotate(var(--rr))}
      .vx-loader-mini img{width:48px;height:48px;object-fit:contain;position:absolute;left:13px;top:29px;opacity:.8}
      .vx-loader-card{position:absolute;left:86px;top:75px;width:128px;height:182px;border-radius:11px;background:linear-gradient(145deg,#080b1c,#28104d);border:3px solid #d8b45a;box-shadow:0 0 38px rgba(168,85,247,.8);display:grid;place-items:center;opacity:0;animation:vxLoaderReveal 3.1s ease-in-out infinite}
      .vx-loader-card img{width:78px;height:78px;object-fit:contain;filter:drop-shadow(0 0 15px rgba(255,255,255,.25))}.vx-loader-card:before{content:"VORTEX OPS • #001";position:absolute;left:9px;right:9px;top:10px;color:#fff;font:800 8px/1 ui-sans-serif;text-align:center;letter-spacing:.09em}.vx-loader-card:after{content:"COLLECT • SELL • GROW";position:absolute;left:8px;right:8px;bottom:11px;color:#e4bd66;font:800 6px/1 ui-sans-serif;text-align:center;letter-spacing:.1em}
      .vx-loader-slab{position:absolute;left:66px;top:39px;width:168px;height:250px;border:4px solid rgba(238,246,255,.92);border-radius:15px;background:linear-gradient(110deg,rgba(255,255,255,.04),rgba(255,255,255,.18),rgba(255,255,255,.04));box-shadow:0 0 48px rgba(168,85,247,.85),inset 0 0 0 3px rgba(255,255,255,.14);opacity:0;animation:vxLoaderSlab 3.1s ease-in-out infinite}
      .vx-loader-grade{position:absolute;left:75px;top:50px;width:150px;height:48px;border-radius:7px;background:#f8fafc;color:#151525;display:flex;align-items:center;justify-content:space-between;padding:0 10px;font:900 8px/1 ui-sans-serif;box-sizing:border-box;opacity:0;animation:vxLoaderGrade 3.1s ease-in-out infinite}.vx-loader-grade b{font-size:28px;color:#7c3aed}.vx-loader-grade span{display:block;font-size:6px;color:#64748b;margin-top:4px;letter-spacing:.1em}
      .vx-loader-foil{position:absolute;left:66px;top:39px;width:168px;height:250px;overflow:hidden;border-radius:15px;opacity:0;animation:vxLoaderFoil 3.1s ease-in-out infinite}.vx-loader-foil:after{content:"";position:absolute;top:-30%;left:-90%;width:45%;height:165%;transform:rotate(18deg);background:linear-gradient(90deg,transparent,rgba(255,255,255,.9),transparent);animation:vxLoaderShine 3.1s ease-in-out infinite}
      .vx-loader-copy{position:absolute;left:50%;bottom:1%;transform:translateX(-50%);width:min(88vw,480px);text-align:center;color:#fff;font:800 19px/1.3 ui-sans-serif}
      .vx-loader-status{display:block;min-height:25px}.vx-loader-sub{display:block;margin-top:8px;color:rgba(255,255,255,.62);font-size:9px;letter-spacing:.22em;text-transform:uppercase}
      .vx-loader-progress{height:7px;margin:16px auto 0;width:min(74vw,340px);border:1px solid rgba(216,180,254,.8);border-radius:999px;padding:2px;box-shadow:0 0 15px rgba(168,85,247,.28)}.vx-loader-progress:before{content:"";display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#7c3aed,#d946ef,#fff);animation:vxLoaderProgress 3.1s linear infinite}
      .vx-loader-dots{display:flex;gap:9px;justify-content:center;margin-top:13px}.vx-loader-dots i{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.2);animation:vxLoaderDot 3.1s linear infinite}.vx-loader-dots i:nth-child(2){animation-delay:.22s}.vx-loader-dots i:nth-child(3){animation-delay:.44s}.vx-loader-dots i:nth-child(4){animation-delay:.66s}.vx-loader-dots i:nth-child(5){animation-delay:.88s}
      @keyframes vxLoaderPack{0%,16%{opacity:1;transform:scale(.96)}25%,100%{opacity:0;transform:scale(1.04)}}
      @keyframes vxLoaderRip{0%,13%{opacity:0;transform:translateY(0) rotate(0)}18%{opacity:1}29%,100%{opacity:0;transform:translate(42px,-55px) rotate(22deg)}}
      @keyframes vxLoaderBurst{0%,17%{opacity:0;transform:scale(.3)}28%{opacity:1;transform:scale(1.7)}42%,100%{opacity:0;transform:scale(2.2)}}
      @keyframes vxLoaderMini{0%,19%{opacity:0;transform:translate(0,70px) rotate(0)}31%,46%{opacity:.78;transform:translate(var(--dx),var(--dy)) rotate(var(--rr))}57%,100%{opacity:0;transform:translate(calc(var(--dx)*1.15),calc(var(--dy)*1.15)) rotate(var(--rr))}}
      @keyframes vxLoaderReveal{0%,25%{opacity:0;transform:translateY(75px) scale(.82) rotate(-3deg)}38%,62%{opacity:1;transform:translateY(0) scale(1.05) rotate(2deg)}72%,100%{opacity:0;transform:translateY(-5px) scale(.92)}}
      @keyframes vxLoaderSlab{0%,48%{opacity:0;transform:scale(.88)}59%,88%{opacity:1;transform:scale(1)}97%,100%{opacity:0;transform:scale(.9)}}
      @keyframes vxLoaderGrade{0%,55%{opacity:0;transform:translateY(-14px)}64%,89%{opacity:1;transform:translateY(0)}97%,100%{opacity:0}}
      @keyframes vxLoaderFoil{0%,65%{opacity:0}72%,91%{opacity:1}98%,100%{opacity:0}}
      @keyframes vxLoaderShine{0%,68%{left:-90%}88%,100%{left:150%}}
      @keyframes vxLoaderProgress{0%{width:7%}18%{width:25%}38%{width:48%}63%{width:73%}86%{width:92%}100%{width:100%}}
      @keyframes vxLoaderDot{0%,12%,100%{background:rgba(255,255,255,.2);box-shadow:none}18%,55%{background:#a855f7;box-shadow:0 0 10px #a855f7}}
      @media(max-width:640px){.vx-loader-stage{transform:scale(.84);width:100vw}.vx-loader-copy{bottom:2%;font-size:18px}.vx-loader-shelf{opacity:.25}}
      @media(prefers-reduced-motion:reduce){.vx-loader-pack,.vx-loader-rip,.vx-loader-burst,.vx-loader-mini,.vx-loader-card,.vx-loader-slab,.vx-loader-grade,.vx-loader-foil,.vx-loader-foil:after,.vx-loader-progress:before,.vx-loader-dots i{animation:none}.vx-loader-card,.vx-loader-slab,.vx-loader-grade{opacity:1}.vx-loader-pack,.vx-loader-rip,.vx-loader-burst,.vx-loader-mini,.vx-loader-foil{display:none}}
    `;
    document.head.appendChild(style);

    const loader=document.createElement('div');
    loader.id='vx-nav-loader'; loader.setAttribute('role','status'); loader.setAttribute('aria-live','polite');
    loader.innerHTML=`<div class="vx-loader-stage">
      <div class="vx-loader-shelf" aria-hidden="true">
        <div class="vx-loader-bgslab" style="--x:5%;--y:13%;--r:-9deg"><img src="" data-vx-brand-logo alt=""></div>
        <div class="vx-loader-bgslab" style="--x:73%;--y:8%;--r:8deg"><img src="" data-vx-brand-logo alt=""></div>
        <div class="vx-loader-bgslab" style="--x:14%;--y:56%;--r:7deg"><img src="" data-vx-brand-logo alt=""></div>
        <div class="vx-loader-bgslab" style="--x:68%;--y:58%;--r:-7deg"><img src="" data-vx-brand-logo alt=""></div>
      </div>
      <div class="vx-loader-scene">
        <div class="vx-loader-pack"><img src="" data-vx-brand-logo alt=""><div class="vx-loader-pack-label">VortexOps<small>COLLECT • SELL • GROW</small></div></div>
        <div class="vx-loader-rip"></div><div class="vx-loader-burst"></div>
        <div class="vx-loader-mini" style="--dx:-72px;--dy:-56px;--rr:-18deg"><img src="" data-vx-brand-logo alt=""></div>
        <div class="vx-loader-mini" style="--dx:72px;--dy:-48px;--rr:16deg"><img src="" data-vx-brand-logo alt=""></div>
        <div class="vx-loader-mini" style="--dx:-92px;--dy:25px;--rr:-27deg"><img src="" data-vx-brand-logo alt=""></div>
        <div class="vx-loader-card"><img src="" data-vx-brand-logo alt=""></div>
        <div class="vx-loader-slab"></div>
        <div class="vx-loader-grade"><div>VORTEX GRADE<span>GEM MINT</span></div><b>10</b></div>
        <div class="vx-loader-foil"></div>
      </div>
      <div class="vx-loader-copy"><span class="vx-loader-status">Loading VortexOps…</span><small class="vx-loader-sub">Rip • Reveal • Grade • Inventory</small><div class="vx-loader-progress"></div><div class="vx-loader-dots"><i></i><i></i><i></i><i></i><i></i></div></div>
    </div>`;
    document.body.appendChild(loader);
    const configuredLogo = document.querySelector('meta[name="vortex-brand-logo"]')?.content || '/images/vb-logo-sidebar.svg';
    loader.querySelectorAll('[data-vx-brand-logo]').forEach((img) => { img.src = configuredLogo; });
    const status = loader.querySelector('.vx-loader-status');
    let statusTimers = [];
    const resetStatus = () => {
      statusTimers.forEach(clearTimeout); statusTimers = [];
      if (status) status.textContent = 'Loading VortexOps…';
    };
    const runStatus = () => {
      resetStatus();
      [['Ripping Pack…',520],['Revealing Cards…',980],['Found a Hit…',1450],['Grading Hit…',1950],['Almost Ready…',2500]].forEach(([copy,ms]) => statusTimers.push(setTimeout(()=>{if(status)status.textContent=copy},ms)));
    };

    let timer=null, watchdog=null;
    const show=()=>{clearTimeout(timer);clearTimeout(watchdog);resetStatus();timer=setTimeout(()=>{runStatus();loader.classList.add('vx-show');watchdog=setTimeout(()=>{loader.classList.remove('vx-show');resetStatus()},12000)},75)};
    const hide=()=>{clearTimeout(timer);clearTimeout(watchdog);resetStatus();loader.classList.remove('vx-show')};

    // Register once. Reinstalling these listeners after every SPA navigation was
    // the source of stale overlays and partially morphed pages.
    if (!window.__vortexNavLoaderBound) {
      window.__vortexNavLoaderBound = true;
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
    // Do not force the overlay in beforeunload: full navigations already show
    // immediately from the click handler, and beforeunload can leave a stale
    // overlay visible when the browser restores/cancels navigation.
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
