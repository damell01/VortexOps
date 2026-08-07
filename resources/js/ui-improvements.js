/**
 * UI Improvements: Forms, Tables, Modals, Keyboard Navigation
 * Enhances usability on both mobile and desktop
 */

class UIImprovements {
    constructor() {
        this.init();
    }

    init() {
        this.initKeyboardNavigation();
        this.initFormEnhancements();
        this.initTableEnhancements();
        this.initModalKeyboardSupport();
        this.initSidebarKeyboardSupport();
        this.initFieldValidation();
    }

    /**
     * Global keyboard navigation
     */
    initKeyboardNavigation() {
        // ESC key: close modals/sidebars
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeOpenModals();
                this.closeSidebar();
            }
        });

        // Tab key: improve focus management
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                document.body.classList.add('showing-focus-ring');
            }
        });

        // Mouse click: hide focus ring (only show on keyboard)
        document.addEventListener('mousedown', () => {
            document.body.classList.remove('showing-focus-ring');
        });

        // Keyboard shortcut hints
        this.addKeyboardHints();
    }

    /**
     * Form field enhancements
     */
    initFormEnhancements() {
        const inputs = document.querySelectorAll('input, textarea, select');

        inputs.forEach((input) => {
            // Add visual feedback on focus
            input.addEventListener('focus', function () {
                this.closest('.fi-input-wrp')?.classList.add('focused');
            });

            input.addEventListener('blur', function () {
                this.closest('.fi-input-wrp')?.classList.remove('focused');
            });

            // Real-time validation feedback
            input.addEventListener('change', () => this.validateField(input));
            input.addEventListener('blur', () => this.validateField(input));
        });

        // Optional field indicators
        this.addOptionalFieldBadges();
    }

    /**
     * Table sorting and filtering enhancements
     */
    initTableEnhancements() {
        // Sort direction indicators
        const sortButtons = document.querySelectorAll('.fi-ta-header-cell-sort-btn');
        sortButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                this.updateSortIndicators(btn);
            });
        });

        // Filter badge management
        this.initFilterBadges();

        // Add clear filters button if filters exist
        this.addClearFiltersButton();
    }

    /**
     * Modal keyboard support
     */
    initModalKeyboardSupport() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.querySelector('[role="dialog"]:not([aria-hidden="true"])');
                if (modal) {
                    const closeBtn = modal.querySelector('[data-modal-close], .fi-modal-close-btn');
                    closeBtn?.click();
                }
            }
        });

        // Focus trap in modals
        this.setupModalFocusTrap();
    }

    /**
     * Sidebar keyboard support
     */
    initSidebarKeyboardSupport() {
        const sidebar = document.querySelector('.fi-sidebar');
        if (!sidebar) return;

        // Escape key closes sidebar on mobile
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && window.innerWidth <= 1024) {
                const overlay = document.querySelector('.fi-sidebar-overlay');
                if (overlay || sidebar.classList.contains('open')) {
                    this.closeSidebar();
                }
            }
        });
    }

    /**
     * Field validation with visual feedback
     */
    initFieldValidation() {
        const form = document.querySelector('form');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
            inputs.forEach((input) => {
                if (!input.value.trim()) {
                    this.markFieldError(input, 'This field is required');
                } else {
                    this.clearFieldError(input);
                }
            });
        });

        form.addEventListener('change', (e) => {
            if (e.target.matches('input, textarea, select')) {
                if (e.target.value.trim()) {
                    this.clearFieldError(e.target);
                }
            }
        });
    }

    /**
     * Validate a single field
     */
    validateField(field) {
        if (!field.value.trim() && field.hasAttribute('required')) {
            this.markFieldError(field, 'This field is required');
            return false;
        }

        if (field.type === 'email' && field.value && !this.isValidEmail(field.value)) {
            this.markFieldError(field, 'Please enter a valid email');
            return false;
        }

        this.clearFieldError(field);
        return true;
    }

    /**
     * Mark field with error state
     */
    markFieldError(field, message) {
        const wrapper = field.closest('.fi-input-wrp') || field.parentElement;
        if (!wrapper) return;

        wrapper.classList.add('fi-error');

        let errorMsg = wrapper.querySelector('.fi-field-error-message');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'fi-field-error-message';
            errorMsg.setAttribute('role', 'alert');
            wrapper.parentElement?.insertBefore(errorMsg, wrapper.nextSibling);
        }

        errorMsg.textContent = message;
    }

    /**
     * Clear field error state
     */
    clearFieldError(field) {
        const wrapper = field.closest('.fi-input-wrp') || field.parentElement;
        if (!wrapper) return;

        wrapper.classList.remove('fi-error');

        const errorMsg = wrapper.parentElement?.querySelector('.fi-field-error-message');
        if (errorMsg) {
            errorMsg.remove();
        }
    }

    /**
     * Add optional badges to fields
     */
    addOptionalFieldBadges() {
        const requiredFields = document.querySelectorAll('[required]');
        const allFields = document.querySelectorAll('input, textarea, select');

        allFields.forEach((field) => {
            const label = field.closest('.fi-fo-field-wrp')?.querySelector('.fi-fo-field-label');
            if (!label) return;

            // Skip if already has badge
            if (label.querySelector('.fi-fo-field-label-optional')) return;

            // Add optional badge to non-required fields
            if (!field.hasAttribute('required')) {
                const badge = document.createElement('span');
                badge.className = 'fi-fo-field-label-optional';
                badge.textContent = 'Optional';
                label.appendChild(badge);
            }
        });
    }

    /**
     * Initialize filter badge management
     */
    initFilterBadges() {
        const filterBadges = document.querySelectorAll('.fi-ta-filter-badge');

        filterBadges.forEach((badge) => {
            const closeBtn = badge.querySelector('.fi-ta-filter-badge-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    badge.remove();
                    this.updateClearFiltersButton();
                });
            }
        });
    }

    /**
     * Add or update clear filters button
     */
    addClearFiltersButton() {
        const filterContainer = document.querySelector('.fi-ta-filter-indicators');
        if (!filterContainer) return;

        const hasFilters = filterContainer.querySelectorAll('.fi-ta-filter-badge').length > 0;
        if (!hasFilters) return;

        let clearBtn = filterContainer.querySelector('.fi-ta-filters-clear-btn');
        if (!clearBtn) {
            clearBtn = document.createElement('button');
            clearBtn.className = 'fi-ta-filters-clear-btn';
            clearBtn.type = 'button';
            clearBtn.textContent = 'Clear All';
            clearBtn.addEventListener('click', () => this.clearAllFilters());
            filterContainer.appendChild(clearBtn);
        }
    }

    /**
     * Update clear filters button visibility
     */
    updateClearFiltersButton() {
        const filterContainer = document.querySelector('.fi-ta-filter-indicators');
        if (!filterContainer) return;

        const hasFilters = filterContainer.querySelectorAll('.fi-ta-filter-badge').length > 0;
        const clearBtn = filterContainer.querySelector('.fi-ta-filters-clear-btn');

        if (!hasFilters && clearBtn) {
            clearBtn.remove();
        }
    }

    /**
     * Clear all active filters
     */
    clearAllFilters() {
        const badges = document.querySelectorAll('.fi-ta-filter-badge');
        badges.forEach((badge) => badge.remove());
        this.updateClearFiltersButton();

        // Trigger table reload via Livewire if available
        if (window.Livewire) {
            window.Livewire.dispatch('refresh-table');
        }
    }

    /**
     * Update sort direction indicators
     */
    updateSortIndicators(clickedBtn) {
        // Remove sort indicators from other buttons
        document.querySelectorAll('.fi-ta-header-cell-sort-btn').forEach((btn) => {
            if (btn !== clickedBtn) {
                btn.removeAttribute('aria-sort');
            }
        });

        // Toggle sort direction on clicked button
        const current = clickedBtn.getAttribute('aria-sort');
        if (current === 'ascending') {
            clickedBtn.setAttribute('aria-sort', 'descending');
        } else {
            clickedBtn.setAttribute('aria-sort', 'ascending');
        }
    }

    /**
     * Close open modals
     */
    closeOpenModals() {
        const modals = document.querySelectorAll('[role="dialog"]:not([aria-hidden="true"])');
        modals.forEach((modal) => {
            const closeBtn = modal.querySelector('[data-modal-close], .fi-modal-close-btn');
            closeBtn?.click();
        });
    }

    /**
     * Close sidebar
     */
    closeSidebar() {
        const sidebar = document.querySelector('.fi-sidebar');
        if (sidebar?.classList.contains('open')) {
            sidebar.classList.remove('open');
        }

        const overlay = document.querySelector('.fi-sidebar-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    /**
     * Setup modal focus trap
     */
    setupModalFocusTrap() {
        document.addEventListener('focus', (e) => {
            const modal = e.target.closest('[role="dialog"]');
            if (!modal) return;

            const focusableElements = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            if (focusableElements.length === 0) return;

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            // If focus moves outside modal, trap it
            if (e.target !== modal && !modal.contains(e.target)) {
                firstElement.focus();
            }
        }, true);
    }

    /**
     * Add keyboard shortcut hints
     */
    addKeyboardHints() {
        // Already added via CSS for modals and sidebar
        // This can be extended for custom shortcuts
    }

    /**
     * Validate email format
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Add status badge to element
     */
    addStatusBadge(element, status) {
        const badge = document.createElement('span');
        badge.className = `fi-ta-cell-status fi-ta-cell-status-${status}`;

        const statusIcons = {
            success: '✓',
            warning: '⚠',
            danger: '✕',
        };

        badge.textContent = statusIcons[status] || '';
        element.appendChild(badge);
    }

    /**
     * Add timestamp to element
     */
    addTimestamp(element, timestamp) {
        const time = document.createElement('div');
        time.className = 'fi-stat-timestamp';
        time.textContent = `Updated: ${this.formatTime(timestamp)}`;
        element.appendChild(time);
    }

    /**
     * Format timestamp to readable format
     */
    formatTime(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diffMs = now - time;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;

        return time.toLocaleDateString();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.uiImprovements = new UIImprovements();
});

// Also initialize when Livewire updates the DOM
if (window.Livewire) {
    Livewire.hook('morph.updated', () => {
        window.uiImprovements?.init();
    });
}

// Export for use in other contexts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UIImprovements;
}
