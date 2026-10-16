// Home-screen install and automatic updates.
//
// Install: Chrome/Edge/Android fire beforeinstallprompt, which we hold and
// offer from a banner at the top of the page and from the "Install app"
// button in the top bar. Browsers with no prompt (iPhone, Safari) get this
// device's steps in a small window instead. Closing the banner hides it for
// the rest of the browser session; it comes back each session until the app
// is installed, and never shows inside the installed app.
//
// Updates: the app always loads the live site, so an install never goes
// stale — except a copy left open for days, which keeps running the build it
// loaded. We compare the deployed version (/app-version, which the service
// worker never caches) with the one this page started on, whenever the app
// comes back into view and every 10 minutes. When a new build is out and the
// app is coming back after a minute or more away, it reloads straight onto it
// (unless something has been typed on the screen). Otherwise the next in-app
// navigation does a full load (nothing is lost), and a small bar offers
// "Reload" for anyone staying on the same screen.
import '../css/app-shell.css';

const DISMISS_KEY = 'vx-install-dismissed';
const store = {
    get(key) { try { return localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch { /* private mode */ } },
};
const session = {
    get(key) { try { return sessionStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { sessionStorage.setItem(key, value); } catch { /* private mode */ } },
};

// Any of the installed-app display modes: desktop app windows can report
// window-controls-overlay or minimal-ui rather than standalone.
const APP_MODES = '(display-mode: standalone), (display-mode: window-controls-overlay), (display-mode: minimal-ui), (display-mode: fullscreen)';
const isStandalone = () => window.matchMedia?.(APP_MODES).matches || window.navigator.standalone === true;
const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const dismissed = () => session.get(DISMISS_KEY) === '1';

function bar(id, html, actions) {
    document.getElementById(id)?.remove();
    const el = document.createElement('div');
    el.id = id;
    el.className = 'vx-shell-bar';
    el.setAttribute('role', 'status');
    el.innerHTML = `<div class="vx-shell-bar__text">${html}</div><div class="vx-shell-bar__actions"></div>`;
    const holder = el.querySelector('.vx-shell-bar__actions');
    actions.forEach(({ label, primary, onClick }) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.className = primary ? 'vx-shell-bar__btn vx-shell-bar__btn--primary' : 'vx-shell-bar__btn';
        button.addEventListener('click', () => onClick(el));
        holder.appendChild(button);
    });
    document.body.appendChild(el);
    return el;
}

// ── Large controls ──────────────────────────────────────────────────────
// Remembered per device by x-touch-mode-toggle; applied before anything paints.
if (store.get('vx-touch-mode') === '1') document.documentElement.classList.add('vx-touch');

// ── Install ─────────────────────────────────────────────────────────────
let deferredPrompt = null;

// The top-bar "Install app" button ([data-vx-install]) hides itself once the
// app is running installed.
// Re-applied after in-app navigation, which can replace <html>'s classes.
const markStandalone = () => { if (isStandalone()) document.documentElement.classList.add('vx-standalone'); };
markStandalone();
document.addEventListener('livewire:navigated', markStandalone);

function dismissInstall(el) {
    session.set(DISMISS_KEY, '1');
    el?.remove();
}

function installBanner(message, actions) {
    // A banner at the top of the page content, under the menu bar. Livewire
    // replaces <main> on in-app navigation, so it is put back after each one
    // until it is dismissed.
    // Inside the page container, so it lines up with the page header and cards.
    const main = document.querySelector('.fi-page') || document.querySelector('main.fi-main');
    if (!main || document.getElementById('vx-install-banner')) return;
    const el = document.createElement('div');
    el.id = 'vx-install-banner';
    el.className = 'vx-install-banner';
    el.setAttribute('role', 'region');
    el.setAttribute('aria-label', 'Install VortexOps');
    el.innerHTML = `<img src="/icons/icon-192.png" alt="" class="vx-install-banner__icon"><div class="vx-install-banner__text">${message}</div><div class="vx-install-banner__actions"></div><button type="button" class="vx-install-banner__close" aria-label="Dismiss">×</button>`;
    const holder = el.querySelector('.vx-install-banner__actions');
    actions.forEach(({ label, onClick }) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'vx-install-banner__btn';
        button.textContent = label;
        button.addEventListener('click', () => onClick(el));
        holder.appendChild(button);
    });
    el.querySelector('.vx-install-banner__close').addEventListener('click', () => dismissInstall(el));
    main.prepend(el);
}

function offerInstall() {
    if (isStandalone() || dismissed() || !location.pathname.startsWith('/admin') || location.pathname.startsWith('/admin/login')) return;
    // Every device gets the same banner and one button: the browser's own
    // install prompt where there is one, this device's steps where there isn't.
    installBanner('<strong>Install VortexOps as an app</strong> — full screen from your home screen or taskbar.', [
        { label: 'Install', onClick: (el) => window.vxInstallApp().then((ok) => ok && el.remove()) },
    ]);
}

