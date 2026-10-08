import '../css/page-layout-fixes.css';
import './confirmation-dialog.js';
import './guided-help.js';
import './mobile-controls.js';
import '../css/mobile-report-pass.css';

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

    const style=document.createElement('style');
    style.id='vx-nav-loader-styles';
    style.textContent=`
      #vx-nav-loader{position:fixed;inset:0;z-index:2147483000;display:grid;place-items:center;background:radial-gradient(circle at 50% 42%,#24104a 0,#0b0718 42%,#05040c 76%);opacity:0;visibility:hidden;pointer-events:none;transition:.14s ease;overflow:hidden}
      #vx-nav-loader.vx-show{opacity:1;visibility:visible;pointer-events:auto}
      .vx-loader-wrap{position:relative;width:min(96vw,900px);height:min(92vh,720px);display:grid;place-items:center;perspective:900px}.vx-loader-wrap:before{content:"";position:absolute;inset:8% -18% 14%;background:linear-gradient(90deg,transparent,rgba(168,85,247,.12),transparent),repeating-linear-gradient(90deg,transparent 0 13%,rgba(139,92,246,.08) 13% 14%,transparent 14% 27%);filter:blur(2px);border-bottom:1px solid rgba(216,180,254,.18)}
      .vx-loader-glow{position:absolute;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,rgba(216,180,254,.26),rgba(126,34,206,.11) 45%,transparent 72%);filter:blur(10px);animation:vxSvgPulse 2s ease-in-out infinite}
      .vx-loader-bgcard{position:absolute;width:112px;height:158px;border:3px solid rgba(216,180,254,.28);border-radius:12px;background:linear-gradient(145deg,rgba(255,255,255,.08),rgba(88,28,135,.2));box-shadow:0 0 24px rgba(168,85,247,.18);transform:rotate(var(--r));left:var(--x);top:var(--y);overflow:hidden}
      .vx-loader-bgcard svg{width:100%;height:100%;display:block}.vx-loader-bgcard text{font-family:ui-sans-serif,system-ui;font-weight:900;letter-spacing:.04em}.vx-loader-bgcard{filter:saturate(1.2)}.vx-loader-bgcard:after{content:"";position:absolute;inset:-35% -70%;background:linear-gradient(105deg,transparent 42%,rgba(255,255,255,.34) 50%,transparent 58%);transform:translateX(-45%) rotate(8deg);animation:vxFoil 2.6s ease-in-out infinite;pointer-events:none}@keyframes vxFoil{0%,30%{transform:translateX(-45%) rotate(8deg);opacity:0}55%{opacity:.75}85%,100%{transform:translateX(45%) rotate(8deg);opacity:0}}
      .vx-loader-svg{position:relative;width:min(82vw,430px);height:auto;filter:drop-shadow(0 28px 45px rgba(0,0,0,.68));overflow:visible}.vx-loader-floor{position:absolute;left:50%;bottom:14%;width:min(86vw,680px);height:120px;transform:translateX(-50%) rotateX(72deg);border:1px solid rgba(168,85,247,.3);border-radius:50%;background:radial-gradient(ellipse,rgba(168,85,247,.22),transparent 68%);box-shadow:0 0 55px rgba(168,85,247,.24)}
      .vx-svg-pack{transform-origin:180px 260px;animation:vxSvgPack 3.15s ease-in-out infinite}.vx-svg-tear{transform-origin:180px 133px;animation:vxSvgTear 3.15s ease-in-out infinite}
      .vx-svg-card-a,.vx-svg-card-b,.vx-svg-card-c{transform-origin:180px 210px;opacity:0}.vx-svg-card-a{animation:vxSvgCardA 3.15s ease-in-out infinite}.vx-svg-card-b{animation:vxSvgCardB 3.15s ease-in-out infinite}.vx-svg-card-c{animation:vxSvgCardC 3.15s ease-in-out infinite}
      .vx-svg-hit{transform-origin:180px 205px;opacity:0;animation:vxSvgHit 3.15s ease-in-out infinite}.vx-svg-slab{transform-origin:180px 205px;opacity:0;animation:vxSvgSlab 3.15s ease-in-out infinite}
      .vx-svg-burst{transform-origin:180px 210px;opacity:0;animation:vxSvgBurst 3.15s ease-out infinite}.vx-svg-shine{animation:vxSvgShine 1.2s linear infinite}
      .vx-loader-copy{position:absolute;bottom:1%;width:100%;text-align:center;color:#fff;font:800 20px/1.25 ui-sans-serif,system-ui;text-shadow:0 2px 16px #000}.vx-loader-sub{display:block;margin-top:8px;color:#c4b5fd;font-size:9px;letter-spacing:.25em;text-transform:uppercase}
      .vx-loader-progress{height:7px;margin:15px auto 0;width:min(70vw,330px);border:1px solid #c084fc;border-radius:999px;padding:2px;background:#0b0718}.vx-loader-progress:before{content:"";display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#7c3aed,#d946ef,#fff);box-shadow:0 0 13px #a855f7;animation:vxSvgProgress 3.15s linear infinite}
      .vx-loader-dots{display:flex;gap:9px;justify-content:center;margin-top:12px}.vx-loader-dots i{width:7px;height:7px;border-radius:50%;background:#34264b;animation:vxSvgDot 3.15s linear infinite}.vx-loader-dots i:nth-child(2){animation-delay:.2s}.vx-loader-dots i:nth-child(3){animation-delay:.4s}.vx-loader-dots i:nth-child(4){animation-delay:.6s}.vx-loader-dots i:nth-child(5){animation-delay:.8s}
      @keyframes vxSvgPack{0%,18%{transform:translateY(0) scale(.98)}27%,38%{transform:translateY(18px) scale(1.03)}48%,100%{transform:translateY(45px) scale(.9);opacity:0}}
      @keyframes vxSvgTear{0%,16%{transform:translate(0,0) rotate(0)}25%,38%{transform:translate(46px,-32px) rotate(15deg);opacity:0}39%,100%{opacity:0}}
      @keyframes vxSvgCardA{0%,27%{opacity:0;transform:translateY(90px)}37%,51%{opacity:1;transform:translate(-72px,-40px) rotate(-13deg)}61%,100%{opacity:0;transform:translate(-90px,-65px) rotate(-18deg)}}
      @keyframes vxSvgCardB{0%,30%{opacity:0;transform:translateY(90px)}40%,53%{opacity:1;transform:translate(70px,-48px) rotate(13deg)}62%,100%{opacity:0;transform:translate(92px,-70px) rotate(18deg)}}
      @keyframes vxSvgCardC{0%,32%{opacity:0;transform:translateY(90px)}42%,54%{opacity:1;transform:translate(0,-76px) rotate(2deg)}62%,100%{opacity:0;transform:translateY(-100px)}}
      @keyframes vxSvgHit{0%,47%{opacity:0;transform:translateY(55px) scale(.75) rotate(-4deg)}56%,70%{opacity:1;transform:translateY(-18px) scale(1.08) rotate(1deg)}77%,100%{opacity:0;transform:translateY(-18px) scale(.98)}}
      @keyframes vxSvgSlab{0%,67%{opacity:0;transform:scale(.82)}76%,96%{opacity:1;transform:scale(1.07)}100%{opacity:0;transform:scale(.98)}}
      @keyframes vxSvgBurst{0%,46%{opacity:0;transform:scale(.2) rotate(0)}57%{opacity:1;transform:scale(1.2) rotate(20deg)}78%,100%{opacity:0;transform:scale(1.65) rotate(35deg)}}
      @keyframes vxSvgShine{0%{transform:translateX(-190px) skewX(-18deg)}100%{transform:translateX(390px) skewX(-18deg)}}@keyframes vxSvgPulse{50%{transform:scale(1.08);opacity:.7}}
      @keyframes vxSvgProgress{0%{width:5%}25%{width:27%}50%{width:53%}75%{width:78%}100%{width:100%}}@keyframes vxSvgDot{0%,15%,100%{background:#34264b}20%,58%{background:#c084fc;box-shadow:0 0 10px #a855f7}}
      @media(max-width:640px){.vx-loader-wrap{height:82vh}.vx-loader-svg{width:min(86vw,340px)}.vx-loader-bgcard{opacity:.45}.vx-loader-copy{font-size:18px}}
      @media(prefers-reduced-motion:reduce){.vx-loader-svg *,.vx-loader-glow,.vx-loader-progress:before,.vx-loader-dots i{animation:none!important}.vx-svg-pack,.vx-svg-tear,.vx-svg-card-a,.vx-svg-card-b,.vx-svg-card-c,.vx-svg-hit{display:none}.vx-svg-slab{opacity:1}}
    `;
    document.head.appendChild(style);

    const loader=document.createElement('div');loader.id='vx-nav-loader';loader.setAttribute('role','status');loader.setAttribute('aria-live','polite');
    loader.innerHTML=`<div class="vx-loader-wrap">
      <div class="vx-loader-glow"></div><div class="vx-loader-floor"></div>
      <div class="vx-loader-bgcard" style="--x:3%;--y:17%;--r:-10deg"><svg viewBox="0 0 112 158"><defs><radialGradient id="pa"><stop stop-color="#fff7a8"/><stop offset=".42" stop-color="#facc15"/><stop offset="1" stop-color="#4a2800"/></radialGradient></defs><rect width="112" height="158" rx="10" fill="#f59e0b"/><rect x="4" y="4" width="104" height="150" rx="8" fill="url(#pa)" stroke="#fff2a8" stroke-width="2"/><path d="M9 65L104 36M9 119L104 77M22 9L91 151" stroke="#fff" opacity=".3" stroke-width="2"/><rect x="8" y="8" width="96" height="19" rx="4" fill="#fff5b5" opacity=".9"/><text x="13" y="21" fill="#211600" font-size="9">PIKACHU</text><path d="M36 72L20 38l29 23M76 72l16-34-28 23" fill="#facc15" stroke="#fff38a" stroke-width="4"/><ellipse cx="56" cy="86" rx="30" ry="29" fill="#facc15" stroke="#fff38a" stroke-width="2"/><ellipse cx="45" cy="82" rx="4" ry="6"/><ellipse cx="67" cy="82" rx="4" ry="6"/><circle cx="36" cy="95" r="6" fill="#ef4444"/><circle cx="76" cy="95" r="6" fill="#ef4444"/><path d="M49 102q7 8 14 0M82 102l15 7-9 9 12 7-22 15" fill="none" stroke="#fff06a" stroke-width="5"/><rect x="9" y="124" width="94" height="23" rx="5" fill="#4b2c00" opacity=".72"/><text x="56" y="138" text-anchor="middle" fill="#fff" font-size="7">THUNDER CHASE</text></svg></div>
      <div class="vx-loader-bgcard" style="--x:78%;--y:12%;--r:9deg"><svg viewBox="0 0 112 158"><defs><radialGradient id="lu"><stop stop-color="#e0f2fe"/><stop offset=".42" stop-color="#38bdf8"/><stop offset="1" stop-color="#081b42"/></radialGradient></defs><rect width="112" height="158" rx="10" fill="#60a5fa"/><rect x="4" y="4" width="104" height="150" rx="8" fill="url(#lu)" stroke="#dbeafe" stroke-width="2"/><path d="M3 105Q40 73 109 88M5 125Q52 92 108 110" fill="none" stroke="#fff" opacity=".35" stroke-width="5"/><rect x="8" y="8" width="96" height="19" rx="4" fill="#eff6ff" opacity=".9"/><text x="13" y="21" fill="#172554" font-size="9">LUGIA</text><path d="M56 42c-10 10-13 23-10 39L17 64l23 30-20 16 31-7c-1 14 1 26 5 36 5-10 7-22 5-36l31 7-20-16 23-30-29 17c3-16 0-29-10-39z" fill="#f8fafc" stroke="#bfdbfe" stroke-width="3"/><path d="M47 61l9-12 9 12-9 8zM43 97q13 10 26 0" fill="#31558c"/><rect x="9" y="124" width="94" height="23" rx="5" fill="#071a3a" opacity=".7"/><text x="56" y="138" text-anchor="middle" fill="#fff" font-size="7">AERIAL LEGEND</text></svg></div>
      <div class="vx-loader-bgcard" style="--x:7%;--y:55%;--r:7deg"><svg viewBox="0 0 112 158"><defs><radialGradient id="mw"><stop stop-color="#fff1ff"/><stop offset=".35" stop-color="#f0abfc"/><stop offset=".72" stop-color="#a855f7"/><stop offset="1" stop-color="#12304b"/></radialGradient></defs><rect width="112" height="158" rx="10" fill="#d946ef"/><rect x="4" y="4" width="104" height="150" rx="8" fill="url(#mw)" stroke="#67e8f9" stroke-width="2"/><circle cx="20" cy="50" r="15" fill="none" stroke="#fff" opacity=".4"/><circle cx="90" cy="88" r="22" fill="none" stroke="#67e8f9" opacity=".45"/><path d="M7 115Q44 70 106 43M18 141Q55 91 103 75" fill="none" stroke="#fff" opacity=".35" stroke-width="2"/><rect x="8" y="8" width="96" height="19" rx="4" fill="#fae8ff" opacity=".9"/><text x="13" y="21" fill="#4a044e" font-size="9">MEW</text><path d="M42 67L29 43l25 17M70 67l13-24-25 17" fill="#f5b8fa" stroke="#ffe1ff" stroke-width="4"/><ellipse cx="56" cy="82" rx="25" ry="27" fill="#f0abfc" stroke="#fce7ff" stroke-width="2"/><ellipse cx="48" cy="79" rx="4" ry="8" fill="#0e7490"/><ellipse cx="64" cy="79" rx="4" ry="8" fill="#0e7490"/><path d="M52 94q4 4 8 0M58 107c20 4 32 15 23 29-7 11-23 5-17-4 4-6 12-1 9 3" fill="none" stroke="#f7c7fb" stroke-width="5" stroke-linecap="round"/><rect x="9" y="124" width="94" height="23" rx="5" fill="#4a145f" opacity=".68"/><text x="56" y="138" text-anchor="middle" fill="#fff" font-size="7">COSMIC RARE</text></svg></div>
      <div class="vx-loader-bgcard" style="--x:80%;--y:54%;--r:-8deg"><svg viewBox="0 0 112 158"><rect width="112" height="158" rx="10" fill="#170c2c"/><rect x="4" y="4" width="104" height="150" rx="8" fill="none" stroke="#a855f7" stroke-width="3"/><rect x="8" y="8" width="96" height="19" rx="4" fill="#31234a"/><text x="13" y="21" fill="#ddd6fe" font-size="8">MYSTERY PULL</text><path d="M56 43l32 28-32 34-32-34z" fill="#13091f" stroke="#c084fc" stroke-width="6"/><circle cx="56" cy="73" r="8" fill="#e879f9"/><path d="M14 121L97 37M7 92L83 151" stroke="#fff" opacity=".18" stroke-width="3"/><text x="56" y="140" text-anchor="middle" fill="#e9d5ff" font-size="7">NEXT HIT?</text></svg></div>
      <svg class="vx-loader-svg" viewBox="0 0 360 430" aria-hidden="true">
        <defs>
          <linearGradient id="vxP" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#090612"/><stop offset=".48" stop-color="#1b0b32"/><stop offset="1" stop-color="#05040b"/></linearGradient>
          <linearGradient id="vxN" x1="0" x2="1"><stop stop-color="#7c3aed"/><stop offset=".5" stop-color="#e879f9"/><stop offset="1" stop-color="#8b5cf6"/></linearGradient>
          <linearGradient id="vxH" x1="0" y1="1" x2="1" y2="0"><stop stop-color="#111827"/><stop offset=".3" stop-color="#6d28d9"/><stop offset=".58" stop-color="#f59e0b"/><stop offset=".78" stop-color="#06b6d4"/><stop offset="1" stop-color="#111827"/></linearGradient>
          <filter id="vxG"><feGaussianBlur stdDeviation="5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
          <clipPath id="vxClip"><rect x="112" y="91" width="136" height="215" rx="11"/></clipPath>
        </defs>
        <g class="vx-svg-burst" stroke="#d946ef" stroke-width="3" filter="url(#vxG)"><path d="M180 205L180 30M180 205L300 70M180 205L340 200M180 205L290 340M180 205L180 395M180 205L65 350M180 205L20 210M180 205L60 70"/></g>
        <g class="vx-svg-card-a"><rect x="118" y="88" width="124" height="184" rx="11" fill="#080713" stroke="#a855f7" stroke-width="4"/><path d="M180 130l35 30-35 31-35-31z" fill="none" stroke="#d8b4fe" stroke-width="7"/></g>
        <g class="vx-svg-card-b"><rect x="118" y="88" width="124" height="184" rx="11" fill="#080713" stroke="#d946ef" stroke-width="4"/><path d="M180 130l35 30-35 31-35-31z" fill="none" stroke="#c084fc" stroke-width="7"/></g>
        <g class="vx-svg-card-c"><rect x="118" y="88" width="124" height="184" rx="11" fill="#080713" stroke="#8b5cf6" stroke-width="4"/><path d="M180 130l35 30-35 31-35-31z" fill="none" stroke="#e879f9" stroke-width="7"/></g>
        <g class="vx-svg-pack" filter="url(#vxG)">
          <path d="M86 105h188l-9 218-18 21H112l-18-21z" fill="url(#vxP)" stroke="#8b5cf6" stroke-width="3"/>
          <path d="M86 105l12-18h164l12 18-10 17H96z" fill="#080713" stroke="#a855f7" stroke-width="3"/>
          <path d="M102 318h156l-10 24H112z" fill="#080713" stroke="#7c3aed" stroke-width="3"/>
          <path d="M180 154l43 37-43 42-43-42z" fill="none" stroke="url(#vxN)" stroke-width="9"/><path d="M180 170l23 21-23 23-23-23z" fill="#080713"/>
          <text x="180" y="270" text-anchor="middle" fill="#fff" font-size="23" font-weight="900" font-family="sans-serif">VortexOps</text><text x="180" y="289" text-anchor="middle" fill="#c4b5fd" font-size="8" letter-spacing="2" font-family="sans-serif">COLLECT • SELL • GROW</text>
        </g>
        <g class="vx-svg-tear"><path d="M91 105l25-17 20 14 21-14 23 15 24-15 20 14 21-14 27 17-13 19H103z" fill="#120a20" stroke="#e879f9" stroke-width="3"/></g>
        <g class="vx-svg-hit" filter="url(#vxG)"><rect x="106" y="74" width="148" height="222" rx="12" fill="#2a0904" stroke="#f5d0fe" stroke-width="5"/><rect x="112" y="80" width="136" height="210" rx="9" fill="url(#vxH)" stroke="#fbbf24" stroke-width="3"/><path d="M116 198Q139 127 180 92q40 34 64 106-28 69-64 84-37-16-64-84z" fill="#f97316" opacity=".45"/><path d="M180 132c-15-23-37-17-40 2-23-10-41 6-37 26 4 17 23 23 38 16-10 24 3 52 39 68 36-16 49-44 39-68 15 7 34 1 38-16 4-20-14-36-37-26-3-19-25-25-40-2z" fill="#f97316" stroke="#fed7aa" stroke-width="4"/><path d="M160 157l-32-18 18 33M200 157l32-18-18 33M164 190q16 15 32 0" fill="none" stroke="#7c2d12" stroke-width="5" stroke-linecap="round"/><path d="M188 130q17-28 31-5-18 1-21 20" fill="#fde047"/><path d="M122 115l29 16M218 112l-25 18M120 235l28-13M238 225l-29-12" stroke="#fff7ed" stroke-width="3" opacity=".6"/><rect x="118" y="88" width="124" height="26" rx="5" fill="#7c2d12" opacity=".86"/><text x="180" y="106" text-anchor="middle" fill="#fff7ed" font-size="12" font-weight="900" font-family="sans-serif">CHARIZARD HIT</text><rect x="120" y="251" width="120" height="27" rx="5" fill="#3b0b06" opacity=".78"/><text x="180" y="268" text-anchor="middle" fill="#fff" font-size="10" font-weight="900" font-family="sans-serif">FIRE CHASE • 330</text><rect class="vx-svg-shine" x="115" y="70" width="38" height="235" fill="rgba(255,255,255,.32)" clip-path="url(#vxClip)"/></g>
        <g class="vx-svg-slab" filter="url(#vxG)"><rect x="92" y="48" width="176" height="286" rx="16" fill="rgba(220,215,255,.11)" stroke="#ede9fe" stroke-width="5"/><rect x="102" y="60" width="156" height="48" rx="7" fill="#f5f3ff"/><text x="113" y="79" fill="#2e1065" font-size="9" font-weight="900" font-family="sans-serif">VORTEX GRADE</text><text x="113" y="94" fill="#6d28d9" font-size="8" font-weight="800" font-family="sans-serif">GEM MINT</text><text x="238" y="92" text-anchor="end" fill="#2e1065" font-size="30" font-weight="900" font-family="sans-serif">10</text><rect x="109" y="119" width="142" height="200" rx="9" fill="url(#vxH)" stroke="#c084fc" stroke-width="3"/><path d="M180 163l39 34-39 39-39-39z" fill="#100820" stroke="#fff" stroke-width="7"/><text x="180" y="273" text-anchor="middle" fill="#fff" font-size="17" font-weight="900" font-family="sans-serif">VortexOps</text></g>
      </svg>
      <div class="vx-loader-copy"><span class="vx-loader-status">Loading VortexOps…</span><small class="vx-loader-sub">Rip • Reveal • Grade • Inventory</small><div class="vx-loader-progress"></div><div class="vx-loader-dots"><i></i><i></i><i></i><i></i><i></i></div></div>
    </div>`;
    document.body.appendChild(loader);
    const status=loader.querySelector('.vx-loader-status');let statusTimers=[];
    const resetStatus=()=>{statusTimers.forEach(clearTimeout);statusTimers=[];if(status)status.textContent='Loading VortexOps…'};
    const runStatus=()=>{resetStatus();[['Ripping Pack…',480],['Revealing Cards…',980],['Found a Hit…',1500],['Grading Hit…',2050],['Almost Ready…',2600]].forEach(([copy,ms])=>statusTimers.push(setTimeout(()=>{if(status)status.textContent=copy},ms)))};
    let timer=null,watchdog=null;
    // The loader is shown from a link click, before anything knows whether the
    // click will really leave the page. A download (export/PDF/CSV) or a link
    // whose own handler cancels it never navigates, which used to leave this
    // full-screen layer over the page for 12 seconds. So: decide after the click
    // has finished bubbling, skip download-shaped links, give a click that never
    // unloaded the page only a few seconds, and let a tap or Esc dismiss it.
    const show=(event=null,limit=12000)=>{clearTimeout(timer);clearTimeout(watchdog);resetStatus();timer=setTimeout(()=>{if(event?.defaultPrevented)return;runStatus();loader.classList.add('vx-show');watchdog=setTimeout(()=>{loader.classList.remove('vx-show');resetStatus()},limit)},75)};
    const hide=()=>{clearTimeout(timer);clearTimeout(watchdog);resetStatus();loader.classList.remove('vx-show')};
    if(!window.__vortexNavLoaderBound){window.__vortexNavLoaderBound=true;document.addEventListener('livewire:navigating',()=>window.__vortexNavLoader?.show());document.addEventListener('livewire:navigated',()=>window.__vortexNavLoader?.hide())}
    window.__vortexNavLoader={show,hide};window.addEventListener('pageshow',hide,{once:true});
    document.addEventListener('click',(event)=>{const link=event.target.closest?.('a[href]');if(!link||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||link.target==='_blank'||link.hasAttribute('download')||link.hasAttribute('wire:click')||link.hasAttribute('x-on:click')||link.hasAttribute('@click'))return;const url=new URL(link.href,location.href);if(url.origin!==location.origin||url.href===location.href||(url.hash&&url.pathname===location.pathname&&url.search===location.search))return;if(/(^|\/)(export|exports|download|print)(\/|$)|\.(pdf|csv|xlsx|zip)$|[?&]download=/i.test(url.pathname+url.search))return;show(event,link.hasAttribute('wire:navigate')?12000:4000)},{capture:true});
    loader.addEventListener('click',hide);document.addEventListener('keydown',(e)=>{if(e.key==='Escape')hide()});document.addEventListener('visibilitychange',hide);
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

