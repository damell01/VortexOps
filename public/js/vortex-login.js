(() => {
    if (window.vxLoginCaptureInstalled) return;
    window.vxLoginCaptureInstalled = true;
    const KEY = 'vx-workspace-entry';
    const root = () => document.querySelector('.vx-login-experience');
    const overlay = () => document.querySelector('[data-vx-workspace-entry]');
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let active = false, timer, slowTimer, startedAt = 0, memoryEntry = null;
    function remember(value) {
        memoryEntry = value;
        try { value ? sessionStorage.setItem(KEY, JSON.stringify(value)) : sessionStorage.removeItem(KEY); } catch (_) {}
    }
    function pending() {
        try { const value = JSON.parse(sessionStorage.getItem(KEY) || 'null') || memoryEntry; return value && Date.now() - value.at < 30000 ? value : null; } catch (_) { return memoryEntry && Date.now() - memoryEntry.at < 30000 ? memoryEntry : null; }
    }
    function announce(text) {
        const node = root()?.querySelector('[role=status]');
        if (node) node.textContent = text;
    }
    function reset() {
        clearTimeout(timer); clearTimeout(slowTimer); active = false;
        document.body.classList.remove('vx-login-catching', 'vx-login-caught', 'vx-login-entering');
    }
    function dismiss() {
        const el = overlay();
        if (el) { el.hidden = true; el.classList.remove('is-ready', 'is-leaving'); }
        document.documentElement.classList.remove('vx-workspace-pending');
        remember(null); reset();
    }
    function showEntry(destination) {
        const el = overlay();
        if (!el) return;
        el.hidden = false;
        el.classList.remove('is-ready', 'is-leaving');
        const link = el.querySelector('[data-vx-entry-continue]');
        if (link) { link.href = destination; link.hidden = true; }
        slowTimer = setTimeout(() => {
            if (link) link.hidden = false;
            const caption = el.querySelector('.vx-entry-caption');
            if (caption) caption.textContent = 'Still loading. You can open your Dashboard directly below.';
        }, 8000);
    }
    function reveal() {
        const entry = pending();
        if (!entry || root()) return;
        const el = overlay();
        if (!el) { remember(null); return; }
        clearTimeout(slowTimer);
        el.hidden = false;
        const label = el.querySelector('[data-vx-entry-status]');
        if (label) label.textContent = 'Welcome to your Dashboard';
        // Two frames let the new page paint behind the ball before it opens.
        requestAnimationFrame(() => requestAnimationFrame(() => {
            el.classList.add('is-ready');
            timer = setTimeout(() => {
                el.classList.add('is-leaving');
                timer = setTimeout(dismiss, reduced() ? 0 : 350);
            }, reduced() ? 0 : 550);
        }));
    }
    function enter(destination) {
        document.body.classList.add('vx-login-entering');
        remember({ at: Date.now(), destination });
        showEntry(destination);
        // Keep the existing scene on screen while Livewire fetches the real page.
        // Full navigation is a fallback; the pending marker carries the reveal across it.
        try {
            if (typeof window.Livewire?.navigate === 'function') {
                const navigation = window.Livewire.navigate(destination);
                navigation?.catch?.(() => window.location.assign(destination));
            } else window.location.assign(destination);
        } catch (_) { window.location.assign(destination); }
    }
    function install() {
        if (!window.Livewire || window.vxLoginCaptureHookInstalled) return;
        window.vxLoginCaptureHookInstalled = true;
        window.Livewire.hook('request', ({ payload, succeed, fail }) => {
            if (!root()) return;
            let data;
            try { data = typeof payload === 'string' ? JSON.parse(payload) : payload; } catch (_) { return; }
            if (!data?.components?.some(c => c.calls?.some(call => call.method === 'authenticate'))) return;
            reset(); active = true; startedAt = Date.now();
            document.body.classList.add('vx-login-catching');
            announce('Signing in…');
            timer = setTimeout(() => { reset(); announce('Sign-in is taking longer than usual.'); }, 15000);
            fail(() => { reset(); announce('Sign-in could not be completed. Please try again.'); });
            succeed(({ json }) => {
                if (!active || !root()) return;
                const effects = json?.components?.map(c => c.effects).find(e => e?.redirect);
                if (!effects) { reset(); announce('Please check the sign-in form.'); return; }
                let destination;
                try { destination = new URL(effects.redirect, window.location.href); } catch (_) { reset(); return; }
                if (destination.origin !== window.location.origin || reduced()) { reset(); return; }
                delete effects.redirect; delete effects.redirectUsingNavigate;
                clearTimeout(timer);
                timer = setTimeout(() => {
                    document.body.classList.remove('vx-login-catching');
                    document.body.classList.add('vx-login-caught');
                    announce('Signed in. Open your Dashboard.');
                    timer = setTimeout(() => enter(destination.href), 650);
                }, Math.max(0, 650 - (Date.now() - startedAt)));
            });
        });
    }
    document.addEventListener('livewire:init', install);
    function boot() { install(); if (root()) { if (pending()) dismiss(); } else reveal(); }
    document.addEventListener('livewire:navigated', boot);
    document.addEventListener('DOMContentLoaded', boot);
    window.addEventListener('pageshow', event => { if (event.persisted) dismiss(); });
    install();
    if (document.readyState !== 'loading') boot();
})();
