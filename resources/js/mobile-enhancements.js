/**
 * Mobile UX enhancements.
 * All bindings are idempotent because Livewire can morph/navigate repeatedly
 * without a full browser reload.
 */
class MobileEnhancements {
    constructor() {
        this.documentBound = false;
        this.sidebarTouchBound = false;
        this.touchStartX = 0;
        this.installSidebarGestureGuard();
        this.init();
    }

    init() {
        this.installSidebarGestureGuard();
        this.bindDocumentHandlersOnce();
        this.initInfiniteScroll();
        this.initFloatingActionButton();
        this.initCollapsibleSections();
        this.initTableRowSelection();
        this.bindSidebarLinks();
    }

    /**
     * AdminPanelProvider historically re-called a global
     * initMobileSidebarGestures() after every Livewire morph. Replace it with
     * this safe implementation so the provider's existing hook becomes harmless
     * rather than stacking document touch handlers forever.
     */
    installSidebarGestureGuard() {
        const instance = this;
        window.initMobileSidebarGestures = function () {
            instance.bindSidebarGesturesOnce();
            instance.bindSidebarLinks();
        };
        this.bindSidebarGesturesOnce();
    }

    bindSidebarGesturesOnce() {
        if (this.sidebarTouchBound) return;
        this.sidebarTouchBound = true;

        document.addEventListener('touchstart', (e) => {
            const touch = e.changedTouches?.[0];
            if (touch) this.touchStartX = touch.screenX;
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            const touch = e.changedTouches?.[0];
            if (! touch) return;

            const diff = this.touchStartX - touch.screenX;
            const threshold = 50;

            if (this.touchStartX < 50 && diff < -threshold) {
                document.querySelector('.fi-topbar-open-sidebar-btn')?.click();
            } else if (diff > threshold) {
                const close = document.querySelector('.fi-topbar-close-sidebar-btn');
                if (close instanceof HTMLElement && close.offsetParent !== null) close.click();
            }
        }, { passive: true });
    }

    bindSidebarLinks() {
        document.querySelectorAll('.fi-sidebar a[href]').forEach((item) => {
            if (item.dataset.vxSidebarCloseBound === '1') return;
            item.dataset.vxSidebarCloseBound = '1';
            item.addEventListener('click', () => {
                const close = document.querySelector('.fi-topbar-close-sidebar-btn');
                if (close instanceof HTMLElement && close.offsetParent !== null) {
                    setTimeout(() => close.click(), 50);
                }
            });
        });
    }

    bindDocumentHandlersOnce() {
        if (this.documentBound) return;
        this.documentBound = true;

        document.addEventListener('change', (e) => {
            if (e.target instanceof HTMLInputElement && e.target.type === 'checkbox' && e.target.closest('table')) {
                this.updateBottomActionBar();
            }
        });

        document.addEventListener('click', (e) => {
            const target = e.target instanceof Element ? e.target : null;
            if (target?.closest('[data-selectable-row]')) {
                requestAnimationFrame(() => this.updateBottomActionBar());
            }
        });
    }

    initInfiniteScroll() {
        document.querySelectorAll('.infinite-scroll-container').forEach((container) => {
            if (container.dataset.vxInfiniteBound === '1') return;
            const loadMoreUrl = container.dataset.loadMore;
            const loader = container.querySelector('.infinite-scroll-loader');
            if (! loadMoreUrl || ! loader) return;

            container.dataset.vxInfiniteBound = '1';
            const pageParam = container.dataset.pageParam || 'page';
            let currentPage = 1;
            let isLoading = false;
            let hasMore = true;

            const observer = new IntersectionObserver(async (entries) => {
                if (! entries.some((entry) => entry.isIntersecting) || ! hasMore || isLoading) return;
                isLoading = true;
                const separator = loadMoreUrl.includes('?') ? '&' : '?';

                try {
                    const response = await fetch(`${loadMoreUrl}${separator}${pageParam}=${currentPage + 1}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    });
                    const data = await response.json();
                    if (Array.isArray(data.items) && data.items.length) {
                        loader.insertAdjacentHTML('beforebegin', data.items.join(''));
                        currentPage++;
                        this.initTableRowSelection();
                    } else {
                        hasMore = false;
                        loader.innerHTML = '<div class="infinite-scroll-end">No more items to load</div>';
                        observer.disconnect();
                    }
                } catch (error) {
                    console.warn('[mobile] infinite scroll failed', error);
                } finally {
                    isLoading = false;
                }
            }, { rootMargin: '200px', threshold: 0.1 });

            observer.observe(loader);
        });
    }

    initFloatingActionButton() {
        document.querySelectorAll('.floating-action-button').forEach((fab) => {
            if (fab.dataset.vxFabBound === '1') return;
            fab.dataset.vxFabBound = '1';
            fab.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                document.querySelector('.floating-action-menu')?.classList.toggle('open');
                document.querySelector('.floating-action-overlay')?.classList.toggle('open');
            });
        });

        document.querySelectorAll('.floating-action-overlay').forEach((overlay) => {
            if (overlay.dataset.vxFabOverlayBound === '1') return;
            overlay.dataset.vxFabOverlayBound = '1';
            overlay.addEventListener('click', () => {
                overlay.classList.remove('open');
                document.querySelectorAll('.floating-action-menu.open').forEach((menu) => menu.classList.remove('open'));
            });
        });
    }

    initCollapsibleSections() {
        document.querySelectorAll('.mobile-collapsible').forEach((section) => {
            const header = section.querySelector('.mobile-collapsible-header');
            if (! header || header.dataset.vxCollapsibleBound === '1') return;
            header.dataset.vxCollapsibleBound = '1';

            const toggle = (e) => {
                e?.preventDefault();
                const open = section.classList.toggle('open');
                header.setAttribute('aria-expanded', String(open));
            };
            header.addEventListener('click', toggle);
            header.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') toggle(e);
            });
        });
    }

    initTableRowSelection() {
        document.querySelectorAll('table[data-selectable] tbody tr').forEach((row) => {
            if (row.dataset.vxSelectableBound === '1') return;
            row.dataset.vxSelectableBound = '1';

            row.addEventListener('click', (e) => {
                const target = e.target instanceof Element ? e.target : null;
                if (target?.closest('a,button,input,select,textarea,label')) return;
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (! checkbox) return;
                checkbox.checked = ! checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });

            const checkbox = row.querySelector('input[type="checkbox"]');
            if (checkbox && checkbox.dataset.vxRowCheckboxBound !== '1') {
                checkbox.dataset.vxRowCheckboxBound = '1';
                const sync = () => row.classList.toggle('table-row-selected', checkbox.checked);
                checkbox.addEventListener('change', sync);
                sync();
            }
        });
    }

    updateBottomActionBar() {
        const count = document.querySelectorAll('table input[type="checkbox"]:checked').length;
        const bar = document.querySelector('.bottom-action-bar');
        if (! bar) return;
        bar.classList.toggle('active', count > 0);
        const info = bar.querySelector('.bottom-action-bar-info');
        if (info) info.innerHTML = `<strong>${count}</strong> item(s) selected`;
    }
}

const boot = () => {
    if (! window.MobileEnhancements) window.MobileEnhancements = new MobileEnhancements();
    else window.MobileEnhancements.init();
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
else boot();

document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:initialized', boot, { once: true });

export default MobileEnhancements;
