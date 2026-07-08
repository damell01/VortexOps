/**
 * Whatnot Seller Dashboard Scraper
 *
 * Modes (WHATNOT_MODE env var):
 *   analytics    (default) — scrape per-show analytics from the dashboard, newest first
 *   test         — verify credentials only, output {connected, email}
 *   show-orders  — scrape order/lot list for one show (requires WHATNOT_SHOW_URL)
 *   shows        — legacy: scrape the /seller/shows list page (less data, kept as fallback)
 *
 * Called by app/Services/WhatnotScraper.php via Symfony Process.
 *
 * Env vars:
 *   WHATNOT_EMAIL        seller account email
 *   WHATNOT_PASSWORD     seller account password
 *   WHATNOT_LIMIT        max shows to return per run (default: 50)
 *   WHATNOT_MODE         analytics | test | show-orders | shows (default: analytics)
 *   WHATNOT_SHOW_URL     required for show-orders mode
 *   WHATNOT_CHANNEL_NAME whatnot_username of the channel to switch to before scraping
 *                        (leave blank to scrape the default/current active channel)
 *   WHATNOT_DEBUG        set to "1" to save debug screenshots to /tmp
 *
 * Exit codes:
 *   0  success, JSON on stdout
 *   1  login failed or nav error, error message on stderr
 *   2  selector miss — page structure changed, update SELECTORS below
 */

'use strict';

const { execSync } = require('child_process');

function loadPlaywright() {
  // 1. Try npm's global root (works on any system regardless of install prefix)
  try {
    const globalRoot = execSync('npm root -g', { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }).trim();
    return require(globalRoot + '/playwright');
  } catch {}
  // 2. Common fixed paths (Docker image, Debian/Ubuntu system npm, Homebrew)
  const candidates = [
    '/opt/node22/lib/node_modules/playwright',
    '/usr/lib/node_modules/playwright',
    '/usr/local/lib/node_modules/playwright',
    '/opt/homebrew/lib/node_modules/playwright',
  ];
  for (const p of candidates) {
    try { return require(p); } catch {}
  }
  throw new Error(
    'Playwright not found. Install it with:\n' +
    '  npm install -g playwright && npx playwright install chromium --with-deps'
  );
}

const { chromium } = loadPlaywright();

// ── Selectors ────────────────────────────────────────────────────────────────
// Analytics page selectors are based on the live Whatnot seller dashboard HTML
// (captured July 2026). Update if Whatnot changes their markup.
const SELECTORS = {
  // Login page
  loginEmailInput:    'input[type="email"], input[name="email"], input[placeholder*="email" i]',
  loginPasswordInput: 'input[type="password"]',
  loginSubmitBtn:     'button[type="submit"]',

  // Analytics page — tabs
  // Tab buttons use stable aria attributes — prefer these over class names.
  analyticsShowsTab:  'button[aria-controls="simple-tabpanel-1"], button#simple-tab-1',

  // Analytics page — show navigation (aria-label is stable across deploys)
  showNavOlder:       'button[aria-label="See older show"]',
  showNavNewer:       'button[aria-label="See newer show"]',

  // Analytics page — show header
  // Title uses an inline nowrap/ellipsis style; the max-width:90% distinguishes
  // it from other large text on the page.
  showTitle:          'div[style*="white-space: nowrap"][style*="text-overflow: ellipsis"][style*="max-width: 90%"]',

  // Analytics page — metric cards
  // Each card is 160px tall with border-radius: 16px.
  // Within each card: name is font-size:16px bold, value is font-size:32px bold.
  metricCard:         'div[style*="height: 160px"][style*="border-radius: 16px"]',

  // Seller shows list page (/seller/shows)
  // Show cards on the seller dashboard list — try multiple patterns
  showCard:         '[data-testid="show-card"], [class*="ShowCard"], [class*="show-card"]',
  showCardLink:     'a[href*="/live/"], a[href*="/show/"]',

  // Show orders page — Whatnot uses dynamic CSS class names so we target
  // structural patterns instead of class names. Update if layout changes.
  // "Lots" tab on seller show page
  lotsTab:          'button:has-text("Lots"), [role="tab"]:has-text("Lots"), button:has-text("Orders"), [role="tab"]:has-text("Orders"), button:has-text("Sales"), [role="tab"]:has-text("Sales")',
};

