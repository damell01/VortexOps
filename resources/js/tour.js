import { driver } from 'driver.js';

const STORAGE_KEY = 'vortexops_tour_v1';

function page() {
    const p = window.location.pathname;
    if (p.match(/\/admin\/?$/))                return 'dashboard';
    if (p.includes('whatnot-shows'))           return 'shows';
    if (p.includes('deduction-requests'))      return 'deductions';
    if (p.includes('payouts') && !p.includes('weekly')) return 'payouts';
    if (p.includes('weekly-payout-batches'))   return 'pay-runs';
    if (p.includes('inventory-items'))         return 'items';
    if (p.includes('inventory-locations'))     return 'locations';
    if (p.includes('inventory-movements'))     return 'movements';
    if (p.includes('inventory-stock'))         return 'stock';
    if (p.includes('streamers'))               return 'streamers';
    if (p.includes('whatnot-channels'))        return 'channels';
    if (p.includes('app-settings'))            return 'settings';
    if (p.includes('ai-assistant'))            return 'ai';
    return 'general';
}

// ── Shared helpers ─────────────────────────────────────────────────────────────
function el(selector) {
    return document.querySelector(selector) ? selector : 'body';
}

function sidebarLink(href) {
    return `a[href*="${href}"]`;
}

// ── Tour step definitions ──────────────────────────────────────────────────────

