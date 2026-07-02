/**
 * Whatnot Seller Dashboard Scraper
 *
 * Modes (WHATNOT_MODE env var):
 *   shows        (default) — scrape completed show list, output JSON array
 *   test         — verify credentials only, output {connected, email}
 *   show-orders  — scrape order/lot list for one show (requires WHATNOT_SHOW_URL)
 *
 * Called by app/Services/WhatnotScraper.php via Symfony Process.
 *
 * Env vars:
 *   WHATNOT_EMAIL       seller account email
 *   WHATNOT_PASSWORD    seller account password
 *   WHATNOT_LIMIT       max shows to return per run (default: 50)
 *   WHATNOT_MODE        shows | test | show-orders (default: shows)
 *   WHATNOT_SHOW_URL    required for show-orders mode
 *   WHATNOT_DEBUG       set to "1" to save debug screenshots to /tmp
 *
 * Exit codes:
 *   0  success, JSON on stdout
 *   1  login failed or nav error, error message on stderr
 *   2  selector miss — page structure changed, update SELECTORS below
 */

'use strict';

const { chromium } = require('/opt/node22/lib/node_modules/playwright');

// ── Selectors ────────────────────────────────────────────────────────────────
// Update these if Whatnot changes their markup.
const SELECTORS = {
  // Login page
  loginEmailInput:    'input[type="email"], input[name="email"], input[placeholder*="email" i]',
  loginPasswordInput: 'input[type="password"]',
  loginSubmitBtn:     'button[type="submit"]',

  // Seller dashboard — shows list page
  showRow:            '[data-testid="show-row"], .show-row, tr[data-show-id], [class*="ShowRow"], [class*="show-item"]',
  showTitle:          '[data-testid="show-title"], .show-title, [class*="ShowTitle"], h3, h4',
  showDate:           '[data-testid="show-date"], .show-date, time, [class*="ShowDate"]',
  showDuration:       '[data-testid="show-duration"], [class*="duration"]',
  showGross:          '[data-testid="gross-sales"], [class*="gross"], [class*="revenue"]',
  showNet:            '[data-testid="net-payout"], [class*="payout"], [class*="net"]',
  showTips:           '[data-testid="tips"], [class*="tip"]',
  showUnits:          '[data-testid="units-sold"], [class*="units"]',
  showStatus:         '[data-testid="show-status"], [class*="status"]',

  // Show detail / order list page
  // Update these selectors once you've inspected the seller show detail page.
  orderRow:           '[data-testid="order-row"], [class*="OrderRow"], [class*="order-item"], tr[data-order-id]',
  orderBuyer:         '[data-testid="buyer-name"], [data-testid="buyer-username"], [class*="buyer"], [class*="Buyer"]',
  orderItemName:      '[data-testid="item-name"], [data-testid="product-name"], [class*="ItemName"], [class*="item-title"]',
  orderLotNumber:     '[data-testid="lot-number"], [class*="lot"], [class*="Lot"]',
  orderQuantity:      '[data-testid="quantity"], [class*="qty"], [class*="Qty"]',
  orderPrice:         '[data-testid="price"], [data-testid="sale-price"], [class*="price"], [class*="Price"]',
  orderTotal:         '[data-testid="total"], [data-testid="order-total"], [class*="total"], [class*="Total"]',
  orderStatus:        '[data-testid="order-status"], [class*="OrderStatus"], [class*="order-status"]',
};

const URLS = {
  home:       'https://www.whatnot.com',
  login:      'https://www.whatnot.com/signin',
  shows:      'https://www.whatnot.com/seller/shows',
  sellerHub:  'https://www.whatnot.com/seller',
};

const CHROMIUM_PATH = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const DEBUG         = process.env.WHATNOT_DEBUG === '1';
const LIMIT         = parseInt(process.env.WHATNOT_LIMIT || '50', 10);
const MODE          = process.env.WHATNOT_MODE || 'shows';

// ── Helpers ──────────────────────────────────────────────────────────────────

function log(...args) {
  if (DEBUG) process.stderr.write('[whatnot-scraper] ' + args.join(' ') + '\n');
}

async function debugShot(page, name) {
  if (!DEBUG) return;
  const p = `/tmp/whatnot-debug-${name}.png`;
  await page.screenshot({ path: p, fullPage: false });
  log(`screenshot saved: ${p}`);
}

function parseMoney(str) {
  if (!str) return null;
  const cleaned = str.replace(/[^0-9.-]/g, '');
  const val = parseFloat(cleaned);
  return isNaN(val) ? null : val;
}

function parseDurationToMinutes(str) {
  if (!str) return null;
  const hm = str.match(/(\d+)\s*h(?:r|our)?s?\s*(?:(\d+)\s*m)?/i);
  if (hm) return parseInt(hm[1]) * 60 + (parseInt(hm[2] || '0'));
  const minOnly = str.match(/(\d+)\s*m(?:in)?/i);
  if (minOnly) return parseInt(minOnly[1]);
  const hms = str.match(/(\d+):(\d+):(\d+)/);
  if (hms) return parseInt(hms[1]) * 60 + parseInt(hms[2]);
  return null;
}

