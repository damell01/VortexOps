import { test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';
import { mkdirSync } from 'fs';

const EMAIL    = 'dev@vortexbreaks.com';
const PASSWORD = 'devpassword';
const BASE_DIR = 'tests/Browser/screenshots';

function ssDir(info: TestInfo): string {
    const dir = `${BASE_DIR}/${info.project.name}`;
    mkdirSync(dir, { recursive: true });
    return dir;
}

async function login(page: Page) {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(800);
}

async function dismissTour(page: Page) {
    await page.evaluate(() => {
        document.querySelectorAll('.driver-overlay, .driver-popover').forEach((el) => el.remove());
    }).catch(() => {});
}

async function goto(page: Page, path: string) {
    await page.goto(path);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
}

async function snap(page: Page, info: TestInfo, name: string, full = false) {
    await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
    await dismissTour(page);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${ssDir(info)}/${name}.png`, fullPage: full });
}

test('VortexOps — remaining gallery shots (debug-off)', async ({ page }, info) => {
    const isMobile = info.project.name === 'mobile';
    await login(page);

    // Show action modal (16)
    await goto(page, '/admin/shows');
    const showRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    if (await showRow.isVisible().catch(() => false)) {
        await showRow.click();
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(600);
        const raiseBtn = page.locator('button:has-text("Raise Deduction"), button:has-text("Map Items")').first();
        if (await raiseBtn.isVisible().catch(() => false)) {
            await raiseBtn.click();
            await page.waitForTimeout(500);
            await snap(page, info, '16-show-action-modal');
            await page.keyboard.press('Escape');
        }
    }

    // Users, Activity Log, Settings
    await goto(page, '/admin/users');           await snap(page, info, '39-users-list');
    await goto(page, '/admin/activity-log');     await snap(page, info, '40-activity-log');
    await goto(page, '/admin/settings');         await snap(page, info, '41-settings');
    await snap(page, info, '42-settings-full', true);

    // Dropdown + global search (desktop only)
    if (!isMobile) {
        await goto(page, '/admin/inventory-items');
        await page.waitForTimeout(500);
        const actionBtn = page.locator('.fi-dropdown-trigger, button[aria-haspopup="menu"]').first();
        if (await actionBtn.isVisible().catch(() => false)) {
            await actionBtn.click();
            await page.waitForTimeout(400);
            await snap(page, info, '43-dropdown-open');
            await page.keyboard.press('Escape');
        }
        await page.keyboard.press('Meta+k');
        await page.waitForTimeout(400);
        const searchOpen = page.locator('.fi-global-search-field, [role="dialog"][aria-label*="search" i]').first();
        if (await searchOpen.isVisible().catch(() => false)) {
            await page.keyboard.type('inventory');
            await page.waitForTimeout(600);
            await snap(page, info, '44-global-search');
            await page.keyboard.press('Escape');
        }
    }

    // Receiving Sessions
    await goto(page, '/admin/receiving-sessions'); await snap(page, info, '46-receiving-sessions-list');
    await snap(page, info, '47-receiving-sessions-list-full', true);
    await goto(page, '/admin/receiving-sessions/create'); await snap(page, info, '48-receiving-session-create');

    // Product detail overview
    await goto(page, '/admin/inventory-items');
    const detailRow = page.locator('tr.fi-ta-row, .fi-ta-row').first();
    if (await detailRow.isVisible().catch(() => false)) {
        await detailRow.click();
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(700);
        await snap(page, info, '53-product-detail-overview');
        await page.goBack();
        await page.waitForLoadState('networkidle').catch(() => {});
    }

    // Product Health Dashboard
    await goto(page, '/admin/product-health-dashboard'); await snap(page, info, '57-product-health-dashboard');
    await snap(page, info, '58-product-health-dashboard-full', true);

    // Receiving Analytics
    await goto(page, '/admin/receiving-analytics'); await snap(page, info, '59-receiving-analytics');
    await snap(page, info, '60-receiving-analytics-full', true);

    // Duplicate Detector
    await goto(page, '/admin/duplicate-product-detector'); await snap(page, info, '61-duplicate-detector-empty');
    const scanBtn = page.locator('button:has-text("Scan for Duplicates")').first();
    if (await scanBtn.isVisible().catch(() => false)) {
        await scanBtn.click();
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(2000);
        await snap(page, info, '62-duplicate-detector-results');
        await snap(page, info, '63-duplicate-detector-results-full', true);
    }

    // Receiving Guide + tabs
    await goto(page, '/admin/receiving-guide'); await snap(page, info, '64-receiving-guide-workflow');
    for (const label of ['AI Matching', 'Scanner Modes', 'Product Catalog']) {
        const guideTab = page.locator(`button:has-text("${label}")`).first();
        if (await guideTab.isVisible().catch(() => false)) {
            await guideTab.click();
            await page.waitForTimeout(400);
            await snap(page, info, `65-receiving-guide-${label.toLowerCase().replace(/\s+/g, '-')}`);
        }
    }
    await snap(page, info, '66-receiving-guide-full', true);

    // Final dashboard scroll
    await goto(page, '/admin'); await page.waitForTimeout(600);
    await snap(page, info, '45-dashboard-final', true);
    await snap(page, info, '67-dashboard-final', true);
});
