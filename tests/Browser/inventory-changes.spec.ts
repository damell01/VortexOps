import { test, expect } from '@playwright/test';
import fs from 'node:fs';

async function login(page, email: string) {
    const state = `storage/inventory-changes-auth-${email.replace(/[^a-z0-9]/gi, '_')}.json`;
    if (fs.existsSync(state)) {
        await page.context().addCookies(JSON.parse(fs.readFileSync(state, 'utf8')).cookies);
        await page.goto('/admin');
        return;
    }
    await page.goto('/admin/login');
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill('MockupTest123!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page.locator('input[type=password]')).toHaveCount(0, { timeout: 20000 });
    await page.context().storageState({ path: state });
}

for (const scenario of [
    { width: 390, height: 844, dark: true, standalone: true },
    { width: 390, height: 844, dark: false, standalone: false },
    { width: 1440, height: 900, dark: false, standalone: false },
    { width: 844, height: 390, dark: true, standalone: true },
]) {
    test(`one-tap popup and reachable close controls ${JSON.stringify(scenario)}`, async ({ page }) => {
        await page.setViewportSize({ width: scenario.width, height: scenario.height });
        await page.addInitScript(({ dark, standalone }) => {
            localStorage.setItem('theme', dark ? 'dark' : 'light');
            Object.defineProperty(navigator, 'standalone', { get: () => standalone });
        }, scenario);
        await login(page, 'mockup-streamer@example.test');
        await expect(page.locator('[data-vx-page=streamer-home]')).toBeVisible();
        const looseAngles = await page.locator('.fi-page-content').evaluate(el => {
            const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
            let count = 0;
            while (walker.nextNode()) if (/^[<>]$/.test(walker.currentNode.textContent?.trim() ?? '')) count++;
            return count;
        });
        expect(looseAngles).toBe(0);
        await page.waitForFunction(() => performance.getEntriesByType('resource').some(entry => /modal-lifecycle-.*\.js/.test(entry.name)));
        const trigger = page.getByRole('button', { name: /Recent Inventory Changes/ });
        const dialog = page.getByRole('dialog', { name: 'Recent Changes', exact: true });
        let opens = 0;
        page.on('request', request => {
            if (!request.url().includes('/update') || request.method() !== 'POST') return;
            try {
                const payload = request.postDataJSON();
                opens += (payload.components ?? []).flatMap(component => component.calls ?? []).filter(call => call.method === 'show').length;
            } catch { /* Unrelated request. */ }
        });
        await trigger.click();
        await expect(dialog).toBeVisible();
        expect(opens).toBe(1);
        // Exercise the global cleanup that previously sent Escape to this unmarked dialog.
        await page.evaluate(async () => {
            const resource = performance.getEntriesByType('resource').find(entry => /modal-lifecycle-.*\.js/.test(entry.name));
            const cleanup = await import(resource!.name);
            cleanup.reconcile();
        });
        await dialog.getByRole('button', { name: 'Price changes', exact: true }).click();
        await expect(dialog.getByRole('button', { name: 'Price changes', exact: true })).toHaveClass(/bg-purple-700/);
        await expect(dialog.getByText('Inventory change fixture 24', { exact: true })).toBeVisible();
        await expect(dialog).toBeVisible();
        if (scenario.standalone) {
            // Chromium has no iOS status bar; emulate its reserved safe-area space.
            await dialog.evaluate(el => { el.style.paddingTop = '47px'; el.style.paddingBottom = '34px'; });
            expect(await dialog.evaluate(el => getComputedStyle(el).paddingTop)).toBe('47px');
        }
        const scroll = dialog.locator('[data-inventory-changes-scroll]');
        await scroll.evaluate(el => { el.scrollTop = el.scrollHeight; });
        expect(await scroll.evaluate(el => el.scrollTop)).toBeGreaterThan(0);
        for (const button of [dialog.getByRole('button', { name: 'Close recent changes' }), dialog.getByRole('button', { name: 'Done', exact: true })]) {
            await expect(button).toBeInViewport();
            const box = (await button.boundingBox())!;
            expect(box.width).toBeGreaterThanOrEqual(44);
            expect(box.height).toBeGreaterThanOrEqual(44);
            expect(box.y).toBeGreaterThanOrEqual(scenario.standalone ? 47 : 0);
            expect(box.y + box.height).toBeLessThanOrEqual(scenario.height - (scenario.standalone ? 34 : 0));
        }
        await page.screenshot({ path: `tests/Browser/output/inventory-changes/popup-${scenario.width}-${scenario.height}-${scenario.dark ? 'dark' : 'light'}.png` });
        await dialog.getByRole('button', { name: 'Done', exact: true }).click();
        await expect(dialog).toHaveCount(0);
        await trigger.click();
        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', { name: 'Close recent changes' }).click();
        await expect(dialog).toHaveCount(0);
        await trigger.click();
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await trigger.click();
        await expect(dialog).toBeVisible();
        await dialog.click({ position: { x: 2, y: scenario.height / 2 } });
        await expect(dialog).toHaveCount(0);
        expect(opens).toBe(4);
        await expect(trigger).toBeEnabled();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
    });
}

for (const dark of [false, true]) {
    test(`activity logs read as labeled cards on phones (${dark ? 'dark' : 'light'})`, async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.addInitScript(dark => localStorage.setItem('theme', dark ? 'dark' : 'light'), dark);
        await login(page, fs.readFileSync('storage/mockup-owner.txt', 'utf8').trim());
        await page.goto('/admin/activity-logs');
        const table = page.locator('[data-vx-readable-log] table.fi-ta-table');
        await expect(table).toHaveClass(/vx-mobile-readable/);
        const description = 'Inventory item price and stock updated after receiving a restocked shipment';
        const row = table.locator('tr.fi-ta-row').filter({ hasText: description });
        await expect(row).toBeVisible();
        expect(await row.evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length)).toBe(1);
        for (const label of ['description', 'model', 'id', 'changed', 'by', 'when']) {
            const cell = row.locator(`td[data-vx-label="${label}"]`);
            await expect(cell).toBeVisible();
            const box = (await cell.boundingBox())!;
            expect(box.width).toBeGreaterThan(250);
            expect(box.x).toBeGreaterThanOrEqual(0);
            expect(box.x + box.width).toBeLessThanOrEqual(392);
        }
        const clipped = await row.locator('.fi-badge, .fi-ta-text-item').evaluateAll(elements => elements.filter(el => el.scrollWidth > el.clientWidth + 2).map(el => el.textContent));
        expect(clipped).toEqual([]);
        const boxes = await row.locator('td').evaluateAll(cells => cells.map(cell => cell.getBoundingClientRect()).filter(box => box.width && box.height).sort((a, b) => a.top - b.top).map(box => ({ top: box.top, bottom: box.bottom })));
        for (let i = 1; i < boxes.length; i++) expect(boxes[i].top).toBeGreaterThanOrEqual(boxes[i - 1].bottom - 1);
        await expect(page.locator('.vx-tabbar a[href*="fulfillment"]')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
        await page.screenshot({ path: `tests/Browser/output/inventory-changes/activity-log-${dark ? 'dark' : 'light'}.png`, fullPage: true });
    });
}
