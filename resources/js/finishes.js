// Small finishing touches, each under a second and never covering the page:
//   - something marked done (data-vx-done flips 0 → 1) gives a soft green
//     flash, and its check icon draws itself in;
//   - a show that gets fully packed is stamped PACKED (.vx-stamp), settling in
//     once, the first time it happens;
//   - a row that leaves a queue after an action slides out instead of vanishing;
//   - a queue that just emptied gets a Poké Ball that rolls in, opens, and a
//     sleepy Pokémon settles in the empty state;
//   - totals that change after a save tick to their new value.
// Off with the person's "Off" celebrations setting or reduced motion.
import '../css/finishes.css';
import { allowSmall } from './celebrations.js';

const calm = window.matchMedia('(prefers-reduced-motion: reduce)');
const on = () => !calm.matches && allowSmall();

function event() {
    try { return JSON.parse(document.getElementById('vx-fx')?.textContent || '{}').event; } catch { return null; }
}
function seen(key, add) {
    try {
        const list = JSON.parse(localStorage.getItem(key) || '[]');
        if (add === undefined) return list;
        if (!list.includes(add)) localStorage.setItem(key, JSON.stringify([add, ...list].slice(0, 300)));
    } catch {}
    return [];
}

// ── Done: a soft flash and the tick drawing in ────────────────────────────
function doneFlash(el) {
    if (!on()) return;
    el.classList.remove('vx-done-flash'); void el.offsetWidth; el.classList.add('vx-done-flash');
    setTimeout(() => el.classList.remove('vx-done-flash'), 1000);
    const tick = el.querySelector(':scope > svg, :scope > .fi-icon, :scope svg.text-emerald-500');
    if (tick) { tick.classList.add('vx-tick-draw'); setTimeout(() => tick.classList.remove('vx-tick-draw'), 700); }
}
new MutationObserver((list) => {
    for (const m of list) {
        const el = m.target;
        if (m.attributeName === 'data-vx-done' && m.oldValue === '0' && el.getAttribute('data-vx-done') === '1') {
            doneFlash(el);
            stamp(el, true);
        }
    }
}).observe(document.documentElement, { subtree: true, attributes: true, attributeFilter: ['data-vx-done'], attributeOldValue: true });

// ── PACKED: settles in the first time a show is seen going from open to packed ──
const WAITING = 'vx-stamp-waiting';
function stamp(card, now = false) {
    const key = card.dataset.vxStampKey;
    const mark = card.querySelector(':scope > .vx-stamp');
    if (!key) return;
    if (!mark) { seen(WAITING, key); return; } // still open: remember it
    const waiting = seen(WAITING);
    if ((now || waiting.includes(key)) && on() && !mark.dataset.vxSettled) {
        mark.dataset.vxSettled = '1';
        mark.classList.add('is-new');
        try { localStorage.setItem(WAITING, JSON.stringify(waiting.filter((k) => k !== key))); } catch {}
    }
}
function stampAll() { document.querySelectorAll('[data-vx-stamp-key]').forEach((c) => stamp(c)); }

// ── Rows leave a queue with a short slide instead of vanishing ───────────
let actionAt = 0, leaving = 0, leavingReset = null;
function exitable(el) {
    return el instanceof HTMLElement && (el.matches('tr.fi-ta-row') || el.hasAttribute('data-vx-exit'));
}
function slideOut(el, skip) {
    if (!on() || el.dataset.vxLeaving || Date.now() - actionAt > 3000 || !exitable(el)) return;
    const r = el.getBoundingClientRect();
    if (r.bottom < 0 || r.top > innerHeight) return;
    // A whole page of rows changing (a filter, a new page) isn't something leaving.
    leaving++;
    clearTimeout(leavingReset);
    leavingReset = setTimeout(() => { leaving = 0; }, 50);
    if (leaving > 3) return;
    skip();
    el.dataset.vxLeaving = '1';
    el.classList.add('vx-row-out');
    setTimeout(() => el.remove(), 260);
}

