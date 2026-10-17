// Small touches for a card business: holo tilt, count-up figures, status
// flips and a little foil confetti. Light by design; nothing runs when the
// person asks for reduced motion.
import './poke-moments.js';
import { allowSmall, allowBig } from './celebrations.js';
import './finishes.js';
const calm = window.matchMedia('(prefers-reduced-motion: reduce)');
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

// ── Holo tilt on clickable cards ─────────────────────────────────────────
const TILT = 'a.vxr-stat, a.vxr-tile, .vx-home-show, .vx-home-hero-cta';
document.addEventListener('pointermove', (e) => {
    if (calm.matches || !finePointer.matches) return;
    const card = e.target.closest?.(TILT);
    document.querySelectorAll('.vx-tilt.is-tilting').forEach((el) => { if (el !== card) untilt(el); });
    if (!card) return;
    const r = card.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
    card.classList.add('vx-tilt', 'is-tilting');
    card.style.setProperty('--vx-ry', ((x - .5) * 5).toFixed(2) + 'deg');
    card.style.setProperty('--vx-rx', ((.5 - y) * 4).toFixed(2) + 'deg');
    card.style.setProperty('--vx-lift', '-2px');
    card.style.setProperty('--vx-mx', (x * 100).toFixed(0) + '%');
}, { passive: true });
function untilt(el) {
    el.classList.remove('is-tilting');
    ['--vx-rx', '--vx-ry', '--vx-lift'].forEach((p) => el.style.removeProperty(p));
}
document.addEventListener('pointerleave', () => document.querySelectorAll('.vx-tilt.is-tilting').forEach(untilt));

// ── Figures count up the first time they appear ──────────────────────────
const COUNT = '.vxr-stat-value, .vx-home-hero-stats b, .vx-home-performance strong';
const NUM = /^(-)?(\$)?([\d,]+)(\.\d+)?(%)?$/;
function countUp(el) {
    if (el.dataset.vxCounted) return;
    el.dataset.vxCounted = '1';
    const text = el.textContent.trim();
    const m = text.match(NUM);
    if (!m || calm.matches) return;
    const target = parseFloat(m[3].replace(/,/g, '') + (m[4] || ''));
    if (!isFinite(target) || target === 0) return;
    const dp = m[4] ? m[4].length - 1 : 0;
    const fmt = (v) => (m[1] || '') + (m[2] || '') + v.toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp }) + (m[5] || '');
    const start = performance.now(), ms = 650;
    const step = (now) => {
        const t = Math.min(1, (now - start) / ms);
        el.textContent = fmt(target * (1 - Math.pow(1 - t, 3)));
        if (t < 1) requestAnimationFrame(step); else el.textContent = text;
    };
    requestAnimationFrame(step);
}
function countAll() { document.querySelectorAll(COUNT).forEach(countUp); }

// ── Status pills flip when their text changes ────────────────────────────
const PILLS = '.vxr-pill, .vx-home-badge, .vxw-pill, .fi-badge';
function rememberPills() { document.querySelectorAll(PILLS).forEach((el) => { el.dataset.vxWas = el.textContent.trim(); }); }
function flipChanged() {
    document.querySelectorAll(PILLS).forEach((el) => {
        const now = el.textContent.trim();
        if (el.dataset.vxWas !== undefined && el.dataset.vxWas !== now && !calm.matches) {
            el.classList.remove('vx-flip'); void el.offsetWidth; el.classList.add('vx-flip');
        }
        el.dataset.vxWas = now;
    });
}

// ── Foil confetti, for moments worth it ──────────────────────────────────
// Who pops out of the ball: Meowth for pay day, Pikachu for everything else.
const MON = { pay: 'meowth', default: 'pikachu' };
export function celebrate(event) {
    if (calm.matches || !allowBig()) return;
    const detail = Array.isArray(event?.detail) ? event.detail[0] : event?.detail;
    const mon = MON[detail?.kind] || MON.default;
    const catchBox = document.createElement('div');
    catchBox.className = 'vx-catch';
    catchBox.innerHTML = `<div class="vx-catch-ball"></div><span class="vx-catch-star">★</span><img class="vx-catch-mon" src="/img/pokemon/${mon}.gif" alt="">`;
    document.body.appendChild(catchBox);
    setTimeout(() => catchBox.remove(), 2900);
    setTimeout(confetti, 1350);
}

