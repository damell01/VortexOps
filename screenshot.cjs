/**
 * VortexOps — Playwright screenshot generator
 * Run:  node screenshot.cjs
 */
const { chromium } = require('/tmp/node_modules/playwright');
const path = require('path');

const BASE   = 'http://127.0.0.1:8765';
const OUT    = path.join(__dirname, 'docs/screenshots');
const CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

function esc(id) {
  return id.replace(/\./g, '\\.').replace(/\[/g, '\\[').replace(/\]/g, '\\]');
}

async function shot(page, name, label) {
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: true });
  console.log(`  ✓ ${name}.png  — ${label}`);
}

async function login(page) {
  await page.goto(`${BASE}/admin/login`);
  await page.waitForLoadState('networkidle');
  await shot(page, '00-login', 'Login page');
  await page.locator('#' + esc('form.email')).fill('admin@vortexbreaks.com');
  await page.locator('#' + esc('form.password')).fill('password');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/admin$/, { timeout: 15000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1200);
}

async function waitForTable(page) {
  // Wait for at least one table row to appear
  await page.waitForSelector('table tbody tr', { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(400);
}

async function tryOpenFilter(page, name, label) {
  const btn = page.getByRole('button', { name: /filter/i }).first();
  if (await btn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await btn.click();
    await page.waitForTimeout(500);
    await shot(page, name, label);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
  }
}

(async () => {
  const browser = await chromium.launch({
    executablePath: CHROME,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  page.on('console', () => {}); // suppress noise

  // ── Login ─────────────────────────────────────────────────────────────────
  console.log('\n[Auth]');
  await login(page);

  // ── Dashboard ─────────────────────────────────────────────────────────────
  console.log('\n[Dashboard]');
  await page.goto(`${BASE}/admin`);
  await page.waitForTimeout(1500);
  await shot(page, '01-dashboard', 'Dashboard — stats, low stock, recent movements, locations, streamers');

  // ── Inventory Items ───────────────────────────────────────────────────────
  console.log('\n[Inventory Items]');
  await page.goto(`${BASE}/admin/inventory-items`);
  await waitForTable(page);
  await shot(page, '02-inventory-items-list', 'Items list with category badges and qty');
  await tryOpenFilter(page, '03-inventory-items-filters', 'Filters panel — category, low stock, active');

  await page.goto(`${BASE}/admin/inventory-items/create`);
  await shot(page, '04-inventory-items-create', 'Create item form — SKU, name, cost, reorder level');

  await page.goto(`${BASE}/admin/inventory-items/1`);
  await shot(page, '05-inventory-item-view', 'Item detail — with Add Stock quick action');

  // ── Inventory Actions ─────────────────────────────────────────────────────
  console.log('\n[Inventory Actions]');
  await page.goto(`${BASE}/admin/inventory-items`);
  await waitForTable(page);

  // In Filament v5, action groups in tables render a visible trigger button
  // followed by hidden dropdown list items — use :visible to find the trigger
  const firstRow = page.locator('table tbody tr').first();

  async function getGroupBtn() {
    // The trigger button is visible; dropdown items are hidden in DOM
    const visibleBtns = firstRow.locator('button:visible');
    const count = await visibleBtns.count();
    return visibleBtns.nth(count - 1);
  }

  async function openDropdownAndClick(page, actionLabel, screenshotName, screenshotLabel) {
    await page.goto(`${BASE}/admin/inventory-items`);
    await waitForTable(page);
    const btn = await getGroupBtn();
    await btn.click({ timeout: 5000 });
    await page.waitForTimeout(600);
    if (screenshotName === '06-inventory-actions-dropdown') {
      await shot(page, screenshotName, screenshotLabel);
    }
    if (actionLabel === '__dropdown_only__') {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(300);
      return;
    }
    // Find the action in the now-visible dropdown
    const item = page.locator(`button:visible, li:visible, [role="menuitem"]:visible`).filter({ hasText: actionLabel }).first();
    const found = await item.isVisible({ timeout: 3000 }).catch(() => false);
    if (found) {
      await item.click();
      await page.waitForTimeout(900);
      await shot(page, screenshotName, screenshotLabel);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(400);
    } else {
      console.log(`  ⚠ Could not find action "${actionLabel}" — taking screenshot anyway`);
      await shot(page, screenshotName, screenshotLabel + ' (action not found)');
      await page.keyboard.press('Escape');
    }
  }

  // Capture dropdown screenshot (open the menu and screenshot with it open)
  await openDropdownAndClick(page, '__dropdown_only__', '06-inventory-actions-dropdown',
    'Action menu — Add Stock, Transfer, Adjust, Mark Damaged, Move to Returns');

  // Add Stock modal
  await openDropdownAndClick(page, 'Add Stock', '07-add-stock-modal',
    'Add Stock modal — location, qty, movement type');

  // Transfer Stock modal
  await openDropdownAndClick(page, 'Transfer Stock', '08-transfer-stock-modal',
    'Transfer Stock modal — from/to location, qty');

  // Adjust Inventory modal
  await openDropdownAndClick(page, 'Adjust Inventory', '09-adjust-inventory-modal',
    'Adjust Inventory modal — set exact qty with reason');

  // ── Inventory Locations ───────────────────────────────────────────────────
  console.log('\n[Inventory Locations]');
  await page.goto(`${BASE}/admin/inventory-locations`);
  await waitForTable(page);
  await shot(page, '10-inventory-locations-list', 'Locations list — type badges, streamer assignment, SKU count');

  await page.goto(`${BASE}/admin/inventory-locations/create`);
  await shot(page, '11-inventory-locations-create', 'Create location form — type-conditional streamer field');

  // Show streamer_inventory type to reveal the streamer field
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  const typeSelect = page.locator('#' + esc('form.type')).first();
  if (await typeSelect.isVisible({ timeout: 3000 }).catch(() => false)) {
    await typeSelect.selectOption('streamer_inventory');
    await page.waitForTimeout(700);
    await shot(page, '12-location-streamer-field', 'Streamer inventory type — conditional streamer select visible');
  }

  await page.goto(`${BASE}/admin/inventory-locations/1`);
  await shot(page, '13-inventory-location-view', 'Location detail — Main Storage');

  // ── Stock Levels ──────────────────────────────────────────────────────────
  console.log('\n[Stock Levels]');
  await page.goto(`${BASE}/admin/inventory-stocks`);
  await waitForTable(page);
  await shot(page, '14-stock-levels', 'Stock levels — item × location with unit cost and stock value');
  await tryOpenFilter(page, '15-stock-levels-filters', 'Stock levels filter by location');

  // ── Movement Log ──────────────────────────────────────────────────────────
  console.log('\n[Movement Log]');
  await page.goto(`${BASE}/admin/inventory-movements`);
  await waitForTable(page);
  await shot(page, '16-movement-log', 'Movement log — immutable audit trail, newest first');
  await tryOpenFilter(page, '17-movement-log-filters', 'Movement log filters — by type, item, location');

  // ── Streamers ─────────────────────────────────────────────────────────────
  console.log('\n[Streamers]');
  await page.goto(`${BASE}/admin/streamers`);
  await waitForTable(page);
  await shot(page, '18-streamers-list', 'Streamers list — payout type, status, tips badges');

  await page.goto(`${BASE}/admin/streamers/create`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await shot(page, '19-streamers-create', 'Create streamer form');

  // Profit share — show payout % field
  const payoutSel = page.locator('#' + esc('form.payout_type'));
  await payoutSel.selectOption('profit_share');
  await page.waitForTimeout(700);
  await shot(page, '20-streamer-profit-share', 'Profit Share — payout percentage field visible');

  // Package rate
  await payoutSel.selectOption('package');
  await page.waitForTimeout(700);
  await shot(page, '21-streamer-package-rate', 'Package — package rate per-slot field visible');

  // Hourly
  await payoutSel.selectOption('hourly');
  await page.waitForTimeout(700);
  await shot(page, '22-streamer-hourly-rate', 'Hourly — hourly rate field visible');

  await page.goto(`${BASE}/admin/streamers/1`);
  await shot(page, '23-streamer-view', 'Streamer detail view — Jordan, profit share config');

  // ── Whatnot Channels ──────────────────────────────────────────────────────
  console.log('\n[Whatnot Channels]');
  await page.goto(`${BASE}/admin/whatnot-channels`);
  await waitForTable(page);
  await shot(page, '24-whatnot-channels-list', 'Channels list — shared company channels');

  await page.goto(`${BASE}/admin/whatnot-channels/create`);
  await shot(page, '25-whatnot-channels-create', 'Create channel form');

  await browser.close();
  console.log(`\n✓ Done — ${require('fs').readdirSync(OUT).length} screenshots in docs/screenshots/\n`);
})().catch(err => {
  console.error('\nFATAL:', err.message);
  process.exit(1);
});
