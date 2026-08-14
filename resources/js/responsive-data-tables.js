/**
 * Adds presentation metadata to Filament's server-rendered tables.
 *
 * The records, Livewire actions, filters, pagination, and accessible table
 * semantics remain Filament-owned.  On phones the accompanying stylesheet
 * uses these classes to lay each row out as a card:
 *
 *     ┌──────┬───────────────────────┐
 *     │ tile │ title                 │   .vx-mobile-tile / .vx-mobile-title
 *     ├──────┴───────────────────────┤
 *     │ stat        │ stat           │   .vx-mobile-stat  (max 2)
 *     ├──────────────────────────────┤
 *     │ detail rows (label / value)  │   .vx-mobile-detail
 *     ├──────────────────────────────┤
 *     │ footer (date)                │   .vx-mobile-foot
 *     ├──────────────────────────────┤
 *     │ actions                      │   .vx-mobile-actions
 *     └──────────────────────────────┘
 */

// Order matters: the product/person name should win the heading over a code.
const titleTerms = ['name', 'title', 'customer', 'streamer', 'recipient', 'item', 'product', 'show'];
// Short codes (SKU, barcode, internal ids) are omitted from the card; the
// name carries the identity and the codes just added noise.
const tileTerms = ['sku', 'code', 'barcode', 'reference', 'id', 'number'];
// Headline columns promoted into the two-up stat row. Mostly numeric, plus
// status: it's the first thing you look for on a card, and pairing it with a
// count fills the row instead of leaving a solo half-width cell.
const statTerms = ['status', 'stock', 'qty', 'quantity', 'amount', 'total', 'value', 'balance', 'price', 'revenue', 'reorder', 'rate', 'count'];
// Dates fall to the card footer.
const footTerms = ['updated', 'created', 'date', 'last seen', 'when'];

const MAX_STATS = 2;

const normalise = (value) => value.replace(/\s+/g, ' ').trim().toLowerCase();
const hits = (label, terms) => terms.some((term) => label.includes(term));

const CLASSES = [
    'vx-mobile-title', 'vx-mobile-tile', 'vx-mobile-stat', 'vx-mobile-detail',
    'vx-mobile-foot', 'vx-mobile-highlight', 'vx-mobile-actions', 'vx-mobile-selection',
    'vx-mobile-omit',
];

function decorateTable(table) {
    const headers = [...table.querySelectorAll('thead th')]
        .map((header) => normalise(header.innerText || header.getAttribute('aria-label') || ''));

    if (!headers.length) return;

    table.querySelectorAll('tbody tr').forEach((row) => {
        const cells = [...row.querySelectorAll(':scope > td')];
        if (!cells.length) return;

        let titleAssigned = false;
        let footAssigned = false;
        let statCount = 0;

        // First pass: classify the structural cells (selection / actions), and
        // find the heading before anything else can claim it.
        const meta = cells.map((cell, index) => {
            const label = headers[index] || '';
            const isSelection = Boolean(cell.querySelector('input[type="checkbox"]'));
            const hasControl = Boolean(cell.querySelector('button, [role="menu"], a[wire\\:click]'));
            const isActions = !isSelection && hasControl && (!label || label.includes('action'));
            return { cell, label, isSelection, isActions };
        });

        // Heading: prefer a real name over a code, in header order.
        const titleCell =
            meta.find((m) => !m.isSelection && !m.isActions && hits(m.label, titleTerms)) ||
            meta.find((m) => !m.isSelection && !m.isActions && m.label && !hits(m.label, tileTerms)) ||
            meta.find((m) => !m.isSelection && !m.isActions);

        meta.forEach(({ cell, label, isSelection, isActions }) => {
            cell.dataset.vxLabel = label || 'Details';
            cell.classList.remove(...CLASSES);

            if (isSelection) {
                cell.classList.add('vx-mobile-selection');
                return;
            }

            if (isActions) {
                cell.classList.add('vx-mobile-actions');
                return;
            }

            if (!titleAssigned && titleCell && cell === titleCell.cell) {
                cell.classList.add('vx-mobile-title');
                titleAssigned = true;
                return;
            }

            if (hits(label, tileTerms)) {
                cell.classList.add('vx-mobile-omit');
                return;
            }

            if (statCount < MAX_STATS && hits(label, statTerms)) {
                cell.classList.add('vx-mobile-stat');
                statCount += 1;
                return;
            }

            if (!footAssigned && hits(label, footTerms)) {
                cell.classList.add('vx-mobile-foot');
                footAssigned = true;
                return;
            }

            cell.classList.add('vx-mobile-detail');
        });

        // An odd number of stats would leave a ragged half-width cell.
        if (statCount === 1) {
            row.querySelector(':scope > td.vx-mobile-stat')?.classList.add('vx-mobile-stat-solo');
        }

    });
}

function decorateTables(root = document) {
    // Hand-written Blade tables opt in with .vx-cardify. Tagging them with
    // Filament's structural classes lets them reuse the same card stylesheet
    // instead of duplicating every rule for a second selector family.
    root.querySelectorAll('.vx-cardify').forEach((table) => {
        table.classList.add('fi-ta-table');
        table.querySelectorAll(':scope > tbody > tr').forEach((row) => {
            row.classList.add('fi-ta-row');
            row.querySelectorAll(':scope > td').forEach((cell) => cell.classList.add('fi-ta-cell'));
        });
    });

    root.querySelectorAll('.fi-ta-table').forEach(decorateTable);
}

function start() {
    decorateTables();

    // Livewire replaces table markup after searches, filters, and pagination.
    const observer = new MutationObserver((mutations) => {
        if (mutations.some((mutation) => mutation.addedNodes.length)) {
            window.requestAnimationFrame(() => decorateTables());
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
}

// This module is imported dynamically, so DOMContentLoaded has usually
// already fired by the time it evaluates — listening for it alone meant the
// decorator never ran and cells got no labels.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}

document.addEventListener('livewire:navigated', () => decorateTables());
