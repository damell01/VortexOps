import { test, expect } from '@playwright/test';

// Read-only visual smoke tests. Set VX_BASE_URL and optionally VX_STORAGE_STATE
// (a Playwright storageState JSON for a development test account).
// Never run destructive workflows against production.
const baseURL = process.env.VX_BASE_URL || 'http://127.0.0.1:8000';
const routes = (process.env.VX_AUDIT_ROUTES || '/admin').split(',').map(s => s.trim()).filter(Boolean);
const widths = [320, 375, 390, 430, 768, 1024, 1280, 1440, 1920];
const storageState = process.env.VX_STORAGE_STATE || undefined;
const auditRole = process.env.VX_AUDIT_ROLE || 'unspecified';
const allowProduction = process.env.VX_ALLOW_PRODUCTION_AUDIT === '1';
const target = new URL(baseURL);
if (!allowProduction && !['localhost', '127.0.0.1', '::1'].includes(target.hostname) && !/dev|staging|test/i.test(target.hostname)) {
  throw new Error(`Refusing to audit an unrecognized environment: ${target.hostname}. Set VX_ALLOW_PRODUCTION_AUDIT=1 only for explicitly authorized read-only audits.`);
}

test.use({ baseURL, ...(storageState ? { storageState } : {}) });

for (const route of routes) {
  for (const width of widths) {
    test(`layout: ${auditRole} ${route} at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      const errors = [];
      const failedRequests = [];
      page.on('pageerror', error => errors.push(error.message));
      page.on('requestfailed', request => {
        if (!/^(?:data|blob):/.test(request.url())) failedRequests.push(`${request.method()} ${request.url()}: ${request.failure()?.errorText}`);
      });
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      expect(response?.status(), 'route HTTP status').toBeLessThan(400);
      // An authenticated route redirecting to login is not a successful audit.
      expect(new URL(page.url()).pathname.replace(/\/$/, '') || '/', 'Unexpected redirect: check role and storage state').toBe(route.replace(/\/$/, '') || '/');
      await page.locator('body').waitFor();
      await page.evaluate(() => document.fonts.ready);
      await expect(page.locator('body')).not.toBeEmpty();
      // Check the whole document; do not mask overflow with overflow-x:hidden.
      const metrics = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
      }));
      await page.screenshot({ path: `test-results/layout-${route.replace(/[^a-z0-9]+/gi, '-')}-${width}.png`, fullPage: true });
      expect(Math.max(metrics.document, metrics.body) - metrics.viewport,
        `horizontal overflow at ${route} (${width}px): ${JSON.stringify(metrics)}`).toBeLessThanOrEqual(2);
      expect(errors, `uncaught browser errors on ${route}`).toEqual([]);
      expect(failedRequests, `failed network requests on ${route}`).toEqual([]);
      const clippedControls = await page.evaluate(() => [...document.querySelectorAll('button, a, input, select, textarea')]
        .filter(el => {
          const r = el.getBoundingClientRect();
          const style = getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden' || !r.width || !r.height) return false;
          // Only report controls whose own rendered content is clipped; some icon buttons intentionally hide overflow.
          return el.scrollWidth > el.clientWidth + 3 && style.overflowX === 'hidden' && !!el.textContent?.trim();
        }).slice(0, 20).map(el => ({ tag: el.tagName, text: el.textContent.trim().slice(0, 70) })));
      expect(clippedControls, `Potentially clipped controls on ${route}`).toEqual([]);
    });
  }
}
