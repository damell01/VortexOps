{{--
    The redesigned View Pallet page owns its title and action bar.
    Keep the legacy Filament record header completely out of the way so the
    old action stack cannot render above the new pallet workspace.
--}}
<style>
body.vx-pallet-view-screen .fi-page-header {
    display: none !important;
}

body.vx-pallet-view-screen .fi-page-content {
    padding-top: 10px !important;
}
</style>

<script>
(() => {
    const isView = () => /^\/admin\/pallets\/\d+\/?$/.test(location.pathname.toLowerCase());

    const apply = () => {
        const active = isView();
        document.body.classList.toggle('vx-pallet-view-screen', active);
        document.body.classList.toggle('vx-pallet-screen', active);
        document.querySelectorAll('.vx-pallet-workflow-bar').forEach((bar) => bar.remove());
    };

    document.addEventListener('DOMContentLoaded', apply);
    document.addEventListener('livewire:navigated', apply);
    document.addEventListener('livewire:initialized', apply);
    apply();
})();
</script>
