(() => {
    if (window.vxLoginCaptureInstalled) return;
    window.vxLoginCaptureInstalled = true;
    const root = () => document.querySelector('.vx-login-experience');
    let active = false;
    let timer;
    const announce = (text) => { const el = root()?.querySelector('[role=status]'); if (el) el.textContent = text; };
    const reset = () => {
        clearTimeout(timer);
        active = false;
        document.body.classList.remove('vx-login-catching', 'vx-login-caught', 'vx-login-entering');
    };
    function install() {
        if (!window.Livewire || window.vxLoginCaptureHookInstalled) return;
        window.vxLoginCaptureHookInstalled = true;
        window.Livewire.hook('request', ({ payload, succeed, fail }) => {
            if (!root()) return;
            let data;
            try { data = typeof payload === 'string' ? JSON.parse(payload) : payload; } catch { return; }
            const login = data?.components?.some(c => c.calls?.some(call => call.method === 'authenticate'));
            if (!login) return;
            reset(); active = true;
            document.body.classList.add('vx-login-catching');
            announce('Signing in…');
            // Restore the buddy after a stalled request; never claim authentication succeeded.
            timer = setTimeout(reset, 15000);
            fail(() => { reset(); announce('Sign-in could not be completed. Please try again.'); });
            succeed(({ json }) => {
                if (!active || !root()) return;
                const effects = json?.components?.map(c => c.effects).find(e => e?.redirect);
                if (!effects) { reset(); announce('Please check the sign-in form.'); return; }
                const destination = new URL(effects.redirect, window.location.href);
                // Leave unusual redirects and reduced-motion navigation entirely to Livewire.
                if (destination.origin !== location.origin || matchMedia('(prefers-reduced-motion: reduce)').matches) { reset(); return; }
                delete effects.redirect;
                delete effects.redirectUsingNavigate;
                clearTimeout(timer);
                document.body.classList.remove('vx-login-catching');
                document.body.classList.add('vx-login-caught');
                announce('Signed in. Opening your workspace.');
                timer = setTimeout(() => {
                    document.body.classList.add('vx-login-entering');
                    timer = setTimeout(() => location.assign(destination.href), 350);
                }, 650);
            });
        });
    }
    document.addEventListener('livewire:init', install);
    document.addEventListener('livewire:navigating', reset);
    window.addEventListener('pageshow', reset);
    install();
})();
