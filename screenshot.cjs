/**
 * VortexOps — Playwright screenshot generator
 * Run:  node screenshot.cjs
 */
const { chromium } = require('/tmp/node_modules/playwright');
const path = require('path');
const fs   = require('fs');

const BASE   = 'http://127.0.0.1:8765';
const OUT    = path.join(__dirname, 'docs/screenshots');
const CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

fs.mkdirSync(OUT, { recursive: true });

function esc(id) {
  return id.replace(/\./g, '\\.').replace(/\[/g, '\\[').replace(/\]/g, '\\]');
}

async function shot(page, name, label) {
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(700);
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
  await page.waitForTimeout(1500);
}

async function waitForTable(page) {
  await page.waitForSelector('table tbody tr', { timeout: 12000 }).catch(() => {});
  await page.waitForTimeout(500);
}

async function tryOpenFilter(page, name, label) {
  const btn = page.getByRole('button', { name: /filter/i }).first();
  if (await btn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await btn.click();
    await page.waitForTimeout(600);
    await shot(page, name, label);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);
  }
}

(async () => {
  const browser = await chromium.launch({
    executablePath: CHROME,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  const ctx  = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  page.on('console', () => {});

  // ── Login ─────────────────────────────────────────────────────────────────
  console.log('\n[Auth]');
  await login(page);

  // ── Dashboard ─────────────────────────────────────────────────────────────
  console.log('\n[Dashboard]');
  await page.goto(`${BASE}/admin`);
  await page.waitForTimeout(1800);
  await shot(page, '01-dashboard', 'Dashboard — stats, low stock widget, recent movements');

  // ── Shows ─────────────────────────────────────────────────────────────────
  console.log('\n[Shows]');
  await page.goto(`${BASE}/admin/shows`);
  await waitForTable(page);
  await shot(page, '02-shows-list', 'Shows list — status badges, AI mapping action, pending_review count badge');
  await tryOpenFilter(page, '03-shows-filters', 'Shows filters — by status, channel, import source, date range');

  await page.goto(`${BASE}/admin/shows/create`);
  await shot(page, '04-shows-create', 'Create show form — channel, streamers, financials');

  await page.goto(`${BASE}/admin/shows/1`);
  await shot(page, '05-show-view', 'Show detail — with AI Mapping action, full financials');

  // ── Deduction Requests ────────────────────────────────────────────────────
  console.log('\n[Deduction Requests]');
  await page.goto(`${BASE}/admin/deduction-requests`);
  await waitForTable(page);
  await shot(page, '06-deduction-requests-list', 'Deduction requests — status, COGS totals, streamer badges');

  await page.goto(`${BASE}/admin/deduction-requests/1`);
  await shot(page, '07-deduction-request-review', 'Deduction review — AI lines, approve/reject actions, ops notes');

  // ── Inventory Items ───────────────────────────────────────────────────────
  console.log('\n[Inventory Items]');
  await page.goto(`${BASE}/admin/inventory-items`);
  await waitForTable(page);
  await shot(page, '08-inventory-items-list', 'Items list — qty, reorder level, action menu');
  await tryOpenFilter(page, '09-inventory-items-filters', 'Items filters — category, low stock toggle');

  await page.goto(`${BASE}/admin/inventory-items/create`);
  await shot(page, '10-inventory-items-create', 'Create item — SKU, cost, reorder level');

  await page.goto(`${BASE}/admin/inventory-items/1`);
  await shot(page, '11-inventory-item-view', 'Item detail — stock by location');

  // Action dropdown
  await page.goto(`${BASE}/admin/inventory-items`);
  await waitForTable(page);
  try {
    const firstRow2 = page.locator('table tbody tr').first();
    const allBtns   = firstRow2.locator('button');
    const btnCnt    = await allBtns.count();
    // Try each button from the end to find the action group trigger
    for (let i = btnCnt - 1; i >= 0; i--) {
      const btn = allBtns.nth(i);
      if (await btn.isVisible({ timeout: 1000 }).catch(() => false)) {
        await btn.click({ timeout: 3000 });
        await page.waitForTimeout(700);
        await shot(page, '12-inventory-actions-dropdown', 'Action menu — Add Stock, Transfer, Adjust, Mark Damaged');
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
        break;
      }
    }
  } catch (e) {
    console.log('  ⚠ Action dropdown skipped:', e.message.split('\n')[0]);
  }

  async function openModal(label, shotName, shotLabel) {
    try {
      await page.goto(`${BASE}/admin/inventory-items`);
      await waitForTable(page);
      const row  = page.locator('table tbody tr').first();
      const btns = row.locator('button');
      const cnt  = await btns.count();
      for (let i = cnt - 1; i >= 0; i--) {
        const btn = btns.nth(i);
        if (await btn.isVisible({ timeout: 1000 }).catch(() => false)) {
          await btn.click({ timeout: 3000 });
          await page.waitForTimeout(500);
          break;
        }
      }
      const item = page.locator(`button:visible, [role="menuitem"]:visible`).filter({ hasText: label }).first();
      if (await item.isVisible({ timeout: 3000 }).catch(() => false)) {
        await item.click();
        await page.waitForTimeout(900);
      }
      await shot(page, shotName, shotLabel);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(400);
    } catch (e) {
      console.log(`  ⚠ Modal "${label}" skipped:`, e.message.split('\n')[0]);
    }
  }

  await openModal('Add Stock',        '13-add-stock-modal',       'Add Stock modal');
  await openModal('Transfer Stock',   '14-transfer-stock-modal',  'Transfer Stock modal');
  await openModal('Adjust Inventory', '15-adjust-inventory-modal','Adjust Inventory modal');

  // ── Inventory Locations ───────────────────────────────────────────────────
  console.log('\n[Inventory Locations]');
  await page.goto(`${BASE}/admin/inventory-locations`);
  await waitForTable(page);
  await shot(page, '16-inventory-locations-list', 'Locations — type badges, streamer assignment');

  await page.goto(`${BASE}/admin/inventory-locations/create`);
  await shot(page, '17-inventory-locations-create', 'Create location — type-conditional fields');

  await page.goto(`${BASE}/admin/inventory-locations/1`);
  await shot(page, '18-inventory-location-view', 'Location detail');

  // ── Stock Levels ──────────────────────────────────────────────────────────
  console.log('\n[Stock Levels]');
  await page.goto(`${BASE}/admin/inventory-stocks`);
  await waitForTable(page);
  await shot(page, '19-stock-levels', 'Stock levels — item × location matrix');

  // ── Movement Log ──────────────────────────────────────────────────────────
  console.log('\n[Movement Log]');
  await page.goto(`${BASE}/admin/inventory-movements`);
  await waitForTable(page);
  await shot(page, '20-movement-log', 'Movement log — immutable audit trail');

  // ── Payouts ───────────────────────────────────────────────────────────────
  console.log('\n[Payouts]');
  await page.goto(`${BASE}/admin/payouts`);
  await waitForTable(page);
  await shot(page, '21-payouts-list', 'Payouts list — streamer, amount, status, show link');
  await tryOpenFilter(page, '22-payouts-filters', 'Payout filters — status, streamer, date');

  // ── Weekly Pay Runs ───────────────────────────────────────────────────────
  console.log('\n[Pay Runs]');
  await page.goto(`${BASE}/admin/weekly-payout-batches`);
  await waitForTable(page);
  await shot(page, '23-pay-runs-list', 'Weekly pay run batches — total, streamer count, status');

  await page.goto(`${BASE}/admin/weekly-payout-batches/1`);
  await shot(page, '24-pay-run-view', 'Pay run detail — finalize / mark submitted / mark paid actions');

  // ── Streamers ─────────────────────────────────────────────────────────────
  console.log('\n[Streamers]');
  await page.goto(`${BASE}/admin/streamers`);
  await waitForTable(page);
  await shot(page, '25-streamers-list', 'Streamers list — payout type, status');

  await page.goto(`${BASE}/admin/streamers/1`);
  await shot(page, '26-streamer-view', 'Streamer detail — payout config, linked locations');

  // ── Feedback Tickets ──────────────────────────────────────────────────────
  console.log('\n[Feedback]');
  await page.goto(`${BASE}/admin/feedback-tickets`);
  await waitForTable(page).catch(() => {});
  await shot(page, '27-feedback-tickets-list', 'Feedback tickets — priority + status badges, nav badge count');

  // Show the feedback widget button (scroll to bottom to reveal)
  await page.goto(`${BASE}/admin`);
  await page.waitForTimeout(1500);
  await shot(page, '28-feedback-widget-button', 'Dashboard with floating feedback button in bottom-right');

  // ── AI Assistant ──────────────────────────────────────────────────────────
  console.log('\n[AI]');
  await page.goto(`${BASE}/admin/ai-assistant`);
  await page.waitForTimeout(1000);
  await shot(page, '29-ai-assistant', 'AI Assistant chat page — full screen with page context');

  await page.goto(`${BASE}/admin/ai-logs`);
  await waitForTable(page);
  await shot(page, '30-ai-logs', 'AI log — action type, latency, success/fail status');

  // ── Settings ─────────────────────────────────────────────────────────────
  console.log('\n[Settings]');
  await page.goto(`${BASE}/admin/app-settings`);
  await page.waitForTimeout(1000);
  await shot(page, '31-settings', 'App settings — branding, Ollama config, notifications, maintenance');

  // ── Users ────────────────────────────────────────────────────────────────
  console.log('\n[Users]');
  await page.goto(`${BASE}/admin/users`);
  await waitForTable(page);
  await shot(page, '32-users-list', 'Users list — role badges, streamer link');

  // ── Activity Log ──────────────────────────────────────────────────────────
  console.log('\n[Activity Log]');
  await page.goto(`${BASE}/admin/activity-logs`);
  await waitForTable(page);
  await shot(page, '33-activity-log', 'Activity log — created/updated/deleted events with model + actor');

  await page.goto(`${BASE}/admin/activity-logs/1`);
  await shot(page, '34-activity-log-detail', 'Activity log detail — before/after diff table');

  await browser.close();
  const count = fs.readdirSync(OUT).filter(f => f.endsWith('.png')).length;
  console.log(`\n✓ Done — ${count} screenshots in docs/screenshots/\n`);
})().catch(err => {
  console.error('\nFATAL:', err.message);
  process.exit(1);
});
