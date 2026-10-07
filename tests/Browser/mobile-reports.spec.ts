import { test, expect } from '@playwright/test';
import fs from 'node:fs';

const authState = 'storage/mobile-report-browser-auth.json';
test.use({ storageState: authState, actionTimeout: 10000 });
test.beforeAll(async ({ browser }) => {
    if (fs.existsSync(authState)) return;
    const context = await browser.newContext({ storageState: undefined });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.locator('input[type=email]').fill('dev@vortexbreaks.com');
    await page.locator('input[type=password]').fill('devpassword');
    await page.locator('button[type=submit]').click();
    await expect(page.locator('input[type=password]')).toHaveCount(0, { timeout: 20000 });
    await context.storageState({ path: authState });
    await context.close();
});

const pages = ['inventory-items', 'inventory-report', 'inventory-age', 'inventory-analytics', 'inventory-value-dashboard', 'inventory-velocity-analytics', 'reports', 'streamer-analytics', 'shows', 'payroll-overview'];

for (const width of [320, 390, 430, 768, 1440]) {
    test(`inventory and reports fit ${width}px`, async ({ page }, testInfo) => {
        test.setTimeout(240000);
        await page.setViewportSize({ width, height: 900 });
        for (const path of pages) {
            const response = await page.goto(`/admin/${path}`);
            expect(response?.status(), path).toBe(200);
            await page.waitForLoadState('networkidle');
            await expect(page.locator('.fi-main')).toBeVisible();
            const overflow = await page.evaluate(() => {
                const viewport = document.documentElement.clientWidth;
                return [...document.querySelectorAll('.fi-main, .fi-page-content, .vx-report, .vx-report .grid, .vx-kpis, .vx-catalog-grid, .vx-mobile-report-table')]
                    .filter((el) => el.getBoundingClientRect().width && (el.getBoundingClientRect().right > viewport + 2 || el.getBoundingClientRect().left < -2))
                    .map((el) => {
                        const ancestors = []; let parent = el.parentElement;
                        while (parent && ancestors.length < 5) { const css = getComputedStyle(parent); ancestors.push({ class: parent.className, width: parent.getBoundingClientRect().width, minWidth: css.minWidth, display: css.display }); parent = parent.parentElement; }
                        return { class: el.className, width: el.getBoundingClientRect().width, ancestors };
                    });
            });
            expect(overflow, `${path} at ${width}`).toEqual([]);
            const clippedValues = await page.locator('.vx-kpi-value, .vx-an-kpi-value, .vx-metric-value').evaluateAll((elements) => elements.filter((el) => el.getBoundingClientRect().width && el.scrollWidth > el.clientWidth + 2).map((el) => el.textContent));
            expect(clippedValues, `${path} KPI values at ${width}`).toEqual([]);
            if (path === 'inventory-items' && width < 1024) {
                await expect(page.getByRole('button', { name: 'Table', exact: true })).toBeHidden();
                await expect(page.locator('.vx-catalog-view')).toBeVisible();
                if (width < 640) {
                    const columns = await page.locator('.vx-catalog-grid').evaluate((el) => getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/).length);
                    expect(columns, 'Phone cards must have enough room for prices and actions').toBe(1);
                }
                const custom = page.getByRole('button', { name: 'Open navigation', exact: true });
                if (await custom.count()) {
                    await custom.click();
                    const menu = page.getByRole('dialog', { name: 'Main navigation' });
                    await expect(menu).toBeVisible();
                    // Exercise the cleanup module even when it arrives after the
                    // drawer opens (the Safari timing that previously closed it).
                    await page.waitForFunction(() => performance.getEntriesByType('resource').some((entry) => /modal-lifecycle-.*\.js/.test(entry.name)));
                    await page.evaluate(async () => {
                        const url = performance.getEntriesByType('resource').find((entry) => /modal-lifecycle-.*\.js/.test(entry.name))!.name;
                        const cleanup = await import(url);
                        cleanup.reconcile();
                    });
                    await expect(menu).toBeVisible();
                    await menu.getByRole('button', { name: /Inventory/ }).first().click();
                    await expect(menu.locator('a[href$="/admin/inventory-items"]')).toBeVisible();
                    await page.keyboard.press('Escape');
                    await expect(menu).toBeHidden();
                    if (await page.getByRole('button', { name: 'Open full menu' }).count()) { await page.getByRole('button', { name: 'Open full menu' }).click(); } else { await custom.click(); }
                    await expect(menu).toBeVisible();
                    await page.screenshot({ path: `tests/Browser/output/mobile-menu-${testInfo.project.name}-${width}.png` });
                    await menu.getByRole('button', { name: 'Close navigation' }).click();
                } else {
                    await page.locator('.fi-topbar-open-sidebar-btn').click();
                    await expect(page.locator('.fi-sidebar')).toBeVisible();
                    await page.locator('.fi-topbar-close-sidebar-btn').click();
                }
            }
            await page.screenshot({ path: `tests/Browser/output/mobile-pass-${testInfo.project.name}-${width}-${path}.png`, fullPage: true });
        }
    });
}

test('desktop table selection recovers cards when resized to mobile', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/admin/inventory-items');
    await page.getByRole('button', { name: 'Table', exact: true }).click();
    await expect(page.locator('.vx-inventory-items-panel')).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('.vx-inventory-items-panel')).toBeHidden();
    await expect(page.locator('.vx-catalog-view')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Table', exact: true })).toBeHidden();
});
