import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { mkdirSync, writeFileSync, appendFileSync } from 'fs';

const EMAIL    = 'dev@vortexbreaks.com';
const PASSWORD = 'devpassword';
const SS_DIR   = 'tests/Browser/screenshots';
const ERR_LOG  = 'tests/Browser/page-errors.log';

mkdirSync(SS_DIR, { recursive: true });

// ── Helpers ───────────────────────────────────────────────────────────────────

async function login(page: Page) {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
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

/** Navigate to a URL and verify no server error is returned. */
async function goto(page: Page, path: string, label: string): Promise<boolean> {
    let httpStatus = 200;
    const handler = (response: { url: () => string; status: () => number }) => {
        if (response.url().includes(path.replace(/\?.*/, ''))) {
            httpStatus = response.status();
        }
    };
    page.on('response', handler);
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    page.off('response', handler);

    // DOM-level error detection
    const bodyText = await page.textContent('body').catch(() => '');
    const title    = await page.title().catch(() => '');
    const hasDomError =
        title.includes('500') ||
        (bodyText?.includes('ErrorException') ?? false) ||
        (bodyText?.includes('Illuminate\\') && bodyText?.includes('Exception'));

    if (httpStatus >= 500 || hasDomError) {
        const msg = `[ERROR] ${label} → ${page.url()} (HTTP ${httpStatus})\n`;
        appendFileSync(ERR_LOG, msg);
        console.error(msg.trim());
        return false;
    }
    return true;
}

async function snap(page: Page, name: string) {
    await page.waitForLoadState('networkidle');
    await dismissTour(page);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${SS_DIR}/${name}.png`, fullPage: false });
}

async function scrollSnap(page: Page, name: string) {
    await page.waitForLoadState('networkidle');
    await dismissTour(page);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${SS_DIR}/${name}.png`, fullPage: true });
}

// ── Test ──────────────────────────────────────────────────────────────────────