function confetti() {
    const box = document.createElement('div');
    box.className = 'vx-confetti';
    const colours = ['#7c3aed', '#a78bfa', '#4f46e5', '#c026d3', '#e9d5ff', '#38bdf8'];
    for (let i = 0; i < 36; i++) {
        const p = document.createElement('i');
        const a = Math.random() * Math.PI * 2, d = 140 + Math.random() * 260;
        p.style.setProperty('--dx', Math.cos(a) * d + 'px');
        p.style.setProperty('--dy', Math.sin(a) * d * .7 + 180 + 'px');
        p.style.setProperty('--rot', (Math.random() * 720 - 360) + 'deg');
        p.style.background = colours[i % colours.length];
        p.style.animationDelay = Math.random() * 90 + 'ms';
        box.appendChild(p);
    }
    document.body.appendChild(box);
    setTimeout(() => box.remove(), 1400);
}
window.addEventListener('vx-celebrate', celebrate);

// ── Pay run marked paid: Pikachu hops up, cheers, and scampers off ───────
export function pikaCheer(event) {
    if (calm.matches || !allowSmall()) return;
    const detail = Array.isArray(event?.detail) ? event.detail[0] : event?.detail;
    document.querySelector('.vx-pika')?.remove();
    const box = document.createElement('div');
    box.className = 'vx-pika';
    box.setAttribute('role', 'status');
    const say = document.createElement('span');
    say.className = 'vx-pika-say';
    say.textContent = detail?.text || 'Pika pika!';
    box.innerHTML = '<i class="vx-pika-bolt" aria-hidden="true">⚡</i><i class="vx-pika-bolt" aria-hidden="true">⚡</i><img class="vx-pika-mon" src="/img/pokemon/pikachu.gif" alt="">';
    box.prepend(say);
    document.body.appendChild(box);
    setTimeout(() => box.remove(), 3300);
}
window.addEventListener('vx-pika-cheer', pikaCheer);

// ── Report submitted: a Poké Ball that wobbles and becomes a check, once ──
function reportDone() {
    if (calm.matches || document.querySelector('.vx-done')) return;
    const box = document.createElement('div');
    box.className = 'vx-done';
    box.setAttribute('role', 'status');
    box.innerHTML = '<span class="vx-done-ball"></span><span class="vx-done-check"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.2 4.2L19 7" /></svg></span><b>Report submitted</b>';
    document.body.appendChild(box);
    setTimeout(() => box.remove(), 2600);
}
window.vxReportDone = reportDone;

// ── Gotcha! The report caught a Pokémon: a ball wobbles, clicks, and out it pops ──
function catchReveal(species, shiny, rarity, displayName, eventLabel, note = '') {
    if (!allowBig()) return;
    document.querySelector('.vx-gotcha')?.remove();
    const name = displayName || species.charAt(0).toUpperCase() + species.slice(1);
    const box = document.createElement('div');
    box.className = 'vx-gotcha' + (shiny ? ' is-shiny' : '') + (rarity === 'rare' || rarity === 'legendary' ? ' is-rare' : '') + (rarity === 'legendary' ? ' is-legendary' : '');
    box.setAttribute('role', 'status');
    const tag = [note || (eventLabel ? `${eventLabel} event!` : ''), shiny ? '✨ Shiny!' : '', rarity === 'legendary' ? '🌟 Legendary!' : rarity === 'rare' ? '★ Rare!' : rarity === 'uncommon' ? 'Uncommon' : ''].filter(Boolean).join(' · ');
    box.innerHTML = `<div class="vx-gotcha-stage"><span class="vx-gotcha-ball"></span><img class="vx-gotcha-mon" src="/img/pokemon/${shiny ? 'shiny/' : ''}${species}.gif" alt=""></div>`
        + `<div class="vx-gotcha-text"><b>Gotcha! ${name} was caught!</b>${tag ? `<i>${tag}</i>` : ''}<span>Added to your Pokédex</span></div>`;
    const close = () => { box.classList.add('is-out'); setTimeout(() => box.remove(), 300); };
    box.addEventListener('click', close);
    document.body.appendChild(box);
    setTimeout(close, calm.matches ? 4000 : 5200);
}
window.vxCatchReveal = catchReveal;