const TOURS = {

    dashboard: [
        {
            popover: {
                title: '👋 Welcome to VortexOps',
                description:
                    'This is your operations hub for Vortex Breaks. Let\'s take a 2-minute tour so you know where everything lives and how it all connects.',
                side: 'over',
                align: 'center',
            },
        },
        {
            element: el('nav, aside, [data-panel-sidebar], .fi-sidebar'),
            popover: {
                title: 'Navigation',
                description:
                    'Everything is grouped by function in the sidebar. Use the groups to move between Inventory, Stream Tracking, Payouts, and Settings.',
                side: 'right',
                align: 'start',
            },
        },
        {
            element: el(sidebarLink('whatnot-shows')),
            popover: {
                title: '🎥 Stream Tracking',
                description:
                    '<b>Shows</b> — log each stream manually or import via CSV/scraper.<br><br>'
                    + '<b>Deduction Requests</b> — after a show is logged, deductions wait here for your approval before any inventory is touched.',
                side: 'right',
                align: 'start',
            },
        },
        {
            element: el(sidebarLink('payouts')),
            popover: {
                title: '💰 Payouts & Pay Runs',
                description:
                    '<b>Payouts</b> — per-streamer earnings calculated from each show.<br><br>'
                    + '<b>Pay Runs</b> — group payouts into weekly ADP batches. Draft → Finalized → Submitted → Paid.',
                side: 'right',
                align: 'start',
            },
        },
        {
            element: el(sidebarLink('inventory-items')),
            popover: {
                title: '📦 Inventory',
                description:
                    '<b>Items</b> — your product catalogue with cost basis and reorder levels.<br><br>'
                    + '<b>Locations</b> — Main Storage, streamer locations, damaged, returned.<br><br>'
                    + '<b>Stock Levels</b> — read-only view of every item × location combination.<br><br>'
                    + '<b>Movement Log</b> — immutable audit trail of every stock change.',
                side: 'right',
                align: 'start',
            },
        },
        {
            element: el(sidebarLink('streamers')),
            popover: {
                title: '🎙 Operations',
                description:
                    '<b>Streamers</b> — profiles, payout type configuration, ADP employee ID.<br><br>'
                    + '<b>Whatnot Channels</b> — the channel(s) you stream on (shared across all streamers).',
                side: 'right',
                align: 'start',
            },
        },
        {
            element: el('main h1, .fi-header-heading, [class*="heading"]'),
            popover: {
                title: '📊 Dashboard',
                description:
                    'Your live snapshot — total SKUs, units in stock, low-stock alerts, and total inventory value. Updates in real time as you make stock changes.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: el('[id*="low-stock"], [class*="low-stock"], [wire\\:id]'),
            popover: {
                title: '⚠️ Low Stock Alerts',
                description:
                    'Items at or below their reorder level surface here automatically after any stock operation. Click a row to go straight to that item.',
                side: 'top',
                align: 'start',
            },
        },
        {
            element: el('#vortexops-tour-btn'),
            popover: {
                title: '❓ This Button',
                description:
                    'Click the <b>?</b> button any time to restart the tour for whatever page you\'re currently on. Each section of the app has its own guided walkthrough.',
                side: 'top',
                align: 'end',
            },
        },
        {
            popover: {
                title: "✅ You're all set!",
                description:
                    'Start by adding your inventory items and locations under <b>Operations</b>. '
                    + 'After your first show, log it under <b>Stream Tracking → Shows</b>, '
                    + 'approve the deductions, and calculate payouts. '
                    + '<br><br>The <b>?</b> button gives you context-specific guidance on any page.',
                side: 'over',
                align: 'center',
            },
        },
    ],

    shows: [
        {
            element: el('h1, .fi-header-heading'),
            popover: {
                title: '🎥 Shows',
                description:
                    'A Show represents one Whatnot streaming session. Log every break here after it ends — or import via CSV or the Playwright scraper.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: el('a[href*="/create"], button[class*="create"]'),
            popover: {
                title: 'Creating a Show',
                description:
                    'Click <b>New Show</b> to open the entry form. You\'ll fill in:<br>'
                    + '• Show date + stream times<br>'
                    + '• Which streamers appeared<br>'
                    + '• Sales line items (one row per item sold)<br>'
                    + '• Financial totals (gross, fees, shipping, tips)',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: el('table, .fi-ta'),
            popover: {
                title: 'Show Status Lifecycle',
                description:
                    '<b>Draft</b> — just created, data still being entered.<br>'
                    + '<b>Pending Reconciliation</b> — deduction requests generated, awaiting approval.<br>'
                    + '<b>Reconciled</b> — all approved deductions executed against inventory.<br>'
                    + '<b>Paid</b> — streamers have been paid for this show.',
                side: 'top',
                align: 'start',
            },
        },
        {
            popover: {
                title: 'After You Create a Show',
                description:
                    '1. Open the show → <b>AI Match Items</b> to auto-link sales to inventory.<br>'
                    + '2. Review matches, fix any that are wrong.<br>'
                    + '3. Hit <b>Generate Deduction Requests</b>.<br>'
                    + '4. Go to Deduction Requests and approve them.<br>'
                    + '5. Back on the show, hit <b>Execute Approved Deductions</b>.<br>'
                    + '6. Then <b>Calculate Payouts</b>.',
                side: 'over',
                align: 'center',
            },
        },
    ],

    deductions: [
        {
            element: el('h1, .fi-header-heading'),
            popover: {
                title: '✅ Deduction Requests',
                description:
                    'Every inventory deduction from a show goes through this approval queue first. <b>Nothing is deducted from stock until you approve it here.</b> This is by design.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: el('table, .fi-ta'),
            popover: {
                title: 'Reviewing Requests',
                description:
                    'Each row shows the item, quantity, location it will be deducted from, and which show it came from. The view page shows available stock so you can catch discrepancies before approving.',
                side: 'top',
                align: 'start',
            },
        },
        {
            popover: {
                title: 'Approve or Reject',
                description:
                    '<b>Approve</b> — queues the deduction for execution.<br>'
                    + '<b>Reject</b> — skips it (you\'ll enter a reason). Use this for items that were returned or wrongly logged.<br>'
                    + '<b>Bulk Approve</b> — select multiple rows and approve them all at once.<br><br>'
                    + 'After approving, go back to the Show and hit <b>Execute Approved Deductions</b> to apply them to inventory.',
                side: 'over',
                align: 'center',
            },
        },
    ],

    payouts: [
        {
            element: el('h1, .fi-header-heading'),
            popover: {
                title: '💸 Payouts',
                description:
                    'Payouts are calculated per streamer per show based on their payout type. Run <b>Calculate Payouts</b> from any Show view page to generate them.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: el('table, .fi-ta'),
            popover: {
                title: 'How Payouts Are Calculated',
                description:
                    '<b>Profit Share</b> — their % of net revenue (after platform fee and owner fee).<br>'
                    + '<b>Package Rate</b> — flat rate per show + tips if enabled.<br>'
                    + '<b>Hourly</b> — hourly rate × hours (derived from stream start/end times).<br>'
                    + '<b>Flat Rate</b> — fixed amount per show.<br><br>'
                    + 'The Calculation Notes column shows exactly how the number was reached.',
                side: 'top',
                align: 'start',
            },
        },
        {
            popover: {
                title: 'From Payouts → Pay Runs',
                description:
                    'Once payouts are calculated, go to <b>Pay Runs</b> and create a weekly batch. It automatically pulls in all unattached payouts for that week. Finalize → Submit to ADP → Mark Paid.',
                side: 'over',
                align: 'center',
            },
        },
    ],

    'pay-runs': [
        {
            element: el('h1, .fi-header-heading'),
            popover: {
                title: '📅 Pay Runs',
                description:
                    'Pay Runs group weekly payouts into a single batch for ADP. Create one batch per week — it auto-collects all unbatched payouts for that week.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            popover: {
                title: 'Pay Run Lifecycle',
                description:
                    '<b>Draft</b> — payouts attached, amounts still adjustable.<br>'
                    + '<b>Finalized</b> — amounts locked, streamer payouts marked Approved.<br>'
                    + '<b>Submitted to ADP</b> — sent for processing.<br>'
                    + '<b>Paid</b> — streamers have received payment.',
                side: 'over',
                align: 'center',
            },
        },
    ],

    items: [
        {
            element: el('h1, .fi-header-heading'),
            popover: {
                title: '📦 Inventory Items',
                description:
                    'Your product catalogue. Every card box, pack, or product you stock lives here with its SKU, category, unit cost, and reorder level.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: el('table, .fi-ta'),
            popover: {
                title: 'Item Rows',
                description:
                    'Each row shows the total quantity across all locations and a low-stock warning when quantity is at or below the reorder level. Click any row to open the item detail.',
                side: 'top',
                align: 'start',
            },
        },
        {
            popover: {
                title: '⚡ Stock Actions',
                description:
                    'From the row action menu (or the item view page) you can:<br>'
                    + '• <b>Add Stock</b> — receive new inventory<br>'
                    + '• <b>Transfer</b> — move between locations<br>'
                    + '• <b>Adjust</b> — correct the count after a physical audit<br>'
                    + '• <b>Mark Damaged</b> — move to the damaged location<br>'
                    + '• <b>Move to Returns</b> — handle customer returns<br><br>'
                    + 'Every action is wrapped in a transaction and creates a permanent movement record.',
                side: 'over',
                align: 'center',
            },
        },
    ],

    locations: [
        {
            element: el('h1, .fi-header-heading'),
            popover: {
                title: '📍 Inventory Locations',
                description:
                    'Locations represent physical storage areas. The four standard ones (Main Storage, Fulfillment, Returned, Damaged) are created on setup. Add a Streamer Inventory location for each streamer.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            popover: {
                title: 'Streamer Inventory Locations',
                description:
                    'When a location type is set to <b>Streamer Inventory</b>, a streamer selector appears. That links the location to a streamer profile, which is how the deduction engine knows which location to pull from after a show.',
                side: 'over',
                align: 'center',
            },
        },
    ],

    settings: [
        {
            element: el('h1, .fi-header-heading'),
            popover: {
                title: '⚙️ Settings',
                description:
                    'Global configuration for branding and the AI assistant. Changes apply on next page load (cached for 1 hour).',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            popover: {
                title: 'Branding',
                description:
                    '<b>Logo</b> — upload a PNG/JPG/SVG (max 2 MB). Shows in the sidebar header.<br>'
                    + '<b>Brand name</b> — fallback text when no logo is set.<br>'
                    + '<b>Primary colour</b> — pick from swatches or enter a custom hex. Changes buttons, badges, and active nav items across the entire panel.',
                side: 'over',
                align: 'center',
            },
        },
        {
            popover: {
                title: '🤖 AI Assistant',
                description:
                    'Connects to a local Ollama instance — no data leaves your server.<br><br>'
                    + 'Toggle it on/off here. When enabled, a sparkles button appears on every page and loads context for what you\'re viewing. You can also use the dedicated AI Assistant page for full-screen chat.',
                side: 'over',
                align: 'center',
            },
        },
    ],

    general: [
        {
            popover: {
                title: '💡 Page Guide',
                description:
                    'Click the <b>?</b> button on any page to get a walkthrough for that specific section. The main overview tour is available from the Dashboard.',
                side: 'over',
                align: 'center',
            },
        },
    ],
};

// ── Driver instance ────────────────────────────────────────────────────────────

function buildDriver() {
    return driver({
        animate: true,
        showProgress: true,
        showButtons: ['next', 'previous', 'close'],
        nextBtnText: 'Next →',
        prevBtnText: '← Back',
        doneBtnText: 'Done',
        progressText: '{{current}} of {{total}}',
        onDestroyed: () => {
            localStorage.setItem(STORAGE_KEY, '1');
        },
    });
}

function startTour(section) {
    const steps = TOURS[section] || TOURS.general;
    const d = buildDriver();
    d.setSteps(steps);
    d.drive();
}

// ── Public API ─────────────────────────────────────────────────────────────────

window.vortexTour = {
    start() {
        startTour(page());
    },
    startOverview() {
        startTour('dashboard');
    },
    reset() {
        localStorage.removeItem(STORAGE_KEY);
    },
};

// Auto-start on very first visit
document.addEventListener('DOMContentLoaded', () => {
    if (!localStorage.getItem(STORAGE_KEY) && page() === 'dashboard') {
        // Small delay so Filament widgets finish rendering
        setTimeout(() => startTour('dashboard'), 800);
    }
});
