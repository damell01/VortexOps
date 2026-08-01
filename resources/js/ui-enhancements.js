/**
 * UI Enhancements - Copy to Clipboard, Form Validation, Search History, Undo
 */

// ============================================================================
// COPY TO CLIPBOARD
// ============================================================================

window.copyToClipboard = async function(text, label = 'Copied!') {
    try {
        await navigator.clipboard.writeText(text);
        window.toast?.success?.(label, 2000);
        return true;
    } catch (err) {
        window.toast?.error?.('Failed to copy', 2000);
        return false;
    }
};

// Add copy buttons to elements with data-copy attribute
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy]').forEach(element => {
        // Create copy button
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'copy-button ml-2 opacity-60 hover:opacity-100 transition-opacity';
        btn.innerHTML = '📋';
        btn.title = 'Copy to clipboard';
        btn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const text = element.dataset.copy || element.textContent;
            const label = element.dataset.copyLabel || 'Copied!';
            window.copyToClipboard(text, label);
        };

        element.appendChild(btn);
    });
});

// ============================================================================
// REAL-TIME FORM VALIDATION
// ============================================================================

window.validateField = function(field) {
    const validation = field.dataset.validation;
    if (!validation) return true;

    const value = field.value?.trim();
    let isValid = true;
    let errorMessage = '';

    try {
        const rules = JSON.parse(validation);

        // Check required
        if (rules.required && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        // Check min length
        else if (rules.minLength && value.length < rules.minLength) {
            isValid = false;
            errorMessage = `Minimum ${rules.minLength} characters required`;
        }
        // Check max length
        else if (rules.maxLength && value.length > rules.maxLength) {
            isValid = false;
            errorMessage = `Maximum ${rules.maxLength} characters allowed`;
        }
        // Check min value (numbers)
        else if (rules.min && !isNaN(value) && Number(value) < rules.min) {
            isValid = false;
            errorMessage = `Minimum value is ${rules.min}`;
        }
        // Check max value (numbers)
        else if (rules.max && !isNaN(value) && Number(value) > rules.max) {
            isValid = false;
            errorMessage = `Maximum value is ${rules.max}`;
        }
        // Check pattern (regex)
        else if (rules.pattern && value && !new RegExp(rules.pattern).test(value)) {
            isValid = false;
            errorMessage = rules.patternMessage || 'Invalid format';
        }
    } catch (e) {
        // Validation JSON invalid, skip validation
    }

    // Update field visual feedback
    updateFieldValidation(field, isValid, errorMessage);
    return isValid;
};

function updateFieldValidation(field, isValid, errorMessage) {
    const wrapper = field.closest('.form-field') || field.parentElement;

    // Remove existing feedback
    wrapper.querySelectorAll('.validation-feedback, .validation-icon').forEach(el => el.remove());

    if (!isValid) {
        // Add error icon
        const icon = document.createElement('span');
        icon.className = 'validation-icon absolute right-3 top-3 text-red-500';
        icon.innerHTML = '❌';
        if (field.offsetParent) { // Check if visible
            field.parentElement.style.position = 'relative';
            field.parentElement.appendChild(icon);
        }

        // Add error message
        if (errorMessage) {
            const msg = document.createElement('p');
            msg.className = 'validation-feedback text-sm text-red-600 dark:text-red-400 mt-1 animate-fade-in';
            msg.textContent = errorMessage;
            wrapper.appendChild(msg);
        }

        // Add red border
        field.classList.add('border-red-500');
        field.classList.remove('border-green-500');
    } else if (field.value?.trim()) {
        // Add success icon only if field has content
        const icon = document.createElement('span');
        icon.className = 'validation-icon absolute right-3 top-3 text-green-500';
        icon.innerHTML = '✓';
        if (field.offsetParent) {
            field.parentElement.style.position = 'relative';
            field.parentElement.appendChild(icon);
        }

        // Add green border
        field.classList.add('border-green-500');
        field.classList.remove('border-red-500');
    } else {
        // Reset borders
        field.classList.remove('border-red-500', 'border-green-500');
    }
}

// Initialize real-time validation
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-validation]').forEach(field => {
        field.addEventListener('blur', () => window.validateField(field));
        field.addEventListener('input', () => {
            // Debounce validation
            clearTimeout(field._validationTimeout);
            field._validationTimeout = setTimeout(() => {
                window.validateField(field);
            }, 300);
        });
    });
});

// ============================================================================
// SEARCH HISTORY
// ============================================================================

