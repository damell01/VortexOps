import '../css/guided-help.css';

let active = null;
const visibleTarget = (selector) => {
    if (!selector) return null;
    try { return [...document.querySelectorAll(selector)].find(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden') || null; }
    catch { return null; }
};

export function startTour(tour) {
    closeTour();
    if (!tour?.steps?.length) return;
    const previousFocus = document.activeElement;
    const dialog = document.createElement('dialog');
    dialog.className = 'vx-guide-dialog';
    dialog.setAttribute('aria-labelledby', 'vx-guide-title');
    dialog.setAttribute('aria-describedby', 'vx-guide-body');
    dialog.innerHTML = '<div class="vx-guide-highlight" hidden aria-hidden="true"></div><div class="vx-guide-top"><span data-progress aria-live="polite"></span><button type="button" data-close aria-label="Close walkthrough">×</button></div><h2 id="vx-guide-title"></h2><p id="vx-guide-body"></p><p class="vx-guide-hint" hidden>This control appears when you reach that step or have matching records.</p><div class="vx-guide-footer"><button type="button" data-back>Back</button><button type="button" data-next>Next</button></div>';
    let index = 0;
    let target = null;
    const highlight = dialog.querySelector('.vx-guide-highlight');
    const position = () => {
        if (!target?.isConnected || !target.getClientRects().length) { highlight.hidden = true; return; }
        const r = target.getBoundingClientRect();
        highlight.hidden = false;
        Object.assign(highlight.style, { left: `${Math.max(4, r.left - 4)}px`, top: `${Math.max(4, r.top - 4)}px`, width: `${Math.min(innerWidth - 8, r.width + 8)}px`, height: `${Math.min(innerHeight - 8, r.height + 8)}px` });
    };
    const render = () => {
        const step = tour.steps[index];
        dialog.querySelector('#vx-guide-title').textContent = step.title;
        dialog.querySelector('#vx-guide-body').textContent = step.body;
        dialog.querySelector('[data-progress]').textContent = `${tour.title} · ${index + 1} of ${tour.steps.length}`;
        dialog.querySelector('[data-back]').disabled = index === 0;
        dialog.querySelector('[data-next]').textContent = index === tour.steps.length - 1 ? 'Finish' : 'Next';
        target = visibleTarget(step.target);
        dialog.querySelector('.vx-guide-hint').hidden = !step.target || !!target;
        if (target) target.scrollIntoView({ block: 'center', behavior: 'instant' });
        position();
    };
    const close = () => {
        window.removeEventListener('resize', position);
        window.removeEventListener('scroll', position, true);
        dialog.remove();
        if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
        if (active?.dialog === dialog) active = null;
    };
    dialog.querySelector('[data-close]').addEventListener('click', close);
    dialog.querySelector('[data-back]').addEventListener('click', () => { if (index > 0) { index--; render(); } });
    dialog.querySelector('[data-next]').addEventListener('click', () => { if (index + 1 >= tour.steps.length) close(); else { index++; render(); } });
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);
    document.body.appendChild(dialog);
    active = { dialog, close };
    dialog.showModal();
    render();
    dialog.querySelector('[data-next]').focus();
}

export function closeTour() { active?.close(); }

function initializeHelp() {
    closeTour();
    const shell = document.getElementById('vx-guide-shell');
    if (!shell) return;
    let tours = {};
    try { tours = JSON.parse(document.getElementById('vx-guide-data')?.textContent || '{}'); } catch { return; }
    const matchesPage = tour => location.pathname.replace(/\/$/, '') === tour.path.replace(/\/$/, '');
    const current = Object.values(tours).find(matchesPage);
    const button = shell.querySelector('[data-vx-guide-start]');
    if (button) {
        button.hidden = !current;
        button.onclick = () => startTour(current);
    }
    const url = new URL(location.href);
    const requested = url.searchParams.get('vx-tour');
    if (requested) {
        url.searchParams.delete('vx-tour');
        history.replaceState(history.state, '', url);
        if (tours[requested] && matchesPage(tours[requested])) startTour(tours[requested]);
    }
}
document.addEventListener('livewire:navigating', closeTour);
document.addEventListener('livewire:navigated', initializeHelp);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeHelp, { once: true });
else initializeHelp();
