{{--
    View Pallet should feel like a receiving workstation, not an admin action
    dump. Keep the three workflow actions plus More in the header. Less-used
    actions are folded into the existing More menu at runtime so the underlying
    Filament actions/modals stay unchanged.
--}}
<style>
body.vx-pallet-view-screen .fi-page-header {
    align-items: flex-start !important;
    gap: 10px !important;
}

body.vx-pallet-view-screen .fi-header-actions,
body.vx-pallet-view-screen .fi-header-actions-ctn {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 8px !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
}

body.vx-pallet-view-screen .fi-header-actions > *,
body.vx-pallet-view-screen .fi-header-actions-ctn > * {
    width: auto !important;
    min-width: 0 !important;
    margin: 0 !important;
}

/* Only the real workflow belongs in the first screen. */
body.vx-pallet-view-screen .vx-secondary-header-action {
    display: none !important;
}

body.vx-pallet-view-screen .fi-header-actions .fi-btn,
body.vx-pallet-view-screen .fi-header-actions-ctn .fi-btn,
body.vx-pallet-view-screen .fi-header-actions a,
body.vx-pallet-view-screen .fi-header-actions button,
body.vx-pallet-view-screen .fi-header-actions-ctn a,
body.vx-pallet-view-screen .fi-header-actions-ctn button {
    width: auto !important;
    max-width: 100% !important;
    min-width: 0 !important;
    min-height: 42px !important;
    padding: 9px 14px !important;
    border-radius: 10px !important;
    white-space: nowrap !important;
    line-height: 1.15 !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    justify-content: center !important;
    gap: 7px !important;
}

/* Strong contrast: colored workflow buttons always use white text/icons. */
body.vx-pallet-view-screen .vx-primary-receive .fi-btn,
body.vx-pallet-view-screen .vx-primary-receive a,
body.vx-pallet-view-screen .vx-primary-receive button,
body.vx-pallet-view-screen .vx-primary-scan .fi-btn,
body.vx-pallet-view-screen .vx-primary-scan a,
body.vx-pallet-view-screen .vx-primary-scan button,
body.vx-pallet-view-screen .vx-primary-review .fi-btn,
body.vx-pallet-view-screen .vx-primary-review a,
body.vx-pallet-view-screen .vx-primary-review button,
body.vx-pallet-view-screen .vx-primary-receive svg,
body.vx-pallet-view-screen .vx-primary-scan svg,
body.vx-pallet-view-screen .vx-primary-review svg {
    color: #ffffff !important;
}

body.vx-pallet-view-screen .vx-primary-receive .fi-btn-label,
body.vx-pallet-view-screen .vx-primary-scan .fi-btn-label,
body.vx-pallet-view-screen .vx-primary-review .fi-btn-label {
    color: #ffffff !important;
}

/* Neutral More button stays readable in both themes. */
body.vx-pallet-view-screen .vx-native-more .fi-btn,
body.vx-pallet-view-screen .vx-native-more button,
body.vx-pallet-view-screen .vx-native-more a {
    color: #1f2937 !important;
    background: #ffffff !important;
    border: 1px solid #d1d5db !important;
}

html.dark body.vx-pallet-view-screen .vx-native-more .fi-btn,
html.dark body.vx-pallet-view-screen .vx-native-more button,
html.dark body.vx-pallet-view-screen .vx-native-more a {
    color: #f8fafc !important;
    background: #182235 !important;
    border-color: #334155 !important;
}

/* Hide empty shells left by conditional actions. */
body.vx-pallet-view-screen .fi-header-actions > *:not(:has(a)):not(:has(button)),
body.vx-pallet-view-screen .fi-header-actions-ctn > *:not(:has(a)):not(:has(button)) {
    display: none !important;
}

/* Get useful pallet information above the fold quickly. */
body.vx-pallet-view-screen .fi-page-content {
    padding-top: 10px !important;
}

body.vx-pallet-view-screen .fi-page-content > .space-y-6 {
    gap: 12px !important;
}

/* Extra actions injected into Filament's existing More dropdown. */
.vx-pallet-more-divider {
    height: 1px;
    margin: 6px 8px;
    background: #e5e7eb;
}

html.dark .vx-pallet-more-divider {
    background: #334155;
}

.vx-pallet-more-item {
    display: flex !important;
    width: calc(100% - 8px) !important;
    min-height: 40px !important;
    margin: 2px 4px !important;
    padding: 9px 10px !important;
    align-items: center !important;
    gap: 9px !important;
    border-radius: 8px !important;
    color: #1f2937 !important;
    background: transparent !important;
    border: 0 !important;
    text-align: left !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
}

.vx-pallet-more-item:hover {
    background: #f3f4f6 !important;
}

html.dark .vx-pallet-more-item {
    color: #f1f5f9 !important;
}

html.dark .vx-pallet-more-item:hover {
    background: #1f2937 !important;
}