// ── Shiny: done statuses sparkle once, the first time they appear ───────
const DONE = /^(paid|approved|finalized|complete|completed|submitted|ready for pay)$/i;
function sparkleDone(scope = document) {
    if (calm.matches) return;
    scope.querySelectorAll(PILLS).forEach((el) => {
        if (el.dataset.vxShone || !DONE.test(el.textContent.trim())) return;
        el.dataset.vxShone = '1';
        el.classList.add('vx-shiny');
        setTimeout(() => el.classList.remove('vx-shiny'), 1200);
    });
}

// ── Energy ripple where a primary button is pressed ──────────────────────
document.addEventListener('pointerdown', (e) => {
    if (calm.matches) return;
    const btn = e.target.closest?.('.vxr-btn--primary, .vx-home-button.primary, .fi-btn.fi-color-primary');
    if (!btn) return;
    const r = btn.getBoundingClientRect(), size = Math.max(r.width, r.height);
    const dot = document.createElement('span');
    dot.className = 'vx-ripple';
    dot.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX - r.left - size / 2}px;top:${e.clientY - r.top - size / 2}px`;
    btn.appendChild(dot);
    setTimeout(() => dot.remove(), 600);
}, { passive: true });

// ── Table headers stay in view while a long table scrolls past ──────────
// Tables usually sit in an overflow-x box, where position:sticky does not
// reach the page scroll, so the header is translated instead. A soft shadow
// shows only while it is lifted.
function stickyTop() {
    const bar = document.querySelector('.fi-topbar');
    if (!bar) return 0;
    const pos = getComputedStyle(bar.closest('.fi-topbar-ctn') || bar).position;
    return pos === 'sticky' || pos === 'fixed' ? Math.max(0, bar.getBoundingClientRect().bottom) : 0;
}
let headsQueued = false;
function liftHeads() {
    headsQueued = false;
    const top = stickyTop();
    document.querySelectorAll('.fi-main table').forEach((table) => {
        const head = table.tHead;
        if (!head || (table.tBodies[0]?.rows.length ?? 0) < 8) return;
        const r = table.getBoundingClientRect(), h = head.offsetHeight;
        if (r.top < top && r.bottom - h * 2 > top) {
            head.style.transform = `translateY(${top - r.top}px)`;
            head.classList.add('vx-head-lifted');
        } else if (head.classList.contains('vx-head-lifted')) {
            head.style.transform = '';
            head.classList.remove('vx-head-lifted');
        }
    });
}
function queueHeads() { if (!headsQueued) { headsQueued = true; requestAnimationFrame(liftHeads); } }
window.addEventListener('scroll', queueHeads, { passive: true });
window.addEventListener('resize', queueHeads, { passive: true });

// ── While an update is on its way, figures shimmer where they will land ──
// Only for updates someone just asked for (a click or a picked option, not
// background polling), only after a short wait so fast ones do not flicker,
// and never while someone is typing into a field.
let lastAsk = 0;
['pointerdown', 'change'].forEach((t) => document.addEventListener(t, () => { lastAsk = Date.now(); }, { passive: true, capture: true }));
const typing = () => document.activeElement?.matches?.('input:not([type=checkbox]):not([type=radio]), textarea, [contenteditable]');
function waitShimmer(component) {
    const el = component?.el;
    if (!el || calm.matches || typing() || Date.now() - lastAsk > 1500) return () => {};
    const timer = setTimeout(() => el.classList.add('vx-loading'), 220);
    return () => { clearTimeout(timer); el.classList.remove('vx-loading'); };
}

// ── "Gotcha!" on a successful save ───────────────────────────────────────
function gotcha(node) {
    if (!(node instanceof Element)) return;
    const toasts = node.matches('.fi-no-notification.fi-status-success') ? [node] : node.querySelectorAll?.('.fi-no-notification.fi-status-success') ?? [];
    toasts.forEach((toast) => {
        if (toast.dataset.vxGotcha || toast.closest('.fi-no-database, .fi-modal')) return;
        toast.dataset.vxGotcha = '1';
        // Something deleted is released rather than caught: the ball opens first.
        const released = /\b(deleted|removed|released)\b/i.test(toast.querySelector('.fi-no-notification-title')?.textContent ?? '');
        toast.classList.add('vx-gotcha', ...(released ? ['vx-gotcha--release'] : []));
        // The ball wobbles, then turns into a check — the same moment as a submitted report, in miniature.
        const icon = document.createElement('span');
        icon.className = 'vx-gotcha-icon'; icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = (released ? '<span class="vx-gotcha-ball vx-gotcha-half"></span><span class="vx-gotcha-ball vx-gotcha-lid"></span>' : '<span class="vx-gotcha-ball"></span>')
            + '<span class="vx-gotcha-check"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.2 4.2L19 7" /></svg></span>';
        toast.querySelector('.fi-no-notification-icon')?.before(icon);
        const word = document.createElement('span');
        word.className = 'vx-gotcha-word'; word.textContent = released ? 'Released!' : 'Gotcha!';
        // During a catch event the toast wears its emoji: 🎃 Gotcha!
        if (!released && document.documentElement.dataset.vxEvent) {
            try { const ev = JSON.parse(document.getElementById('vx-fx')?.textContent || '{}').event; if (ev?.emoji) word.textContent = `${ev.emoji} Gotcha!`; } catch {}
        }
        toast.querySelector('.fi-no-notification-text')?.prepend(word);
    });
}
new MutationObserver((list) => list.forEach((m) => m.addedNodes.forEach(gotcha))).observe(document.documentElement, { childList: true, subtree: true });

// ── Slow load: a sleepy Pokémon says so, instead of nothing ──────────────
const QUIPS = [['snorlax', 'Snorlax is waking up…'], ['psyduck', 'Psyduck is thinking really hard…'], ['gengar', 'Gengar is fetching it from the shadows…'], ['eevee', 'Eevee is on it…']];
let slowTimer = null;
function slowStart() {
    clearTimeout(slowTimer);
    slowTimer = setTimeout(() => {
        let el = document.getElementById('vx-slow');
        if (!el) {
            el = document.createElement('div');
            el.id = 'vx-slow'; el.setAttribute('role', 'status');
            el.innerHTML = '<img alt=""><span></span>';
            document.body.appendChild(el);
        }
        const [mon, line] = QUIPS[Math.floor(Math.random() * QUIPS.length)];
        el.querySelector('img').src = `/img/pokemon/${mon}.gif`;
        el.querySelector('span').textContent = line;
        el.classList.add('is-on');
    }, 2000);
}
function slowStop() {
    clearTimeout(slowTimer);
    document.getElementById('vx-slow')?.classList.remove('is-on');
}
document.addEventListener('livewire:navigate', slowStart);
document.addEventListener('livewire:navigated', slowStop);

// ── Shiny visits: about one page in 50, every sprite is its shiny colours ──
// Real shiny sprites live in /img/pokemon/shiny/. ?vxshiny=1 forces one.
let shinyVisit = false, shinyRolledFor = null;
function rollShiny() {
    if (shinyRolledFor === location.href) return; // boot can run twice for one page
    shinyRolledFor = location.href;
    shinyVisit = /[?&]vxshiny=1\b/.test(location.search) || Math.random() < 1 / 50;
    if (shinyVisit) { shinySwap(); announceShiny(); }
}
function shinySwap() {
    if (!shinyVisit) return;
    document.querySelectorAll('img[src*="/img/pokemon/"]:not([src*="/shiny/"])').forEach((img) => {
        img.src = img.getAttribute('src').replace('/img/pokemon/', '/img/pokemon/shiny/');
    });
}
function announceShiny() {
    if (calm.matches || !document.querySelector('img[src*="/img/pokemon/shiny/"]')) return;
    const note = document.createElement('div');
    note.className = 'vx-shiny-note'; note.setAttribute('role', 'status');
    note.textContent = '✨ A shiny appeared!';
    document.body.appendChild(note);
    setTimeout(() => note.remove(), 3600);
}

// ── Search results read like Pokédex entries: #043 from the record's id ──
function dexify() {
    document.querySelectorAll('.fi-global-search-result:not([data-vx-dex])').forEach((row) => {
        row.dataset.vxDex = '1';
        const link = row.querySelector('a.fi-global-search-result-link, a');
        const id = link?.getAttribute('href')?.match(/\/(\d+)(?:\/[\w-]+)?\/?(?:[?#].*)?$/)?.[1];
        if (!id) return;
        const no = document.createElement('span');
        no.className = 'vx-dex-no';
        no.textContent = '#' + id.padStart(3, '0');
        (row.querySelector('.fi-global-search-result-heading') || link).prepend(no);
    });
}

// ── Searching: a Pokédex scan line sweeps the field until the results land ──
const SEARCH = '.fi-ta-search-field input, .fi-global-search-field input, .vx-inv-input';
const scanning = new Set();
document.addEventListener('input', (e) => {
    const wrap = e.target.matches?.(SEARCH) && e.target.closest('.fi-input-wrp, .vx-inv-search');
    if (!wrap || calm.matches) return;
    wrap.classList.add('vx-dex-scan');
    scanning.add(wrap);
    clearTimeout(wrap.vxScanStop);
    wrap.vxScanStop = setTimeout(() => stopScan(wrap), 6000); // never left running
}, { passive: true });
function stopScan(wrap) { wrap.classList.remove('vx-dex-scan'); scanning.delete(wrap); }
function scansDone(component) {
    // Only the component that owns the field; the search goes out after its debounce.
    scanning.forEach((wrap) => { if (!wrap.isConnected || component?.el?.contains(wrap)) stopScan(wrap); });
}

// ── Unread notifications: the Vortex tab icon gets a red dot ──
let plainIcon = null, ballIcon = null, lastUnread = -1;
function makeBallIcon(done) {
    const img = new Image();
    img.onload = () => {
        const c = document.createElement('canvas'); c.width = c.height = 32;
        const g = c.getContext('2d');
        g.drawImage(img, 0, 0, 32, 32);
        g.beginPath(); g.arc(24, 8, 7, 0, Math.PI * 2); g.fillStyle = '#fff'; g.fill();
        g.beginPath(); g.arc(24, 8, 5.5, 0, Math.PI * 2); g.fillStyle = '#ef4444'; g.fill();
        ballIcon = c.toDataURL('image/png'); done();
    };
    img.src = plainIcon || '/icons/icon-32.png';
}
function unreadCount() {
    const badge = document.querySelector('.fi-topbar-database-notifications-btn .fi-badge, .fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn');
    return badge ? parseInt(badge.textContent.replace(/\D/g, ''), 10) || 0 : 0;
}
// ── "A wild report appeared!": when the bell's count goes up, ask whether it
// was a report filed and approved, and pop a Pokémon toast for it once. ──
let wildCount = null;
const WILD_SEEN = 'vx-wild-seen';
function wildSeen() { try { return JSON.parse(localStorage.getItem(WILD_SEEN) || '[]'); } catch { return []; } }
function checkWild(n) {
    if (wildCount !== null && n <= wildCount) { wildCount = n; return; }
    const first = wildCount === null;
    wildCount = n;
    if (n === 0 || (first && document.visibilityState !== 'visible')) return;
    fetch('/admin/wild-report.json', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null)).catch(() => null)
        .then((w) => {
            if (!w?.id || wildSeen().includes(w.id)) return;
            try { localStorage.setItem(WILD_SEEN, JSON.stringify([w.id, ...wildSeen()].slice(0, 30))); } catch {}
            wildToast(w);
        });
}
function wildToast(w) {
    document.querySelector('.vx-wild')?.remove();
    const box = document.createElement('div');
    box.className = 'vx-wild';
    box.setAttribute('role', 'status');
    const mon = w.species ? `<img class="vx-wild-mon" src="/img/pokemon/${w.shiny ? 'shiny/' : ''}${w.species}.gif" alt="">` : '<span class="vx-wild-ball"></span>';
    box.innerHTML = `${mon}<div class="vx-wild-text"><b></b><span></span></div>${w.url ? '<a class="vx-wild-open">Open</a>' : ''}<button type="button" class="vx-wild-x" aria-label="Dismiss">×</button>`;
    box.querySelector('b').textContent = w.title;
    box.querySelector('.vx-wild-text span').textContent = w.body;
    if (w.url) box.querySelector('.vx-wild-open').href = w.url;
    const close = () => { box.classList.add('is-out'); setTimeout(() => box.remove(), 300); };
    box.querySelector('.vx-wild-x').addEventListener('click', close);
    document.body.appendChild(box);
    setTimeout(close, 9000);
}

function tabBadge() {
    const n = unreadCount();
    checkWild(n);
    const icon = document.querySelector('link[rel="icon"]');
    const base = document.title.replace(/^\(\d+\+?\) /, '');
    const title = n > 0 ? `(${n > 99 ? '99+' : n}) ${base}` : base;
    if (document.title !== title) document.title = title; // writing it is itself a mutation
    if (!icon || n === lastUnread) return;
    lastUnread = n;
    plainIcon ??= icon.href;
    if (n === 0) { icon.href = plainIcon; return; }
    if (ballIcon) icon.href = ballIcon; else makeBallIcon(() => { if (unreadCount() > 0) icon.href = ballIcon; });
}
let badgeQueued = false;
new MutationObserver(() => {
    if (badgeQueued) return;
    badgeQueued = true;
    requestAnimationFrame(() => { badgeQueued = false; tabBadge(); shinySwap(); dexify(); });
}).observe(document.body || document.documentElement, { childList: true, subtree: true }); // not text edits: timers tick every second

// ── Visitors: each peek is someone new from the cast (App\Support\Season::visitors) ──
document.addEventListener('animationiteration', (e) => {
    const peek = e.target.closest?.('.vx-season-peek[data-vx-visitors]');
    if (!peek || e.target !== peek) return;
    let cast = [];
    try { cast = JSON.parse(peek.dataset.vxVisitors || '[]'); } catch {}
    if (cast.length < 2) return;
    const img = peek.querySelector('img');
    if (!img) return;
    // The buddy appears more than once in the cast, so advance by position,
    // not indexOf(species), which would keep returning to the first buddy.
    const index = (Number(peek.dataset.vxVisitorIndex || 0) + 1) % cast.length;
    peek.dataset.vxVisitorIndex = String(index);
    const next = cast[index];
    img.src = `/img/pokemon/${shinyVisit ? 'shiny/' : ''}${next}.gif`;
});

// The top bar's height, for anything that tucks underneath it (Gengar in October).
function topbarVar() {
    const bar = document.querySelector('.fi-topbar');
    if (bar) document.documentElement.style.setProperty('--vx-topbar-h', `${Math.round(bar.getBoundingClientRect().height)}px`);
}
window.addEventListener('resize', topbarVar, { passive: true });

function boot() {
    countAll();
    rememberPills();
    sparkleDone();
    // A celebration flashed by the server for the page we just landed on.
    const flashed = document.querySelector('[data-vx-celebrate]');
    if (flashed) setTimeout(flashed.dataset.vxCelebrate === 'report' ? reportDone : celebrate, 250);
    const caught = document.querySelector('[data-vx-caught]');
    if (caught) { const { vxCaught, shiny, rarity, name, event, note } = caught.dataset; caught.remove(); setTimeout(() => { catchReveal(vxCaught, shiny === '1', rarity, name, event, note); chimeIfOn(); }, flashed ? 2500 : 300); }
    queueHeads();
    topbarVar();
    tabBadge();
    rollShiny();
}
// An optional catch chime, made in the browser (no sound file to load). Off
// unless someone turns it on in My Pokédex, and only for this device.
window.vxCatchChime = () => {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        [[880, 0], [1175, .12], [1568, .24]].forEach(([hz, at]) => {
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'triangle'; o.frequency.value = hz;
            g.gain.setValueAtTime(0.0001, ctx.currentTime + at);
            g.gain.exponentialRampToValueAtTime(0.12, ctx.currentTime + at + .02);
            g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + .22);
            o.connect(g).connect(ctx.destination); o.start(ctx.currentTime + at); o.stop(ctx.currentTime + at + .25);
        });
        setTimeout(() => ctx.close(), 800);
    } catch { /* no audio here */ }
};
function chimeIfOn() { try { if (localStorage.getItem('vx-dex-sound') === '1') window.vxCatchChime(); } catch { /* storage blocked */ } }
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true }); else boot();
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook?.('commit', ({ component, succeed, fail }) => {
        const asked = Date.now() - lastAsk < 1500;
        const done = waitShimmer(component);
        if (asked) slowStart();
        const finish = () => { done(); if (asked) slowStop(); };
        fail(finish);
        succeed(() => { finish(); scansDone(component); queueMicrotask(() => { flipChanged(); countAll(); sparkleDone(); queueHeads(); tabBadge(); }); });
    });
});
