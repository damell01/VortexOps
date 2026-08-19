import { test, expect } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';
import { mkdirSync, writeFileSync, appendFileSync } from 'fs';

const EMAIL    = 'dev@vortexbreaks.com';
const PASSWORD = 'devpassword';
const BASE_DIR = 'tests/Browser/screenshots';
const ERR_LOG  = 'tests/Browser/page-errors.log';

mkdirSync(`${BASE_DIR}/desktop`, { recursive: true });
mkdirSync(`${BASE_DIR}/mobile`,  { recursive: true });

// ── Helpers ───────────────────────────────────────────────────────────────────

function ssDir(info: TestInfo): string {
    return `${BASE_DIR}/${info.project.name}`;
}

async function login(page: Page) {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
}

async function dismissTour(page: Page) {
    const overlay = page.locator('.driver-overlay, .driver-popover');
    if (await overlay.first().isVisible().catch(() => false)) {
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
        const closeBtn = page.locator('.driver-popover-close-btn, button[aria-label*="close" i]').first();
        if (await closeBtn.isVisible().catch(() => false)) {
            await closeBtn.click();
            await page.waitForTimeout(200);
        }
    }
    await page.evaluate(() => {
        document.querySelectorAll('.driver-overlay, .driver-popover').forEach(el => el.remove());
    });
}

/** On mobile Filament collapses the sidebar — open it via the burger button if needed. */
async function openSidebarIfMobile(page: Page, info: TestInfo) {
    if (info.project.name !== 'mobile') return;
    const burger = page.locator(
        'button[aria-label*="sidebar" i], button[x-on\\:click*="sidebar" i], .fi-sidebar-open-btn, [x-on\\:click*="isSidebarOpen" i]'
    ).first();
    if (await burger.isVisible().catch(() => false)) {
        await burger.click();
        await page.waitForTimeout(400);
    }
}

/** Navigate to a URL and verify no server error is returned. */
async function goto(page: Page, path: string, label: string, errLog: string): Promise<boolean> {
    let httpStatus = 200;
    let mainHandled = false;
    const handler = (response: { url: () => string; status: () => number; request: () => { resourceType: () => string } }) => {
        const url  = response.url();
        const type = response.request().resourceType();
        if (type === 'document' && url.includes(path.replace(/\?.*/, '')) && !mainHandled) {
            httpStatus = response.status();
            mainHandled = true;
        }
    };
    page.on('response', handler);
    await page.goto(path);
    await page.waitForLoadState('domcontentloaded');
    // networkidle can hang with Livewire polling — wait up to 10s then continue
    await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {});
    page.off('response', handler);

    const bodyText = await page.textContent('body').catch(() => '');
    const title    = await page.title().catch(() => '');
    const hasDomError =
        title.includes('500') ||
        (bodyText?.includes('ErrorException') ?? false);

    if (httpStatus >= 500 || hasDomError) {
        const msg = `[ERROR] ${label} → ${page.url()} (HTTP ${httpStatus})\n`;
        appendFileSync(errLog, msg);
        console.error(msg.trim());
        return false;
    }
    return true;
}

async function snap(page: Page, info: TestInfo, name: string) {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
    await dismissTour(page);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${ssDir(info)}/${name}.png`, fullPage: false });
}

async function scrollSnap(page: Page, info: TestInfo, name: string) {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
    await dismissTour(page);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${ssDir(info)}/${name}.png`, fullPage: true });
}

// ── Test ──────────────────────────────────────────────────────────────────────

