import { test, expect } from '@playwright/test';

// Read-only visual smoke tests. Set VX_BASE_URL and optionally VX_STORAGE_STATE
// (a Playwright storageState JSON for a development test account).
// Never run destructive workflows against production.
const baseURL = process.env.VX_BASE_URL || 'http://127.0.0.1:8000';
const routes = (process.env.VX_AUDIT_ROUTES || '/admin').split(',').map(s => s.trim()).filter(Boolean);
const widths = [320, 375, 390, 430, 768, 1024, 1280, 1440, 1920];
const storageState = process.env.VX_STORAGE_STATE || undefined;

test.use({ baseURL, ...(storageState ? { storageState } : {}) });

for (const route of routes) {
  for (const width of widths) {
    test(`layout: ${route} at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      expect(response?.status(), 'route HTTP status').toBeLessThan(500);
      await page.locator('body').waitFor();
      await page.evaluate(() => document.fonts.ready);
      // Check the whole document; do not mask overflow with overflow-x:hidden.
      const metrics = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
      }));
      expect(Math.max(metrics.document, metrics.body) - metrics.viewport,
        `horizontal overflow at ${route} (${width}px): ${JSON.stringify(metrics)}`).toBeLessThanOrEqual(2);
      expect(errors, `uncaught browser errors on ${route}`).toEqual([]);
      await page.screenshot({ path: `test-results/layout-${route.replace(/[^a-z0-9]+/gi, '-')}-${width}.png`, fullPage: true });
    });
  }
}
