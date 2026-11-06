import { test, expect } from '@playwright/test';

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
        await page.goto('/admin/login');
        await page.locator('input[type=email]').fill('mockup-streamer@example.test');
        await page.locator('input[type=password]').fill('MockupTest123!');
        await page.getByRole('button', { name: 'Sign in', exact: true }).click();
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
        await expect(dialog.getByText('Inventory change fixture 24', { exact: true })).toBeVisible();
        await expect(dialog).toBeVisible();
        if (scenario.standalone) {
            // Chromium has no iOS status bar; emulate its reserved safe-area space.
            await dialog.evaluate(el => { el.style.paddingTop = '47px'; el.style.paddingBottom = '34px'; });
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