const URLS = {
  home:       'https://www.whatnot.com',
  login:      'https://www.whatnot.com/signin',
  analytics:  'https://www.whatnot.com/dashboard/analytics/overview',
  shows:      'https://www.whatnot.com/seller/shows',
  sellerHub:  'https://www.whatnot.com/seller',
};

// Resolve Chromium binary.
// Priority: env override → build-time marker file → Playwright API → directory scan → system bins
const CHROMIUM_PATH = (() => {
  const fs = require('fs');

  // 1. Explicit env override (highest priority, covers dev/CI overrides)
  //    Verify existence — path may come from a marker written by root and be inaccessible to www-data.
  if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) {
    const p = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
    if (fs.existsSync(p)) return p;
    // Path is set but inaccessible from this process; fall through to scan.
  }

  // 2. Marker files written by `php artisan whatnot:setup-chromium` or Docker build
  const markerFiles = [
    `${__dirname}/../storage/chromium-path.txt`,                              // bare VPS (artisan)
    `${process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers'}/.chromium-path`, // Docker
  ];
  for (const markerFile of markerFiles) {
    try {
      const p = fs.readFileSync(markerFile, 'utf8').trim();
      if (p && fs.existsSync(p)) return p;
    } catch {}
  }

  // 3. Playwright's own API — works when PLAYWRIGHT_BROWSERS_PATH is set correctly
  try {
    const p = chromium.executablePath();
    if (p && fs.existsSync(p)) return p;
  } catch {}

  // 4. Scan PLAYWRIGHT_BROWSERS_PATH and common user cache dirs
  function findInDir(base) {
    if (!fs.existsSync(base)) return null;
    // Check direct paths first — Playwright may install without a version subdirectory
    // (e.g. PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers → binary at base/chrome-linux/chrome)
    for (const bin of ['chrome-linux64/headless_shell', 'chrome-linux64/chrome',
                        'chrome-linux/headless_shell', 'chrome-linux/chrome',
                        'headless_shell', 'chrome']) {
      const full = `${base}/${bin}`;
      if (fs.existsSync(full)) return full;
    }
    let dirs;
    try {
      dirs = fs.readdirSync(base)
        .filter(d => d.startsWith('chromium-') || d.startsWith('chromium_headless_shell-'))
        .sort().reverse();
    } catch { return null; }
    for (const dir of dirs) {
      for (const bin of ['chrome-linux64/headless_shell', 'chrome-linux64/chrome',
                          'chrome-linux/headless_shell', 'chrome-linux/chrome',
                          'headless_shell', 'chrome']) {
        const full = `${base}/${dir}/${bin}`;
        if (fs.existsSync(full)) return full;
      }
    }
    return null;
  }

  const searchRoots = [
    process.env.PLAYWRIGHT_BROWSERS_PATH,
    '/opt/pw-browsers',                      // shared location written by whatnot:setup-chromium
    ...[process.env.HOME, '/root', '/var/www', '/home/www-data']
      .filter(Boolean)
      .map(h => `${h}/.cache/ms-playwright`),
  ].filter(Boolean);

  for (const root of searchRoots) {
    const found = findInDir(root);
    if (found) return found;
  }

  // 5. System chromium (apt / snap)
  for (const bin of ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome']) {
    if (fs.existsSync(bin)) return bin;
  }

  process.stderr.write(
    '[whatnot-scraper] ERROR: Chromium not found.\n' +
    '  VPS:    php artisan whatnot:setup-chromium\n' +
    '  Manual: PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/path/to/chrome node scripts/whatnot-scraper.cjs\n'
  );
  process.exit(2);
})();
const DEBUG          = process.env.WHATNOT_DEBUG === '1';
const LIMIT          = parseInt(process.env.WHATNOT_LIMIT || '50', 10);
const MODE           = process.env.WHATNOT_MODE || 'analytics';
const CHANNEL_NAME   = (process.env.WHATNOT_CHANNEL_NAME || '').trim();

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
  if (!str || str === 'N/A') return null;
  const cleaned = str.replace(/[^0-9.-]/g, '');
  const val = parseFloat(cleaned);
  return isNaN(val) ? null : val;
}