// Where the browser has no install prompt to open (iPhone and iPad, Safari on
// a Mac, a prompt already used or turned down), the button shows the two or
// three taps for this exact device instead.
const SHARE_ICON = '<svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="vx-install-guide__glyph"><path d="M10 1.5a.75.75 0 0 1 .53.22l3 3a.75.75 0 0 1-1.06 1.06l-1.72-1.72v8.19a.75.75 0 0 1-1.5 0V4.06L7.53 5.78a.75.75 0 0 1-1.06-1.06l3-3A.75.75 0 0 1 10 1.5ZM5 8.5a.5.5 0 0 0-.5.5v7c0 .28.22.5.5.5h10a.5.5 0 0 0 .5-.5V9a.5.5 0 0 0-.5-.5h-1.25a.75.75 0 0 1 0-1.5H15a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h1.25a.75.75 0 0 1 0 1.5H5Z"/></svg>';

function installSteps() {
    const ua = navigator.userAgent;
    if (isIos()) {
        const where = /crios|fxios|edgios/i.test(ua) ? 'in the address bar' : 'at the bottom of the screen (top right on iPad)';
        return {
            title: 'Add VortexOps to your Home Screen',
            steps: [
                `Tap <strong>Share</strong> ${SHARE_ICON} ${where}.`,
                'Scroll down and tap <strong>Add to Home Screen</strong>.',
                'Tap <strong>Add</strong>. Open VortexOps from its new icon.',
            ],
            note: 'iPhone and iPad don’t let a website install itself, so this is the quickest way — it only takes these three taps once.',
        };
    }
    if (/android/i.test(ua)) {
        return {
            title: 'Install VortexOps on this phone',
            steps: [
                'Tap the browser menu <strong>⋮</strong> (top right).',
                'Tap <strong>Install app</strong> or <strong>Add to Home screen</strong>.',
                'Tap <strong>Install</strong>. VortexOps appears with your other apps.',
            ],
        };
    }
    if (/firefox/i.test(ua)) {
        return {
            title: 'Install VortexOps',
            steps: ['Firefox on a computer can’t install web apps. Open VortexOps in <strong>Chrome</strong>, <strong>Edge</strong> or <strong>Safari</strong> and press <strong>Install app</strong> there.'],
        };
    }
    if (/safari/i.test(ua) && !/chrome|chromium|edg\//i.test(ua)) {
        return {
            title: 'Add VortexOps to your Dock',
            steps: [
                'In the menu bar, choose <strong>File</strong> → <strong>Add to Dock…</strong>',
                'Click <strong>Add</strong>. VortexOps opens in its own window from the Dock.',
            ],
            note: 'Needs macOS Sonoma or later.',
        };
    }
    if (/edg\//i.test(ua)) {
        return {
            title: 'Install VortexOps',
            steps: [
                'Click the <strong>App available</strong> icon at the right of the address bar, or open the menu <strong>⋯</strong> → <strong>Apps</strong> → <strong>Install this site as an app</strong>.',
                'Click <strong>Install</strong>. VortexOps opens in its own window and pins to your taskbar.',
            ],
        };
    }
    return {
        title: 'Install VortexOps',
        steps: [
            'Click the <strong>install</strong> icon at the right of the address bar, or open the menu <strong>⋮</strong> → <strong>Cast, save and share</strong> → <strong>Install page as app</strong>.',
            'Click <strong>Install</strong>. VortexOps opens in its own window and pins to your taskbar or Dock.',
        ],
    };
}

function installGuide() {
    document.getElementById('vx-install-guide')?.remove();
    const { title, steps, note } = installSteps();
    const el = document.createElement('div');
    el.id = 'vx-install-guide';
    el.className = 'vx-install-guide';
    el.innerHTML = `<div class="vx-install-guide__panel" role="dialog" aria-modal="true" aria-labelledby="vx-install-guide-title">
        <button type="button" class="vx-install-guide__close" aria-label="Close">×</button>
        <img src="/icons/icon-192.png" alt="" class="vx-install-guide__icon">
        <h2 id="vx-install-guide-title" class="vx-install-guide__title">${title}</h2>
        <ol class="vx-install-guide__steps">${steps.map((step) => `<li><span>${step}</span></li>`).join('')}</ol>
        ${note ? `<p class="vx-install-guide__note">${note}</p>` : ''}
        <p class="vx-install-guide__note">It opens full screen and updates itself — there’s nothing to reinstall.</p>
        <button type="button" class="vx-install-guide__done">Got it</button>
    </div>`;
    const close = () => { el.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (event) => { if (event.key === 'Escape') close(); };
    el.addEventListener('click', (event) => { if (event.target === el || event.target.closest('.vx-install-guide__close, .vx-install-guide__done')) close(); });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(el);
    el.querySelector('.vx-install-guide__done').focus();
}

document.addEventListener('click', (event) => {
    if (!event.target.closest?.('[data-vx-install]')) return;
    event.preventDefault();
    window.vxInstallApp();
});

document.addEventListener('livewire:navigated', () => setTimeout(offerInstall, 300));

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    document.documentElement.dataset.vxInstallable = '1';
    offerInstall();
});
window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    delete document.documentElement.dataset.vxInstallable;
    document.documentElement.classList.add('vx-standalone');
    document.getElementById('vx-install-banner')?.remove();
});

