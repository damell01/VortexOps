{{-- Responsive guardrails for the custom Inventory Item view page.
     This page can render with a tablet-sized CSS viewport on iOS, so the
     original 640px breakpoint was too narrow and left the desktop layout
     overflowing/clipped on phones. --}}
<style>
body:has(.vx-item-shell) .fi-page-content,
body:has(.vx-item-shell) .fi-page,
body:has(.vx-item-shell) main {
    min-width: 0 !important;
}

body:has(.vx-item-shell) .fi-page-content {
    width: 100% !important;
    max-width: none !important;
    overflow-x: clip !important;
}

.vx-item-shell,
.vx-item-shell * {
    box-sizing: border-box;
}

.vx-item-shell {
    width: 100% !important;
    max-width: 1100px;
    min-width: 0 !important;
}

.vx-item-shell > *,
.vx-item-shell .vx-card,
.vx-item-shell .vx-hero-main,
.vx-item-shell .vx-overview,
.vx-item-shell .vx-metrics,
.vx-item-shell .vx-actions,
.vx-item-shell .vx-summary-grid {
    min-width: 0 !important;
    max-width: 100% !important;
}

@media (max-width: 900px) {
    /* The custom hero already owns the title/actions on compact screens. */
    body:has(.vx-item-shell) .fi-page-header {
        display: none !important;
    }

    body:has(.vx-item-shell) .fi-page-content {
        padding-inline: 12px !important;
    }

    .vx-item-shell {
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .vx-item-shell .vx-hero {
        width: 100% !important;
        padding: 14px !important;
        overflow: hidden !important;
    }

    .vx-item-shell .vx-hero-main {
        display: grid !important;
        grid-template-columns: 78px minmax(0, 1fr) !important;
        gap: 12px !important;
        width: 100% !important;
        align-items: start !important;
    }

    .vx-item-shell .vx-hero-photo {
        width: 78px !important;
        height: 78px !important;
    }

    .vx-item-shell .vx-hero-main > .min-w-0 {
        min-width: 0 !important;
        overflow: hidden !important;
    }

    .vx-item-shell .vx-hero-main h1,
    .vx-item-shell .vx-hero-main .text-xs {
        overflow-wrap: anywhere !important;
        word-break: break-word !important;
    }

    .vx-item-shell .vx-metrics,
    .vx-item-shell .vx-actions {
        grid-column: 1 / -1 !important;
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        width: 100% !important;
    }

    .vx-item-shell .vx-metric {
        min-width: 0 !important;
        width: 100% !important;
        padding: 10px !important;
        text-align: center !important;
        border: 1px solid #263248 !important;
        border-radius: 10px !important;
    }

    .vx-item-shell .vx-actions > * {
        min-width: 0 !important;
        width: 100% !important;
        min-height: 46px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding-inline: 8px !important;
    }

    .vx-item-shell .vx-tabs {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        overscroll-behavior-x: contain;
        -webkit-overflow-scrolling: touch;
        background: #101827;
        border: 1px solid #263248 !important;
        border-radius: 12px;
        padding-inline: 4px;
    }

    .vx-item-shell .vx-tab {
        flex: 0 0 auto !important;
        padding: 11px 13px !important;
    }

    .vx-item-shell .vx-overview {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 12px !important;
        width: 100% !important;
    }

    .vx-item-shell .vx-overview > .vx-card {
        width: 100% !important;
        margin: 0 !important;
        padding: 16px !important;
        overflow: hidden !important;
    }

    .vx-item-shell .vx-summary-grid {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 7px !important;
        width: 100% !important;
    }

    .vx-item-shell .vx-summary-grid > div {
        min-width: 0 !important;
        padding: 10px 5px !important;
        text-align: center !important;
        border: 1px solid #263248 !important;
        border-radius: 10px !important;
    }

    .vx-item-shell .vx-summary-grid .text-xs {
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }

    .vx-item-shell .vx-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .vx-item-shell .vx-table-wrap {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
}

@media (max-width: 430px) {
    body:has(.vx-item-shell) .fi-page-content {
        padding-inline: 8px !important;
    }

    .vx-item-shell .vx-summary-grid {
        grid-template-columns: 1fr !important;
    }

    .vx-item-shell .vx-stat-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>
