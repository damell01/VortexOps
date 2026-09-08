import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';

const ROOT = 'public/guide/manual';
const FIXTURES = 'storage/handbook-screenshot-fixtures.json';
const EMAIL = 'dev@vortexbreaks.com';
const PASSWORD = 'devpassword';

type Fixtures = {
    show_id: number;
    review_log_id: number;
    fulfillment_show_id: number;
    pay_run_id: number;
};

function fixtures(): Fixtures {
    return JSON.parse(fs.readFileSync(FIXTURES, 'utf8')) as Fixtures;
}

async function visit(page: Page, url: string) {
    let lastError: unknown;
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
            await page.waitForTimeout(700);
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

async function ready(page: Page) {
    await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
    await page.waitForTimeout(500);
}

async function shot(page: Page, name: string) {
    fs.mkdirSync(ROOT, { recursive: true });
    await ready(page);
    await page.screenshot({ path: `${ROOT}/${name}.png`, fullPage: true });
}

test.describe('Current operational handbook screenshots', () => {
    test('shows and admin review', async ({ page }) => {
        const data = fixtures();
        await login(page);

        await visit(page, '/admin/shows');
        await shot(page, 'ops-shows-command-center');

        await visit(page, `/admin/shows/${data.show_id}`);
        await expect(page.getByText('Show Workspace', { exact: true })).toBeVisible({ timeout: 10000 });
        await shot(page, 'ops-show-workspace');

        await visit(page, `/admin/streamer-logs/${data.review_log_id}/edit`);
        await expect(page.getByText(/Admin Review Workspace|Streamer Report/).filter({ visible: true }).first()).toBeVisible({ timeout: 10000 });
        await shot(page, 'ops-admin-review');
    });

    test('fulfillment', async ({ page }) => {
        const data = fixtures();
        await login(page);

        await visit(page, '/admin/fulfillment-center');
        await shot(page, 'ops-fulfillment-center');

        await visit(page, `/admin/fulfillment-center/${data.fulfillment_show_id}`);
        await expect(page.getByText(/Packing Workstation|Pack Show/).filter({ visible: true }).first()).toBeVisible({ timeout: 10000 });
        await shot(page, 'ops-packing-workstation');
    });

    test('payroll and pay run', async ({ page }) => {
        const data = fixtures();
        await login(page);

        await visit(page, '/admin/payroll-overview');
        await shot(page, 'ops-payroll-command-center');

        await visit(page, `/admin/weekly-payout-batches/${data.pay_run_id}`);
        await expect(page.getByText(/Pay Run Workspace|Run Total|Total Payroll/).filter({ visible: true }).first()).toBeVisible({ timeout: 10000 });
        await shot(page, 'ops-pay-run-workspace');
    });
});
