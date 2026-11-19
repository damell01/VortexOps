import { test, expect } from '@playwright/test';
import fs from 'node:fs';

test('payroll workspace, full-width creation and persistent clock reminder', async ({ page }, testInfo) => {
    await page.goto('/admin/login');
    await page.fill('input[type="email"]', 'dev@vortexbreaks.com');
    await page.fill('input[type="password"]', 'devpassword');
    await page.click('button[type="submit"]');
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    await page.goto('/admin/timekeeping');
    const banner = page.locator('[data-vx="global-clock-banner"]');
    if (!(await banner.getByText('You are clocked in', { exact: true }).isVisible())) {
        await page.locator('[data-vx="clock-widget"]').getByRole('button', { name: 'Clock in', exact: true }).click();
    }
    await expect(banner.getByText('You are clocked in', { exact: true })).toBeVisible();
    await page.goto('/admin/payroll-overview');
    await expect(page.getByRole('heading', { name: 'Payout trend', exact: true })).toBeVisible();
    await expect(banner.getByText('You are clocked in', { exact: true })).toBeVisible();
    fs.mkdirSync('tests/Browser/screenshots/payroll-workspace', { recursive: true });
    await page.screenshot({ path: `tests/Browser/screenshots/payroll-workspace/overview-${testInfo.project.name}.png`, fullPage: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2)).toBe(false);
    await page.goto('/admin/payment-structures');
    await expect(banner.getByText('You are clocked in', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Rates & Adjustments', exact: true }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'New Pay Run', exact: true })).toBeVisible();
    fs.mkdirSync('tests/Browser/screenshots/payroll-workspace', { recursive: true });
    await page.screenshot({ path: `tests/Browser/screenshots/payroll-workspace/rates-${testInfo.project.name}.png`, fullPage: true });
    const createType = page.getByRole('link', { name: '+ Create run', exact: true }).first();
    await expect(createType).toBeVisible();
    await createType.click();
    // Pay-run creation now uses Filament's step-by-step wizard.
    await expect(page.getByText('What to pay', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Choose your pay run', { exact: true })).toBeVisible();
    await expect(page.getByText('Set up the run', { exact: true })).toBeVisible();
    await expect(banner.getByText('You are clocked in', { exact: true })).toBeVisible();
    await page.screenshot({ path: `tests/Browser/screenshots/payroll-workspace/create-${testInfo.project.name}.png`, fullPage: true });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2);
    expect(overflow).toBe(false);
    await banner.getByRole('button', { name: 'Clock out', exact: true }).click();
    await expect(banner.getByText('You are clocked in', { exact: true })).toHaveCount(0);
    await page.goto('/admin/payment-structures');
    await expect(banner.getByText('You are clocked in', { exact: true })).toHaveCount(0);
});
