{{--
    Keep View Pallet's native Filament header actions intact.

    The previous version physically moved Filament action wrapper nodes into a
    custom toolbar. Those wrappers are responsive/conditional shells, so moving
    them caused icon-only white rows and full-width stacked buttons at desktop
    widths. This component now does layout only: Filament continues to own the
    action DOM, Livewire bindings, labels, menus, visibility, and modals.
--}}
<style>
body.vx-pallet-view-screen .fi-page-header {
    align-items: flex-start !important;
    gap: 10px !important;
}

body.vx-pallet-view-screen .fi-page-header .fi-header-actions,
body.vx-pallet-view-screen .fi-page-header .fi-header-actions-ctn {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 8px !important;
    width: auto !important;
    max-width: 100% !important;
    margin: 0 !important;
}

body.vx-pallet-view-screen .fi-page-header .fi-header-actions > *,
body.vx-pallet-view-screen .fi-page-header .fi-header-actions-ctn > * {
    width: auto !important;
    min-width: 0 !important;
    margin: 0 !important;
}

body.vx-pallet-view-screen .fi-page-header .fi-btn,
body.vx-pallet-view-screen .fi-page-header .fi-icon-btn {
    width: auto !important;
    min-height: 40px !important;
}

body.vx-pallet-view-screen .fi-page-content {
    padding-top: 10px !important;
}

@media (max-width: 768px) {
    body.vx-pallet-view-screen .fi-page-header .fi-header-actions,
    body.vx-pallet-view-screen .fi-page-header .fi-header-actions-ctn {
        width: 100% !important;
    }

    body.vx-pallet-view-screen .fi-page-header .fi-btn {
        min-height: 44px !important;
    }
}
</style>

<script>
(() => {
    const isView = () => /^\/admin\/pallets\/\d+\/?$/.test(location.pathname.toLowerCase());

    const apply = () => {
        const active = isView();
        document.body.classList.toggle('vx-pallet-view-screen', active);
        document.body.classList.toggle('vx-pallet-screen', active);

        // Clean up a toolbar injected by the previous implementation if this
        // page was reached through Livewire navigation without a hard reload.
        document.querySelectorAll('.vx-pallet-workflow-bar').forEach((bar) => bar.remove());
    };

    document.addEventListener('DOMContentLoaded', apply);
    document.addEventListener('livewire:navigated', apply);
    document.addEventListener('livewire:initialized', apply);
    apply();
})();
</script>
