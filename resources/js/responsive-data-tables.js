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
    'vx-mobile-omit', 'vx-mobile-inline',
];

function decorateTable(table) {
    const headers = [...table.querySelectorAll('thead th')]
        .map((header) => normalise(header.innerText || header.textContent || header.getAttribute('aria-label') || ''));

    if (!headers.length) return;

    // Dense audit/report rows need a single reading column on phones.
    const readableLog = Boolean(table.closest('[data-vx-readable-log]'));

    // Filament's own tables become an app-style list on phones (see
    // decorateList). Audit logs and tables that opt out keep the cards.
    if (!readableLog && table.closest('.fi-ta-content-ctn') && !table.closest('[data-vx-keep-cards]')) {
        if (table.classList.contains('vx-mobile-readable')) table.classList.remove('vx-mobile-readable');
        decorateList(table, headers);
        return;
    }
    if (table.classList.contains('vx-mlist')) table.classList.remove('vx-mlist');
    table.classList.toggle('vx-mobile-readable', readableLog || headers.filter(Boolean).length >= 6);

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
            (readableLog ? meta.find((m) => m.label === 'description') : null) ||
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

            if (!readableLog && hits(label, tileTerms)) {
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
            // A short value sits beside its label instead of under it, so a
            // wide table's card stays a glance tall on a phone.
            const text = (cell.innerText || '').replace(/\s+/g, ' ').trim();
            cell.classList.toggle('vx-mobile-inline', text.length > 0 && text.length <= 32 && !cell.querySelector('img, textarea, input:not([type=checkbox])'));
        });

        // An odd number of stats would leave a ragged half-width cell.
        if (statCount === 1) {
            row.querySelector(':scope > td.vx-mobile-stat')?.classList.add('vx-mobile-stat-solo');
        }

    });
}

/*
 * Phone list rows for Filament tables:
 *
 *     [☐] Title                              $791.00
 *         Nov 12 · Parker Packer           Whatnot gross
 *         (Reconciled) (Calculate payout)
 *         ⌄ 4 more                         👁 ✎ ⋯
 *
 * The name, a muted line of short facts, the headline amount and the status
 * badges are always visible; every other column waits behind "more".
 */
const ML = ['vx-ml-sel', 'vx-ml-title', 'vx-ml-money', 'vx-ml-sub', 'vx-ml-sub-next', 'vx-ml-badge', 'vx-ml-extra', 'vx-ml-empty', 'vx-ml-actions'];
const moneyLabels = ['amount', 'total', 'net', 'payout', 'pay', 'revenue', 'gross', 'value', 'balance', 'cost', 'price', 'earnings'];
const badgeLabels = ['status', 'next', 'type', 'role', 'stage', 'state', 'priority', 'condition', 'result'];
const subLabels = ['date', 'when', 'created', 'updated', 'streamer', 'vendor', 'business', 'channel', 'show', 'email', 'period', 'week', 'location', 'po'];
const MONEY = /^[-−(]?\s*\$\s?[\d,]+(\.\d+)?\)?$/;

