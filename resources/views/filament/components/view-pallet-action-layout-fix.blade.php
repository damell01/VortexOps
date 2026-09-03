{{-- Compact View Pallet header actions. The previous global hotfix forced every
     Filament header action into a full-width grid, which made a normal desktop
     pallet page look like a stack of giant mobile buttons. Keep the primary
     workflow actions easy to hit without letting the header consume the first
     screen. --}}
<style>
body.vx-pallet-view-screen .fi-page-header {
    align-items: flex-start !important;
}

body.vx-pallet-view-screen .fi-header-actions,
body.vx-pallet-view-screen .fi-header-actions-ctn {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 8px !important;
    width: auto !important;
    max-width: 100% !important;
    margin: 0 !important;
}

body.vx-pallet-view-screen .fi-header-actions > *,
body.vx-pallet-view-screen .fi-header-actions-ctn > * {
    width: auto !important;
    min-width: 0 !important;
    margin: 0 !important;
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
    min-height: 40px !important;
    padding: 8px 12px !important;
    border-radius: 9px !important;
    white-space: nowrap !important;
    line-height: 1.15 !important;
}

/* The receiving CTA should lead, but it should not become a 700px-wide banner. */
body.vx-pallet-view-screen .fi-header-actions > :first-child .fi-btn,
body.vx-pallet-view-screen .fi-header-actions-ctn > :first-child .fi-btn,
body.vx-pallet-view-screen .fi-header-actions > :first-child a,
body.vx-pallet-view-screen .fi-header-actions-ctn > :first-child a {
    min-width: 220px !important;
}

/* Hide empty action shells. A few Filament action wrappers remain rendered when
   their label/button is conditionally absent; the old forced grid turned those
   harmless shells into large blank white bars. */
body.vx-pallet-view-screen .fi-header-actions > *:not(:has(a)):not(:has(button)),
body.vx-pallet-view-screen .fi-header-actions-ctn > *:not(:has(a)):not(:has(button)) {
    display: none !important;
}

/* Details begin closer to the header. */
body.vx-pallet-view-screen .fi-page-content {
    padding-top: 18px !important;
}

body.vx-pallet-view-screen .fi-page-content > .space-y-6 {
    gap: 14px !important;
}

@media (max-width: 768px) {
    body.vx-pallet-view-screen .fi-page-header {
        gap: 10px !important;
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

    body.vx-pallet-view-screen .fi-header-actions .fi-btn,
    body.vx-pallet-view-screen .fi-header-actions-ctn .fi-btn,
    body.vx-pallet-view-screen .fi-header-actions a,
    body.vx-pallet-view-screen .fi-header-actions button,
    body.vx-pallet-view-screen .fi-header-actions-ctn a,
    body.vx-pallet-view-screen .fi-header-actions-ctn button {
        width: 100% !important;
        min-height: 44px !important;
        padding: 9px 10px !important;
        white-space: normal !important;
        text-align: center !important;
        justify-content: center !important;
        font-size: 13px !important;
    }

    body.vx-pallet-view-screen .fi-header-actions > :first-child,
    body.vx-pallet-view-screen .fi-header-actions-ctn > :first-child {
        grid-column: 1 / -1 !important;
    }

    body.vx-pallet-view-screen .fi-header-actions > :first-child .fi-btn,
    body.vx-pallet-view-screen .fi-header-actions-ctn > :first-child .fi-btn,
    body.vx-pallet-view-screen .fi-header-actions > :first-child a,
    body.vx-pallet-view-screen .fi-header-actions-ctn > :first-child a {
        min-width: 0 !important;
    }

    body.vx-pallet-view-screen .fi-page-content {
        padding-top: 12px !important;
    }
}
</style>