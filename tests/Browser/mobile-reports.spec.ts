import { test, expect } from '@playwright/test';

const pages = ['inventory-items', 'inventory-report', 'inventory-age', 'inventory-analytics', 'inventory-value-dashboard', 'inventory-velocity-analytics', 'reports', 'streamer-analytics'];

for (const width of [320, 390, 430, 768, 1440]) {
    test(`inventory and reports fit ${width}px`, async ({ page }) => {
        test.setTimeout(240000);
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/admin/login');
        await page.locator('input[type=email]').fill('dev@vortexbreaks.com');
        await page.locator('input[type=password]').fill('devpassword');
        await page.locator('button[type=submit]').click();
        await expect(page.locator('input[type=password]')).toHaveCount(0, { timeout: 20000 });
        for (const path of pages) {
            const response = await page.goto(`/admin/${path}`);
            expect(response?.status(), path).toBe(200);
            await page.waitForLoadState('networkidle');
            await expect(page.locator('.fi-main')).toBeVisible();
            const overflow = await page.evaluate(() => {
                const viewport = document.documentElement.clientWidth;
                return [...document.querySelectorAll('.fi-main, .fi-page-content, .vx-report, .vx-report .grid, .vx-kpis, .vx-catalog-grid')]
                    .filter((el) => el.getBoundingClientRect().width && (el.getBoundingClientRect().right > viewport + 2 || el.getBoundingClientRect().left < -2))
                    .map((el) => ({ class: el.className, width: el.getBoundingClientRect().width }));
            });
            expect(overflow, `${path} at ${width}`).toEqual([]);
            if (path === 'inventory-items' && width < 1024) {
                await expect(page.getByRole('button', { name: 'Table', exact: true })).toBeHidden();
                await expect(page.locator('.vx-catalog-view')).toBeVisible();
                const custom = page.getByRole('button', { name: 'Open navigation', exact: true });
                if (await custom.count()) {
                    await custom.click();
                    const menu = page.getByRole('dialog', { name: 'Main navigation' });
                    await expect(menu).toBeVisible();
                    await menu.getByRole('button', { name: 'Inventory', exact: true }).click();
                    await expect(menu.getByRole('link', { name: 'All Inventory', exact: true })).toBeVisible();
                    await page.keyboard.press('Escape');
                    await expect(menu).toBeHidden();
                    await page.getByRole('button', { name: 'Open full menu' }).click();
                    await expect(menu).toBeVisible();
                    await menu.getByRole('button', { name: 'Close navigation' }).click();
                } else {
                    await page.locator('.fi-topbar-open-sidebar-btn').click();
                    await expect(page.locator('.fi-sidebar')).toBeVisible();
                    await page.locator('.fi-topbar-close-sidebar-btn').click();
                }
            }
            await page.screenshot({ path: `tests/Browser/output/mobile-pass-${width}-${path}.png`, fullPage: true });
        }
    });
}

test('desktop table selection recovers cards when resized to mobile', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/admin/login');
    await page.locator('input[type=email]').fill('dev@vortexbreaks.com');
    await page.locator('input[type=password]').fill('devpassword');
    await page.locator('button[type=submit]').click();
    await expect(page.locator('input[type=password]')).toHaveCount(0, { timeout: 20000 });
    await page.goto('/admin/inventory-items');
    await page.getByRole('button', { name: 'Table', exact: true }).click();
    await expect(page.locator('.vx-inventory-items-panel')).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('.vx-inventory-items-panel')).toBeHidden();
    await expect(page.locator('.vx-catalog-view')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Table', exact: true })).toBeHidden();
});
