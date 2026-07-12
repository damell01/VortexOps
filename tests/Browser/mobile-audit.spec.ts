import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * Mobile regression guard: on a 390px phone viewport, no admin page should
 * scroll sideways. Horizontal overflow is the classic mobile-breaking bug —
 * a stray full-width table or fixed pixel width pushes the whole layout off
 * screen. This test drives the key pages and asserts the document never
 * exceeds the viewport width. Run with: npx playwright test mobile-audit
 * --project=mobile
 *
 * Screenshots land in the (gitignored) output dir for eyeballing, not the
 * tracked screenshots dir, so this stays a lightweight check.
 */

const EMAIL    = 'dev@vortexbreaks.com';
const PASSWORD = 'devpassword';

const PAGES: Array<[string, string]> = [
    ['dashboard', '/admin'],
    ['shows', '/admin/shows'],
    ['streamer-log', '/admin/streamer-logs'],
    ['streamer-log-edit', '/admin/streamer-logs/1/edit'],
    ['payouts', '/admin/payouts'],
    ['weekly-batches', '/admin/weekly-payout-batches'],
    ['product-insights', '/admin/product-insights'],
    ['inventory-items', '/admin/inventory-items'],
    ['pallets', '/admin/pallets'],
    ['streamers', '/admin/streamers'],
    ['how-it-works', '/admin/how-it-works'],
    ['status-board', '/admin/show-status-board'],
    ['deduction-requests', '/admin/deduction-requests'],
];

async function login(page: Page) {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(600);
}

test('no admin page overflows a 390px viewport', async ({ page }, info) => {
    test.skip(info.project.name !== 'mobile', 'mobile viewport only');

    await login(page);

    for (const [name, path] of PAGES) {
        await page.goto(path);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
        await page.waitForTimeout(400);

        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        );

        await page.screenshot({ path: `${info.outputDir}/mobile-${name}.png`, fullPage: true });

        // Allow 1px of sub-pixel rounding slack.
        expect(overflow, `${name} (${path}) scrolls sideways by ${overflow}px`).toBeLessThanOrEqual(1);
    }
});
