// Small touches for a card business: holo tilt, count-up figures, status
// flips and a little foil confetti. Light by design; nothing runs when the
// person asks for reduced motion.
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
const PILLS = '.vxr-pill, .vx-home-badge, .vxw-pill';
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
export function celebrate() {
    if (calm.matches) return;
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

function boot() {
    countAll();
    rememberPills();
    // A celebration flashed by the server for the page we just landed on.
    if (document.querySelector('[data-vx-celebrate]')) setTimeout(celebrate, 250);
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true }); else boot();
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook?.('commit', ({ succeed }) => succeed(() => queueMicrotask(() => { flipChanged(); countAll(); })));
});
