import { test, expect } from '@playwright/test';
import fs from 'node:fs';

test('assistant dashboard only clocks time and displays paid own payroll', async ({ page }) => {
    const fixture = JSON.parse(fs.readFileSync('storage/framework/testing/inventory-audit-fixture.json', 'utf8'));
    await page.goto('/admin/login');
    await page.locator('input[type=email]').fill(fixture.assistantEmail);
    await page.locator('input[type=password]').fill('inventory-audit-local');
    await page.locator('button[type=submit]').click();
    await expect(page.locator('input[type=password]')).toHaveCount(0);
    await page.waitForLoadState('networkidle');
    await page.goto(fixture.dashboardPath);
    await expect(page.locator('[data-vx-page="assistant-dashboard"]')).toBeVisible();
    const pay = page.getByRole('region', { name: 'Paid pay runs' });
    await expect(pay).toContainText('125.25');
    await expect(pay).not.toContainText('876.54');
    await expect(page.getByRole('button', { name: 'Clock in', exact: true })).toBeVisible();
    await expect(page.getByText('Choose a show', { exact: true })).toHaveCount(0);
    await expect(page.locator('a[href*="/admin/shows"],a[href*="/admin/inventory"],a[href*="/admin/show-leaderboard"]')).toHaveCount(0);
    await page.getByRole('button', { name: 'Clock in', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Clock out', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Clock out', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Clock in', exact: true })).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
    for (const path of ['/admin/shows', '/admin/inventory-items', '/admin/weekly-payout-batches', '/admin/show-leaderboard']) {
        const response = await page.request.get(path);
        expect(response.status(), path).toBe(403);
    }
});
