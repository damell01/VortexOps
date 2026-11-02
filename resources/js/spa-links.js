// In-app navigation for plain links.
//
// The panel runs in SPA mode, but Livewire only swaps pages in place for
// links marked wire:navigate. Filament marks its own links; the custom
// Blade pages (cards, Back buttons, tabs, dashboard tiles) mostly don't, so
// each of those clicks reloaded the whole app — every script, style and the
// menu — instead of just the page. This sends any ordinary click on a link
// to another admin page through Livewire's navigation, the same path the
// menus use.
//
// Left alone, so they behave exactly as before:
//  - links already handled (wire:navigate), new-tab, download, or opted out
//    with data-no-spa
//  - clicks another handler already took (defaultPrevented), modified clicks
//    (ctrl/cmd/shift/alt, middle button)
//  - anything off /admin, and the /admin routes that return files or JSON
//    rather than a panel page (exports, PDFs, statements, push, API, guides)

const NON_PAGE_PREFIXES = [
    '/admin/export/',
    '/admin/financial-reports/',
    '/admin/push/',
    '/admin/whatnot-api/',
    '/admin/guides/',
    '/admin/inventory-guide/images/',
    '/admin/logout',
];

function navigableUrl(link) {
    if (link.hasAttribute('download') || link.hasAttribute('data-no-spa')) return null;
    if ([...link.attributes].some((a) => a.name.startsWith('wire:navigate') || a.name.startsWith('x-navigate'))) return null;
    const target = link.getAttribute('target');
    if (target && target !== '_self') return null;

    const raw = link.getAttribute('href');
    if (!raw || raw.startsWith('#') || raw.startsWith('javascript:')) return null;

    let url;
    try { url = new URL(raw, window.location.href); } catch { return null; }
    if (url.origin !== window.location.origin) return null;
    if (url.pathname !== '/admin' && !url.pathname.startsWith('/admin/')) return null;
    if (NON_PAGE_PREFIXES.some((p) => url.pathname.startsWith(p))) return null;
    // A file (…/report.pdf, …/sheet.xlsx), not a page.
    if (/\.[a-z0-9]{2,5}$/i.test(url.pathname)) return null;
    // Same page, different #section: let the browser scroll.
    if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) return null;

    return url.href;
}

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (!window.Livewire?.navigate) return;

    const link = event.target.closest?.('a[href]');
    if (!link) return;

    const href = navigableUrl(link);
    if (!href) return;

    event.preventDefault();
    window.Livewire.navigate(href);
});
