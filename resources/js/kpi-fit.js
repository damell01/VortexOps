// KPI numbers never spill out of their card. A big figure ($1,234,567.89)
// in a narrow tile shrinks just enough to fit, down to a floor, instead of
// overflowing the card or wrapping mid-number. Runs on load, after Livewire
// updates and on resize; it only touches numbers that don't fit.

const CARDS = '.ivx-kpi, .vxsim-tile, .vx-stream-kpi, .vx-payroll-kpi, .vxr-stat, .vx-kpi, .vx-an-kpi, .fi-wi-stats-overview-stat, [class*="kpi"]:not([class*="kpis"]), [class*="stat-card"], [data-vx-fit-card]';
const NUMBER = /^[-−–+]?\s?[$£€]?\s?[-−–]?[\d][\d,]*(\.\d+)?\s?[%kKmMbBx]?$/;
const MIN_PX = 12;

function leafNumbers(card) {
    return [...card.querySelectorAll('*')].filter((el) => el.children.length === 0 && NUMBER.test(el.textContent.trim()));
}

function fit(el, card) {
    el.style.removeProperty('font-size');
    el.style.removeProperty('white-space');
    el.style.removeProperty('overflow-wrap');
    const style = getComputedStyle(card);
    const limit = card.getBoundingClientRect().right - parseFloat(style.paddingRight || '0') - parseFloat(style.borderRightWidth || '0');
    const range = document.createRange();
    range.selectNodeContents(el);
    let size = parseFloat(getComputedStyle(el).fontSize);
    let guard = 0;
    while (range.getBoundingClientRect().right > limit + 0.5 && size > MIN_PX && guard++ < 30) {
        size = Math.max(MIN_PX, size * 0.92);
        // important: some pages size these with !important rules of their own.
        el.style.setProperty('font-size', `${size}px`, 'important');
    }
    // Still too wide at the smallest size: let it wrap rather than spill out.
    if (range.getBoundingClientRect().right > limit + 0.5) {
        el.style.setProperty('white-space', 'normal', 'important');
        el.style.setProperty('overflow-wrap', 'anywhere', 'important');
    }
}

function fitAll() {
    document.querySelectorAll(CARDS).forEach((card) => {
        if (!card.getBoundingClientRect().width) return;
        leafNumbers(card).forEach((el) => fit(el, card));
    });
}

let queued = false;
const schedule = () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => { queued = false; fitAll(); });
};

new MutationObserver((mutations) => {
    if (mutations.some((m) => m.type === 'characterData' || m.addedNodes.length)) schedule();
}).observe(document.documentElement, { childList: true, subtree: true, characterData: true });
window.addEventListener('resize', schedule, { passive: true });
document.addEventListener('livewire:navigated', schedule);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', schedule); else schedule();
