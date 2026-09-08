import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';

const ROOT = 'public/guide/manual';
const EMAIL = 'dev@vortexbreaks.com';
const PASSWORD = 'devpassword';

async function visit(page: Page, url: string) {
    let lastError: unknown;
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
            await page.waitForTimeout(650);
            return;
        } catch (error) {
            lastError = error;
            await page.waitForTimeout(500);
        }
    }
    throw lastError;
}

async function login(page: Page) {
    await visit(page, '/admin/login');
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
    await page.waitForTimeout(900);
}

async function shot(page: Page, name: string) {
    fs.mkdirSync(ROOT, { recursive: true });
    await page.screenshot({ path: `${ROOT}/${name}.png`, fullPage: true });
}

async function openFirst(page: Page, selector: string): Promise<boolean> {
    const link = page.locator(selector).first();
    if (!(await link.count())) return false;
    await link.click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
    await page.waitForTimeout(650);
    return true;
}

test.describe('Current operational handbook screenshots', () => {
    test('shows and admin review', async ({ page }) => {
        await login(page);

        await visit(page, '/admin/shows');
        await expect(page.getByText(/Shows|Priority Work/i).first()).toBeVisible();
        await shot(page, 'ops-shows-command-center');

        const openedShow = await openFirst(page, 'a[href*="/admin/shows/"]');
        expect(openedShow).toBeTruthy();
        await shot(page, 'ops-show-workspace');

        await visit(page, '/admin/streamer-logs');
        const openedReview = await openFirst(page, 'a[href*="/admin/streamer-logs/"][href*="/edit"]');
        if (!openedReview) {
            await visit(page, '/admin/streamer-logs/1/edit');
        }
        await expect(page.getByText(/Admin Review Workspace|Streamer Report/i).first()).toBeVisible();
        await shot(page, 'ops-admin-review');
    });

    test('fulfillment', async ({ page }) => {
        await login(page);

        await visit(page, '/admin/fulfillment-center');
        await expect(page.getByText(/Fulfillment Workspace|Fulfillment/i).first()).toBeVisible();
        await shot(page, 'ops-fulfillment-center');

        const opened = await openFirst(page, 'a[href*="/admin/fulfillment-center/"]');
        expect(opened).toBeTruthy();
        await expect(page.getByText(/Packing Workstation|Pack Show|Packing/i).first()).toBeVisible();
        await shot(page, 'ops-packing-workstation');
    });

    test('payroll and pay run', async ({ page }) => {
        await login(page);

        await visit(page, '/admin/payroll-overview');
        await expect(page.getByText(/Payroll Command Center|Payroll/i).first()).toBeVisible();
        await shot(page, 'ops-payroll-command-center');

        await visit(page, '/admin/weekly-payout-batches');
        const opened = await openFirst(page, 'a[href*="/admin/weekly-payout-batches/"]');
        expect(opened).toBeTruthy();
        await expect(page.getByText(/Pay Run Workspace|Run Total|Total Payroll/i).first()).toBeVisible();
        await shot(page, 'ops-pay-run-workspace');
    });
});