// ── A queue that just emptied: a ball rolls in and someone settles down ──
const SLEEPY = ['snorlax', 'slowpoke', 'jigglypuff', 'wooloo', 'umbreon', 'eevee'];
function rest(container) {
    if (!on() || !container || container.querySelector('.vx-rest')) return;
    const ev = event();
    const pick = ev?.pool?.length ? ev.pool : SLEEPY;
    const mon = ev?.swap?.snorlax && Math.random() < 0.5 ? ev.swap.snorlax : pick[Math.floor(Math.random() * pick.length)];
    const box = document.createElement('div');
    box.className = 'vx-rest';
    box.setAttribute('aria-hidden', 'true');
    box.innerHTML = `<span class="vx-rest-ball"></span><img class="vx-rest-mon" alt="" src="/img/pokemon/${mon}.gif"><i class="vx-rest-z">z z</i>`;
    container.prepend(box);
}
function emptyIn(scope) {
    return scope.querySelector('.fi-ta-empty-state, [data-vx-empty]');
}
const counts = new WeakMap();
function rowsIn(table) { return table.querySelectorAll('tbody tr.fi-ta-row').length; }
function watchQueues(fromCommit) {
    // Filament tables: rows before and after this update.
    document.querySelectorAll('.fi-ta').forEach((t) => {
        const now = rowsIn(t), before = counts.get(t);
        counts.set(t, now);
        if (fromCommit && before > 0 && now === 0) rest(emptyIn(t));
    });
    // Our own queues remember their count across visits, so finishing the last
    // show and coming back to the list still counts as emptying it.
    document.querySelectorAll('[data-vx-queue]').forEach((q) => {
        const key = `vx-queue:${q.dataset.vxQueue}`, now = parseInt(q.dataset.vxCount || '0', 10);
        let before = null;
        try { before = sessionStorage.getItem(key); sessionStorage.setItem(key, String(now)); } catch {}
        if (before !== null && parseInt(before, 10) > 0 && now === 0) rest(emptyIn(q));
    });
}

// ── Totals tick to their new value after a save ──────────────────────────
const NUMS = '[data-vx-num], .fi-wi-stats-overview-stat-value, .vxr-stat-value';
const NUM = /^(-|−)?(\$)?([\d,]+)(\.\d+)?(%)?$/;
function parse(text) {
    const m = text.trim().match(NUM);
    return m ? { m, v: parseFloat(m[3].replace(/,/g, '') + (m[4] || '')) * (m[1] ? -1 : 1) } : null;
}
function rememberNums() { document.querySelectorAll(NUMS).forEach((el) => { el.dataset.vxNumWas = el.textContent.trim(); }); }
function nudgeNums() {
    document.querySelectorAll(NUMS).forEach((el) => {
        const was = el.dataset.vxNumWas, text = el.textContent.trim();
        el.dataset.vxNumWas = text;
        if (was === undefined || was === text || !on()) return;
        const a = parse(was), b = parse(text);
        if (!a || !b || !isFinite(a.v) || !isFinite(b.v)) return;
        const dp = b.m[4] ? b.m[4].length - 1 : 0;
        const fmt = (v) => (v < 0 ? '-' : '') + (b.m[2] || '') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp }) + (b.m[5] || '');
        const start = performance.now(), ms = 450;
        el.classList.add('vx-num-nudge');
        const step = (t) => {
            const k = Math.min(1, (t - start) / ms), e = 1 - Math.pow(1 - k, 3);
            if (k < 1) el.textContent = fmt(a.v + (b.v - a.v) * e);
            if (k < 1) requestAnimationFrame(step); else { el.textContent = text; el.dataset.vxNumWas = text; el.classList.remove('vx-num-nudge'); }
        };
        requestAnimationFrame(step);
    });
}

document.addEventListener('livewire:init', () => {
    window.Livewire?.hook?.('commit', ({ commit, succeed }) => {
        if ((commit?.calls || []).some((c) => !/^[$_]/.test(c.method))) actionAt = Date.now();
        succeed(() => queueMicrotask(() => { nudgeNums(); watchQueues(true); stampAll(); }));
    });
    window.Livewire?.hook?.('morph.removing', ({ el, skip }) => slideOut(el, skip));
});
function boot() { rememberNums(); watchQueues(false); stampAll(); }
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true }); else boot();
document.addEventListener('livewire:navigated', boot);