function decorateList(table, headers) {
    if (!table.classList.contains('vx-mlist')) table.classList.add('vx-mlist');
    table.querySelectorAll(':scope > tbody > tr.fi-ta-row').forEach((row) => {
        const cells = [...row.querySelectorAll(':scope > td')];
        if (!cells.length) return;
        const meta = cells.map((cell, index) => {
            const label = headers[index] || '';
            const more = cell.querySelector(':scope > .vx-ml-more')?.innerText || '';
            const text = (cell.innerText || '').replace(more, '').replace(/\s+/g, ' ').trim();
            const isSelection = Boolean(cell.querySelector('input[type="checkbox"]'));
            const hasControl = Boolean(cell.querySelector('button, [role="menu"], a[wire\\:click]'));
            const isActions = !isSelection && (Boolean(cell.querySelector('.fi-ta-actions')) || (hasControl && (!label || label.includes('action'))));
            const badge = Boolean(cell.querySelector('.fi-badge'));
            if (cell.dataset.vxLabel !== (label || 'Details')) cell.dataset.vxLabel = label || 'Details';
            return { cell, label, text, isSelection, isActions, badge, empty: text === '' || text === '—' || text === '-' };
        });
        const free = (m) => !m.role && !m.isSelection && !m.isActions;
        meta.forEach((m) => { if (m.isSelection) m.role = 'sel'; else if (m.isActions) m.role = 'actions'; });

        const title = meta.find((m) => free(m) && !m.empty && hits(m.label, titleTerms) && !hits(m.label, ['date']))
            || meta.find((m) => free(m) && !m.empty && !m.badge && !MONEY.test(m.text) && !hits(m.label, tileTerms))
            || meta.find((m) => free(m) && !m.empty);
        if (title) title.role = 'title';

        const monies = meta.filter((m) => free(m) && MONEY.test(m.text));
        const money = monies.find((m) => hits(m.label, moneyLabels)) || monies[0];
        if (money) money.role = 'money';

        meta.filter((m) => free(m) && m.empty).forEach((m) => { m.role = 'empty'; });
        meta.filter((m) => free(m) && m.badge && hits(m.label, badgeLabels)).slice(0, 3).forEach((m) => { m.role = 'badge'; });
        meta.filter((m) => free(m) && !m.badge && m.text.length <= 36 && hits(m.label, subLabels) && !hits(m.label, tileTerms)).slice(0, 2).forEach((m) => { m.role = 'sub'; });
        meta.filter(free).forEach((m) => { m.role = 'extra'; });

        // Re-tag a cell only when its role changed: every class write is a
        // DOM mutation, and this runs on every change anywhere on the page.
        let subs = 0;
        meta.forEach((m) => {
            const want = ['vx-ml-' + m.role]; if (m.role === 'sub' && subs++ > 0) want.push('vx-ml-sub-next');
            const key = want.join(' ');
            if (m.cell.dataset.vxMl === key) return;
            m.cell.classList.remove(...ML, ...CLASSES);
            m.cell.classList.add(...want);
            m.cell.dataset.vxMl = key;
        });

        // The "more" toggle lives in the actions cell (or, without one, the
        // title cell) and is put back whenever Livewire re-renders the row.
        const extras = meta.filter((m) => m.role === 'extra').length;
        const host = extras ? (meta.find((m) => m.role === 'actions')?.cell || title?.cell) : null;
        // Only touch the DOM when something changed: adding a node re-runs
        // the table watcher, and an unconditional re-add would loop forever.
        row.querySelectorAll('.vx-ml-more').forEach((b) => { if (b.parentElement !== host || b.dataset.count !== String(extras)) b.remove(); });
        if (host && !host.querySelector(':scope > .vx-ml-more')) {
            const open = row.classList.contains('vx-ml-open');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'vx-ml-more';
            button.dataset.count = String(extras);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.textContent = open ? 'Less' : extras + ' more';
            host.prepend(button);
        }
    });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest?.('.vx-ml-more');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    const row = button.closest('tr');
    const open = row.classList.toggle('vx-ml-open');
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.textContent = open ? 'Less' : button.dataset.count + ' more';
}, true);

function decorateTables(root = document) {
    // Every other table in a page or a pop-up keeps every value and its
    // column name, as a card per row on phones (no sideways scrolling). A
    // table that must stay a grid opts out with data-vx-keep-table.
    root.querySelectorAll(':is(.fi-main, .fi-modal-window, .vx-report) table:not(.fi-ta-table):not(.vx-cardify):not([data-vx-keep-table])').forEach((table) => {
        if (table.querySelector('th[rowspan]')) return;
        // A table its page already hides on phones (in favour of a mobile
        // list of its own) stays hidden: the cards would show it twice.
        const tagged = table.classList.contains('vx-mobile-report-table');
        if (tagged) table.classList.remove('vx-mobile-report-table');
        const hiddenByPage = getComputedStyle(table).display === 'none';
        if (hiddenByPage) return;
        // A header spanning two columns names both of them.
        const labels = [...table.querySelectorAll(':scope > thead > tr:last-child > th')]
            .flatMap((th) => Array(Number(th.getAttribute('colspan')) || 1).fill(th.textContent.replace(/\s+/g, ' ').trim()));
        if (!labels.length) return;
        table.classList.add('vx-mobile-report-table');
        table.querySelectorAll(':scope > tbody > tr, :scope > tfoot > tr').forEach((row) => {
            let column = 0;
            row.querySelectorAll(':scope > td, :scope > th').forEach((cell) => {
                const span = Number(cell.getAttribute('colspan')) || 1;
                if (span === 1 && labels[column] && !cell.dataset.reportLabel) cell.dataset.reportLabel = labels[column];
                column += span;
            });
        });
    });

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
        // The debug toolbar's ticking counter is not a table change.
        if (mutations.some((mutation) => mutation.addedNodes.length && !mutation.target.closest?.('.phpdebugbar'))) {
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