function parseDurationToMinutes(str) {
  if (!str) return null;
  // "7h 0m", "0h 53m", "1h 30m", "90 min", "1:30:00"
  const hm = str.match(/(\d+)\s*h(?:r|our)?s?\s*(?:(\d+)\s*m)?/i);
  if (hm) return parseInt(hm[1]) * 60 + (parseInt(hm[2] || '0'));
  const minOnly = str.match(/(\d+)\s*m(?:in)?/i);
  if (minOnly) return parseInt(minOnly[1]);
  const hms = str.match(/(\d+):(\d+):(\d+)/);
  if (hms) return parseInt(hms[1]) * 60 + parseInt(hms[2]);
  return null;
}

// Parse "7/1/2026, 7:42 PM CDT" → "2026-07-01"
function parseDateString(str) {
  if (!str) return null;
  const m = str.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
  if (!m) return null;
  const [, mo, d, y] = m;
  return `${y}-${mo.padStart(2, '0')}-${d.padStart(2, '0')}`;
}

function parseInteger(str) {
  if (!str || str === 'N/A') return null;
  const cleaned = str.replace(/[^0-9]/g, '');
  const val = parseInt(cleaned, 10);
  return isNaN(val) ? null : val;
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
    return;
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

// ── Role / channel switcher ───────────────────────────────────────────────────
// Whatnot's navigation sidebar (the slide-out drawer) contains a "Switch Role"
// item (an h4 with that text, class ogVNN, inside a div.eCoev). Clicking it
// shows a list of the available channels. This function opens the sidebar,
// clicks Switch Role, then clicks the target channel by name.
//
// The sidebar is opened from a nav/header button (profile avatar or hamburger).
// Run with WHATNOT_DEBUG=1 to capture screenshots if the switch doesn't work.

async function switchToChannel(page, channelName) {
  if (!channelName) return; // no switch needed — scrape the currently active channel
  log(`switching to channel: "${channelName}"`);

  // Navigate to the Whatnot home — the top nav with the sidebar trigger is always present
  await page.goto(URLS.home, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(2000);
  await debugShot(page, 'role-switch-01-home');

  // ── Step 1: Open the navigation sidebar ─────────────────────────────────────
  // The sidebar is a slide-out drawer containing the "Switch Role" h4 item.
  // It's opened by clicking the profile avatar (or menu button) in the top nav.
  // "Switch Role" text exact match tells us the drawer is open.

  const isSidebarOpen = () =>
    page.getByText('Switch Role', { exact: true }).first().isVisible().catch(() => false);

  if (!await isSidebarOpen()) {
    // Try nav/header trigger buttons in priority order
    const navTriggers = [
      'button:has(img[alt="avatar"])',          // profile avatar inside a button
      'nav button[aria-label*="profile" i]',    // aria-labelled profile button
      'nav button[aria-label*="menu" i]',       // aria-labelled menu button
      'header button[aria-label*="menu" i]',    // header menu button
      '[aria-label="Open navigation drawer"]',
      '[aria-label="Open menu"]',
    ];

    for (const sel of navTriggers) {
      const trigger = await page.$(sel).catch(() => null);
      if (!trigger || !await trigger.isVisible().catch(() => false)) continue;

      await trigger.click();
      await page.waitForTimeout(1200);

      if (await isSidebarOpen()) {
        log(`opened navigation sidebar via: ${sel}`);
        break;
      }

      // Wrong thing opened — dismiss and try next
      await page.keyboard.press('Escape').catch(() => {});
      await page.waitForTimeout(400);
    }
  }

  if (!await isSidebarOpen()) {
    await debugShot(page, 'role-switch-failed-sidebar');
    log(`WARNING: could not open the navigation sidebar to find "Switch Role".`);
    log(`Run with WHATNOT_DEBUG=1, check role-switch-failed-sidebar.png, then update navTriggers above.`);
    return; // continue on whatever channel is currently active
  }

  await debugShot(page, 'role-switch-02-sidebar-open');

  // ── Step 2: Click "Switch Role" ──────────────────────────────────────────────
  // In the sidebar HTML, "Switch Role" is an h4 (class ogVNN) inside a
  // div.eCoev with role="presentation". Clicking the text propagates to the div.
  await page.getByText('Switch Role', { exact: true }).first().click();
  await page.waitForTimeout(2000);
  await debugShot(page, 'role-switch-03-role-list');

  // ── Step 3: Click the target channel ─────────────────────────────────────────
  // After clicking Switch Role, Whatnot shows the list of available channel roles.
  // Find by the whatnot_username (e.g. "vortexcards", "vortexbreaks").
  const target = page.getByText(channelName, { exact: false }).first();

  if (!await target.isVisible().catch(() => false)) {
    await debugShot(page, 'role-switch-failed-channel');
    log(`WARNING: channel "${channelName}" not found in role list.`);
    log(`Check role-switch-03-role-list.png to see the available options.`);
    return;
  }

  await target.click();
  await page.waitForTimeout(2500);
  await debugShot(page, 'role-switch-04-done');
  log(`switched to channel "${channelName}"`);
}

// ── Extract all metric cards from the current analytics page view ─────────────

async function extractAnalyticsMetrics(page) {
  return page.evaluate(({ SEL }) => {
    // Find a div by checking its inline style for a substring
    function styleIncludes(el, ...parts) {
      const s = el.getAttribute('style') || '';
      return parts.every(p => s.includes(p));
    }

    // Show title: the nowrap/ellipsis div with max-width: 90%
    const titleEl = document.querySelector(SEL.showTitle);
    const title = titleEl ? titleEl.textContent.trim() : null;

    // Show date: find a div whose text content matches a date pattern (M/D/YYYY)
    const dateEl = Array.from(document.querySelectorAll('div')).find(el => {
      const s = el.getAttribute('style') || '';
      return s.includes('font-size: 14px') && s.includes('font-weight: 600') &&
             /\d{1,2}\/\d{1,2}\/\d{4}/.test(el.textContent);
    });
    const dateText = dateEl ? dateEl.textContent.trim() : null;

    // All metric cards (height: 160px + border-radius: 16px)
    const cards = Array.from(document.querySelectorAll('div')).filter(el =>
      styleIncludes(el, 'height: 160px') && styleIncludes(el, 'border-radius: 16px')
    );

    const metrics = {};
    for (const card of cards) {
      // Metric name: first div with font-size: 16px and font-weight: 600
      const nameEl = Array.from(card.querySelectorAll('div')).find(el =>
        styleIncludes(el, 'font-size: 16px') && styleIncludes(el, 'font-weight: 600')
      );
      // Metric value: div with font-size: 32px
      const valueEl = Array.from(card.querySelectorAll('div')).find(el =>
        styleIncludes(el, 'font-size: 32px')
      );
      if (nameEl && valueEl) {
        metrics[nameEl.textContent.trim()] = valueEl.textContent.trim();
      }
    }

    // Check if older/newer show buttons are disabled
    const olderBtn  = document.querySelector(SEL.showNavOlder);
    const hasOlder  = olderBtn && !olderBtn.disabled;

    // Try to find the show's public/seller URL from any anchor on the page.
    // Whatnot show URLs contain "/live/" followed by a channel username and ID.
    const allAnchors = Array.from(document.querySelectorAll('a[href]'));
    const showLink = allAnchors.find(a => {
      const h = a.getAttribute('href') || '';
      return /\/live\/[^/]+\/[^/]+/.test(h) || /\/show\/\d+/.test(h);
    });
    const detailUrl = showLink
      ? (showLink.href.startsWith('http') ? showLink.href : 'https://www.whatnot.com' + showLink.getAttribute('href'))
      : null;

    return { title, dateText, metrics, hasOlder, cardCount: cards.length, detailUrl };
  }, { SEL: SELECTORS });
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

    // Switch to the target channel before scraping (skipped for test mode)
    if (MODE !== 'test' && CHANNEL_NAME) {
      await switchToChannel(page, CHANNEL_NAME);
    }

    // ── Mode: test ───────────────────────────────────────────────────────────
    if (MODE === 'test') {
      log('test mode — verifying seller access');
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(1500);
      await debugShot(page, '05-seller-hub');

      const currentUrl = page.url();
      const isSeller   = currentUrl.includes('/seller') || currentUrl.includes('/creator') || currentUrl.includes('/dashboard');
      const pageTitle  = await page.title();

      if (!isSeller) {
        throw new Error(`Credentials valid but seller hub not accessible. Landed on: ${currentUrl}.`);
      }

      process.stdout.write(JSON.stringify({ connected: true, email, page_title: pageTitle, seller_url: currentUrl }) + '\n');
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
      await page.waitForTimeout(2500);
      await debugShot(page, 'orders-01-show-page');

      // ── Step 1: Try to find and click a Lots / Orders / Sales tab ───────────
      const tabCandidates = [
        'button:has-text("Lots")',
        '[role="tab"]:has-text("Lots")',
        'button:has-text("Orders")',
        '[role="tab"]:has-text("Orders")',
        'button:has-text("Sales")',
        '[role="tab"]:has-text("Sales")',
        'button:has-text("Sold")',
        '[role="tab"]:has-text("Sold")',
        'a:has-text("Lots")',
        'a:has-text("Orders")',
      ];

      for (const sel of tabCandidates) {
        const tab = await page.$(sel).catch(() => null);
        if (!tab) continue;
        const visible = await tab.isVisible().catch(() => false);
        if (!visible) continue;
        const selected = await tab.evaluate(el => el.getAttribute('aria-selected')).catch(() => null);
        if (selected === 'true') { log(`tab already selected: ${sel}`); break; }
        await tab.click();
        await page.waitForTimeout(1800);
        log(`clicked tab: ${sel}`);
        await debugShot(page, 'orders-02-tab-clicked');
        break;
      }

      // ── Step 2: Wait for order-like content to appear ────────────────────────
      // Whatnot uses dynamic class names; look for structural signals instead.
      // "Lot #" text or "$" price values appearing in a repeated pattern is the signal.
      await page.waitForTimeout(1000);

      // ── Step 3: Extract order data from the page ─────────────────────────────
      const orders = await page.evaluate(() => {
        // Helper: find the closest ancestor container shared by a set of text nodes.
        // We look for a repeating structure: elements containing both a dollar amount
        // and a lot or order identifier.

        const bodyText = document.body.innerText || '';

        // --- Strategy A: rows containing "Lot #N" patterns ---
        // Whatnot typically shows lots as "Lot #1", "Lot #2" etc.
        // Find all elements whose text matches this pattern, then walk up to find
        // a container that also has price info.
        function findParentWithText(el, maxLevels = 6) {
          let node = el;
          for (let i = 0; i < maxLevels; i++) {
            node = node.parentElement;
            if (!node) break;
            if (node.tagName === 'TR' || node.tagName === 'LI' || node.tagName === 'ARTICLE') return node;
            const s = node.getAttribute('style') || '';
            // A container element is likely a card/row if it has flex/grid and a fixed height
            if ((s.includes('display: flex') || s.includes('display:flex')) && s.match(/height:\s*(4|5|6|7|8)\d/)) return node;
          }
          return el.parentElement?.parentElement || el;
        }

        const results = [];
        const seen = new Set();

        // Find all text nodes containing "Lot #" pattern
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        let textNode;
        while ((textNode = walker.nextNode())) {
          const text = textNode.textContent || '';
          const lotMatch = text.match(/Lot\s*#?\s*(\d+)/i);
          if (!lotMatch) continue;

          const el = textNode.parentElement;
          if (!el) continue;
          const container = findParentWithText(el);
          if (!container || seen.has(container)) continue;
          seen.add(container);

          const containerText = container.innerText || container.textContent || '';
          const lines = containerText.split('\n').map(l => l.trim()).filter(Boolean);

          // Extract lot number
          const lotNum = parseInt(lotMatch[1], 10);

          // Extract price(s): dollar amounts in the container
          const prices = [...containerText.matchAll(/\$[\d,]+\.?\d*/g)]
            .map(m => parseFloat(m[0].replace(/[^0-9.]/g, '')))
            .filter(v => !isNaN(v) && v > 0);

          // Extract buyer: look for "@username" patterns
          const buyerMatch = containerText.match(/@([\w.]+)/);
          const buyer = buyerMatch ? buyerMatch[1] : null;

          // Look for "Sold" / "Completed" / "Refunded" status text
          const statusMatch = containerText.match(/\b(sold|completed|refunded|cancelled|pending|shipped)\b/i);
          const status = statusMatch ? statusMatch[1].toLowerCase() : 'completed';

          // Item name: longest non-price, non-buyer, non-status line
          const itemName = lines
            .filter(l => !l.startsWith('@') && !l.startsWith('$') && !l.startsWith('Lot') && !/^(sold|completed|refunded|cancelled|pending|shipped|view)$/i.test(l) && l.length > 3)
            .sort((a, b) => b.length - a.length)[0] || null;

          results.push({
            lot_number: lotNum,
            buyer,
            item_name:  itemName,
            unit_price: prices.length > 0 ? prices[prices.length - 1] : null,
            total_price: prices.length > 1 ? prices.reduce((a, b) => a + b, 0) : (prices[0] || null),
            status,
            raw_text: containerText.replace(/\s+/g, ' ').trim().substring(0, 400),
          });
        }

        // --- Strategy B: table rows if Strategy A found nothing ---
        if (results.length === 0) {
          const rows = Array.from(document.querySelectorAll('tr, [role="row"]'));
          for (const row of rows) {
            if (seen.has(row)) continue;
            const text = row.innerText || row.textContent || '';
            if (text.length < 5) continue;
            const prices = [...text.matchAll(/\$[\d,]+\.?\d*/g)]
              .map(m => parseFloat(m[0].replace(/[^0-9.]/g, '')))
              .filter(v => !isNaN(v) && v > 0);
            if (prices.length === 0) continue;
            seen.add(row);
            const lotMatch = text.match(/(?:Lot\s*#?\s*|#)(\d+)/i);
            const buyerMatch = text.match(/@([\w.]+)/);
            results.push({
              lot_number: lotMatch ? parseInt(lotMatch[1], 10) : null,
              buyer: buyerMatch ? buyerMatch[1] : null,
              item_name: null,
              unit_price: prices[prices.length - 1] || null,
              total_price: prices[0] || null,
              status: 'completed',
              raw_text: text.replace(/\s+/g, ' ').trim().substring(0, 400),
            });
          }
        }

        // Capture page snapshot if both strategies find nothing
        if (results.length === 0) {
          return {
            fallback: true,
            html: document.body.innerHTML.substring(0, 12000),
            text: document.body.innerText.substring(0, 4000),
          };
        }

        return results;
      });

      if (orders && orders.fallback) {
        process.stderr.write('SELECTOR_MISS: Could not find order/lot rows on the show page.\n');
        process.stderr.write('PAGE_TEXT_SAMPLE: ' + (orders.text || '') + '\n');
        if (DEBUG) process.stderr.write('PAGE_HTML: ' + (orders.html || '') + '\n');
        process.exit(2);
      }

      const normalized = (orders || [])
        .filter(o => o.lot_number || o.buyer || o.item_name)
        .map(o => ({
          order_id:    null,
          buyer:       o.buyer || null,
          item_name:   o.item_name || null,
          lot_number:  o.lot_number || null,
          quantity:    1,
          unit_price:  o.unit_price,
          total_price: o.total_price,
          status:      o.status || 'completed',
          raw_text:    o.raw_text || null,
        }));

      if (normalized.length === 0) {
        process.stderr.write('SELECTOR_MISS: Orders array was empty after normalization.\n');
        process.exit(2);
      }

      process.stdout.write(JSON.stringify(normalized, null, 2) + '\n');
      log(`show-orders: returned ${normalized.length} orders`);
      process.exit(0);
    }

    // ── Mode: seller-shows ────────────────────────────────────────────────────
    // Scrapes /seller/shows to capture show URLs for each past show.
    // Returns [{title, show_date, detail_url}].
    if (MODE === 'seller-shows') {
      log('navigating to seller shows list');
      await page.goto(URLS.shows, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(2500);
      await debugShot(page, 'seller-shows-01-list');

      // Scroll to load more shows
      for (let i = 0; i < 5; i++) {
        await page.evaluate(() => window.scrollBy(0, window.innerHeight));
        await page.waitForTimeout(600);
      }
      await debugShot(page, 'seller-shows-02-scrolled');

      const shows = await page.evaluate(() => {
        const results = [];
        const seen = new Set();

        // Find all links that look like show detail pages
        const anchors = Array.from(document.querySelectorAll('a[href]'));
        for (const a of anchors) {
          const href = a.getAttribute('href') || '';
          // Show URLs: /live/<username>/<id> or /show/<id> or /seller/shows/<id>
          if (!(/\/live\/[^/]+\/[^/]+/.test(href) || /\/show\/[\w-]+/.test(href) || /\/seller\/shows\/[\w-]+/.test(href))) continue;
          const fullUrl = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
          if (seen.has(fullUrl)) continue;
          seen.add(fullUrl);

          // Walk up from the anchor to find its container
          let container = a;
          for (let i = 0; i < 6; i++) {
            container = container.parentElement;
            if (!container) break;
            const t = container.innerText || container.textContent || '';
            if (t.length > 20) break;
          }

          const containerText = container ? (container.innerText || container.textContent || '') : a.textContent || '';
          const dateMatch = containerText.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/);
          let showDate = null;
          if (dateMatch) {
            const [, m, d, y] = dateMatch;
            const year = y.length === 2 ? '20' + y : y;
            showDate = `${year}-${m.padStart(2,'0')}-${d.padStart(2,'0')}`;
          }

          // Title: the anchor text or longest non-date, non-price line in container
          const anchorText = (a.textContent || '').trim();
          const title = anchorText.length > 3 ? anchorText : (
            (containerText.split('\n').map(l => l.trim()).filter(l => l.length > 5 && !/^\$/.test(l) && !/^\d{1,2}\//.test(l))[0]) || null
          );

          results.push({ title, show_date: showDate, detail_url: fullUrl });
        }

        return results.length > 0 ? results : {
          fallback: true,
          text: document.body.innerText.substring(0, 3000),
        };
      });

      if (shows && shows.fallback) {
        process.stderr.write('SELECTOR_MISS: No show links found on /seller/shows.\n');
        process.stderr.write('PAGE_TEXT: ' + (shows.text || '') + '\n');
        process.exit(2);
      }

      process.stdout.write(JSON.stringify(shows, null, 2) + '\n');
      log(`seller-shows: returned ${shows.length} show URLs`);
      process.exit(0);
    }

    // ── Mode: analytics (default) ─────────────────────────────────────────────
    if (MODE === 'analytics' || MODE === 'shows') {
      const isAnalytics = MODE === 'analytics';
      const targetUrl   = isAnalytics ? URLS.analytics : URLS.shows;

      log(`navigating to ${targetUrl}`);
      await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(2000);
      await debugShot(page, '05-analytics-page');

      if (isAnalytics) {
        // Click the "Shows" tab
        const showsTab = await page.waitForSelector(SELECTORS.analyticsShowsTab, { timeout: 10000 })
          .catch(() => null);

        if (!showsTab) {
          const html = await page.evaluate(() => document.body.innerHTML.substring(0, 5000));
          process.stderr.write('SELECTOR_MISS: Analytics "Shows" tab not found.\n');
          process.stderr.write('PAGE_SNAPSHOT: ' + html + '\n');
          process.exit(2);
        }

        // Only click if not already selected
        const isSelected = await showsTab.evaluate(el => el.getAttribute('aria-selected') === 'true');
        if (!isSelected) {
          await showsTab.click();
          await page.waitForTimeout(1500);
        }
        await debugShot(page, '06-shows-tab');
      }

      const results = [];

      for (let i = 0; i < LIMIT; i++) {
        await page.waitForTimeout(800);
        await debugShot(page, `07-show-${i}`);

        const data = await extractAnalyticsMetrics(page);
        log(`show ${i}: title="${data.title}" date="${data.dateText}" cards=${data.cardCount}`);

        if (!data.title && data.cardCount === 0) {
          log('no show data visible — stopping');
          break;
        }

        const m = data.metrics;

        // Map metric labels to normalized field names
        // Labels vary slightly between shows ("Total Estimated Earnings" vs "Completed Earnings")
        // so we check multiple possible label strings.
        const get = (...labels) => {
          for (const l of labels) { if (m[l] !== undefined) return m[l]; }
          return null;
        };

        results.push({
          title:                   data.title,
          show_date:               parseDateString(data.dateText),
          show_date_raw:           data.dateText,
          detail_url:              data.detailUrl || null,

          // Sales Metrics
          gross_revenue:           parseMoney(get('Estimated Sales')),
          whatnot_net:             parseMoney(get('Total Estimated Earnings')),
          completed_earnings:      parseMoney(get('Completed Earnings')),
          units_sold:              parseInteger(get('Orders')),
          avg_order_value:         parseMoney(get('Average Order Value')),
          giveaway_spend:          parseMoney(get('Giveaway Spend')),
          giveaways_count:         parseInteger(get('Giveaways')),

          // Stream Metrics
          buyers_count:            parseInteger(get('Buyers')),
          first_time_buyers:       parseInteger(get('First Time Buyers')),
          returning_buyers:        parseInteger(get('Returning Buyers')),
          shares_count:            parseInteger(get('Shares')),
          show_duration:           parseDurationToMinutes(get('Show Duration')),
          max_concurrent_viewers:  parseInteger(get('Max Concurrent Viewers')),
          total_views:             parseInteger(get('Total Views')),
          avg_order_rating:        parseMoney(get('Average Order Rating')),

          // Raw metrics map for debugging
          _raw_metrics: m,
        });

        if (!data.hasOlder) {
          log('no older shows — reached end of history');
          break;
        }

        // Navigate to the next older show
        await page.click(SELECTORS.showNavOlder);
        log(`navigated to older show (${i + 1} of ${LIMIT})`);
      }

      if (results.length === 0) {
        const html = await page.evaluate(() => document.body.innerHTML.substring(0, 5000));
        process.stderr.write('SELECTOR_MISS: No show data extracted from analytics page.\n');
        process.stderr.write('PAGE_SNAPSHOT: ' + html + '\n');
        process.exit(2);
      }

      process.stdout.write(JSON.stringify(results, null, 2) + '\n');
      log(`done — returned ${results.length} shows`);
      process.exit(0);
    }

    process.stderr.write(`Unknown WHATNOT_MODE: ${MODE}\n`);
    process.exit(1);

  } catch (err) {
    await debugShot(page, 'error');
    process.stderr.write('Error: ' + err.message + '\n');
    process.exit(1);
  } finally {
    await browser.close();
  }
})();