test('VortexOps UI tour — pages and interactions', async ({ page }) => {
    // Clear error log for this run
    writeFileSync(ERR_LOG, `=== UI Tour run ${new Date().toISOString()} ===\n`);

    // ── 01. Login ──────────────────────────────────────────────────────────────
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await snap(page, '01-login-empty');

    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await snap(page, '02-login-filled');

    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1200);
    await dismissTour(page);

    // ── 02. Dashboard ──────────────────────────────────────────────────────────
    await snap(page, '03-dashboard');

    const statCard = page.locator('.fi-wi-stats-overview-stat-content').first();
    if (await statCard.isVisible().catch(() => false)) {
        await statCard.hover({ force: true });
        await page.waitForTimeout(300);
        await snap(page, '04-dashboard-stat-hover');
    }

    // Dark mode
    const darkBtn = page.locator('[data-toggle-theme], [x-on\\:click*="theme"], .fi-topbar button[title*="dark" i], .fi-topbar button[title*="theme" i]').first();
    if (await darkBtn.isVisible().catch(() => false)) {
        await darkBtn.click();
        await page.waitForTimeout(600);
    } else {
        await page.evaluate(() => document.documentElement.classList.add('dark'));
    }
    await snap(page, '05-dashboard-dark');
    await page.evaluate(() => document.documentElement.classList.remove('dark'));
    await page.waitForTimeout(200);

    // ── 03. Inventory Items ────────────────────────────────────────────────────
    await goto(page, '/admin/inventory-items', 'Inventory Items list');
    await snap(page, '06-inventory-items-list');

    const searchInput = page.locator('.fi-ta-search-input, input[placeholder*="Search"]').first();
    if (await searchInput.isVisible().catch(() => false)) {
        await searchInput.fill('Pokemon');
        await page.waitForTimeout(600);
        await snap(page, '07-inventory-search');
        await searchInput.clear();
        await page.waitForTimeout(400);
    }

    const createBtn = page.locator('button:has-text("Create"), a:has-text("Create inventory item"), [href*="create"]').first();
    if (await createBtn.isVisible().catch(() => false)) {
        await createBtn.click();
        await page.waitForTimeout(600);
        await snap(page, '08-inventory-create-form');
        const nameInput = page.locator('input[id*="name"], input[placeholder*="name" i]').first();
        if (await nameInput.isVisible().catch(() => false)) {
            await nameInput.fill('2024 Topps Chrome Hobby Box');
            await page.waitForTimeout(200);
            await snap(page, '09-inventory-form-filled');
        }
        await page.keyboard.press('Escape');
        await page.waitForTimeout(400);
    }

    const invRow = page.locator('.fi-ta-record-content').first();
    if (await invRow.isVisible().catch(() => false)) {
        await invRow.hover();
        await page.waitForTimeout(300);
        await snap(page, '10-inventory-row-hover');
        await invRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, '11-inventory-item-detail');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 04. Pallets ───────────────────────────────────────────────────────────
    await goto(page, '/admin/pallets', 'Pallets list');
    await snap(page, '12-pallets-list');

    const palletRow = page.locator('.fi-ta-record-content').first();
    if (await palletRow.isVisible().catch(() => false)) {
        await palletRow.hover();
        await page.waitForTimeout(200);
        await palletRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, '13-pallet-detail');
        await scrollSnap(page, '14-pallet-detail-full');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 05. Shows — list + detail + P&L section ───────────────────────────────
    await goto(page, '/admin/shows', 'Shows list');
    await snap(page, '15-shows-list');

    const showRow = page.locator('.fi-ta-record-content').first();
    if (await showRow.isVisible().catch(() => false)) {
        await showRow.hover();
        await page.waitForTimeout(200);
        await showRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, '16-show-detail');

        // Scroll to P&L Summary section
        const plSection = page.locator('text=P&L Summary').first();
        if (await plSection.isVisible().catch(() => false)) {
            await plSection.scrollIntoViewIfNeeded();
            await page.waitForTimeout(300);
            await snap(page, '17-show-pl-section');
        }

        // Check Raise Deduction button
        const raiseBtn = page.locator('button:has-text("Raise Deduction")').first();
        if (await raiseBtn.isVisible().catch(() => false)) {
            await raiseBtn.click();
            await page.waitForTimeout(500);
            await snap(page, '18-show-raise-deduction-modal');
            await page.keyboard.press('Escape');
            await page.waitForTimeout(300);
        }

        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 06. Shows — create form ────────────────────────────────────────────────
    await goto(page, '/admin/shows/create', 'Shows create');
    await snap(page, '19-show-create-form');
    await scrollSnap(page, '20-show-create-form-full');
    await page.goBack();
    await page.waitForLoadState('networkidle');

    // ── 07. Pending Approvals (Deduction Requests) ────────────────────────────
    await goto(page, '/admin/deduction-requests', 'Deduction Requests');
    await snap(page, '21-deduction-requests');

    // ── 08. Payouts ───────────────────────────────────────────────────────────
    await goto(page, '/admin/payouts', 'Payouts list');
    await snap(page, '22-payouts-list');

    const payoutRow = page.locator('.fi-ta-record-content').first();
    if (await payoutRow.isVisible().catch(() => false)) {
        await payoutRow.hover();
        await page.waitForTimeout(200);
        await payoutRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, '23-payout-detail');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 09. Weekly Pay Runs ────────────────────────────────────────────────────
    await goto(page, '/admin/weekly-payout-batches', 'Weekly Pay Runs');
    await snap(page, '24-weekly-pay-runs');

    // ── 10. Streamers — list + detail ─────────────────────────────────────────
    await goto(page, '/admin/streamers', 'Streamers list');
    await snap(page, '25-streamers-list');

    const streamerRow = page.locator('.fi-ta-record-content').first();
    if (await streamerRow.isVisible().catch(() => false)) {
        await streamerRow.hover();
        await page.waitForTimeout(200);
        await snap(page, '26-streamer-row-hover');
        await streamerRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, '27-streamer-detail');
        await scrollSnap(page, '28-streamer-detail-full');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 11. Streamer Loans — list + create ────────────────────────────────────
    await goto(page, '/admin/streamer-loans', 'Streamer Loans list');
    await snap(page, '29-streamer-loans-list');

    const loanCreateBtn = page.locator('a:has-text("New streamer loan"), button:has-text("Create"), a[href*="streamer-loans/create"]').first();
    if (await loanCreateBtn.isVisible().catch(() => false)) {
        await loanCreateBtn.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);
        await snap(page, '30-streamer-loan-create');
        // Fill minimal fields to show the form
        const streamerSelect = page.locator('[id*="streamer_id"]').first();
        if (await streamerSelect.isVisible().catch(() => false)) {
            await streamerSelect.click();
            await page.waitForTimeout(300);
            const firstOption = page.locator('[role="option"]').first();
            if (await firstOption.isVisible().catch(() => false)) {
                await firstOption.click();
                await page.waitForTimeout(200);
            }
        }
        const labelInput = page.locator('input[id*="label"]').first();
        if (await labelInput.isVisible().catch(() => false)) {
            await labelInput.fill('Equipment advance — camera gear');
            await page.waitForTimeout(200);
        }
        const amountInput = page.locator('input[id*="original_amount"]').first();
        if (await amountInput.isVisible().catch(() => false)) {
            await amountInput.fill('500');
            await page.waitForTimeout(200);
        }
        await snap(page, '31-streamer-loan-create-filled');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 12. Show Ingestion Logs ────────────────────────────────────────────────
    await goto(page, '/admin/show-ingestion-logs', 'Show Ingestion Logs');
    await snap(page, '32-show-ingestion-logs');

    const logRow = page.locator('.fi-ta-record-content').first();
    if (await logRow.isVisible().catch(() => false)) {
        await logRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, '33-ingestion-log-detail');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 13. Reports & Analytics ───────────────────────────────────────────────
    await goto(page, '/admin/reports', 'Reports page');
    await snap(page, '34-reports-30-days');
    await scrollSnap(page, '35-reports-30-days-full');

    // Switch to 90-day period
    const period90 = page.locator('button:has-text("Last 90 days")').first();
    if (await period90.isVisible().catch(() => false)) {
        await period90.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, '36-reports-90-days');
    }

    // Switch to this year
    const periodYear = page.locator('button:has-text("This year")').first();
    if (await periodYear.isVisible().catch(() => false)) {
        await periodYear.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await scrollSnap(page, '37-reports-year-full');
    }

    // ── 14. Inventory Locations ────────────────────────────────────────────────
    await goto(page, '/admin/inventory-locations', 'Inventory Locations');
    await snap(page, '38-inventory-locations');

    // ── 15. Inventory Movements ────────────────────────────────────────────────
    await goto(page, '/admin/inventory-movements', 'Inventory Movements');
    await snap(page, '39-inventory-movements');

    // ── 16. Inventory Stock ────────────────────────────────────────────────────
    await goto(page, '/admin/inventory-stock', 'Inventory Stock');
    await snap(page, '40-inventory-stock');

    // ── 17. Vendors ───────────────────────────────────────────────────────────
    await goto(page, '/admin/vendors', 'Vendors list');
    await snap(page, '41-vendors-list');

    // ── 18. Whatnot Channels ──────────────────────────────────────────────────
    await goto(page, '/admin/whatnot-channels', 'Whatnot Channels');
    await snap(page, '42-whatnot-channels');

    // ── 19. AI Assistant ──────────────────────────────────────────────────────
    await goto(page, '/admin/ai-assistant', 'AI Assistant');
    await snap(page, '43-ai-assistant');
    // Click "Operations Briefing" quick action to show button states
    const opsBriefBtn = page.locator('button:has-text("Operations Briefing")').first();
    if (await opsBriefBtn.isVisible().catch(() => false)) {
        await snap(page, '44-ai-assistant-buttons');
    }

    // ── 20. Users ─────────────────────────────────────────────────────────────
    await goto(page, '/admin/users', 'Users list');
    await snap(page, '45-users-list');

    // ── 21. Activity log ──────────────────────────────────────────────────────
    await goto(page, '/admin/activity-log', 'Activity Log');
    await snap(page, '46-activity-log');

    // ── 22. Settings — scroll to module toggles ───────────────────────────────
    await goto(page, '/admin/settings', 'Settings');
    await snap(page, '47-settings');
    await scrollSnap(page, '48-settings-full');

    // ── 23. Dropdown action menu ──────────────────────────────────────────────
    await goto(page, '/admin/inventory-items', 'Inventory Items (dropdown)');
    await page.waitForTimeout(500);
    const actionBtn = page.locator('.fi-dropdown-trigger, button[aria-haspopup="menu"]').first();
    if (await actionBtn.isVisible().catch(() => false)) {
        await actionBtn.click();
        await page.waitForTimeout(400);
        await snap(page, '49-dropdown-open');
        await page.keyboard.press('Escape');
    }

    // ── 24. Global search ─────────────────────────────────────────────────────
    await page.keyboard.press('Meta+k');
    await page.waitForTimeout(400);
    const searchOpen = page.locator('.fi-global-search-field, [role="dialog"][aria-label*="search" i]').first();
    if (await searchOpen.isVisible().catch(() => false)) {
        await page.keyboard.type('inventory');
        await page.waitForTimeout(600);
        await snap(page, '50-global-search');
        await page.keyboard.press('Escape');
    }

    // ── 25. Final dashboard ────────────────────────────────────────────────────
    await goto(page, '/admin', 'Dashboard final');
    await page.waitForTimeout(800);
    await scrollSnap(page, '51-dashboard-final');

    // ── Report error log ──────────────────────────────────────────────────────
    const { readFileSync: readLog } = await import('fs');
    const log = readLog(ERR_LOG, 'utf8');
    const hasErrors = log.includes('[ERROR]');
    if (hasErrors) {
        console.error('\n⚠️  Page errors detected during tour:\n' + log);
    } else {
        console.log('\n✅ All pages loaded without server errors.');
    }
    expect(hasErrors).toBe(false);
});
