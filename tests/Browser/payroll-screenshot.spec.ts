import { test, expect } from '@playwright/test';
import fs from 'node:fs';

const ROOT = 'tests/Browser/screenshots/payroll';

async function login(page) {
    await page.goto('/admin/login');
    await page.fill('input[type="email"]', 'dev@vortexbreaks.com');
    await page.fill('input[type="password"]', 'devpassword');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
}

async function shot(page, name: string) {
    fs.mkdirSync(ROOT, { recursive: true });
    await page.screenshot({ path: `${ROOT}/${name}.png`, fullPage: true });
}

test.describe('Payroll structures and automation screenshots', () => {
    test('payment structures, individual overrides and automation', async ({ page }) => {
        await login(page);
        await page.goto('/admin/payment-structures');
        await page.waitForLoadState('networkidle');
        await expect(page.getByRole('heading', { name: 'Payment Structures' })).toBeVisible();
        await shot(page, 'payment-structures');

        const adopt = page.getByRole('button', { name: 'Use Team Default' }).first();
        if (await adopt.count()) {
            page.once('dialog', dialog => dialog.accept());
            await adopt.click();
            await page.waitForTimeout(500);
        }

        const customize = page.getByRole('button', { name: 'Customize' }).first();
        if (await customize.count()) {
            await customize.click();
            await page.waitForTimeout(300);
            await shot(page, 'payment-structures-individual-override');
        }
    });

    test('historical pay run backfill preview', async ({ page }) => {
        await login(page);
        await page.goto('/admin/pay-run-backfill');
        await page.waitForLoadState('networkidle');
        await expect(page.getByRole('heading', { name: 'Pay Run Backfill' })).toBeVisible();
        await shot(page, 'pay-run-backfill-empty');

        await page.getByRole('button', { name: 'Preview / Dry Run' }).click();
        await page.waitForTimeout(1000);
        await shot(page, 'pay-run-backfill-preview');
    });

    test('weekly pay run rollup', async ({ page }) => {
        await login(page);
        await page.goto('/admin/weekly-payout-batches');
        await page.waitForLoadState('networkidle');
        await shot(page, 'weekly-pay-runs');

        const viewButton = page.locator('a[href*="/admin/weekly-payout-batches/"]').first();
        if (await viewButton.count()) {
            await viewButton.click();
            await page.waitForLoadState('networkidle');
            await shot(page, 'weekly-pay-run-detail');
        }
    });
});
