{{-- Whole-app SPA: any plain link to another panel page loads in place (like the top menu) instead of reloading the app. --}}
<script data-navigate-once>
(() => {
    if (window.__vxSpaLinks) return;
    window.__vxSpaLinks = true;
    const deny = @json(\App\Support\SpaLinks::excluded()).map((p) => new RegExp(p));
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        const raw = a.getAttribute('href') || '';
        if (raw === '' || raw.startsWith('#') || raw.startsWith('javascript:') || raw.startsWith('mailto:') || raw.startsWith('tel:')) return;
        if ([...a.attributes].some((x) => x.name.startsWith('wire:navigate') || x.name === 'download')) return;
        if ((a.target && a.target !== '_self') || a.closest('[data-no-spa]')) return;
        const url = new URL(a.href, location.href);
        if (url.origin !== location.origin || !/^\/admin(\/|$)/.test(url.pathname)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        if (/\.(csv|pdf|json|xlsx?|zip|png|jpe?g|webp|gif|svg)$/i.test(url.pathname) || /\/(logout|export|print|download)(\/|$)/.test(url.pathname)) return;
        const path = url.pathname.replace(/^\//, '');
        if (deny.some((r) => r.test(path))) return;
        if (!window.Livewire || typeof window.Livewire.navigate !== 'function') return;
        e.preventDefault();
        window.Livewire.navigate(url.href);
    });
})();
</script>
