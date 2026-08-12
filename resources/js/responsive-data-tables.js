/**
 * Adds presentation metadata to Filament's server-rendered tables.
 *
 * The records, Livewire actions, filters, pagination, and accessible table
 * semantics remain Filament-owned.  On phones the accompanying stylesheet
 * uses these classes to present the same rows as scan-friendly record cards.
 */
const priorityTitles = ['name', 'title', 'customer', 'streamer', 'recipient', 'item', 'product', 'sku', 'id'];
const keyDetails = ['email', 'description', 'date', 'show', 'order', 'payout', 'barcode', 'category'];
const highlights = ['status', 'stock', 'qty', 'quantity', 'amount', 'total', 'value', 'balance', 'price', 'role', 'active', 'availability'];

const normalise = (value) => value.replace(/\s+/g, ' ').trim().toLowerCase();

function decorateTable(table) {
    const headers = [...table.querySelectorAll('thead th')]
        .map((header) => normalise(header.innerText || header.getAttribute('aria-label') || ''));

    if (!headers.length) return;

    table.querySelectorAll('tbody tr').forEach((row) => {
        const cells = [...row.querySelectorAll(':scope > td')];
        if (!cells.length) return;

        let titleAssigned = false;
        let detailAssigned = false;

        cells.forEach((cell, index) => {
            const label = headers[index] || '';
            const hasControl = Boolean(cell.querySelector('button, [role="menu"], input[type="checkbox"]'));

            cell.dataset.vxLabel = label || 'Details';
            cell.classList.remove('vx-mobile-title', 'vx-mobile-detail', 'vx-mobile-highlight', 'vx-mobile-actions', 'vx-mobile-selection');

            if (cell.querySelector('input[type="checkbox"]')) {
                cell.classList.add('vx-mobile-selection');
            } else if (hasControl && (!label || label.includes('action'))) {
                cell.classList.add('vx-mobile-actions');
            } else if (!titleAssigned && priorityTitles.some((term) => label.includes(term))) {
                cell.classList.add('vx-mobile-title');
                titleAssigned = true;
            } else if (!detailAssigned && keyDetails.some((term) => label.includes(term))) {
                cell.classList.add('vx-mobile-detail');
                detailAssigned = true;
            } else if (highlights.some((term) => label.includes(term))) {
                cell.classList.add('vx-mobile-highlight');
            }
        });

        // Tables with generic headings still need a useful heading in each card.
        if (!titleAssigned) {
            const firstContentCell = cells.find((cell) => !cell.classList.contains('vx-mobile-selection') && !cell.classList.contains('vx-mobile-actions'));
            firstContentCell?.classList.add('vx-mobile-title');
        }
    });
}

function decorateTables(root = document) {
    root.querySelectorAll('.fi-ta-table').forEach(decorateTable);
}

document.addEventListener('DOMContentLoaded', () => {
    decorateTables();

    // Livewire replaces table markup after searches, filters, and pagination.
    const observer = new MutationObserver((mutations) => {
        if (mutations.some((mutation) => mutation.addedNodes.length)) {
            window.requestAnimationFrame(() => decorateTables());
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
