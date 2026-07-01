/**
 * Whatnot Seller Dashboard Scraper
 *
 * Logs into Whatnot as the seller account, navigates to the show history,
 * and outputs completed show data as JSON on stdout.
 *
 * Called by app/Services/WhatnotScraper.php via Symfony Process.
 *
 * Env vars:
 *   WHATNOT_EMAIL      seller account email
 *   WHATNOT_PASSWORD   seller account password
 *   WHATNOT_LIMIT      max shows to return per run (default: 50)
 *   WHATNOT_DEBUG      set to "1" to save debug screenshots to /tmp
 *
 * Exit codes:
 *   0  success, JSON array on stdout
 *   1  login failed or nav error, error message on stderr
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
  // Whatnot seller hub: https://www.whatnot.com/seller/shows
  showRow:            '[data-testid="show-row"], .show-row, tr[data-show-id], [class*="ShowRow"], [class*="show-item"]',
  showTitle:          '[data-testid="show-title"], .show-title, [class*="ShowTitle"], h3, h4',
  showDate:           '[data-testid="show-date"], .show-date, time, [class*="ShowDate"]',
  showDuration:       '[data-testid="show-duration"], [class*="duration"]',
  showGross:          '[data-testid="gross-sales"], [class*="gross"], [class*="revenue"]',
  showNet:            '[data-testid="net-payout"], [class*="payout"], [class*="net"]',
  showTips:           '[data-testid="tips"], [class*="tip"]',
  showUnits:          '[data-testid="units-sold"], [class*="units"]',
  showStatus:         '[data-testid="show-status"], [class*="status"]',

  // Navigation to show history
  sellerDashboardNav: 'a[href*="/seller"], a[href*="/creator"], [class*="seller"]',
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
  // Handles "1h 30m", "90 min", "1:30:00", "2h"
  const hm = str.match(/(\d+)\s*h(?:r|our)?s?\s*(?:(\d+)\s*m)?/i);
  if (hm) return parseInt(hm[1]) * 60 + (parseInt(hm[2] || '0'));
  const minOnly = str.match(/(\d+)\s*m(?:in)?/i);
  if (minOnly) return parseInt(minOnly[1]);
  const hms = str.match(/(\d+):(\d+):(\d+)/);
  if (hms) return parseInt(hms[1]) * 60 + parseInt(hms[2]);
  return null;
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
    // ── Step 1: Log in ──────────────────────────────────────────────────────
    log('navigating to login page');
    await page.goto(URLS.login, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await debugShot(page, '01-login-page');

    // Wait for email input
    const emailInput = await page.waitForSelector(SELECTORS.loginEmailInput, { timeout: 15000 })
      .catch(() => null);

    if (!emailInput) {
      // Already logged in?
      log('no email input found — may already be logged in');
    } else {
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

      // Check for 2FA or error
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

    // ── Step 2: Navigate to show history ────────────────────────────────────
    log('navigating to seller shows page');
    await page.goto(URLS.shows, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(2000);
    await debugShot(page, '05-shows-page');

    // If redirect happened (e.g. not a seller account), detect it
    const currentUrl = page.url();
    if (!currentUrl.includes('/seller') && !currentUrl.includes('/creator')) {
      throw new Error(`Expected seller dashboard but landed on: ${currentUrl}. Ensure this Whatnot account is a registered seller.`);
    }

    // ── Step 3: Extract show rows ────────────────────────────────────────────
    log('extracting show data');

    // Try to wait for some table/list content to load
    await page.waitForSelector(SELECTORS.showRow, { timeout: 15000 })
      .catch(() => log('show row selector not matched — will attempt generic extraction'));

    const shows = await page.evaluate(({ SEL, limit }) => {
      const rows = Array.from(document.querySelectorAll(SEL.showRow)).slice(0, limit);

      if (rows.length === 0) {
        // Fallback: try to get any structured data from the page
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
          title:    text(SEL.showTitle),
          date:     attr('time', 'datetime') || text(SEL.showDate),
          duration: text(SEL.showDuration),
          gross:    text(SEL.showGross),
          net:      text(SEL.showNet),
          tips:     text(SEL.showTips),
          units:    text(SEL.showUnits),
          status:   text(SEL.showStatus),
          // Capture raw href for the show detail page if available
          detailUrl: row.querySelector('a') ? row.querySelector('a').href : null,
        };
      });
    }, { SEL: SELECTORS, limit: LIMIT });

    await debugShot(page, '06-extracted');

    if (shows && shows.fallback) {
      // The selectors didn't match — output debug info so the selectors can be updated
      process.stderr.write('SELECTOR_MISS: Show row selector did not match any elements.\n');
      process.stderr.write('PAGE_SNAPSHOT: ' + shows.html + '\n');
      process.exit(2);
    }

    // ── Step 4: Normalize and output ─────────────────────────────────────────
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
    })).filter(s => s.title || s.show_date); // skip completely empty rows

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
