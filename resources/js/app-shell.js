// Home-screen install and automatic updates.
//
// Install: Chrome/Edge/Android fire beforeinstallprompt, which we hold and
// offer from a dismissible banner at the top of the page. iPhone Safari has no prompt, so it gets the
// two-step "Share → Add to Home Screen" hint instead. Both can be dismissed
// for 30 days and never show once the app is running installed.
//
// Updates: the app always loads the live site, so an install never goes
// stale — except a copy left open for days, which keeps running the build it
// loaded. We compare the deployed version (/app-version, which the service
// worker never caches) with the one this page started on, whenever the app
// comes back into view and every 10 minutes. When a new build is out, the
// next in-app navigation does a full load (nothing is lost), and a small bar
// offers "Reload" for anyone staying on the same screen.
import '../css/app-shell.css';

const DISMISS_KEY = 'vx-install-dismissed-until';
const store = {
    get(key) { try { return localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch { /* private mode */ } },
};

const isStandalone = () => window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const dismissed = () => Number(store.get(DISMISS_KEY) || 0) > Date.now();

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

// ── Install ─────────────────────────────────────────────────────────────
let deferredPrompt = null;

function dismissInstall(el) {
    store.set(DISMISS_KEY, String(Date.now() + 30 * 24 * 3600 * 1000));
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
    if (deferredPrompt) {
        installBanner('<strong>Install VortexOps as an app.</strong> Open it full screen from your home screen or taskbar. It updates itself — nothing to reinstall.', [
            { label: 'Install', onClick: (el) => window.vxInstallApp().then((ok) => ok && el.remove()) },
        ]);
    } else if (isIos() && /safari/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent)) {
        installBanner('<strong>Add VortexOps to your Home Screen:</strong> tap <strong>Share</strong> <span aria-hidden="true">⬆︎</span>, then <strong>Add to Home Screen</strong>. It opens full screen and updates itself.', []);
    }
}

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
    document.getElementById('vx-install-banner')?.remove();
});

/** Opens the browser's install prompt; also used by the Help & Guides button. */
window.vxInstallApp = async () => {
    if (!deferredPrompt) {
        offerInstall();
        return false;
    }
    deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice.catch(() => null);
    deferredPrompt = null;
    return choice?.outcome === 'accepted';
};
window.vxCanInstall = () => Boolean(deferredPrompt) || (isIos() && !isStandalone());

// iPhone never fires beforeinstallprompt; offer the hint shortly after load.
setTimeout(offerInstall, 4000);

// ── Updates ─────────────────────────────────────────────────────────────
const startedOn = document.querySelector('meta[name="vortex-app-version"]')?.content || null;
let updateReady = false;
let lastCheck = 0;

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

document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') checkForUpdate(); });
window.addEventListener('focus', checkForUpdate);
setInterval(checkForUpdate, 10 * 60 * 1000);