@media (max-width: 768px) {
    body.vx-pallet-view-screen .fi-page-header {
        gap: 8px !important;
    }

    body.vx-pallet-view-screen .fi-header-actions,
    body.vx-pallet-view-screen .fi-header-actions-ctn {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        width: 100% !important;
    }

    body.vx-pallet-view-screen .fi-header-actions > *,
    body.vx-pallet-view-screen .fi-header-actions-ctn > * {
        width: 100% !important;
    }

    body.vx-pallet-view-screen .vx-primary-receive {
        grid-column: 1 / -1 !important;
    }

    body.vx-pallet-view-screen .vx-native-more {
        grid-column: 1 / -1 !important;
        width: auto !important;
        justify-self: start !important;
    }

    body.vx-pallet-view-screen .fi-header-actions .fi-btn,
    body.vx-pallet-view-screen .fi-header-actions-ctn .fi-btn,
    body.vx-pallet-view-screen .fi-header-actions a,
    body.vx-pallet-view-screen .fi-header-actions button,
    body.vx-pallet-view-screen .fi-header-actions-ctn a,
    body.vx-pallet-view-screen .fi-header-actions-ctn button {
        width: 100% !important;
        min-height: 46px !important;
        padding: 9px 10px !important;
        white-space: normal !important;
        text-align: center !important;
        font-size: 13px !important;
    }

    body.vx-pallet-view-screen .vx-native-more .fi-btn,
    body.vx-pallet-view-screen .vx-native-more button,
    body.vx-pallet-view-screen .vx-native-more a {
        width: auto !important;
        min-width: 104px !important;
    }

    body.vx-pallet-view-screen .fi-page-content {
        padding-top: 8px !important;
    }
}
</style>

<script>
(() => {
    if (window.__vxPalletHeaderHierarchyLoaded) return;
    window.__vxPalletHeaderHierarchyLoaded = true;

    const clean = value => (value || '').replace(/\s+/g, ' ').trim();

    const actionContainers = () => [...document.querySelectorAll(
        'body.vx-pallet-view-screen .fi-header-actions, body.vx-pallet-view-screen .fi-header-actions-ctn'
    )];

    const interactive = wrapper => wrapper?.querySelector('a, button');

    const classify = () => {
        if (!document.body.classList.contains('vx-pallet-view-screen')) return;

        actionContainers().forEach(container => {
            [...container.children].forEach(wrapper => {
                wrapper.classList.remove(
                    'vx-primary-receive', 'vx-primary-scan', 'vx-primary-review',
                    'vx-secondary-header-action', 'vx-native-more'
                );

                const text = clean(wrapper.textContent).toLowerCase();
                if (!text) return;

                if (text.includes('continue receiving') || text.includes('start receiving')) {
                    wrapper.classList.add('vx-primary-receive');
                    return;
                }

                if (text.includes('scan item')) {
                    wrapper.classList.add('vx-primary-scan');
                    return;
                }

                if (text.includes('review & receive') || text.includes('review manifest')) {
                    wrapper.classList.add('vx-primary-review');
                    return;
                }

                if (text === 'more' || text.startsWith('more ')) {
                    wrapper.classList.add('vx-native-more');
                    bindMore(wrapper);
                    return;
                }

                if (
                    text.includes('items from this pallet') ||
                    text.includes('add lines') ||
                    text.includes('add photos / documents')
                ) {
                    wrapper.classList.add('vx-secondary-header-action');
                }
            });
        });
    };

    const secondaryActions = () => {
        const entries = [];
        document.querySelectorAll('body.vx-pallet-view-screen .vx-secondary-header-action').forEach(wrapper => {
            const target = interactive(wrapper);
            const label = clean(wrapper.textContent);
            if (!target || !label) return;
            entries.push({ label, target });
        });
        return entries;
    };

    const findVisibleMenu = () => {
        const candidates = [...document.querySelectorAll(
            '[role="menu"], .fi-dropdown-panel, .fi-dropdown-list'
        )].filter(el => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden');
        return candidates[candidates.length - 1] || null;
    };

    const injectSecondaryIntoMore = () => {
        const menu = findVisibleMenu();
        if (!menu || menu.querySelector('[data-vx-pallet-more-extra="1"]')) return;

        const actions = secondaryActions();
        if (!actions.length) return;

        const block = document.createElement('div');
        block.dataset.vxPalletMoreExtra = '1';

        const divider = document.createElement('div');
        divider.className = 'vx-pallet-more-divider';
        block.appendChild(divider);

        actions.forEach(({ label, target }) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'vx-pallet-more-item';
            item.textContent = label;
            item.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();

                if (target.tagName === 'A' && target.href) {
                    window.location.href = target.href;
                    return;
                }

                target.click();
            });
            block.appendChild(item);
        });

        menu.prepend(block);
    };

    function bindMore(wrapper) {
        const button = interactive(wrapper);
        if (!button || button.dataset.vxPalletMoreBound === '1') return;
        button.dataset.vxPalletMoreBound = '1';
        button.addEventListener('click', () => {
            [0, 30, 80, 160].forEach(delay => setTimeout(injectSecondaryIntoMore, delay));
        });
    }

    const refresh = () => requestAnimationFrame(classify);
    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('livewire:navigated', refresh);
    new MutationObserver(refresh).observe(document.body, { childList: true, subtree: true });
    refresh();
})();
</script>