window.SearchHistory = class {
    constructor(storageKey = 'vortex_search_history') {
        this.key = storageKey;
        this.maxItems = 10;
    }

    add(query) {
        if (!query?.trim()) return;
        const history = this.getAll();
        // Remove duplicates
        const filtered = history.filter(h => h.toLowerCase() !== query.toLowerCase());
        // Add new item and limit
        filtered.unshift(query);
        localStorage.setItem(this.key, JSON.stringify(filtered.slice(0, this.maxItems)));
    }

    getAll() {
        const history = localStorage.getItem(this.key);
        return history ? JSON.parse(history) : [];
    }

    clear() {
        localStorage.removeItem(this.key);
    }

    remove(query) {
        const history = this.getAll();
        localStorage.setItem(this.key, JSON.stringify(history.filter(h => h !== query)));
    }
};

// Global search history instance
window.searchHistory = new window.SearchHistory();

// ============================================================================
// UNDO FUNCTIONALITY
// ============================================================================

window.UndoManager = class {
    constructor() {
        this.stack = [];
        this.maxStackSize = 20;
    }

    addAction(action, undo) {
        this.stack.push({ action, undo, timestamp: Date.now() });
        if (this.stack.length > this.maxStackSize) {
            this.stack.shift();
        }
    }

    undo() {
        if (this.stack.length === 0) return false;
        const { undo } = this.stack.pop();
        undo?.();
        return true;
    }

    canUndo() {
        return this.stack.length > 0;
    }

    clear() {
        this.stack = [];
    }
};

// Global undo manager instance
window.undoManager = new window.UndoManager();

// ============================================================================
// FAVORITES/STARRED ITEMS
// ============================================================================

window.Favorites = class {
    constructor(storageKey = 'vortex_favorites') {
        this.key = storageKey;
    }

    toggle(itemId, label = '') {
        const favorites = this.getAll();
        const index = favorites.findIndex(f => f.id === itemId);

        if (index >= 0) {
            favorites.splice(index, 1);
            localStorage.setItem(this.key, JSON.stringify(favorites));
            return false;
        } else {
            favorites.unshift({ id: itemId, label, addedAt: new Date().toISOString() });
            localStorage.setItem(this.key, JSON.stringify(favorites.slice(0, 50)));
            return true;
        }
    }

    isFavorite(itemId) {
        return this.getAll().some(f => f.id === itemId);
    }

    getAll() {
        const favorites = localStorage.getItem(this.key);
        return favorites ? JSON.parse(favorites) : [];
    }

    clear() {
        localStorage.removeItem(this.key);
    }
};

// Global favorites instance
window.favorites = new window.Favorites();

// ============================================================================
// STICKY TABLE HEADERS
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('table[data-sticky-header]').forEach(table => {
        const thead = table.querySelector('thead');
        if (!thead) return;

        // Clone thead for sticky positioning
        const stickyHead = thead.cloneNode(true);
        stickyHead.classList.add('sticky', 'top-0', 'z-10', 'bg-gray-50', 'dark:bg-gray-800', 'shadow-sm');

        // Insert after table start
        table.insertBefore(stickyHead, thead.nextSibling);

        // Hide original thead
        thead.style.display = 'none';
    });
});

// ============================================================================
// KEYBOARD SHORTCUTS
// ============================================================================

window.KeyboardShortcuts = {
    register(key, callback, description = '') {
        document.addEventListener('keydown', (e) => {
            // Don't trigger in input fields
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

            // Check for shortcut
            if (e.key.toLowerCase() === key.toLowerCase() ||
                (e.ctrlKey || e.metaKey) && e.key === key) {
                e.preventDefault();
                callback(e);
            }
        });
    },

    showHelp() {
        const shortcuts = [
            { key: 'Cmd+K', action: 'Global search' },
            { key: 'Cmd+Enter', action: 'Submit form' },
            { key: 'Esc', action: 'Close modal' },
            { key: '?', action: 'Show this help' },
        ];

        const html = `
            <div class="space-y-2">
                <h3 class="font-bold text-lg mb-4">Keyboard Shortcuts</h3>
                ${shortcuts.map(s => `
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">${s.action}</span>
                        <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm font-mono border border-gray-300 dark:border-gray-700">${s.key}</kbd>
                    </div>
                `).join('')}
            </div>
        `;

        window.showToast?.(html, 'info', 5000);
    },
};

// Register basic shortcuts
window.KeyboardShortcuts.register('?', () => window.KeyboardShortcuts.showHelp());
window.KeyboardShortcuts.register('Escape', () => {
    document.querySelectorAll('[role=dialog]').forEach(d => d.close?.());
});

export {
    validateField,
    updateFieldValidation,
    SearchHistory,
    UndoManager,
    Favorites,
    KeyboardShortcuts,
};
