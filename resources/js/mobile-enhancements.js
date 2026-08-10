/**
 * Mobile UX Enhancements
 * Handles: Infinite Scroll, Bottom Action Bar, Floating Action Button, etc.
 */

class MobileEnhancements {
    constructor() {
        this.init();
    }

    init() {
        this.initInfiniteScroll();
        this.initBottomActionBar();
        this.initFloatingActionButton();
        this.initCollapsibleSections();
        this.initTableRowSelection();
    }

    /**
     * Infinite Scroll - Load more items as user scrolls to bottom
     */
    initInfiniteScroll() {
        const containers = document.querySelectorAll('.infinite-scroll-container');

        containers.forEach(container => {
            const loadMoreUrl = container.dataset.loadMore;
            const pageParam = container.dataset.pageParam || 'page';

            if (!loadMoreUrl) return;

            let isLoading = false;
            let currentPage = 1;
            let hasMore = true;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && hasMore && !isLoading) {
                        this.loadMoreItems(container, loadMoreUrl, pageParam, currentPage, (success) => {
                            if (success) {
                                currentPage++;
                            }
                            isLoading = false;
                        });
                        isLoading = true;
                    }
                });
            }, {
                root: null,
                rootMargin: '200px',
                threshold: 0.1
            });

            // Observe the loader element
            const loader = container.querySelector('.infinite-scroll-loader');
            if (loader) {
                observer.observe(loader);
            }
        });
    }

    /**
     * Load more items via AJAX
     */
    loadMoreItems(container, url, pageParam, page, callback) {
        const separator = url.includes('?') ? '&' : '?';
        const fetchUrl = `${url}${separator}${pageParam}=${page + 1}`;

        fetch(fetchUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.items && data.items.length > 0) {
                // Insert new items before loader
                const loader = container.querySelector('.infinite-scroll-loader');
                const itemsHtml = data.items.join('');
                loader.insertAdjacentHTML('beforebegin', itemsHtml);
                callback(true);
            } else {
                // No more items
                this.showScrollEnd(container);
                callback(false);
            }
        })
        .catch(error => {
            console.error('Infinite scroll error:', error);
            this.showScrollError(container);
            callback(false);
        });
    }

    /**
     * Show end-of-list message
     */
    showScrollEnd(container) {
        const loader = container.querySelector('.infinite-scroll-loader');
        if (loader) {
            loader.innerHTML = '<div class="infinite-scroll-end">No more items to load</div>';
        }
    }

    /**
     * Show error message with retry
     */
    showScrollError(container) {
        const loader = container.querySelector('.infinite-scroll-loader');
        if (loader) {
            loader.innerHTML = `
                <div class="infinite-scroll-error">
                    Failed to load more items
                    <button class="infinite-scroll-retry" onclick="location.reload()">Retry</button>
                </div>
            `;
        }
    }

    /**
     * Bottom Action Bar - Show when table rows are selected
     */
    initBottomActionBar() {
        // Listen for table row selection
        document.addEventListener('change', (e) => {
            if (e.target.type === 'checkbox' && e.target.closest('table')) {
                this.updateBottomActionBar();
            }
        });

        // Also listen for row click selection if using that method
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-selectable-row]')) {
                setTimeout(() => this.updateBottomActionBar(), 0);
            }
        });
    }

    /**
     * Update bottom action bar based on selected rows
     */
    updateBottomActionBar() {
        const selectedCount = document.querySelectorAll('input[type="checkbox"]:checked').length;
        const actionBar = document.querySelector('.bottom-action-bar');

        if (selectedCount > 0) {
            if (actionBar) {
                actionBar.classList.add('active');
                const info = actionBar.querySelector('.bottom-action-bar-info');
                if (info) {
                    info.innerHTML = `<strong>${selectedCount}</strong> item(s) selected`;
                }
            }
        } else {
            if (actionBar) {
                actionBar.classList.remove('active');
            }
        }
    }

    /**
     * Floating Action Button
     */
    initFloatingActionButton() {
        const fab = document.querySelector('.floating-action-button');
        const menu = document.querySelector('.floating-action-menu');
        const overlay = document.querySelector('.floating-action-overlay');

        if (!fab) return;

        fab.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (menu) {
                menu.classList.toggle('open');
            }
            if (overlay) {
                overlay.classList.toggle('open');
            }
        });

        // Close menu when clicking overlay
        if (overlay) {
            overlay.addEventListener('click', () => {
                if (menu) {
                    menu.classList.remove('open');
                }
                overlay.classList.remove('open');
            });
        }

        // Close menu when clicking menu items
        const menuItems = document.querySelectorAll('.floating-action-menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', () => {
                if (menu) {
                    menu.classList.remove('open');
                }
                if (overlay) {
                    overlay.classList.remove('open');
                }
            });
        });
    }

    /**
     * Collapsible Sections - Click to expand/collapse
     */
    initCollapsibleSections() {
        const collapsibles = document.querySelectorAll('.mobile-collapsible-header');

        collapsibles.forEach(header => {
            // Remove any existing listeners by cloning and replacing
            if (header.dataset.collapsibleBound) {
                return; // Already bound
            }

            header.dataset.collapsibleBound = 'true';

            header.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const section = header.closest('.mobile-collapsible');
                if (section) {
                    const isOpen = section.classList.contains('open');
                    section.classList.toggle('open');

                    // Ensure content can expand
                    const content = section.querySelector('.mobile-collapsible-content');
                    if (content) {
                        if (!isOpen) {
                            // Opening - set computed height
                            setTimeout(() => {
                                const body = content.querySelector('.mobile-collapsible-body');
                                if (body) {
                                    content.style.maxHeight = (body.scrollHeight + 20) + 'px';
                                }
                            }, 0);
                        } else {
                            // Closing
                            content.style.maxHeight = '0';
                        }
                    }
                }
            });
        });
    }

    /**
     * Table Row Selection with Bottom Action Bar
     */
    initTableRowSelection() {
        const tables = document.querySelectorAll('table[data-selectable]');

        tables.forEach(table => {
            // Make rows clickable to select
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.addEventListener('click', (e) => {
                    // Don't select if clicking on an interactive element
                    if (e.target.closest('a, button, input')) {
                        return;
                    }

                    const checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;

                        // Trigger change event
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));

                        // Highlight row
                        row.classList.toggle('table-row-selected');
                    }
                });

                // Sync row highlight with checkbox
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.addEventListener('change', () => {
                        if (checkbox.checked) {
                            row.classList.add('table-row-selected');
                        } else {
                            row.classList.remove('table-row-selected');
                        }
                    });

                    // Check initial state
                    if (checkbox.checked) {
                        row.classList.add('table-row-selected');
                    }
                }
            });
        });
    }

    /**
     * Show Loading Skeleton - Replace content with skeleton while loading
     */
    static showSkeleton(container, skeletonClass = 'skeleton-card') {
        const skeleton = document.createElement('div');
        skeleton.className = `${skeletonClass} skeleton`;
        container.appendChild(skeleton);
        return skeleton;
    }

    /**
     * Hide Loading Skeleton
     */
    static hideSkeleton(skeleton) {
        if (skeleton) {
            skeleton.remove();
        }
    }

    /**
     * Utility: Create skeleton for table
     */
    static createTableSkeleton(rows = 3) {
        let html = '<div class="skeleton-table">';

        // Header
        html += '<div class="skeleton-table-header">';
        for (let i = 0; i < 4; i++) {
            html += '<div class="skeleton-table-header-cell skeleton"></div>';
        }
        html += '</div>';

        // Rows
        for (let i = 0; i < rows; i++) {
            html += '<div class="skeleton-table-row">';
            for (let j = 0; j < 4; j++) {
                html += '<div class="skeleton-table-cell skeleton"></div>';
            }
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Utility: Create skeleton for list
     */
    static createListSkeleton(items = 3) {
        let html = '';

        for (let i = 0; i < items; i++) {
            html += `
                <div class="skeleton-list-item skeleton">
                    <div class="skeleton-list-item-header">
                        <div class="skeleton-list-item-avatar skeleton"></div>
                        <div class="skeleton-list-item-text">
                            <div class="skeleton-list-item-title skeleton"></div>
                            <div class="skeleton-list-item-subtitle skeleton"></div>
                        </div>
                    </div>
                </div>
            `;
        }

        return html;
    }
}

// Initialize on DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.MobileEnhancements = new MobileEnhancements();
    });
} else {
    window.MobileEnhancements = new MobileEnhancements();
}

// Also reinitialize on Livewire navigation
document.addEventListener('livewire:navigated', () => {
    if (window.MobileEnhancements) {
        window.MobileEnhancements.init();
    }
});

export default MobileEnhancements;
