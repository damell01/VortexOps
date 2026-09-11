// Desktop sidebar behavior for VortexOps.
//
// Filament's normal collapsed state keeps a narrow icon rail visible. In this
// app the rail does not add much value and still steals horizontal space from
// dashboards and tables. When the desktop sidebar is collapsed, hide it
// completely and let the application content use the full viewport width.
// Mobile keeps Filament's native drawer behavior.

const STYLE_ID = 'vx-full-collapse-sidebar-styles';
const ROOT_CLASS = 'vx-sidebar-fully-collapsed';
const DESKTOP_QUERY = '(min-width: 1024px)';

function installStyles() {
    if (document.getElementById(STYLE_ID)) return;

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
        @media (min-width: 1024px) {
            html.${ROOT_CLASS} {
                --sidebar-width: 0rem !important;
                --collapsed-sidebar-width: 0rem !important;
            }

            html.${ROOT_CLASS} .fi-sidebar.fi-main-sidebar,
            html.${ROOT_CLASS} aside.fi-sidebar {
                width: 0 !important;
                min-width: 0 !important;
                max-width: 0 !important;
                border-right-width: 0 !important;
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                visibility: hidden !important;
                pointer-events: none !important;
                overflow: hidden !important;
            }

            html.${ROOT_CLASS} .fi-main-ctn,
            html.${ROOT_CLASS} .fi-main,
            html.${ROOT_CLASS} .fi-page,
            html.${ROOT_CLASS} .fi-page-main {
                margin-inline-start: 0 !important;
                padding-inline-start: 0 !important;
                max-width: none !important;
            }

            html.${ROOT_CLASS} .fi-main-ctn {
                width: 100% !important;
            }

            html.${ROOT_CLASS} nav.fi-topbar,
            html.${ROOT_CLASS} .fi-topbar {
                inset-inline-start: 0 !important;
                width: 100% !important;
                max-width: none !important;
            }

            /* Filament desktop-collapsible mode renders a second pair of
               collapse controls in addition to the normal drawer controls.
               VortexOps only needs one hamburger, so never show the collapse
               pair on desktop. */
            .fi-topbar-open-collapse-sidebar-btn,
            .fi-topbar-close-collapse-sidebar-btn {
                display: none !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }

            /* Exactly one normal desktop sidebar control is visible at a time.
               Collapsed: Open. Expanded: Close. */
            html.${ROOT_CLASS} .fi-topbar-open-sidebar-btn {
                display: inline-flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
            }

            html.${ROOT_CLASS} .fi-topbar-close-sidebar-btn {
                display: none !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }

            html:not(.${ROOT_CLASS}) .fi-topbar-open-sidebar-btn {
                display: none !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }

            html:not(.${ROOT_CLASS}) .fi-topbar-close-sidebar-btn {
                display: inline-flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
            }
        }
    `;

    document.head.appendChild(style);
}

function sidebarIsOpen(sidebar) {
    // Filament v5 mirrors Alpine's $store.sidebar.isOpen with this class.
    return sidebar.classList.contains('fi-sidebar-open');
}

function syncSidebarState() {
    const desktop = window.matchMedia(DESKTOP_QUERY).matches;
    const sidebar = document.querySelector('.fi-sidebar.fi-main-sidebar, aside.fi-sidebar');

    if (!desktop || !sidebar) {
        document.documentElement.classList.remove(ROOT_CLASS);
        return;
    }

    document.documentElement.classList.toggle(ROOT_CLASS, !sidebarIsOpen(sidebar));
}

function observeSidebar() {
    installStyles();
    syncSidebarState();

    const attach = () => {
        const sidebar = document.querySelector('.fi-sidebar.fi-main-sidebar, aside.fi-sidebar');
        if (!sidebar || sidebar.dataset.vxFullCollapseObserved === '1') return;

        sidebar.dataset.vxFullCollapseObserved = '1';
        new MutationObserver(syncSidebarState).observe(sidebar, {
            attributes: true,
            attributeFilter: ['class', 'style'],
        });
    };

    attach();

    // SPA / Livewire navigation can replace shell nodes. Watch for a replacement
    // and reattach without stacking observers on the same sidebar element.
    const shellObserver = new MutationObserver(() => {
        attach();
        syncSidebarState();
    });
    shellObserver.observe(document.body, { childList: true, subtree: true });

    const media = window.matchMedia(DESKTOP_QUERY);
    media.addEventListener?.('change', syncSidebarState);
    window.addEventListener('resize', syncSidebarState, { passive: true });

    document.addEventListener('livewire:navigated', () => {
        requestAnimationFrame(() => {
            attach();
            syncSidebarState();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeSidebar, { once: true });
} else {
    observeSidebar();
}
