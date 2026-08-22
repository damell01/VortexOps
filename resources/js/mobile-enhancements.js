/**
 * Mobile UX enhancements.
 *
 * IMPORTANT: Livewire can morph/navigate the DOM many times without a full page
 * reload. Every binding here is therefore idempotent. The old implementation
 * re-added document and element listeners after each navigation, eventually
 * causing mobile taps (including the Filament sidebar and page tabs) to feel
 * frozen or fire many handlers at once.
 */
class MobileEnhancements {
    constructor() {
        this.documentBound = false;
        this.init();
    }

    init() {
        this.bindDocumentHandlersOnce();
        this.initInfiniteScroll();
        this.initFloatingActionButton();
        this.initCollapsibleSections();
        this.initTableRowSelection();
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
            if (! loadMoreUrl) return;

            const loader = container.querySelector('.infinite-scroll-loader');
            if (! loader) return;

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
                const scope = fab.closest('[data-floating-action-scope]') || document;
                scope.querySelector('.floating-action-menu')?.classList.toggle('open');
                scope.querySelector('.floating-action-overlay')?.classList.toggle('open');
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
        const selectedCount = document.querySelectorAll('table input[type="checkbox"]:checked').length;
        const actionBar = document.querySelector('.bottom-action-bar');
        if (! actionBar) return;

        actionBar.classList.toggle('active', selectedCount > 0);
        const info = actionBar.querySelector('.bottom-action-bar-info');
        if (info) info.innerHTML = `<strong>${selectedCount}</strong> item(s) selected`;
    }

    static showSkeleton(container, skeletonClass = 'skeleton-card') {
        const skeleton = document.createElement('div');
        skeleton.className = `${skeletonClass} skeleton`;
        container.appendChild(skeleton);
        return skeleton;
    }

    static hideSkeleton(skeleton) {
        skeleton?.remove();
    }
}

const boot = () => {
    if (! window.MobileEnhancements) {
        window.MobileEnhancements = new MobileEnhancements();
    } else {
        window.MobileEnhancements.init();
    }
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
else boot();

document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:initialized', boot, { once: true });

export default MobileEnhancements;
