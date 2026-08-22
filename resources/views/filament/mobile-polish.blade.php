<style>
    /* Mobile is the primary operating surface for VortexOps. Keep Filament's
       desktop density on large screens, but reduce dead space and improve touch
       targets on phones without turning every screen into giant cards. */
    @media (max-width: 640px) {
        .fi-main {
            padding-inline: .75rem !important;
        }

        .fi-page-header {
            gap: .75rem !important;
        }

        .fi-page-header-heading {
            font-size: 1.35rem !important;
            line-height: 1.2 !important;
        }

        .fi-page-header-subheading {
            margin-top: .25rem !important;
            font-size: .75rem !important;
            line-height: 1.25rem !important;
        }

        .fi-btn:not(.fi-icon-btn) {
            min-height: 42px;
        }

        input:not([type="checkbox"]):not([type="radio"]):not([type="color"]),
        select,
        textarea {
            font-size: 16px !important; /* prevents iOS input zoom */
        }

        .vx-end-stream section.rounded-2xl,
        .vx-end-stream div.rounded-2xl {
            border-radius: .75rem !important;
        }

        .vx-end-stream section.p-5,
        .vx-end-stream div.p-5 {
            padding: .875rem !important;
        }

        .vx-end-stream .grid.gap-5 {
            gap: .75rem !important;
        }

        .vx-end-stream .space-y-5 > :not([hidden]) ~ :not([hidden]) {
            margin-top: .75rem !important;
        }

        .vx-end-stream h2.text-xl {
            font-size: 1.05rem !important;
            line-height: 1.4 !important;
        }

        .vx-end-stream .grid.grid-cols-2.gap-px > div {
            padding: .7rem .75rem !important;
        }

        .vx-end-stream .grid.grid-cols-2.gap-px > div > div.text-lg,
        .vx-end-stream .grid.grid-cols-2.gap-px > div > div.text-xl {
            font-size: 1rem !important;
        }

        .vx-end-stream ol button {
            min-height: 46px;
            padding: .6rem .35rem !important;
            gap: .35rem !important;
            font-size: .72rem !important;
        }

        .vx-end-stream ol button span:first-child {
            width: 1.35rem !important;
            height: 1.35rem !important;
            font-size: .68rem !important;
        }

        .vx-end-stream aside {
            order: 2;
        }

        .vx-fulfillment-list .fi-ta-header,
        .vx-fulfillment-list .fi-ta-header-toolbar {
            padding-inline: .75rem !important;
        }

        .vx-fulfillment-list .fi-ta-cell {
            padding-top: .7rem !important;
            padding-bottom: .7rem !important;
        }

        .vx-show-detail .fi-section,
        .vx-show-detail .fi-wi {
            border-radius: .75rem !important;
        }
    }

    @media (min-width: 641px) {
        .vx-tour-launcher:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(15,23,42,.16);
        }
    }
</style>

<script>
    (() => {
        const applyVortexPageClasses = () => {
            const path = window.location.pathname;
            const body = document.body;
            if (!body) return;

            body.classList.toggle('vx-end-stream', path.includes('end-of-stream'));
            body.classList.toggle('vx-fulfillment-list', path.includes('fulfillment-center'));
            body.classList.toggle('vx-show-detail', /\/shows\/[^/]+/.test(path));
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyVortexPageClasses, { once: true });
        } else {
            applyVortexPageClasses();
        }

        document.addEventListener('livewire:navigated', applyVortexPageClasses);
    })();
</script>
