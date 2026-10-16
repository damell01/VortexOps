(() => {
    if (window.vxDevicePushInstalled) return;
    window.vxDevicePushInstalled = true;
    async function initialize(el) {
        if (el.dataset.initialized) return;
        el.dataset.initialized = '1';
        const message = el.querySelector('[data-vx-push-message]');
        const enable = el.querySelector('[data-vx-push-enable]');
        const disable = el.querySelector('[data-vx-push-disable]');
        const test = el.querySelector('[data-vx-push-test]');
        let registration, subscription, key;
        const say = text => { message.textContent = text; };
        const state = on => { enable.hidden = on; disable.hidden = !on; test.hidden = !on; enable.disabled = false; };
        const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const installed = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        if (ios && !installed) { say('On iPhone or iPad, add Vortex Ops to your Home Screen, open it from there, then enable push here.'); return; }
        if (!window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) { say('Push isn’t supported in this browser. Use a browser that supports Web Push.'); return; }
        async function request(url, method = 'GET', data) {
            const response = await fetch(url, {method, credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content || ''},...(data ? {body:JSON.stringify(data)} : {})});
            const result = await response.json().catch(()=>({}));
            if (!response.ok) throw new Error(result.message || 'Could not update push settings. Please try again.');
            return result;
        }
        function applicationKey(value) {
            const binary = atob(value.replace(/-/g,'+').replace(/_/g,'/').padEnd(Math.ceil(value.length/4)*4,'='));
            return Uint8Array.from(binary, c=>c.charCodeAt(0));
        }
        try {
            const config = await request(el.dataset.config);
            key = config.public_key;
            if (!key) { say('Push is not configured on the server yet.'); return; }
            await navigator.serviceWorker.register('/sw.js', {scope:'/'});
            registration = await navigator.serviceWorker.ready;
            subscription = await registration.pushManager.getSubscription();
            const enabled = subscription ? (await request(el.dataset.status,'POST',{endpoint:subscription.endpoint})).enabled : false;
            state(enabled);
            say(enabled ? 'Push is enabled on this device.' : Notification.permission === 'denied' ? 'Notifications are blocked. Allow them in your browser’s site settings, then reload this page.' : 'Push is off on this device. Enable it to choose Allow in your browser.');
            if (Notification.permission === 'denied') enable.disabled = true;
        } catch (error) { say(error.message); return; }
        enable.addEventListener('click', async () => {
            enable.disabled = true;
            try {
                // Permission is requested only from this explicit button click.
                const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission();
                if (permission !== 'granted') { say('Push was not enabled. You can enable it later from this page.'); return; }
                subscription = await registration.pushManager.getSubscription() || await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:applicationKey(key)});
                await request(el.dataset.store,'POST',subscription.toJSON());
                state(true); say('Push is enabled on this device. You can send a test below.');
            } catch (error) { say(error.message); }
            finally { enable.disabled = false; }
        });
        disable.addEventListener('click', async () => {
            disable.disabled = true;
            try {
                await request(el.dataset.destroy,'DELETE',{endpoint:subscription.endpoint});
                await subscription.unsubscribe().catch(()=>{});
                subscription = null;
                state(false); say('Push is disabled on this device. Your other devices are unchanged.');
            } catch (error) { say(error.message); }
            finally { disable.disabled = false; }
        });
        test.addEventListener('click', async () => {
            test.disabled = true;
            try { await request(el.dataset.test,'POST',{endpoint:subscription.endpoint}); say('Test queued. Look for a Vortex Ops notification on this device.'); }
            catch (error) { say(error.message); }
            finally { test.disabled = false; }
        });
    }
    const boot = () => document.querySelectorAll('[data-vx-push]').forEach(initialize);
    document.addEventListener('DOMContentLoaded',boot);
    document.addEventListener('livewire:navigated',boot);
    boot();
})();
