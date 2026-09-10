{{--
    Responsive polish shared by pallet detail + AI manifest review.
    The redesigned View Pallet page owns its title and action bar, so Filament's
    legacy record header is hidden only on the pallet detail route.
--}}
<style>
body.vx-pallet-view-screen .fi-page-header,
body.vx-pallet-view-screen .fi-header:not(.fi-topbar),
body.vx-pallet-view-screen main > .fi-header {
    display: none !important;
}

body.vx-pallet-view-screen .fi-page-content {
    padding-top: 10px !important;
}

.vx-ai-manifest-shortcut {
    background: #7c3aed !important;
    border-color: #7c3aed !important;
    color: #fff !important;
}

/* Keep pallet pills/badges readable instead of allowing narrow table columns
   to break them into one-character vertical stacks. */
.vx-pallet-redesign .vx-pill,
.vx-pallet-redesign .vx-status,
.vx-pallet-redesign .vx-count-btn {
    width: max-content;
    max-width: 100%;
    white-space: normal;
    word-break: normal;
    overflow-wrap: normal;
    line-height: 1.25;
}

/* AI manifest review controls should behave like mobile controls, not tiny
   desktop chips. This remains scoped to .vx-ai so other Filament buttons are
   unaffected. */
.vx-ai .vx-line,
.vx-ai .vx-line-grid > div,
.vx-ai .vx-secondary-row > div,
.vx-ai .vx-match {
    min-width: 0;
}

.vx-ai .vx-match-name,
.vx-ai .vx-meta,
.vx-ai .vx-alt button {
    word-break: normal;
    overflow-wrap: anywhere;
}

