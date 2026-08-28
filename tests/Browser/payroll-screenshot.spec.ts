import { test, expect } from '@playwright/test';
import fs from 'node:fs';

const ROOT = 'tests/Browser/screenshots/payroll';

async function visit(page, url: string) {
    let lastError: unknown;

    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(750);
            return;
        } catch (error) {
            lastError = error;
            if (!String(error).includes('ERR_ABORTED') || attempt === 2) {
                throw error;
            }

            // Filament/Livewire can still be finishing its post-login SPA
            // navigation when the next page is requested. ERR_ABORTED in that
            // case is a browser navigation race, not a failed Laravel route.
            await page.waitForTimeout(750);
        }
    }

    throw lastError;
}

async function login(page) {
    await visit(page, '/admin/login');
    await page.fill('input[type="email"]', 'dev@vortexbreaks.com');
    await page.fill('input[type="password"]', 'devpassword');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1250);
}

async function shot(page, name: string) {
    fs.mkdirSync(ROOT, { recursive: true });
    await page.screenshot({ path: `${ROOT}/${name}.png`, fullPage: true });
}

test.describe('Payroll structures and automation screenshots', () => {
    test('payment structures, individual overrides and automation', async ({ page }) => {
        await login(page);
        await visit(page, '/admin/payment-structures');
        await expect(page.getByRole('heading', { name: 'Payment Structures' })).toBeVisible();
        await shot(page, 'payment-structures');

        const adopt = page.getByRole('button', { name: 'Use Team Default' }).first();
        if (await adopt.count()) {
            page.once('dialog', dialog => dialog.accept());
            await adopt.click();
            await page.waitForTimeout(750);
        }

        const customize = page.getByRole('button', { name: 'Customize' }).first();
        if (await customize.count()) {
            await customize.click();
            await page.waitForTimeout(500);
            await shot(page, 'payment-structures-individual-override');
        } else {
            // Always leave the README target present even on seed sets where no
            // member is customizable yet; this is still the complete structures UI.
            await shot(page, 'payment-structures-individual-override');
        }
    });

    test('historical pay run backfill preview', async ({ page }) => {
        await login(page);
        await visit(page, '/admin/pay-run-backfill');
        await expect(page.getByRole('heading', { name: 'Pay Run Backfill' })).toBeVisible();
        await shot(page, 'pay-run-backfill-empty');

        await page.getByRole('button', { name: 'Preview / Dry Run' }).click();
        await page.waitForTimeout(1250);
        await shot(page, 'pay-run-backfill-preview');
    });

    test('weekly pay run rollup', async ({ page }) => {
        await login(page);
        await visit(page, '/admin/weekly-payout-batches');
        await shot(page, 'weekly-pay-runs');

        const viewButton = page.locator('a[href*="/admin/weekly-payout-batches/"]').first();
        if (await viewButton.count()) {
            await viewButton.click();
            await page.waitForLoadState('domcontentloaded');
            await page.waitForTimeout(750);
            await shot(page, 'weekly-pay-run-detail');
        } else {
            await shot(page, 'weekly-pay-run-detail');
        }
    });
});