/** One click: the browser's install prompt when it has one, otherwise this device's steps. */
window.vxInstallApp = async () => {
    if (!deferredPrompt) {
        installGuide();
        return false;
    }
    const prompt = deferredPrompt;
    deferredPrompt = null;
    delete document.documentElement.dataset.vxInstallable;
    prompt.prompt();
    const choice = await prompt.userChoice.catch(() => null);
    return choice?.outcome === 'accepted';
};
window.vxCanInstall = () => !isStandalone();
window.vxIsInstalled = isStandalone;

// Browsers without an install prompt (iPhone, Safari) never fire beforeinstallprompt.
setTimeout(offerInstall, 1500);

// ── Updates ─────────────────────────────────────────────────────────────
const startedOn = document.querySelector('meta[name="vortex-app-version"]')?.content || null;
let updateReady = false;
let lastCheck = 0;
let hiddenAt = 0;

// Fields the person has typed into or changed on this screen, which a reload
// would throw away. Only real input counts: Filament and Livewire set plenty
// of fields from script, and those are not unsaved work.
let edited = new Set();
['input', 'change'].forEach((type) => document.addEventListener(type, (event) => {
    if (event.isTrusted && event.target?.matches?.('input, textarea, select, [contenteditable]')) edited.add(event.target);
}, true));
document.addEventListener('submit', () => { edited = new Set(); }, true);
document.addEventListener('livewire:navigated', () => { edited = new Set(); });
const hasUnsavedInput = () => [...edited].some((field) => field.isConnected);

async function checkForUpdate() {
    if (!startedOn || updateReady || Date.now() - lastCheck < 60_000 || !navigator.onLine) return;
    lastCheck = Date.now();
    try {
        const response = await fetch('/app-version', { cache: 'no-store', headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const { version } = await response.json();
        if (version && version !== startedOn) {
            updateReady = true;
            navigator.serviceWorker?.getRegistration?.().then((reg) => reg?.update()).catch(() => {});
            // Coming back to the app after being away: just load the new
            // version, unless something has been typed that it would lose.
            const resumed = hiddenAt && Date.now() - hiddenAt > 60_000;
            if (resumed && !hasUnsavedInput()) {
                window.location.reload();
                return;
            }
            bar('vx-update-bar', '<strong>A new version of VortexOps is ready.</strong> It loads automatically on your next page; reload now to use it here.', [
                { label: 'Later', onClick: (el) => el.remove() },
                { label: 'Reload', primary: true, onClick: () => window.location.reload() },
            ]);
        }
    } catch { /* offline or deploying — try again later */ }
}

// With a new build out, in-app (SPA) navigation becomes a full page load, so
// the next screen arrives on the new version without anyone pressing Reload.
document.addEventListener('livewire:navigate', (event) => {
    if (!updateReady) return;
    const url = event.detail?.url;
    if (!url) return;
    event.preventDefault();
    window.location.href = typeof url === 'string' ? url : url.toString();
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') { hiddenAt = Date.now(); return; }
    // An update spotted before they left: load it now they're back.
    if (updateReady && hiddenAt && Date.now() - hiddenAt > 60_000 && !hasUnsavedInput()) { window.location.reload(); return; }
    checkForUpdate().finally(() => { hiddenAt = 0; });
});
window.addEventListener('focus', checkForUpdate);
setInterval(checkForUpdate, 10 * 60 * 1000);

// ── Notifications ───────────────────────────────────────────────────────
// Following a link out of the notifications window closes it, so the page
// it opened isn't left behind the window. Also on arrival, for links that
// navigate in place (wire:navigate keeps the window's state otherwise).
const closeNotifications = () => window.dispatchEvent(new CustomEvent('close-modal-quietly', { detail: { id: 'database-notifications' } }));
document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');
    if (!link || !link.closest('.fi-no-database') || link.getAttribute('href').startsWith('#')) return;
    closeNotifications();
}, true);
document.addEventListener('livewire:navigated', closeNotifications);

// ── Back to top ─────────────────────────────────────────────────────────
// A small round button once you're well down a long page.
(() => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'vx-to-top';
    button.setAttribute('aria-label', 'Back to top');
    button.innerHTML = '<svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.56L5.53 9.28a.75.75 0 0 1-1.06-1.06l5-5a.75.75 0 0 1 1.06 0l5 5a.75.75 0 1 1-1.06 1.06L10.75 5.56v10.69A.75.75 0 0 1 10 17Z" clip-rule="evenodd"/></svg>';
    button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }));
    let shown = false;
    const update = () => {
        const show = window.scrollY > window.innerHeight * 1.2;
        if (show === shown) return;
        shown = show;
        button.classList.toggle('is-shown', show);
    };
    const mount = () => { if (!button.isConnected) document.body.appendChild(button); update(); };
    window.addEventListener('scroll', update, { passive: true });
    document.addEventListener('livewire:navigated', mount);
    if (document.body) mount(); else document.addEventListener('DOMContentLoaded', mount);
})();
