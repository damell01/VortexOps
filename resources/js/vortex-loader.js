// The VortexOps pack opening: the pack's top tears off, a Charizard slides
// out, the pack falls away and a graded case closes round the card. Each
// piece is its own image (public/img/loader) moved by CSS, so it is one
// continuous animation rather than cross-faded pictures. Pages usually load faster than the animation, so it
// plays in full once per browser session when the app is opened, and covers
// any page change slow enough to notice (over ~0.3s). Tap, Esc or Skip ends it.
import '../css/vortex-loader.css';

const STAGES = [
    { key: 'pack', text: 'Ripping pack…', at: 0 },
    { key: 'hit', text: 'Found a hit!', at: 1300 },
    { key: 'slab', text: 'Almost ready…', at: 2350 },
];
const FULL = 3400;
const SLOW = 300;
const calm = window.matchMedia('(prefers-reduced-motion: reduce)');

let el = null;
let timers = [];
let armed = null;
let open = false;
let intro = false; // the once-a-session play runs to the end; page events don't cut it short

function build() {
    document.getElementById('vx-nav-loader')?.remove();
    el = document.createElement('div');
    el.id = 'vx-nav-loader';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.innerHTML = `
        <div class="vxl-stage">
            <div class="vxl-logo" aria-hidden="true">
                <svg viewBox="0 0 40 40"><path d="M20 3 37 20 20 37 3 20Z" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linejoin="round"/></svg>
                <span>VortexOps</span>
            </div>
            <div class="vxl-art" aria-hidden="true">
                <i class="vxl-floor"></i>
                <i class="vxl-burst"></i>
                <div class="vxl-card"><img src="/img/loader/card.webp" alt="" decoding="async"></div>
                <img class="vxl-pack" src="/img/loader/pack-body.webp" alt="" decoding="async">
                <img class="vxl-strip" src="/img/loader/pack-strip.webp" alt="" decoding="async">
                <img class="vxl-slab" src="/img/loader/slab-case.webp" alt="" decoding="async">
            </div>
            <p class="vxl-status">${STAGES[0].text}</p>
            <div class="vxl-bar"><i></i></div>
            <p class="vxl-sub">Loading your workspace</p>
            <button type="button" class="vxl-skip">Skip</button>
        </div>`;
    document.body.appendChild(el);
    el.addEventListener('click', hide);
}

function stage(i) {
    el.dataset.stage = STAGES[i].key;
    const status = el.querySelector('.vxl-status');
    status.textContent = STAGES[i].text;
    status.classList.remove('is-swap');
    void status.offsetWidth; // let the text fade in again
    status.classList.add('is-swap');
}

function clear() {
    timers.forEach(clearTimeout);
    timers = [];
}

/** Show the loader and run the sequence; hide after `limit` ms if given. */
function play(limit = 0, isIntro = false) {
    if (!el) return;
    clear();
    open = true;
    intro = isIntro;
    el.classList.remove('is-out', 'is-running');
    void el.offsetWidth; // restart the CSS animations
    el.classList.add('vx-show', 'is-running');
    if (calm.matches) {
        stage(STAGES.length - 1);
    } else {
        STAGES.forEach((s, i) => timers.push(setTimeout(() => stage(i), s.at)));
    }
    if (limit) timers.push(setTimeout(hide, limit));
}

function hide() {
    clearTimeout(armed);
    armed = null;
    if (!open || !el) return;
    clear();
    open = false;
    intro = false;
    el.classList.add('is-out');
    setTimeout(() => {
        if (!open) el.classList.remove('vx-show', 'is-out', 'is-running');
    }, 280);
}

/** A page change finished: end its loader, but let the opening play run. */
function navDone() {
    clearTimeout(armed);
    armed = null;
    if (!intro) hide();
}

/** A page change started: show the loader only if it takes long enough to notice. */
function navStart(event = null, limit = 12000) {
    clearTimeout(armed);
    armed = setTimeout(() => {
        armed = null;
        if (!event?.defaultPrevented && !intro) play(limit);
    }, SLOW);
}

function eligibleLink(event) {
    const link = event.target.closest?.('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return null;
    if (link.target === '_blank' || link.hasAttribute('download')) return null;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || url.href === location.href || (url.hash && url.pathname === location.pathname && url.search === location.search)) return null;
    // Downloads never leave the page, so they would leave the loader up.
    if (/(^|\/)(export|exports|download|print)(\/|$)|\.(pdf|csv|xlsx|zip)$|[?&]download=/i.test(url.pathname + url.search)) return null;
    return link;
}

function install() {
    if (!location.pathname.startsWith('/admin')) return;
    build();

    if (!window.__vortexNavLoaderBound) {
        window.__vortexNavLoaderBound = true;
        document.addEventListener('livewire:navigate', (e) => navStart(e));
        // Livewire swaps the page body on navigation; put the loader back.
        document.addEventListener('livewire:navigated', () => { if (el && !el.isConnected) document.body.appendChild(el); navDone(); });
        // A full page load from an ordinary link; give up after a few seconds
        // in case the click never left the page.
        document.addEventListener('click', (e) => { if (eligibleLink(e)) setTimeout(() => navStart(e, 4000), 0); }, { capture: true });
        // Only a real Esc press: the app sends synthetic ones to close dialogs.
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && e.isTrusted) hide(); });
        document.addEventListener('visibilitychange', () => { if (document.hidden) hide(); });
        window.addEventListener('pageshow', navDone);
    }
    window.__vortexNavLoader = { show: navStart, hide, play };

    // Opening the app: the whole pack opening, once per browser session, on
    // the first page after signing in (not the sign-in page itself).
    if (/^\/admin\/(login|password-reset|register|email-verification)/.test(location.pathname)) return;
    let played = false;
    try { played = sessionStorage.getItem('vx-pack-intro') === '1'; sessionStorage.setItem('vx-pack-intro', '1'); } catch { played = true; }
    if (!played && !calm.matches) play(FULL, true);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', install, { once: true });
else install();
