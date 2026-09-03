{{-- The dashboard no longer needs the old Receiving Queue callout. Receiving is handled from the dedicated pallet/receiving workspace. --}}
<script>
(() => {
    const removeReceivingQueue = () => {
        const path = window.location.pathname.toLowerCase();
        const isDashboard = path === '/admin' || path === '/admin/' || path.includes('/admin/dashboard');
        if (!isDashboard) return;

        const candidates = [...document.querySelectorAll('section, article, .fi-section, .fi-wi-widget, [class*="rounded"]')];

        for (const el of candidates) {
            const text = (el.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            if (!text.includes('receiving queue')) continue;
            if (!text.includes('nothing waiting to be unloaded') && !text.includes('mark it when the pallet')) continue;

            el.remove();
            break;
        }
    };

    let queued = false;
    const refresh = () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            removeReceivingQueue();
        });
    };

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('livewire:navigated', refresh);
    document.addEventListener('livewire:initialized', refresh);
    new MutationObserver(refresh).observe(document.documentElement, { childList: true, subtree: true });
    refresh();
})();
</script>
