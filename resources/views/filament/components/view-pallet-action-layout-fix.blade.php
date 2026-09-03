{{--
    The redesigned View Pallet page owns its title and action bar.
    Filament's legacy record header must not render above it. Older CSS-only
    attempts were being beaten by other global header rules, so this removes
    the legacy header at the DOM level on the View Pallet route while leaving
    the redesigned .vx-pallet-redesign workspace untouched.
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
