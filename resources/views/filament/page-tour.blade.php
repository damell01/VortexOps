@auth
<style>
    [data-vx-tour-active="true"] {
        position: relative !important;
        z-index: 61 !important;
        outline: 3px solid rgb(var(--primary-500, 124 58 237)) !important;
        outline-offset: 4px !important;
        border-radius: 14px !important;
        scroll-margin: 110px !important;
    }

    .vx-tour-launcher {
        position: fixed;
        right: 14px;
        bottom: 14px;
        z-index: 55;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 42px;
        padding: 9px 13px;
        border: 1px solid rgb(229 231 235);
        border-radius: 999px;
        background: rgba(255,255,255,.96);
        color: rgb(55 65 81);
        font-size: 12px;
        font-weight: 700;
        box-shadow: 0 8px 24px rgba(15,23,42,.12);
        backdrop-filter: blur(12px);
    }

    .dark .vx-tour-launcher {
        border-color: rgb(55 65 81);
        background: rgba(17,24,39,.96);
        color: rgb(229 231 235);
    }

    .vx-tour-launcher svg { width: 17px; height: 17px; }

    .vx-tour-sheet {
        position: fixed;
        z-index: 70;
        left: 50%;
        bottom: 14px;
        width: min(440px, calc(100vw - 24px));
        transform: translateX(-50%);
        border: 1px solid rgb(229 231 235);
        border-radius: 18px;
        background: white;
        box-shadow: 0 22px 60px rgba(15,23,42,.24);
        overflow: hidden;
    }

    .dark .vx-tour-sheet {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .vx-tour-progress {
        height: 3px;
        background: rgb(243 244 246);
    }

    .dark .vx-tour-progress { background: rgb(31 41 55); }

    .vx-tour-progress > span {
        display: block;
        height: 100%;
        background: rgb(var(--primary-600, 124 58 237));
        transition: width .2s ease;
    }

    .vx-tour-content { padding: 15px 16px 14px; }
    .vx-tour-kicker { color: rgb(var(--primary-600, 124 58 237)); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
    .vx-tour-title { margin-top: 3px; font-size: 15px; font-weight: 750; color: rgb(17 24 39); line-height: 1.3; }
    .dark .vx-tour-title { color: white; }
    .vx-tour-copy { margin-top: 5px; color: rgb(107 114 128); font-size: 12px; line-height: 1.55; }
    .dark .vx-tour-copy { color: rgb(156 163 175); }

    .vx-tour-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:13px; }
    .vx-tour-actions-left, .vx-tour-actions-right { display:flex; gap:7px; align-items:center; }
    .vx-tour-btn { min-height: 38px; border-radius: 10px; padding: 8px 11px; font-size: 12px; font-weight: 700; }
    .vx-tour-btn-secondary { border:1px solid rgb(229 231 235); color:rgb(75 85 99); background:white; }
    .dark .vx-tour-btn-secondary { border-color:rgb(55 65 81); color:rgb(209 213 219); background:rgb(31 41 55); }
    .vx-tour-btn-primary { border:1px solid transparent; color:white; background:rgb(var(--primary-600, 124 58 237)); }
    .vx-tour-close { color:rgb(156 163 175); font-size:11px; font-weight:700; padding:8px 4px; }

    @media (max-width: 640px) {
        .vx-tour-launcher {
            right: 10px;
            bottom: max(10px, env(safe-area-inset-bottom));
            min-height: 40px;
            padding: 8px 11px;
        }

        .vx-tour-sheet {
            bottom: max(10px, env(safe-area-inset-bottom));
            width: calc(100vw - 16px);
            border-radius: 16px;
        }

        .vx-tour-content { padding: 13px 14px 12px; }
        .vx-tour-title { font-size: 14px; }
        .vx-tour-copy { font-size: 12px; }
        .vx-tour-btn { min-height: 40px; }
    }
</style>

<div
    x-data="vxPageTour()"
    x-init="init()"
    x-cloak
>
    <button
        x-show="tour !== null && !open"
        type="button"
        class="vx-tour-launcher"
        @click="start(false)"
        aria-label="Start page tour"
    >
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8.625 9.75a3.375 3.375 0 116.75 0c0 1.238-.667 1.93-1.514 2.47-.79.503-1.486.918-1.486 2.03M12 18h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Tour</span>
    </button>

    <div x-show="open && current" x-transition.opacity.duration.150ms class="vx-tour-sheet" role="dialog" aria-modal="false" aria-label="Page tour">
        <div class="vx-tour-progress"><span :style="`width:${progress}%`"></span></div>
        <div class="vx-tour-content">
            <div class="vx-tour-kicker" x-text="`${index + 1} of ${steps.length} · ${tour?.label ?? 'Tour'}`"></div>
            <div class="vx-tour-title" x-text="current?.title"></div>
            <div class="vx-tour-copy" x-text="current?.text"></div>

            <div class="vx-tour-actions">
                <div class="vx-tour-actions-left">
                    <button type="button" class="vx-tour-close" @click="finish()">Close</button>
                </div>
                <div class="vx-tour-actions-right">
                    <button x-show="index > 0" type="button" class="vx-tour-btn vx-tour-btn-secondary" @click="prev()">Back</button>
                    <button type="button" class="vx-tour-btn vx-tour-btn-primary" @click="next()" x-text="index === steps.length - 1 ? 'Done' : 'Next'"></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.vxPageTour = window.vxPageTour || function () {
        return {
            open: false,
            tour: null,
            steps: [],
            index: 0,
            highlighted: null,

            get current() { return this.steps[this.index] ?? null; },
            get progress() { return this.steps.length ? ((this.index + 1) / this.steps.length) * 100 : 0; },

            definitions() {
                return [
                    {
                        id: 'streamer-dashboard-v2', label: 'Streamer Center',
                        match: () => document.querySelector('[data-vx-page="streamer-dashboard"]'),
                        steps: [
                            { selector: '[data-vx-tour="role-overview"]', title: 'This is your work center', text: 'Start here. It keeps your next show, report workload, and the inventory you are responsible for together.' },
                            { selector: '[data-vx-tour="role-metrics"]', title: 'Quick numbers, not giant cards', text: 'These compact totals tell you what needs attention without making you scroll through oversized dashboard tiles.' },
                            { selector: '[data-vx-tour="primary-action"]', title: 'Finish a show here', text: 'After a stream ends, open End of Stream. Record sold items, giveaways, promo items, and anything that is not yet in the catalog.' },
                            { selector: '[data-vx-tour="dashboard-widgets"]', title: 'More detail below', text: 'These widgets give you deeper show, inventory, and payout information. The top of the page stays focused on what you need to do next.' },
                        ],
                    },
                    {
                        id: 'admin-dashboard-v2', label: 'Admin Center',
                        match: () => document.querySelector('[data-vx-page="admin-dashboard"]'),
                        steps: [
                            { selector: '[data-vx-tour="role-overview"]', title: 'Start with exceptions', text: 'This dashboard is organized around work that needs attention: report review, unmatched inventory, fulfillment, shipments, and payouts.' },
                            { selector: '[data-vx-tour="role-metrics"]', title: 'Scan the queue', text: 'These compact counts are the fastest way to see where work is piling up.' },
                            { selector: '[data-vx-tour="dashboard-widgets"]', title: 'Work the details', text: 'Below the summary are your workflow controls, needs-attention queues, recent shows, and operating metrics.' },
                        ],
                    },
                    {
                        id: 'fulfillment-dashboard-v2', label: 'Fulfillment Center',
                        match: () => document.querySelector('[data-vx-page="fulfillment-dashboard"]'),
                        steps: [
                            { selector: '[data-vx-tour="role-overview"]', title: 'Shipping work starts here', text: 'This view is about shows and shipments, not financial metrics. Open work is kept at the top.' },
                            { selector: '[data-vx-tour="role-metrics"]', title: 'Know the workload', text: 'Shows to work, open shipments, today’s deliveries, and unassigned work stay visible in a compact grid.' },
                            { selector: '[data-vx-tour="primary-action"]', title: 'Open the Fulfillment Center', text: 'Work show-first. Open a show, then handle its shipment and packing lines instead of searching through one giant shipment table.' },
                        ],
                    },
                    {
                        id: 'end-of-stream-v2', label: 'End of Stream',
                        match: () => location.pathname.includes('end-of-stream'),
                        steps: [
                            { selector: 'main', title: 'Report what actually happened', text: 'Use this after the show. Add inventory used during the stream and classify each line as Sold, Giveaway, Promo / Bonus, or Other.' },
                            { selector: 'main section:first-of-type', title: 'Add show items', text: 'Pick from assigned inventory when possible. If the product is not in the catalog, add it as an unlisted item instead of guessing.' },
                            { selector: 'aside', title: 'Watch the report summary', text: 'The summary updates as you work. Unmatched items are allowed, but they remain visible for admin reconciliation.' },
                            { selector: 'main section:last-of-type', title: 'Review before submitting', text: 'Check quantities, classifications, cost, and inventory exceptions. Submission follows the workflow policy configured by admin.' },
                        ],
                    },
                    {
                        id: 'show-detail-v2', label: 'Show Command Center',
                        match: () => document.querySelector('[data-vx-page="show-report-review"]'),
                        steps: [
                            { selector: '[data-vx-tour="show-report"]', title: 'Review the streamer report here', text: 'You do not need a separate reconciliation page. Reported inventory, giveaways, promo lines, matching status, and approval all live on the show.' },
                            { selector: '[data-vx-tour="show-report-lines"]', title: 'Fix exceptions inline', text: 'Unmatched items can be linked to inventory right here. Posted lines are clearly marked so you can see what has already affected stock.' },
                            { selector: '[data-vx-tour="show-activity"]', title: 'See what changed', text: 'The activity timeline combines Whatnot metric changes, report events, approvals, and show-linked inventory movements.' },
                        ],
                    },
                    {
                        id: 'show-shipments-v2', label: 'Show Shipments',
                        match: () => document.querySelector('[data-vx-page="show-shipments"]'),
                        steps: [
                            { selector: '[data-vx-tour="shipment-filters"]', title: 'Find the show first', text: 'Search or filter at the show level. This keeps thousands of shipment rows from becoming one unreadable list.' },
                            { selector: '[data-vx-tour="shipment-cards"]', title: 'Each card is one show', text: 'Open delivery count, delivered count, shipment total, and shipping spend are summarized before you drill in.' },
                        ],
                    },
                    {
                        id: 'fulfillment-list-v2', label: 'Fulfillment Queue',
                        match: () => location.pathname.includes('fulfillment-center') && document.querySelector('.fi-ta'),
                        steps: [
                            { selector: '.fi-ta', title: 'Work one show at a time', text: 'This list is show-first. Open the show with open work, then process its shipment and packing lines.' },
                            { selector: '.fi-ta-filters, .fi-ta-header-toolbar, .fi-ta-header', title: 'Use filters when the queue grows', text: 'Filter by assignment or status rather than scrolling through unrelated work.' },
                        ],
                    },
                ];
            },

            init() {
                this.tour = this.definitions().find(t => t.match()) ?? null;
                if (!this.tour) return;
                this.steps = this.tour.steps.filter(step => document.querySelector(step.selector));
                if (!this.steps.length) return;

                const key = `vx-tour-seen:${this.tour.id}`;
                if (!localStorage.getItem(key)) {
                    setTimeout(() => this.start(true), 700);
                }
            },

            start(auto = false) {
                if (!this.tour) return;
                this.steps = this.tour.steps.filter(step => document.querySelector(step.selector));
                if (!this.steps.length) return;
                this.index = 0;
                this.open = true;
                this.focusCurrent();
                if (!auto) localStorage.removeItem(`vx-tour-seen:${this.tour.id}`);
            },

            clearHighlight() {
                if (this.highlighted) {
                    this.highlighted.removeAttribute('data-vx-tour-active');
                    this.highlighted = null;
                }
            },

            focusCurrent() {
                this.clearHighlight();
                this.$nextTick(() => {
                    const selector = this.current?.selector;
                    const el = selector ? document.querySelector(selector) : null;
                    if (!el) return;
                    this.highlighted = el;
                    el.setAttribute('data-vx-tour-active', 'true');
                    el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                });
            },

            next() {
                if (this.index >= this.steps.length - 1) return this.finish();
                this.index++;
                this.focusCurrent();
            },

            prev() {
                if (this.index <= 0) return;
                this.index--;
                this.focusCurrent();
            },

            finish() {
                this.clearHighlight();
                this.open = false;
                if (this.tour) localStorage.setItem(`vx-tour-seen:${this.tour.id}`, '1');
            },
        };
    };
</script>
@endauth
