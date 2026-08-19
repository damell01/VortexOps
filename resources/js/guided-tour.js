// A guided tour: a spotlight over one element at a time, with a tooltip beside it.
//
// Two rules drive most of the code here. A step whose element is not on the page
// is dropped rather than shown — a tour that points at empty space and describes
// a button that is not there is worse than no tour. And the tooltip is positioned
// from the element's real measured rectangle every time it is shown, because
// Filament tables reflow, sidebars collapse, and a coordinate captured earlier is
// a coordinate that has since moved.

const PADDING = 6;      // breathing room around the spotlit element
const GAP = 12;         // between the element and its tooltip
const MARGIN = 12;      // minimum distance from the viewport edge

let active = null;

function h(tag, props = {}, ...children) {
    const el = document.createElement(tag);
    Object.entries(props).forEach(([k, v]) => {
        if (k === 'class') el.className = v;
        else if (k === 'style') Object.assign(el.style, v);
        else if (k.startsWith('on')) el.addEventListener(k.slice(2).toLowerCase(), v);
        else el.setAttribute(k, v);
    });
    children.flat().forEach(c => el.append(c instanceof Node ? c : document.createTextNode(c)));
    return el;
}

/**
 * Steps we can actually show.
 *
 * A step with no `el` is an intro — it has nothing to point at by design and is
 * always keepable. A step with an `el` survives only if that element exists and
 * has a real size; a zero-size match is a collapsed or hidden control, which
 * spotlights an invisible rectangle.
 */
function usable(steps) {
    return steps.filter((step) => {
        if (! step.el) return true;

        let target;
        try {
            target = document.querySelector(step.el);
        } catch {
            return false;                       // malformed selector: skip, never throw
        }

        if (! target) return false;

        const r = target.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
    });
}

function scrollIntoView(target) {
    const r = target.getBoundingClientRect();
    const offscreen = r.top < MARGIN || r.bottom > window.innerHeight - MARGIN;

    if (offscreen) {
        target.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: 'center',
        });
    }
}

function placeTooltip(tip, rect) {
    const t = tip.getBoundingClientRect();

    // Prefer below the element, flip above when there is no room. Anything that
    // still will not fit is centred, which is the honest answer on a phone.
    let top = rect.bottom + GAP;
    if (top + t.height > window.innerHeight - MARGIN) {
        top = rect.top - t.height - GAP;
    }
    if (top < MARGIN) {
        top = Math.max(MARGIN, (window.innerHeight - t.height) / 2);
    }

    let left = rect.left + rect.width / 2 - t.width / 2;
    left = Math.min(Math.max(left, MARGIN), window.innerWidth - t.width - MARGIN);

    tip.style.top = `${Math.round(top)}px`;
    tip.style.left = `${Math.round(left)}px`;
}

export function startTour(tour, { onFinish } = {}) {
    stopTour();

    const steps = usable(tour.steps || []);
    if (steps.length === 0) return false;

    let index = 0;

    const spotlight = h('div', { class: 'vx-tour-spotlight', 'aria-hidden': 'true' });
    const tip = h('div', {
        class: 'vx-tour-tip',
        role: 'dialog',
        'aria-modal': 'false',
        'aria-live': 'polite',
    });
    const layer = h('div', { class: 'vx-tour-layer' }, spotlight, tip);

    const finish = (completed) => {
        stopTour();
        if (onFinish) onFinish(completed);
    };

    const onKey = (e) => {
        if (e.key === 'Escape') finish(false);
        else if (e.key === 'ArrowRight') next();
        else if (e.key === 'ArrowLeft') back();
    };

    function render() {
        const step = steps[index];
        const target = step.el ? document.querySelector(step.el) : null;

        if (target) scrollIntoView(target);

        // Measured after any scroll settles, so the spotlight lands where the
        // element actually is rather than where it was.
        requestAnimationFrame(() => {
            const rect = target
                ? target.getBoundingClientRect()
                : { top: window.innerHeight / 2, bottom: window.innerHeight / 2, left: window.innerWidth / 2, width: 0, height: 0 };

            if (target) {
                Object.assign(spotlight.style, {
                    display: 'block',
                    top: `${rect.top - PADDING}px`,
                    left: `${rect.left - PADDING}px`,
                    width: `${rect.width + PADDING * 2}px`,
                    height: `${rect.height + PADDING * 2}px`,
                });
            } else {
                spotlight.style.display = 'none';
            }

            tip.replaceChildren(
                h('p', { class: 'vx-tour-count' }, `${index + 1} of ${steps.length}`),
                h('h2', { class: 'vx-tour-title' }, step.title || tour.title),
                h('p', { class: 'vx-tour-body' }, step.body || ''),
                h('div', { class: 'vx-tour-actions' },
                    h('button', { type: 'button', class: 'vx-tour-skip', onclick: () => finish(false) },
                        index === steps.length - 1 ? 'Close' : 'Skip'),
                    h('div', { class: 'vx-tour-nav' },
                        index > 0
                            ? h('button', { type: 'button', class: 'vx-tour-back', onclick: back }, 'Back')
                            : '',
                        h('button', { type: 'button', class: 'vx-tour-next', onclick: next },
                            index === steps.length - 1 ? 'Done' : 'Next'),
                    ),
                ),
            );

            placeTooltip(tip, rect);
            tip.querySelector('.vx-tour-next')?.focus({ preventScroll: true });
        });
    }

    function next() {
        if (index < steps.length - 1) { index++; render(); }
        else finish(true);
    }

    function back() {
        if (index > 0) { index--; render(); }
    }

    const onReflow = () => render();

    document.body.append(layer);
    document.addEventListener('keydown', onKey);
    window.addEventListener('resize', onReflow);
    window.addEventListener('scroll', onReflow, { passive: true });

    active = {
        teardown() {
            document.removeEventListener('keydown', onKey);
            window.removeEventListener('resize', onReflow);
            window.removeEventListener('scroll', onReflow);
            layer.remove();
        },
    };

    render();
    return true;
}

export function stopTour() {
    if (active) {
        active.teardown();
        active = null;
    }
}

export function tourIsRunning() {
    return active !== null;
}