// ── Login helper (shared by all modes) ───────────────────────────────────────

async function performLogin(page, email, password) {
  log('navigating to login page');
  await page.goto(URLS.login, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await debugShot(page, '01-login-page');

  const emailInput = await page.waitForSelector(SELECTORS.loginEmailInput, { timeout: 15000 })
    .catch(() => null);

  if (!emailInput) {
    log('no email input found — may already be logged in');
    return; // already authenticated
  }

  await emailInput.click();
  await emailInput.fill(email);
  await page.keyboard.press('Tab');
  await page.waitForTimeout(500);

  const passInput = await page.waitForSelector(SELECTORS.loginPasswordInput, { timeout: 10000 })
    .catch(() => null);

  if (!passInput) {
    await debugShot(page, '02-no-password-field');
    throw new Error('Password field not found after entering email. Whatnot may require a different login flow (phone number, magic link, etc.).');
  }

  await passInput.fill(password);
  await debugShot(page, '03-credentials-filled');

  const submitBtn = await page.$(SELECTORS.loginSubmitBtn);
  if (submitBtn) {
    await submitBtn.click();
  } else {
    await page.keyboard.press('Enter');
  }

  log('waiting for navigation after login');
  await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 })
    .catch(() => log('navigation timeout — continuing anyway'));

  await debugShot(page, '04-post-login');

  const url = page.url();
  if (url.includes('signin') || url.includes('login') || url.includes('verify')) {
    const pageText = await page.textContent('body');
    if (pageText.toLowerCase().includes('incorrect') || pageText.toLowerCase().includes('invalid')) {
      throw new Error('Login failed — incorrect email or password.');
    }
    if (pageText.toLowerCase().includes('verify') || pageText.toLowerCase().includes('code')) {
      throw new Error('Login requires 2FA verification. Disable 2FA on the Whatnot account or use a session cookie approach instead.');
    }
    throw new Error(`Login did not complete. Still on: ${url}`);
  }
}

// ── Main ─────────────────────────────────────────────────────────────────────