@media (max-width: 640px) {
    /* ---- Pallet detail: turn the six-column desktop receiving table into
       one clean card per manifest line. ---- */
    .vx-pallet-redesign .vx-table-card {
        overflow: visible !important;
    }

    .vx-pallet-redesign .vx-table-wrap {
        overflow: visible !important;
        padding: 0 12px 12px;
    }

    .vx-pallet-redesign .vx-table,
    .vx-pallet-redesign .vx-table tbody,
    .vx-pallet-redesign .vx-table tr,
    .vx-pallet-redesign .vx-table td {
        display: block;
        width: 100%;
    }

    .vx-pallet-redesign .vx-table thead {
        display: none;
    }

    .vx-pallet-redesign .vx-table tbody {
        display: grid;
        gap: 10px;
    }

    .vx-pallet-redesign .vx-table tr {
        margin: 0;
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
    }

    .dark .vx-pallet-redesign .vx-table tr {
        background: #111827;
        border-color: #334155;
    }

    .vx-pallet-redesign .vx-table td {
        min-width: 0 !important;
        padding: 8px 0 !important;
        border: 0 !important;
    }

    .vx-pallet-redesign .vx-table td:first-child {
        padding-top: 0 !important;
        padding-bottom: 10px !important;
        border-bottom: 1px solid #eef2f7 !important;
        margin-bottom: 2px;
    }

    .dark .vx-pallet-redesign .vx-table td:first-child {
        border-bottom-color: #263244 !important;
    }

    .vx-pallet-redesign .vx-table td:nth-child(n+2) {
        display: grid;
        grid-template-columns: minmax(82px, .8fr) minmax(0, 1.6fr);
        align-items: center;
        gap: 12px;
        font-size: 12px;
    }

    .vx-pallet-redesign .vx-table td:nth-child(n+2)::before {
        color: #94a3b8;
        font-size: 9px;
        line-height: 1.2;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .vx-pallet-redesign .vx-table td:nth-child(2)::before { content: 'Location'; }
    .vx-pallet-redesign .vx-table td:nth-child(3)::before { content: 'Expected'; }
    .vx-pallet-redesign .vx-table td:nth-child(4)::before { content: 'Received'; }
    .vx-pallet-redesign .vx-table td:nth-child(5)::before { content: 'Status'; }
    .vx-pallet-redesign .vx-table td:nth-child(6)::before { content: 'Action'; }

    .vx-pallet-redesign .vx-item {
        min-width: 0 !important;
        width: 100%;
        align-items: flex-start;
    }

    .vx-pallet-redesign .vx-item > div:last-child {
        min-width: 0;
    }

    .vx-pallet-redesign .vx-item-name,
    .vx-pallet-redesign .vx-item-sub {
        white-space: normal;
        word-break: normal;
        overflow-wrap: anywhere;
    }

    .vx-pallet-redesign .vx-status {
        display: inline-flex !important;
        justify-self: start;
        padding: 5px 9px;
        font-size: 10px;
    }

    .vx-pallet-redesign .vx-count-btn {
        min-width: 42px;
        min-height: 42px;
        justify-self: start;
        justify-content: center;
    }

    .vx-pallet-redesign .vx-section-head {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .vx-pallet-redesign .vx-section-note {
        width: 100%;
        margin-top: -4px;
    }

    /* ---- AI Manifest review ---- */
    .vx-ai .vx-review-card {
        padding: 10px !important;
    }

    .vx-ai .vx-review-head {
        align-items: stretch;
        flex-direction: column;
    }

    .vx-ai .vx-review-head > .vx-btn {
        width: 100%;
    }

    .vx-ai .vx-line {
        padding: 11px !important;
        border-radius: 12px !important;
    }

    .vx-ai .vx-line-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 9px !important;
    }

    .vx-ai .vx-line-grid > div:first-child,
    .vx-ai .vx-line-grid > div:last-child {
        grid-column: 1 / -1 !important;
    }

    /* Unit cost deserves a full row on a phone rather than a cramped third
       numeric column. */
    .vx-ai .vx-line-grid > div:nth-child(4) {
        grid-column: 1 / -1;
    }

    .vx-ai .vx-input {
        min-width: 0;
        min-height: 42px !important;
        font-size: 14px !important;
    }

    .vx-ai .vx-match {
        padding: 10px !important;
    }

    .vx-ai .vx-match-name {
        font-size: 12px !important;
        line-height: 1.35;
    }

    .vx-ai .vx-meta {
        font-size: 10px !important;
        line-height: 1.4;
    }

    .vx-ai .vx-alt {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 6px !important;
        width: 100%;
        margin-top: 7px !important;
    }

    .vx-ai .vx-alt button {
        width: 100%;
        min-height: 40px;
        padding: 8px 10px !important;
        border-radius: 8px !important;
        font-size: 11px !important;
        line-height: 1.25 !important;
        text-align: left;
        white-space: normal;
    }

    .vx-ai .vx-secondary-row {
        grid-template-columns: 1fr !important;
        gap: 9px !important;
    }

    .vx-ai .vx-line .justify-end {
        justify-content: stretch !important;
    }

    .vx-ai .vx-line .justify-end > button {
        width: 100%;
        min-height: 40px;
        margin-top: 3px;
        padding: 8px 10px;
        border: 1px solid #fecaca;
        border-radius: 8px;
        background: #fff7f7;
        text-align: center;
        font-size: 11px !important;
    }

    .dark .vx-ai .vx-line .justify-end > button {
        border-color: #7f1d1d;
        background: rgba(127, 29, 29, .12);
    }
}

@media (max-width: 390px) {
    .vx-pallet-redesign .vx-table td:nth-child(n+2) {
        grid-template-columns: 74px minmax(0, 1fr);
        gap: 8px;
    }

    .vx-ai .vx-line-grid {
        grid-template-columns: 1fr !important;
    }

    .vx-ai .vx-line-grid > div {
        grid-column: 1 / -1 !important;
    }
}
</style>

<script>
(() => {
    const isView = () => /^\/admin\/pallets\/\d+\/?$/.test(location.pathname.toLowerCase());

    const addAiManifestShortcut = () => {
        if (!isView()) return;
        const actions = document.querySelector('.vx-pallet-redesign .vx-actions');
        if (!actions || actions.querySelector('.vx-ai-manifest-shortcut')) return;

        const match = location.pathname.match(/^\/admin\/pallets\/(\d+)\/?$/i);
        if (!match) return;

        const link = document.createElement('a');
        link.className = 'vx-action vx-ai-manifest-shortcut';
        link.href = `/admin/pallets/${match[1]}/import-manifest`;
        link.innerHTML = '<span aria-hidden="true">✦</span><span>AI Manifest</span>';

        const more = actions.querySelector('.vx-more');
        if (more) actions.insertBefore(link, more);
        else actions.appendChild(link);
    };

    const removeLegacyHeader = () => {
        if (!isView()) return;

        const workspace = document.querySelector('.vx-pallet-redesign');
        if (!workspace) return;

        document.querySelectorAll('.fi-page-header, .fi-header').forEach((header) => {
            if (header.closest('nav.fi-topbar')) return;
            if (workspace.contains(header)) return;
            if (header.compareDocumentPosition(workspace) & Node.DOCUMENT_POSITION_FOLLOWING) {
                header.style.setProperty('display', 'none', 'important');
                header.dataset.vxLegacyPalletHeader = 'hidden';
            }
        });

        const labels = ['Continue receiving', 'Start receiving', 'Scan Item', 'Review & Receive'];
        document.querySelectorAll('main div, main section').forEach((node) => {
            if (workspace.contains(node)) return;
            if (!(node.compareDocumentPosition(workspace) & Node.DOCUMENT_POSITION_FOLLOWING)) return;
            const text = (node.textContent || '').replace(/\s+/g, ' ').trim();
            const hits = labels.filter((label) => text.includes(label)).length;
            if (hits >= 2 && !node.querySelector('.vx-pallet-redesign')) {
                node.style.setProperty('display', 'none', 'important');
                node.dataset.vxLegacyPalletActions = 'hidden';
            }
        });

        document.querySelectorAll('.vx-pallet-workflow-bar').forEach((bar) => bar.remove());
        addAiManifestShortcut();
    };

    const apply = () => {
        const active = isView();
        document.body.classList.toggle('vx-pallet-view-screen', active);
        document.body.classList.toggle('vx-pallet-screen', active);
        if (active) requestAnimationFrame(removeLegacyHeader);
    };

    document.addEventListener('DOMContentLoaded', apply);
    document.addEventListener('livewire:navigated', apply);
    document.addEventListener('livewire:initialized', apply);
    document.addEventListener('livewire:updated', () => requestAnimationFrame(removeLegacyHeader));

    new MutationObserver(() => requestAnimationFrame(removeLegacyHeader))
        .observe(document.documentElement, { childList: true, subtree: true });

    apply();
})();
</script>