test('VortexOps UI tour — pages and interactions', async ({ page }, testInfo) => {
    const errLog = `${BASE_DIR}/${testInfo.project.name}-errors.log`;
    writeFileSync(errLog, `=== ${testInfo.project.name} tour — ${new Date().toISOString()} ===\n`);

    const isMobile = testInfo.project.name === 'mobile';

    // ── 01. Login ──────────────────────────────────────────────────────────────
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await snap(page, testInfo, '01-login-empty');

    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await snap(page, testInfo, '02-login-filled');

    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1200);
    await dismissTour(page);

    // ── 02. Dashboard ──────────────────────────────────────────────────────────
    await snap(page, testInfo, '03-dashboard');

    const statCard = page.locator('.fi-wi-stats-overview-stat-content').first();
    if (await statCard.isVisible().catch(() => false)) {
        await statCard.hover({ force: true });
        await page.waitForTimeout(300);
        await snap(page, testInfo, '04-dashboard-stat-hover');
    }

    // Dark mode toggle
    const darkBtn = page.locator('[data-toggle-theme], [x-on\\:click*="theme"], .fi-topbar button[title*="dark" i], .fi-topbar button[title*="theme" i]').first();
    if (await darkBtn.isVisible().catch(() => false)) {
        await darkBtn.click();
        await page.waitForTimeout(600);
    } else {
        await page.evaluate(() => document.documentElement.classList.add('dark'));
    }
    await snap(page, testInfo, '05-dashboard-dark');
    await page.evaluate(() => document.documentElement.classList.remove('dark'));
    await page.waitForTimeout(200);

    // ── 03. Inventory Items ────────────────────────────────────────────────────
    await goto(page, '/admin/inventory-items', 'Inventory Items list', errLog);
    await snap(page, testInfo, '06-inventory-items-list');

    const searchInput = page.locator('.fi-ta-search-input, input[placeholder*="Search"]').first();
    if (await searchInput.isVisible().catch(() => false)) {
        await searchInput.fill('Pokemon');
        await page.waitForTimeout(600);
        await snap(page, testInfo, '07-inventory-search');
        await searchInput.clear();
        await page.waitForTimeout(400);
    }

    const invRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    if (await invRow.isVisible().catch(() => false)) {
        await invRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, testInfo, '08-inventory-item-detail');
        await scrollSnap(page, testInfo, '09-inventory-item-detail-full');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 04. Pallets ───────────────────────────────────────────────────────────
    await goto(page, '/admin/pallets', 'Pallets list', errLog);
    await snap(page, testInfo, '10-pallets-list');

    const palletRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    if (await palletRow.isVisible().catch(() => false)) {
        await palletRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, testInfo, '11-pallet-detail');
        await scrollSnap(page, testInfo, '12-pallet-detail-full');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 05. Shows ─────────────────────────────────────────────────────────────
    await goto(page, '/admin/shows', 'Shows list', errLog);
    await snap(page, testInfo, '13-shows-list');

    // Advanced filter (QueryBuilder)
    const filterBtn = page.locator('button[aria-label="Filter"], button[title="Filter"]').first();
    if (await filterBtn.isVisible().catch(() => false)) {
        await filterBtn.click();
        await page.waitForTimeout(800);
        await snap(page, testInfo, '13b-shows-filter-panel');
        // open the advanced QueryBuilder constraint picker
        const addRuleBtn = page.locator('button:has-text("Add rule")').first();
        if (await addRuleBtn.isVisible().catch(() => false)) {
            await addRuleBtn.click();
            await page.waitForTimeout(400);
        }
        await snap(page, testInfo, '13c-shows-advanced-filter');
        await filterBtn.click(); // close
        await page.waitForTimeout(400);
    }

    const showRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    if (await showRow.isVisible().catch(() => false)) {
        await showRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, testInfo, '14-show-detail');
        await scrollSnap(page, testInfo, '15-show-detail-full');

        const raiseBtn = page.locator('button:has-text("Raise Deduction"), button:has-text("Map Items")').first();
        if (await raiseBtn.isVisible().catch(() => false)) {
            await raiseBtn.click();
            await page.waitForTimeout(500);
            await snap(page, testInfo, '16-show-action-modal');
            await page.keyboard.press('Escape');
            await page.waitForTimeout(300);
        }

        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    await goto(page, '/admin/shows/create', 'Shows create', errLog);
    await snap(page, testInfo, '17-show-create-form');
    await scrollSnap(page, testInfo, '18-show-create-form-full');
    await page.goBack();
    await page.waitForLoadState('networkidle');

    // ── 06. Deduction Requests ────────────────────────────────────────────────
    await goto(page, '/admin/deduction-requests', 'Deduction Requests', errLog);
    await snap(page, testInfo, '19-deduction-requests');

    // ── 07. Payouts ───────────────────────────────────────────────────────────
    await goto(page, '/admin/payouts', 'Payouts list', errLog);
    await snap(page, testInfo, '20-payouts-list');

    // Group by Streamer — Filament v5 uses a <select> for grouping
    // Find the first visible select that has a "Streamer" option
    const allSelectEls = await page.locator('select:visible').all();
    let groupSelect: ReturnType<typeof page.locator> | null = null;
    for (const sel of allSelectEls) {
        const opts = await sel.locator('option').allTextContents();
        if (opts.some(o => o.trim() === 'Streamer')) { groupSelect = sel; break; }
    }
    if (groupSelect) {
        await snap(page, testInfo, '20b-payouts-group-menu');
        await groupSelect.selectOption({ label: 'Streamer' });
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, testInfo, '20c-payouts-grouped-by-streamer');
        await groupSelect.selectOption({ index: 0 });
        await page.waitForTimeout(400);
    }

    const payoutRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    if (await payoutRow.isVisible().catch(() => false)) {
        await payoutRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, testInfo, '21-payout-detail');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 08. Weekly Pay Runs ────────────────────────────────────────────────────
    await goto(page, '/admin/weekly-payout-batches', 'Weekly Pay Runs', errLog);
    await snap(page, testInfo, '22-weekly-pay-runs');

    // ── 09. Streamers ─────────────────────────────────────────────────────────
    await goto(page, '/admin/streamers', 'Streamers list', errLog);
    await snap(page, testInfo, '23-streamers-list');

    const streamerRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    if (await streamerRow.isVisible().catch(() => false)) {
        await streamerRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, testInfo, '24-streamer-detail');
        await scrollSnap(page, testInfo, '25-streamer-detail-full');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 10. Streamer Loans ────────────────────────────────────────────────────
    await goto(page, '/admin/streamer-loans', 'Streamer Loans list', errLog);
    await snap(page, testInfo, '26-streamer-loans-list');

    // ── 11. Show Ingestion Logs ────────────────────────────────────────────────
    await goto(page, '/admin/show-ingestion-logs', 'Show Ingestion Logs', errLog);
    await snap(page, testInfo, '27-show-ingestion-logs');

    // ── 12. Reports ───────────────────────────────────────────────────────────
    await goto(page, '/admin/reports', 'Reports page', errLog);
    await snap(page, testInfo, '28-reports');
    await scrollSnap(page, testInfo, '29-reports-full');

    const period90 = page.locator('button:has-text("Last 90 days")').first();
    if (await period90.isVisible().catch(() => false)) {
        await period90.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, testInfo, '30-reports-90-days');
    }

    // ── 13. Inventory Locations / Movements / Stock ───────────────────────────
    await goto(page, '/admin/inventory-locations', 'Inventory Locations', errLog);
    await snap(page, testInfo, '31-inventory-locations');

    await goto(page, '/admin/inventory-movements', 'Inventory Movements', errLog);
    await snap(page, testInfo, '32-inventory-movements');

    await goto(page, '/admin/inventory-stock', 'Inventory Stock', errLog);
    await snap(page, testInfo, '33-inventory-stock');

    // ── 14. Vendors ───────────────────────────────────────────────────────────
    await goto(page, '/admin/vendors', 'Vendors list', errLog);
    await snap(page, testInfo, '34-vendors-list');

    // ── 15. Whatnot Channels ──────────────────────────────────────────────────
    await goto(page, '/admin/whatnot-channels', 'Whatnot Channels', errLog);
    await snap(page, testInfo, '35-whatnot-channels');

    // ── 15b. Inventory Scanner ────────────────────────────────────────────────
    await goto(page, '/admin/inventory-scanner', 'Inventory Scanner', errLog);
    await snap(page, testInfo, '35b-inventory-scanner');
    // Simulate a barcode typed by a Bluetooth scanner
    const scanInput = page.locator('#scanner-input').first();
    if (await scanInput.isVisible().catch(() => false)) {
        await scanInput.fill('TEST-SKU-001');
        await page.waitForTimeout(300);
        await snap(page, testInfo, '35c-inventory-scanner-typed');
        await scanInput.press('Enter');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, testInfo, '35d-inventory-scanner-result');
        await scrollSnap(page, testInfo, '35e-inventory-scanner-result-full');
    }

    // ── 15c. Log Viewer ───────────────────────────────────────────────────────
    await goto(page, '/admin/log-viewer', 'Log Viewer', errLog);
    await snap(page, testInfo, '35f-log-viewer');
    await scrollSnap(page, testInfo, '35g-log-viewer-full');
    // Click an error entry if present
    const firstEntry = page.locator('.fi-page button.w-full').first();
    if (await firstEntry.isVisible().catch(() => false)) {
        await firstEntry.click();
        await page.waitForTimeout(400);
        await snap(page, testInfo, '35h-log-viewer-expanded');
    }

    // ── 16. Vortex AI chat panel ──────────────────────────────────────────────
    await goto(page, '/admin', 'Dashboard (AI chat panel)', errLog);
    await page.waitForTimeout(800);

    const aiToggle = page.locator('button:has-text("Vortex AI")').first();
    if (await aiToggle.isVisible().catch(() => false)) {
        await snap(page, testInfo, '36-ai-panel-closed');
        await aiToggle.click();
        await page.waitForTimeout(500);
        await snap(page, testInfo, '37-ai-panel-open');

        const aiInput = page.locator('input[placeholder*="Vortex AI"], input[placeholder*="Ask"]').first();
        if (await aiInput.isVisible().catch(() => false)) {
            await aiInput.fill('How many shows are pending review?');
            await page.waitForTimeout(300);
            await snap(page, testInfo, '38-ai-panel-typed');
        }

        await aiToggle.click();
        await page.waitForTimeout(300);
    } else {
        await snap(page, testInfo, '36-ai-panel-closed');
    }

    // ── 17. Users ─────────────────────────────────────────────────────────────
    await goto(page, '/admin/users', 'Users list', errLog);
    await snap(page, testInfo, '39-users-list');

    // ── 18. Activity Log ──────────────────────────────────────────────────────
    await goto(page, '/admin/activity-log', 'Activity Log', errLog);
    await snap(page, testInfo, '40-activity-log');

    // ── 19. Settings ──────────────────────────────────────────────────────────
    await goto(page, '/admin/settings', 'Settings', errLog);
    await snap(page, testInfo, '41-settings');
    await scrollSnap(page, testInfo, '42-settings-full');

    // ── 20. Global search (desktop only — keyboard shortcut) ──────────────────
    if (!isMobile) {
        await goto(page, '/admin/inventory-items', 'Inventory Items (dropdown)', errLog);
        await page.waitForTimeout(500);
        const actionBtn = page.locator('.fi-dropdown-trigger, button[aria-haspopup="menu"]').first();
        if (await actionBtn.isVisible().catch(() => false)) {
            await actionBtn.click();
            await page.waitForTimeout(400);
            await snap(page, testInfo, '43-dropdown-open');
            await page.keyboard.press('Escape');
        }

        await page.keyboard.press('Meta+k');
        await page.waitForTimeout(400);
        const searchOpen = page.locator('.fi-global-search-field, [role="dialog"][aria-label*="search" i]').first();
        if (await searchOpen.isVisible().catch(() => false)) {
            await page.keyboard.type('inventory');
            await page.waitForTimeout(600);
            await snap(page, testInfo, '44-global-search');
            await page.keyboard.press('Escape');
        }
    }

    // ── 21. Mobile sidebar open state ─────────────────────────────────────────
    if (isMobile) {
        await goto(page, '/admin', 'Dashboard mobile sidebar', errLog);
        await page.waitForTimeout(500);
        await openSidebarIfMobile(page, testInfo);
        await snap(page, testInfo, '43-sidebar-open');
    }

    // ── 23. Receiving Sessions ────────────────────────────────────────────────
    await goto(page, '/admin/receiving-sessions', 'Receiving Sessions list', errLog);
    await snap(page, testInfo, '46-receiving-sessions-list');
    await scrollSnap(page, testInfo, '47-receiving-sessions-list-full');

    // Create form
    await goto(page, '/admin/receiving-sessions/create', 'Receiving Session create', errLog);
    await snap(page, testInfo, '48-receiving-session-create');
    await page.goBack();
    await page.waitForLoadState('networkidle');

    // Review page — navigate to first session if one exists
    const sessionRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    const sessionUrl = '/admin/receiving-sessions';
    await goto(page, sessionUrl, 'Receiving Sessions for review nav', errLog);
    if (await sessionRow.isVisible().catch(() => false)) {
        await sessionRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, testInfo, '49-receiving-session-review');
        await scrollSnap(page, testInfo, '50-receiving-session-review-full');
        // Click "Preview what will happen" toggle
        const previewBtn = page.locator('button:has-text("Preview")').first();
        if (await previewBtn.isVisible().catch(() => false)) {
            await previewBtn.click();
            await page.waitForTimeout(400);
            await snap(page, testInfo, '51-receiving-session-preview');
        }
        // Show auto section
        const showBtn = page.locator('button:has-text("Show")').first();
        if (await showBtn.isVisible().catch(() => false)) {
            await showBtn.click();
            await page.waitForTimeout(400);
            await snap(page, testInfo, '52-receiving-session-auto-expanded');
        }
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 24. Product Detail Page (new tabbed view) ─────────────────────────────
    await goto(page, '/admin/inventory-items', 'Inventory Items for detail', errLog);
    const detailRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    if (await detailRow.isVisible().catch(() => false)) {
        await detailRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, testInfo, '53-product-detail-overview');
        // Click Lots tab
        const lotsTab = page.locator('button:has-text("Lots")').first();
        if (await lotsTab.isVisible().catch(() => false)) {
            await lotsTab.click();
            await page.waitForTimeout(400);
            await snap(page, testInfo, '54-product-detail-lots');
        }
        // Click Aliases & AI tab
        const aliasTab = page.locator('button:has-text("Aliases")').first();
        if (await aliasTab.isVisible().catch(() => false)) {
            await aliasTab.click();
            await page.waitForTimeout(400);
            await snap(page, testInfo, '55-product-detail-aliases');
        }
        // Click Receiving History tab
        const receivingTab = page.locator('button:has-text("Receiving")').first();
        if (await receivingTab.isVisible().catch(() => false)) {
            await receivingTab.click();
            await page.waitForTimeout(400);
            await snap(page, testInfo, '56-product-detail-receiving');
        }
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 25. Catalog Intelligence (Product Health Dashboard) ───────────────────
    await goto(page, '/admin/product-health-dashboard', 'Product Health Dashboard', errLog);
    await snap(page, testInfo, '57-product-health-dashboard');
    await scrollSnap(page, testInfo, '58-product-health-dashboard-full');

    // ── 26. Receiving Analytics ───────────────────────────────────────────────
    await goto(page, '/admin/receiving-analytics', 'Receiving Analytics', errLog);
    await snap(page, testInfo, '59-receiving-analytics');
    await scrollSnap(page, testInfo, '60-receiving-analytics-full');

    // ── 27. Duplicate Detector ────────────────────────────────────────────────
    await goto(page, '/admin/duplicate-product-detector', 'Duplicate Product Detector', errLog);
    await snap(page, testInfo, '61-duplicate-detector-empty');
    // Run the scan
    const scanBtn = page.locator('button:has-text("Scan for Duplicates")').first();
    if (await scanBtn.isVisible().catch(() => false)) {
        await scanBtn.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await snap(page, testInfo, '62-duplicate-detector-results');
        await scrollSnap(page, testInfo, '63-duplicate-detector-results-full');
    }

    // ── 28. Inventory Guide ───────────────────────────────────────────────────
    await goto(page, '/admin/inventory-guide', 'Inventory Guide', errLog);
    await snap(page, testInfo, '64-inventory-guide-workflow');
    // Click through guide tabs
    const guideTabLabels = ['AI Matching', 'Scanner Modes', 'Product Catalog'];
    for (const label of guideTabLabels) {
        const guideTab = page.locator(`button:has-text("${label}")`).first();
        if (await guideTab.isVisible().catch(() => false)) {
            await guideTab.click();
            await page.waitForTimeout(400);
            const slug = label.toLowerCase().replace(/\s+/g, '-');
            await snap(page, testInfo, `65-inventory-guide-${slug}`);
        }
    }
    await scrollSnap(page, testInfo, '66-inventory-guide-full');

    // ── 22. Final dashboard ────────────────────────────────────────────────────
    await goto(page, '/admin', 'Dashboard final', errLog);
    await page.waitForTimeout(800);
    await scrollSnap(page, testInfo, '67-dashboard-final');

    // ── Report ────────────────────────────────────────────────────────────────
    const { readFileSync: readLog } = await import('fs');
    const log = readLog(errLog, 'utf8');
    const hasErrors = log.includes('[ERROR]');
    if (hasErrors) {
        console.error(`\n⚠️  Page errors (${testInfo.project.name}):\n` + log);
    } else {
        console.log(`\n✅ ${testInfo.project.name}: all pages loaded without server errors.`);
    }
    expect(hasErrors).toBe(false);
});
