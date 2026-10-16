// Phones: groups of KPI tiles become one swipeable row (with dots) instead
// of a tall stack of full-width cards, so a page's figures sit in one
// glance and the page itself stays short. Tablets and desktops keep their
// grids. A group opts out with data-vx-no-carousel.
import '../css/carousels.css';

const GROUPS = '.vxr-stats, .vxr-tiles, .ivx-kpis, .vx-stream-kpis, .vx-payroll-kpis, .vxsim-tiles, .vx-an-kpis, .vx-report .vx-kpis, .fi-wi-stats-overview-stats-ctn';
const phone = window.matchMedia('(max-width: 640px)');

function dotsFor(group) {
    let dots = group.nextElementSibling?.classList.contains('vx-carousel-dots') ? group.nextElementSibling : null;
    const count = group.children.length;
    if (!dots) {
        dots = document.createElement('div');
        dots.className = 'vx-carousel-dots';
        dots.setAttribute('aria-hidden', 'true');
        group.after(dots);
    }
    if (dots.childElementCount !== count) dots.innerHTML = '<i></i>'.repeat(count);
    const update = () => {
        const step = group.scrollWidth / count;
        const current = Math.min(count - 1, Math.round(group.scrollLeft / Math.max(1, step)));
        [...dots.children].forEach((dot, i) => dot.classList.toggle('is-on', i === current));
    };
    if (!group.dataset.vxCarouselBound) {
        group.dataset.vxCarouselBound = '1';
        group.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
    }
    update();
}

function apply() {
    document.querySelectorAll(GROUPS).forEach((group) => {
        const on = phone.matches && group.children.length >= 3 && !group.hasAttribute('data-vx-no-carousel');
        group.classList.toggle('vx-carousel', on);
        if (on) group.setAttribute('data-vx-carousel', ''); else group.removeAttribute('data-vx-carousel');
        if (on) dotsFor(group);
        else if (group.nextElementSibling?.classList.contains('vx-carousel-dots')) group.nextElementSibling.remove();
    });
}

let queued = false;
const schedule = () => { if (queued) return; queued = true; requestAnimationFrame(() => { queued = false; apply(); }); };
new MutationObserver((mutations) => { if (mutations.some((m) => m.addedNodes.length)) schedule(); })
    .observe(document.documentElement, { childList: true, subtree: true });
phone.addEventListener?.('change', schedule);
document.addEventListener('livewire:navigated', schedule);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', schedule); else schedule();
