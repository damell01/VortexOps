import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { mkdirSync } from 'fs';

const EMAIL    = 'dev@vortexbreaks.com';
const PASSWORD = 'devpassword';
const SS_DIR   = 'tests/Browser/screenshots';

mkdirSync(SS_DIR, { recursive: true });

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

/** Dismiss any driver.js / tour overlay before interacting */
async function dismissTour(page: Page) {
    const overlay = page.locator('.driver-overlay, .driver-popover');
    if (await overlay.first().isVisible().catch(() => false)) {
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
        // Also try clicking the close button
        const closeBtn = page.locator('.driver-popover-close-btn, button[aria-label*="close" i]').first();
        if (await closeBtn.isVisible().catch(() => false)) {
            await closeBtn.click();
            await page.waitForTimeout(200);
        }
    }
    // Remove the overlay from DOM if still present
    await page.evaluate(() => {
        document.querySelectorAll('.driver-overlay, .driver-popover').forEach(el => el.remove());
    });
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

test('VortexOps UI tour — pages and interactions', async ({ page }) => {

    // ── 01. Login page ─────────────────────────────────────────────────────────
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await snap(page, '01-login-empty');

    // Fill in credentials
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

    // Hover a stat card (force:true skips pointer-event checks)
    const statCard = page.locator('.fi-wi-stats-overview-stat-content').first();
    if (await statCard.isVisible().catch(() => false)) {
        await statCard.hover({ force: true });
        await page.waitForTimeout(300);
        await snap(page, '04-dashboard-stat-hover');
    }

    // ── 03. Dashboard dark mode ────────────────────────────────────────────────
    // Toggle dark mode via Filament theme button if present
    const darkBtn = page.locator('[data-toggle-theme], [x-on\\:click*="theme"], .fi-topbar button[title*="dark" i], .fi-topbar button[title*="theme" i]').first();
    if (await darkBtn.isVisible().catch(() => false)) {
        await darkBtn.click();
        await page.waitForTimeout(600);
    } else {
        await page.evaluate(() => document.documentElement.classList.add('dark'));
    }
    await snap(page, '05-dashboard-dark');
    // Back to light
    await page.evaluate(() => document.documentElement.classList.remove('dark'));
    await page.waitForTimeout(200);

    // ── 04. Inventory Items — list + create modal ──────────────────────────────
    await page.goto('/admin/inventory-items');
    await page.waitForLoadState('networkidle');
    await snap(page, '06-inventory-items-list');

    // Open search / filter
    const searchInput = page.locator('.fi-ta-search-input, input[placeholder*="Search"]').first();
    if (await searchInput.isVisible().catch(() => false)) {
        await searchInput.fill('Pokemon');
        await page.waitForTimeout(600);
        await snap(page, '07-inventory-search');
        await searchInput.clear();
        await page.waitForTimeout(400);
    }

    // Click Create button
    const createBtn = page.locator('button:has-text("Create"), a:has-text("Create inventory item"), [href*="create"]').first();
    if (await createBtn.isVisible().catch(() => false)) {
        await createBtn.click();
        await page.waitForTimeout(600);
        await snap(page, '08-inventory-create-form');
        // Fill in the name field
        const nameInput = page.locator('input[id*="name"], input[placeholder*="name" i]').first();
        if (await nameInput.isVisible().catch(() => false)) {
            await nameInput.fill('2024 Topps Chrome Hobby Box');
            await page.waitForTimeout(200);
            await snap(page, '09-inventory-form-filled');
        }
        // Close / go back
        await page.keyboard.press('Escape');
        await page.waitForTimeout(400);
    }

    // ── 05. Inventory Items — view a record ───────────────────────────────────
    const firstRow = page.locator('.fi-ta-record-content').first();
    if (await firstRow.isVisible().catch(() => false)) {
        await firstRow.hover();
        await page.waitForTimeout(300);
        await snap(page, '10-inventory-row-hover');
        await firstRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await snap(page, '11-inventory-item-detail');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 06. Pallets (Receiving) ────────────────────────────────────────────────
    await page.goto('/admin/pallets');
    await page.waitForLoadState('networkidle');
    await snap(page, '12-pallets-list');

    // Open a pallet if one exists
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

    // ── 07. Shows ─────────────────────────────────────────────────────────────
    await page.goto('/admin/shows');
    await page.waitForLoadState('networkidle');
    await snap(page, '15-shows-list');

    // Click first show
    const showRow = page.locator('.fi-ta-record-content').first();
    if (await showRow.isVisible().catch(() => false)) {
        await showRow.hover();
        await page.waitForTimeout(200);
        await showRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, '16-show-detail');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 08. Streamers ─────────────────────────────────────────────────────────
    await page.goto('/admin/streamers');
    await page.waitForLoadState('networkidle');
    await snap(page, '17-streamers-list');

    const streamerRow = page.locator('.fi-ta-record-content').first();
    if (await streamerRow.isVisible().catch(() => false)) {
        await streamerRow.hover();
        await page.waitForTimeout(200);
        await snap(page, '18-streamer-row-hover');
        await streamerRow.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        await snap(page, '19-streamer-detail');
        await page.goBack();
        await page.waitForLoadState('networkidle');
    }

    // ── 09. Inventory Locations ────────────────────────────────────────────────
    await page.goto('/admin/inventory-locations');
    await page.waitForLoadState('networkidle');
    await snap(page, '20-inventory-locations');

    // ── 10. Users ─────────────────────────────────────────────────────────────
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    await snap(page, '21-users-list');

    // ── 11. Activity log ──────────────────────────────────────────────────────
    await page.goto('/admin/activity-log');
    await page.waitForLoadState('networkidle');
    await snap(page, '22-activity-log');

    // ── 12. Settings ──────────────────────────────────────────────────────────
    await page.goto('/admin/settings');
    await page.waitForLoadState('networkidle');
    await snap(page, '23-settings');
    await scrollSnap(page, '24-settings-full');

    // ── 13. Open a dropdown (action menu) somewhere ────────────────────────────
    await page.goto('/admin/inventory-items');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    const actionBtn = page.locator('.fi-dropdown-trigger, button[aria-haspopup="menu"]').first();
    if (await actionBtn.isVisible().catch(() => false)) {
        await actionBtn.click();
        await page.waitForTimeout(400);
        await snap(page, '25-dropdown-open');
        await page.keyboard.press('Escape');
    }

    // ── 14. Global search ──────────────────────────────────────────────────────
    await page.keyboard.press('Meta+k');
    await page.waitForTimeout(400);
    const searchOpen = page.locator('.fi-global-search-field, [role="dialog"][aria-label*="search" i]').first();
    if (await searchOpen.isVisible().catch(() => false)) {
        await page.keyboard.type('inventory');
        await page.waitForTimeout(600);
        await snap(page, '26-global-search');
        await page.keyboard.press('Escape');
    }

    // ── 15. Final — dashboard overview scrolled ────────────────────────────────
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    await scrollSnap(page, '27-dashboard-final');
});
