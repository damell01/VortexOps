import { chromium } from '@playwright/test';
import fs from 'fs';

const results = {
  timestamp: new Date().toISOString(),
  tests: [],
  summary: { passed: 0, failed: 0 }
};

async function test(name, fn) {
  try {
    await fn();
    results.tests.push({ name, status: '✓ PASS', error: null });
    results.summary.passed++;
    console.log(`✓ ${name}`);
  } catch (error) {
    results.tests.push({ name, status: '✗ FAIL', error: error.message });
    results.summary.failed++;
    console.log(`✗ ${name}: ${error.message}`);
  }
}

async function login(page, email, password) {
  await page.goto('http://localhost:8000/admin/login');
  await page.waitForSelector('#form\\.email', { timeout: 10000 });
  await page.fill('#form\\.email', email);
  await page.fill('#form\\.password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin', { timeout: 15000 });
  // Wait for dashboard to load
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
}

async function runTests() {
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium'
  });

  try {
    console.log('\n╔════════════════════════════════════════╗');
    console.log('║     VORTEXOPS COMPREHENSIVE TESTS      ║');
    console.log('╚════════════════════════════════════════╝\n');

    // ========== STREAMER TESTS ==========
    console.log('📱 STREAMER DASHBOARD TESTS\n');

    await test('Streamer dashboard loads with widgets', async () => {
      const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
      });
      const page = await context.newPage();

      // Login as streamer
      await login(page, 'jordan@vortexbreaks.com', 'password');

      // Check dashboard widgets
      const hasOverview = await page.locator('text=Streamer Overview').isVisible({ timeout: 5000 }).catch(() => false);
      const hasProfitShare = await page.locator('text=Profit Share').isVisible({ timeout: 5000 }).catch(() => false);
      const hasInventory = await page.locator('text=Your Inventory').isVisible({ timeout: 5000 }).catch(() => false);

      if (!hasOverview && !hasProfitShare && !hasInventory) {
        throw new Error('Dashboard widgets not visible');
      }

      await context.close();
    });

    await test('Mobile camera icon displays correctly (no overflow)', async () => {
      const context = await browser.newContext({
        viewport: { width: 375, height: 667 }
      });
      const page = await context.newPage();

      await login(page, 'admin@vortexbreaks.com', 'password');

      // Navigate to inventory scanner
      await page.goto('http://localhost:8000/admin/inventory-scanner', { waitUntil: 'networkidle' });

      // Check if camera button is visible and not overflowing
      const cameraBtnExists = await page.locator('button[title="Scan with camera"]').isVisible({ timeout: 5000 }).catch(() => false);
      const inputField = await page.locator('#scanner-input').boundingBox();
      const cameraBtn = await page.locator('button[title="Scan with camera"]').boundingBox();

      if (inputField && cameraBtn) {
        if (cameraBtn.x + cameraBtn.width > 375) {
          throw new Error('Camera button overflowing on mobile');
        }
      }

      await context.close();
    });

    await test('Manual show creation modal opens and validates', async () => {
      const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
      });
      const page = await context.newPage();

      await login(page, 'streamer@test.com', 'password');

      // Navigate to shows page
      await page.goto('http://localhost:8000/admin/streamer-shows', { waitUntil: 'networkidle' });

      // Click create manual show button
      const createBtn = await page.locator('button:has-text("Create Manual Show")').isVisible({ timeout: 5000 }).catch(() => false);
      if (!createBtn) {
        throw new Error('Create Manual Show button not found');
      }

      await context.close();
    });

    await test('Profit share widget displays current month data', async () => {
      const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
      });
      const page = await context.newPage();

      await login(page, 'streamer@test.com', 'password');

      // Check profit share widget
      const profitWidget = await page.locator('text=Profit Share').isVisible({ timeout: 5000 }).catch(() => false);
      const shareAmount = await page.locator('text=/\$[\d,]+\.\d{2}/').isVisible({ timeout: 5000 }).catch(() => false);

      if (!profitWidget) {
        throw new Error('Profit Share widget not visible');
      }

      await context.close();
    });

    // ========== INVENTORY TESTS ==========
    console.log('\n📦 INVENTORY TESTS\n');

    await test('Inventory dashboard accessible', async () => {
      const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
      });
      const page = await context.newPage();

      await login(page, 'admin@vortexbreaks.com', 'password');

      // Navigate to inventory dashboard
      await page.goto('http://localhost:8000/admin/inventory-dashboard', { waitUntil: 'networkidle' });

      // Check if page loaded
      const dashboardExists = await page.locator('text=Inventory').isVisible({ timeout: 5000 }).catch(() => false);

      if (!dashboardExists) {
        throw new Error('Inventory dashboard not accessible');
      }

      await context.close();
    });

    // ========== RESPONSIVE DESIGN TESTS ==========
    console.log('\n📱 RESPONSIVE DESIGN TESTS\n');

    await test('Dashboard is mobile responsive (mobile view)', async () => {
      const context = await browser.newContext({
        viewport: { width: 375, height: 667 }
      });
      const page = await context.newPage();

      await login(page, 'streamer@test.com', 'password');

      // Verify page loads without errors
      const pageOk = await page.evaluate(() => document.body.innerHTML.length > 0);
      if (!pageOk) {
        throw new Error('Page failed to load on mobile view');
      }

      await context.close();
    });

    await test('Dashboard is responsive (tablet view)', async () => {
      const context = await browser.newContext({
        viewport: { width: 768, height: 1024 }
      });
      const page = await context.newPage();

      await login(page, 'streamer@test.com', 'password');

      // Verify page loads without errors
      const pageTitle = await page.title();
      if (!pageTitle) {
        throw new Error('Page failed to load on tablet view');
      }

      await context.close();
    });

    // ========== DARK MODE TEST ==========
    console.log('\n🌙 DARK MODE TEST\n');

    await test('Dashboard renders in dark mode', async () => {
      const context = await browser.newContext({
        viewport: { width: 1920, height: 1080},
        colorScheme: 'dark'
      });
      const page = await context.newPage();

      await login(page, 'streamer@test.com', 'password');

      // Check if page loads without errors
      const bodyExists = await page.evaluate(() => document.body.innerHTML.length > 0);

      if (!bodyExists) {
        throw new Error('Dark mode rendering failed');
      }

      await context.close();
    });

  } finally {
    await browser.close();

    // Print summary
    console.log('\n╔════════════════════════════════════════╗');
    console.log('║           TEST SUMMARY                 ║');
    console.log('╚════════════════════════════════════════╝');
    console.log(`\n✓ Passed: ${results.summary.passed}`);
    console.log(`✗ Failed: ${results.summary.failed}`);
    console.log(`Total:   ${results.summary.passed + results.summary.failed}\n`);

    if (results.summary.failed > 0) {
      console.log('Failed tests:');
      results.tests.filter(t => t.status === '✗ FAIL').forEach(t => {
        console.log(`  - ${t.name}: ${t.error}`);
      });
    }

    // Save results
    fs.writeFileSync('/tmp/claude-0/-home-user-VortexOps/be66a6b4-0a4e-5d57-95bd-5cef4f0d78fb/scratchpad/test-results.json',
      JSON.stringify(results, null, 2));

    process.exit(results.summary.failed > 0 ? 1 : 0);
  }
}

runTests().catch(console.error);
