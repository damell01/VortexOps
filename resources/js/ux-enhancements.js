/**
 * VortexOps UX Enhancements
 * Keyboard shortcuts, smooth transitions, and better feedback
 */

// ── Keyboard Shortcuts ───────────────────────────────────────────────
document.addEventListener('keydown', (e) => {
    const isInput = ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName);

    // Cmd+Shift+S or Ctrl+Shift+S: Quick search
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 's') {
        e.preventDefault();
        const searchBtn = document.querySelector('[data-global-search-trigger]');
        searchBtn?.click();
    }

    // ?: Show help (if not in input)
    if (e.key === '?' && !isInput) {
        e.preventDefault();
        showKeyboardShortcuts();
    }

    // Cmd+E or Ctrl+E: Quick actions
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('toggle-quick-actions'));
    }

    // Escape: Close modals/dropdowns
    if (e.key === 'Escape') {
        closeAllModals();
    }
});

// ── Toast Notifications ──────────────────────────────────────────────
window.showToast = function(message, type = 'info', duration = 3000) {
    const container = document.querySelector('[data-toast-container]');
    if (!container) return;

    const id = `toast-${Date.now()}`;
    const colors = {
        success: 'bg-green-50 dark:bg-green-950 text-green-800 dark:text-green-200 border-green-200 dark:border-green-800',
        error: 'bg-red-50 dark:bg-red-950 text-red-800 dark:text-red-200 border-red-200 dark:border-red-800',
        warning: 'bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800',
        info: 'bg-blue-50 dark:bg-blue-950 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800',
    };

    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ',
    };

    const toast = document.createElement('div');
    toast.id = id;
    toast.className = `rounded-lg border px-4 py-3 flex items-center gap-2 animate-in slide-in-from-top-4 ${colors[type] || colors.info}`;
    toast.innerHTML = `
        <span class="font-bold text-lg">${icons[type] || icons.info}</span>
        <span class="text-sm font-medium">${message}</span>
    `;

    container.appendChild(toast);

    // Auto-remove after duration
    if (duration > 0) {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 200ms ease-out';
            setTimeout(() => toast.remove(), 200);
        }, duration);
    }

    return id;
};

// ── Close All Modals ────────────────────────────────────────────────
function closeAllModals() {
    // Close Alpine modals
    document.querySelectorAll('[x-show][x-cloak="x-cloak"]').forEach(el => {
        if (el.__x?.getUnobservedData?.()?.isOpen) {
            el.__x?.setEvaluatedExpression('isOpen', false);
        }
    });

    // Close Filament modals
    const closeButtons = document.querySelectorAll('[data-dismiss-modal]');
    closeButtons.forEach(btn => btn.click());
}

// ── Keyboard Shortcuts Help ────────────────────────────────────────
function showKeyboardShortcuts() {
    const shortcuts = [
        { key: 'Cmd+K or /', action: 'Global search' },
        { key: 'Cmd+E', action: 'Quick actions' },
        { key: '?', action: 'This help' },
        { key: 'Esc', action: 'Close modals' },
    ];

    const helpText = shortcuts
        .map(s => `  ${s.key.padEnd(18)} → ${s.action}`)
        .join('\n');

    const message = `⌨️ Keyboard Shortcuts\n\n${helpText}`;

    // Show as alert for now, could be enhanced to modal
    console.group('⌨️ VortexOps Keyboard Shortcuts');
    console.log(helpText);
    console.groupEnd();

    showToast('Press ? for keyboard shortcuts', 'info', 5000);
}

// ── Loading State Feedback ─────────────────────────────────────────
document.addEventListener('livewire:navigating', () => {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
});

document.addEventListener('livewire:navigated', () => {
    document.body.style.opacity = '1';
    document.body.style.pointerEvents = 'auto';
});

// ── Scroll-to-top on navigation ────────────────────────────────────
document.addEventListener('livewire:navigated', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// ── Form dirty state warning ───────────────────────────────────────
let formDirty = false;

document.addEventListener('input', (e) => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target?.tagName)) {
        formDirty = true;
    }
});

document.addEventListener('submit', () => {
    formDirty = false;
});

window.addEventListener('beforeunload', (e) => {
    if (formDirty && !confirm('You have unsaved changes. Leave anyway?')) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// ── Mobile viewport optimization ───────────────────────────────────
function optimizeMobileViewport() {
    const isMobile = window.innerWidth < 768;

    if (isMobile) {
        // Increase button sizes on mobile
        document.querySelectorAll('button, [role="button"]').forEach(btn => {
            if (btn.offsetHeight < 44) {
                btn.style.minHeight = '44px';
            }
        });

        // Ensure inputs are at least 44px
        document.querySelectorAll('input, textarea, select').forEach(input => {
            if (input.offsetHeight < 44) {
                input.style.minHeight = '44px';
            }
        });
    }
}

window.addEventListener('resize', optimizeMobileViewport);
window.addEventListener('load', optimizeMobileViewport);

// ── Touch feedback ─────────────────────────────────────────────────
if (window.matchMedia('(pointer: coarse)').matches) {
    document.addEventListener('touchstart', (e) => {
        if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
            e.target.style.opacity = '0.7';
        }
    });

    document.addEventListener('touchend', (e) => {
        if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
            e.target.style.opacity = '1';
        }
    });
}

// ── Prefetch critical pages ────────────────────────────────────────
function prefetchCriticalPages() {
    // The dashboard lives at /admin, not /admin/dashboard — the old path
    // prefetched a 404 on every page load.
    const links = [
        '/admin',
        '/admin/shows',
        '/admin/streamers',
    ];

    links.forEach(href => {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = href;
        document.head.appendChild(link);
    });
}

if ('requestIdleCallback' in window) {
    requestIdleCallback(prefetchCriticalPages);
} else {
    setTimeout(prefetchCriticalPages, 2000);
}

// ── Export for use ────────────────────────────────────────────────
window.VortexOpsUX = {
    showToast,
    closeAllModals,
    showKeyboardShortcuts,
};
