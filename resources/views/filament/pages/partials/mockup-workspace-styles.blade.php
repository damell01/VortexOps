<style>
/* Shared visual language from the approved VortexOps page references. */
.fi-main:has(.vx-stream-home){max-width:1680px!important}
.vx-stream-home{--home-surface:#fff;--home-border:#dfe5ef;--home-text:#10152e;--home-muted:#57627e;color:var(--home-text);display:grid;gap:22px}
.dark .vx-stream-home{--home-surface:#111827;--home-border:#334155;--home-text:#f8fafc;--home-muted:#a5b4cf}
.vx-stream-home p{color:var(--home-muted);font-size:14px;line-height:1.5}
.vx-home-welcome h1{font-size:32px;line-height:1.2;font-weight:800;letter-spacing:-.025em;margin:4px 0}
.vx-stream-home h2{font-size:20px;font-weight:750;display:flex;align-items:center;gap:10px}.vx-stream-home h3{font-size:20px;font-weight:750;line-height:1.3}
.vx-stream-home svg{width:22px;height:22px;flex:none;color:#7034ed}
.vx-home-columns{display:grid;grid-template-columns:minmax(0,2fr) minmax(300px,1fr);gap:20px;align-items:start}
.vx-home-main,.vx-home-sidebar,.vx-home-show-list{display:grid;gap:16px;min-width:0}.vx-home-sidebar{padding-top:0}
.vx-home-card{background:var(--home-surface);border:1px solid var(--home-border);border-radius:15px;padding:22px;box-shadow:0 5px 20px #17213d06;min-width:0}
.vx-home-tabs{display:flex;background:var(--home-surface);border:1px solid var(--home-border);border-radius:11px;padding:3px;width:fit-content;max-width:100%;gap:3px}
.vx-home-tabs button{padding:11px 22px;border-radius:9px;min-height:44px;font-size:14px;font-weight:650;white-space:nowrap}.vx-home-tabs button.is-active{background:linear-gradient(120deg,#7439f5,#5822e8);color:#fff}.vx-home-tabs span{border-radius:50%;padding:3px 8px;background:#eef0f6;color:#11152d;margin-left:8px}
.vx-home-show{display:grid;grid-template-columns:minmax(0,1fr) auto 210px;gap:16px;align-items:start}.vx-home-show-copy{min-width:0}.vx-home-show h3{margin:7px 0 3px;overflow-wrap:anywhere}.vx-home-date{font-size:12px!important}.vx-home-date b{color:#7028f5;margin-right:8px}.vx-home-item-meta{display:flex;flex-wrap:wrap;gap:10px 16px;font-size:13px;margin-top:16px;color:var(--home-muted)}.vx-home-item-meta b{color:var(--home-text)}
.vx-home-help{border-top:1px solid var(--home-border);padding-top:10px;margin-top:14px;font-size:12px!important}.vx-home-badge{display:inline-flex;align-items:center;justify-content:center;padding:5px 10px;border-radius:8px;background:#ede5ff;color:#6324db;font-size:12px;line-height:1.5;align-self:start;font-weight:650}.vx-home-badge.warning{background:#fff0d5;color:#a95a06}.vx-home-badge.danger{background:#ffe5eb;color:#be123c}.vx-home-badge.success{background:#dcf9ec;color:#057348}
.vx-home-show-actions{display:grid;gap:8px}.vx-home-button{display:flex;align-items:center;justify-content:center;gap:10px;border:1px solid var(--home-border);border-radius:9px;min-height:46px;color:#6925ed;font-size:14px;font-weight:700;padding:10px 14px}.vx-home-button.primary{background:linear-gradient(110deg,#7738f6,#5820e7);color:white;border-color:transparent}.vx-home-button.primary svg{color:white}
.vx-home-attention{display:flex;justify-content:space-between;gap:14px;padding:20px 0;border-bottom:1px solid var(--home-border);font-size:14px}.vx-home-attention:last-child{border:0;padding-bottom:0}.vx-home-attention p{font-size:12px;margin-top:4px}.vx-home-attention>span{color:#7028f5;white-space:nowrap;align-self:center;font-weight:650}.vx-home-sidebar h2{font-size:17px;margin-bottom:16px}.vx-home-footer{display:block;border-top:1px solid var(--home-border);padding-top:14px;margin-top:16px;color:#7028f5;font-size:14px;font-weight:650}.vx-home-performance{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:20px}.vx-home-performance>div{border:1px solid var(--home-border);border-radius:12px;padding:15px}.vx-home-performance strong{font-size:20px;display:block;margin-top:5px}.vx-home-performance p{font-size:12px}.vx-home-card summary{font-size:17px;font-weight:750;cursor:pointer}.vx-home-section-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.vx-home-section-heading h2{font-size:16px}.vx-home-section-heading>a{font-size:13px;color:#7028f5;font-weight:650}.vx-home-entry{display:grid;grid-template-columns:minmax(0,1.5fr) 65px 90px minmax(0,1fr) 110px;gap:12px;padding:15px 10px;border-top:1px solid var(--home-border);align-items:center;font-size:12px}.vx-home-entry b,.vx-home-entry>span{overflow-wrap:anywhere}.vx-home-empty{text-align:center;padding:30px}.vx-home-empty svg{margin:auto}.vx-home-empty h3{margin:12px 0}
.vx-stream-home :is(a,button,summary):focus-visible{outline:3px solid #a78bfa;outline-offset:3px}
@media(min-width:1200px){.vx-home-sidebar{padding-top:44px}}
@media(max-width:1250px){.vx-home-show{grid-template-columns:minmax(0,1fr) auto}.vx-home-show-actions{grid-column:1/-1;grid-template-columns:1fr 1fr}.vx-home-entry{grid-template-columns:minmax(0,1fr) 60px 90px}.vx-home-entry small{grid-column:2/4}}
@media(max-width:900px){.vx-home-columns{grid-template-columns:1fr}.vx-home-sidebar{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-home-sidebar>details{grid-column:1/-1}}
@media(max-width:600px){.vx-home-welcome h1{font-size:25px}.vx-stream-home h2{font-size:18px}.vx-home-tabs{width:100%}.vx-home-tabs button{padding:10px 8px;flex:1;font-size:12px}.vx-home-tabs span{margin-left:3px}.vx-home-card{padding:17px}.vx-home-show{grid-template-columns:1fr}.vx-home-show-actions{grid-template-columns:1fr}.vx-home-sidebar{grid-template-columns:1fr}.vx-home-sidebar>details{grid-column:auto}.vx-home-entry{grid-template-columns:minmax(0,1fr) auto}.vx-home-entry>span:nth-child(4){grid-column:1/-1}.vx-home-entry small{grid-column:auto}.vx-home-entry .vx-home-badge{justify-self:start}.vx-home-section-heading{align-items:start}.vx-home-section-heading>a{max-width:80px}.vx-home-attention{flex-wrap:wrap}}

/* ── Streamer home refresh ─────────────────────────────────────────── */
.vx-home-hero{position:relative;overflow:hidden;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px 28px;padding:30px 32px;border-radius:22px;color:#fff;
  background:radial-gradient(120% 140% at 85% 0%,#7c3aed 0%,transparent 55%),radial-gradient(90% 120% at 0% 100%,#1d4ed8 0%,transparent 60%),linear-gradient(120deg,#0b0b2b,#1e1052 55%,#2a0f63);box-shadow:0 18px 40px -22px #3b0d99}
.vx-home-hero::before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.55;background:radial-gradient(circle at 12% 30%,#fff 0 1px,transparent 1.5px),radial-gradient(circle at 70% 70%,#fff 0 1px,transparent 1.5px),radial-gradient(circle at 40% 12%,#c4b5fd 0 1px,transparent 1.6px),radial-gradient(circle at 88% 40%,#7dd3fc 0 1px,transparent 1.6px),radial-gradient(circle at 58% 88%,#fff 0 1px,transparent 1.5px);animation:vx-home-twinkle 6s ease-in-out infinite alternate}
.vx-home-hero::after{content:"";position:absolute;inset:-40%;pointer-events:none;background:linear-gradient(115deg,transparent 40%,rgb(255 255 255 / .09) 48%,rgb(196 181 253 / .12) 52%,transparent 60%);animation:vx-home-foil 9s linear infinite}
@keyframes vx-home-twinkle{to{opacity:.2}}
@keyframes vx-home-foil{from{transform:translateX(-30%)}to{transform:translateX(30%)}}
.vx-home-hero>*{position:relative;z-index:1}
.vx-home-hero .vx-home-welcome h1{color:#fff;font-size:34px;margin:6px 0 4px}
.vx-home-hero p{color:#d6d3f5!important}
.vx-home-hero-date{display:inline-flex;align-items:center;gap:8px;padding:5px 12px;border-radius:999px;background:rgb(255 255 255 / .1);border:1px solid rgb(255 255 255 / .18);font-size:13px!important;font-weight:600}
.vx-stream-home .vx-home-hero svg{color:#fff}
.vx-home-wave{display:inline-block;transform-origin:70% 70%;animation:vx-home-wave 2.4s ease-in-out 1 .4s}
@keyframes vx-home-wave{0%,60%,100%{transform:rotate(0)}10%,30%{transform:rotate(14deg)}20%{transform:rotate(-8deg)}40%{transform:rotate(-4deg)}50%{transform:rotate(10deg)}}
.vx-home-hero-stats{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.vx-home-hero-stats span{padding:7px 12px;border-radius:10px;background:rgb(255 255 255 / .08);border:1px solid rgb(255 255 255 / .14);font-size:13px;color:#e4e1ff}
.vx-home-hero-stats b{color:#fff;font-size:15px;margin-right:4px}
.vx-home-hero-cta{display:flex;align-items:center;gap:14px;min-width:min(340px,100%);padding:16px 18px;border-radius:16px;background:rgb(255 255 255 / .1);border:1px solid rgb(255 255 255 / .28);color:#fff;text-decoration:none;backdrop-filter:blur(6px);transition:transform .2s,background .2s,box-shadow .2s}
.vx-home-hero-cta:hover{transform:translateY(-2px);background:rgb(255 255 255 / .16);box-shadow:0 14px 30px -16px #000}
.vx-home-hero-cta b{display:block;font-size:16px}.vx-home-hero-cta small{display:block;color:#d6d3f5;font-size:13px;margin-top:2px}
.vx-home-hero-cta-icon{width:40px;height:40px;border-radius:999px;background:#fff;display:grid;place-items:center;flex:none}
.vx-stream-home .vx-home-hero-cta-icon svg{color:#5b21e6}
.vx-home-hero-cta-arrow{margin-left:auto}
.vx-home-section-title{margin-bottom:-6px}
/* The clock keeps its own colours (a white play icon on the green button). */
.vx-stream-home .vx-clock svg{color:inherit}
/* Show cards: details on the left; status and actions together on the right. */
.vx-home-show{grid-template-columns:minmax(0,1fr) 230px;grid-template-areas:"copy badge" "copy actions";row-gap:12px;transition:transform .2s,box-shadow .2s,border-color .2s}
.vx-home-show .vx-home-show-copy{grid-area:copy}.vx-home-show .vx-home-badge{grid-area:badge;justify-self:end}.vx-home-show .vx-home-show-actions{grid-area:actions;align-self:start}
.vx-home-show:hover{transform:translateY(-2px);border-color:#c4b5fd;box-shadow:0 14px 30px -18px #4c1d95}
.vx-home-show{border-left:4px solid #e4dcff}.vx-home-show:has(.vx-home-badge.danger){border-left-color:#fb7185}.vx-home-show:has(.vx-home-badge.warning){border-left-color:#f59e0b}.vx-home-show:has(.vx-home-badge.success){border-left-color:#34d399}
.vx-home-badge.danger{animation:vx-home-nudge 2.6s ease-in-out 2}
@keyframes vx-home-nudge{0%,100%{box-shadow:0 0 0 0 #fb718566}50%{box-shadow:0 0 0 5px transparent}}
.vx-home-button{transition:transform .12s,filter .15s,box-shadow .2s}.vx-home-button:active{transform:scale(.98)}.vx-home-button.primary:hover{filter:brightness(1.08);box-shadow:0 10px 22px -12px #5b21e6}
.vx-home-tabs button{transition:background .2s,color .2s}
/* Everything rises in, a beat apart. */
.vx-home-hero,.vx-stream-home>.vx-clock,.vx-home-show,.vx-home-sidebar>*{animation:vx-home-rise .35s cubic-bezier(.2,.7,.2,1) backwards}
.vx-stream-home>.vx-clock{animation-delay:.05s}.vx-home-show:nth-child(1){animation-delay:.1s}.vx-home-show:nth-child(2){animation-delay:.15s}.vx-home-show:nth-child(n+3){animation-delay:.2s}
.vx-home-sidebar>:nth-child(1){animation-delay:.12s}.vx-home-sidebar>:nth-child(2){animation-delay:.18s}.vx-home-sidebar>:nth-child(3){animation-delay:.24s}
@keyframes vx-home-rise{from{opacity:0;transform:translateY(8px)}}
.vx-home-performance>div{transition:transform .2s,border-color .2s}.vx-home-performance>div:hover{transform:translateY(-2px);border-color:#c4b5fd}
@media(min-width:1200px){.vx-home-sidebar{padding-top:0}}
@media(max-width:1250px){.vx-home-show{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"copy badge" "actions actions"}}
@media(max-width:600px){.vx-home-hero{padding:22px 20px;border-radius:18px}.vx-home-hero .vx-home-welcome h1{font-size:25px}.vx-home-hero-cta{min-width:100%}.vx-home-show{grid-template-columns:1fr;grid-template-areas:"badge" "copy" "actions"}.vx-home-show .vx-home-badge{justify-self:start}}
@media(prefers-reduced-motion:reduce){.vx-stream-home *,.vx-stream-home *::before,.vx-stream-home *::after{animation:none!important;transition:none!important}}
</style>
