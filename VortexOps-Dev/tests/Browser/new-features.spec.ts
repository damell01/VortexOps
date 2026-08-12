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

async function snap(page: Page, info: TestInfo, name: string, full = false) {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
    await dismissTour(page);
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${ssDir(info)}/${name}.png`, fullPage: full });
}

test('VortexOps — new feature screenshots', async ({ page }, info) => {
    await login(page);

    // Home dashboard — setup checklist, Needs Attention, overview widgets.
    await page.goto('/admin');
    await snap(page, info, '50-dashboard-overview', true);

    // Product Insights — margin / sell-through / dead stock.
    await page.goto('/admin/product-insights');
    await snap(page, info, '51-product-insights', true);

    const deadStock = page.getByRole('button', { name: /Dead Stock/i }).first();
    if (await deadStock.isVisible().catch(() => false)) {
        await deadStock.click();
        await page.waitForTimeout(600);
        await snap(page, info, '52-product-insights-dead-stock', true);
    }

    // Pipeline status board — time-in-status aging badges.
    await page.goto('/admin/show-status-board');
    await snap(page, info, '53-status-board-aging', true);

    // Shows list — Net Margin column + filter-preset tabs.
    await page.goto('/admin/shows');
    await snap(page, info, '54-shows-net-margin', false);
});