(async () => {
  const email    = process.env.WHATNOT_EMAIL;
  const password = process.env.WHATNOT_PASSWORD;

  if (!email || !password) {
    process.stderr.write('Error: WHATNOT_EMAIL and WHATNOT_PASSWORD are required\n');
    process.exit(1);
  }

  const browser = await chromium.launch({
    executablePath: CHROMIUM_PATH,
    headless:       true,
    args:           ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    viewport:  { width: 1280, height: 900 },
  });

  const page = await context.newPage();

  try {
    await performLogin(page, email, password);

    // ── Mode: test ───────────────────────────────────────────────────────────
    if (MODE === 'test') {
      // Navigate to seller hub to confirm seller access
      log('test mode — verifying seller access');
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(1500);
      await debugShot(page, '05-seller-hub');

      const currentUrl = page.url();
      const isSeller   = currentUrl.includes('/seller') || currentUrl.includes('/creator');
      const pageTitle  = await page.title();

      if (!isSeller) {
        throw new Error(`Credentials valid but seller hub not accessible. Landed on: ${currentUrl}. Ensure this account has seller status on Whatnot.`);
      }

      process.stdout.write(JSON.stringify({
        connected:   true,
        email:       email,
        page_title:  pageTitle,
        seller_url:  currentUrl,
      }) + '\n');

      log('test complete — connected');
      process.exit(0);
    }

    // ── Mode: show-orders ────────────────────────────────────────────────────
    if (MODE === 'show-orders') {
      const showUrl = process.env.WHATNOT_SHOW_URL;
      if (!showUrl) {
        process.stderr.write('Error: WHATNOT_SHOW_URL is required for show-orders mode\n');
        process.exit(1);
      }

      log(`navigating to show detail: ${showUrl}`);
      await page.goto(showUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(2000);
      await debugShot(page, '05-show-detail');

      // Try to find an orders tab or section
      const ordersTabSel = '[data-testid="orders-tab"], a[href*="orders"], button:has-text("Orders"), button:has-text("Sales")';
      const ordersTab = await page.$(ordersTabSel);
      if (ordersTab) {
        log('clicking orders tab');
        await ordersTab.click();
        await page.waitForTimeout(1500);
        await debugShot(page, '06-orders-tab');
      }

      // Wait for order rows
      await page.waitForSelector(SELECTORS.orderRow, { timeout: 15000 })
        .catch(() => log('order row selector not matched — will attempt generic extraction'));

      const orders = await page.evaluate(({ SEL }) => {
        const rows = Array.from(document.querySelectorAll(SEL.orderRow));

        if (rows.length === 0) {
          return { fallback: true, html: document.body.innerHTML.substring(0, 8000) };
        }

        return rows.map((row, idx) => {
          function text(sel) {
            const el = row.querySelector(sel);
            return el ? el.textContent.trim() : null;
          }
          function attr(sel, att) {
            const el = row.querySelector(sel);
            return el ? el.getAttribute(att) : null;
          }

          return {
            index:        idx,
            buyer:        text(SEL.orderBuyer),
            item_name:    text(SEL.orderItemName),
            lot_number:   text(SEL.orderLotNumber),
            quantity:     text(SEL.orderQuantity),
            price:        text(SEL.orderPrice),
            total:        text(SEL.orderTotal),
            status:       text(SEL.orderStatus),
            order_id:     attr('[data-order-id]', 'data-order-id') ||
                          attr('[data-testid="order-id"]', 'data-testid'),
            raw_text:     row.textContent.replace(/\s+/g, ' ').trim().substring(0, 300),
          };
        });
      }, { SEL: SELECTORS });

      await debugShot(page, '07-orders-extracted');

      if (orders && orders.fallback) {
        process.stderr.write('SELECTOR_MISS: Order row selector did not match any elements.\n');
        process.stderr.write('PAGE_SNAPSHOT: ' + orders.html + '\n');
        process.exit(2);
      }

      const normalized = (orders || []).map(o => ({
        order_id:    o.order_id || null,
        buyer:       o.buyer    || null,
        item_name:   o.item_name || o.raw_text?.substring(0, 100) || null,
        lot_number:  o.lot_number ? parseInt(o.lot_number.replace(/\D/g, ''), 10) || null : null,
        quantity:    o.quantity  ? parseInt(o.quantity.replace(/\D/g, ''),  10) || 1 : 1,
        unit_price:  parseMoney(o.price),
        total_price: parseMoney(o.total),
        status:      o.status   || 'completed',
      })).filter(o => o.buyer || o.item_name);

      process.stdout.write(JSON.stringify(normalized, null, 2) + '\n');
      log(`done — returned ${normalized.length} orders`);
      process.exit(0);
    }

    // ── Mode: shows (default) ────────────────────────────────────────────────
    log('navigating to seller shows page');
    await page.goto(URLS.shows, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(2000);
    await debugShot(page, '05-shows-page');

    const currentUrl = page.url();
    if (!currentUrl.includes('/seller') && !currentUrl.includes('/creator')) {
      throw new Error(`Expected seller dashboard but landed on: ${currentUrl}. Ensure this Whatnot account is a registered seller.`);
    }

    log('extracting show data');

    await page.waitForSelector(SELECTORS.showRow, { timeout: 15000 })
      .catch(() => log('show row selector not matched — will attempt generic extraction'));

    const shows = await page.evaluate(({ SEL, limit }) => {
      const rows = Array.from(document.querySelectorAll(SEL.showRow)).slice(0, limit);

      if (rows.length === 0) {
        return { fallback: true, html: document.body.innerHTML.substring(0, 5000) };
      }

      return rows.map(row => {
        function text(sel) {
          const el = row.querySelector(sel);
          return el ? el.textContent.trim() : null;
        }
        function attr(sel, att) {
          const el = row.querySelector(sel);
          return el ? el.getAttribute(att) : null;
        }

        return {
          title:     text(SEL.showTitle),
          date:      attr('time', 'datetime') || text(SEL.showDate),
          duration:  text(SEL.showDuration),
          gross:     text(SEL.showGross),
          net:       text(SEL.showNet),
          tips:      text(SEL.showTips),
          units:     text(SEL.showUnits),
          status:    text(SEL.showStatus),
          detailUrl: row.querySelector('a') ? row.querySelector('a').href : null,
        };
      });
    }, { SEL: SELECTORS, limit: LIMIT });

    await debugShot(page, '06-extracted');

    if (shows && shows.fallback) {
      process.stderr.write('SELECTOR_MISS: Show row selector did not match any elements.\n');
      process.stderr.write('PAGE_SNAPSHOT: ' + shows.html + '\n');
      process.exit(2);
    }

    const normalized = (shows || []).map(s => ({
      title:         s.title  || null,
      show_date:     s.date   ? s.date.substring(0, 10) : null,
      show_duration: parseDurationToMinutes(s.duration),
      gross_revenue: parseMoney(s.gross),
      whatnot_net:   parseMoney(s.net),
      tips:          parseMoney(s.tips),
      units_sold:    s.units  ? parseInt(s.units.replace(/\D/g, ''), 10) || null : null,
      status:        s.status || null,
      detail_url:    s.detailUrl || null,
    })).filter(s => s.title || s.show_date);

    process.stdout.write(JSON.stringify(normalized, null, 2) + '\n');
    log(`done — returned ${normalized.length} shows`);

  } catch (err) {
    await debugShot(page, 'error');
    process.stderr.write('Error: ' + err.message + '\n');
    process.exit(1);
  } finally {
    await browser.close();
  }
})();
