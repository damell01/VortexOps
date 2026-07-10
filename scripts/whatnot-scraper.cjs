/**
 * Whatnot Seller Dashboard Scraper
 *
 * Modes (WHATNOT_MODE env var):
 *   analytics    (default) — scrape per-show analytics from the dashboard, newest first
 *   test         — verify credentials only, output {connected, email}
 *   show-orders  — scrape order/lot list for one show (requires WHATNOT_SHOW_URL)
 *   shows        — legacy: scrape the /seller/shows list page (less data, kept as fallback)
 *   discover     — 3-phase deep crawl: (1) visit all Seller Hub nav pages, (2) drill into
 *                  individual show detail pages and click each tab (Orders/Lots/Sales/Buyers),
 *                  (3) crawl /seller/orders and individual order detail pages. Logs all
 *                  Phoenix Channel (WS) SEND/RECV frames so you can read topic + event names.
 *   ws-explore   — direct Phoenix Channels probe: skips browser scraping, opens WS directly,
 *                  joins candidate seller_hub:* channels, captures 20 s of messages.
 *                  Output: { topics, messages } — use to learn the real channel/event names.
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
  // Login page — selectors from live HTML (July 2026)
  loginEmailInput:    '#input-login-email, [data-testid="input-login-email"], input[name="identifier"]',
  loginPasswordInput: '#input-login-password, [data-testid="input-login-password"], input[type="password"]',
  loginSubmitBtn:     '[data-testid="button-login-submit"], button[type="submit"]',

  // Analytics page — tabs
  // Text-based selectors are tried first in the scraper loop; these are the CSS fallbacks.
  analyticsShowsTab:  'button[aria-controls="simple-tabpanel-1"], button#simple-tab-1, [role="tab"][data-value="shows"], [role="tab"][data-index="1"]',

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
  home:           'https://www.whatnot.com',
  login:          'https://www.whatnot.com/login',
  analytics:      'https://www.whatnot.com/dashboard/analytics/overview',
  dashboardShows: 'https://www.whatnot.com/dashboard/shows',
  dashboard:      'https://www.whatnot.com/dashboard',
  shows:          'https://www.whatnot.com/seller/shows',
  sellerHub:      'https://www.whatnot.com/seller',
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

// Always-on milestone logging (visible in artisan output / error table)
function info(...args) {
  process.stderr.write('[whatnot] ' + args.join(' ') + '\n');
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

  // Wait for React to render at least some content before checking for the form
  await page.waitForFunction(
    () => (document.body.innerText || '').trim().length > 30,
    { timeout: 10000 }
  ).catch(() => {});

  const currentUrl = page.url();
  info('performLogin: URL after goto:', currentUrl);

  // Already past the login page (session cookie still valid) — goto(/login) redirected away
  if (!currentUrl.includes('/login') && !currentUrl.includes('/signin') && !currentUrl.includes('/auth')) {
    info('performLogin: already logged in, redirected to', currentUrl);
    return;
  }

  const emailInput = await page.waitForSelector(SELECTORS.loginEmailInput, { timeout: 15000 })
    .catch(() => null);

  if (!emailInput) {
    const bodyText = await page.evaluate(() => (document.body.innerText || '').trim()).catch(() => '');
    info('performLogin: login form not found — URL:', currentUrl, '| body chars:', bodyText.length);
    if (bodyText.length < 100) {
      info('performLogin: body nearly empty — bot-detection is likely blocking the login page');
      throw new Error(
        'Whatnot login page rendered empty (bot detection active). ' +
        'Body length: ' + bodyText.length + '. ' +
        'Run with WHATNOT_DEBUG=1 (php artisan whatnot:import --debug) to capture screenshots for inspection.'
      );
    }
    info('performLogin: body preview:', bodyText.substring(0, 300));
    throw new Error('Login form not found on ' + currentUrl + '. Page may have changed. Body: ' + bodyText.substring(0, 200));
  }

  info('filling login form — email length:', email.length, '| password length:', password.length);
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
  info('post-login URL:', url);
  if (url.includes('/login') || url.includes('/signin') || url.includes('/auth') || url.includes('/verify')) {
    const pageText = await page.textContent('body').catch(() => '');
    const snippet  = pageText.replace(/\s+/g, ' ').trim().substring(0, 400);
    info('post-login page text:', snippet);
    if (pageText.toLowerCase().includes('incorrect') || pageText.toLowerCase().includes('invalid') ||
        pageText.toLowerCase().includes('wrong') || pageText.toLowerCase().includes('not found')) {
      throw new Error(`Login failed — credentials rejected by Whatnot. Page said: ${snippet.substring(0, 200)}`);
    }
    if (pageText.toLowerCase().includes('verify') || pageText.toLowerCase().includes('code') ||
        pageText.toLowerCase().includes('2fa') || pageText.toLowerCase().includes('authenticat')) {
      throw new Error('Login requires 2FA/verification. Disable 2FA on the Whatnot account or use the cookie bootstrap (storage/whatnot-cookies.json).');
    }
    throw new Error(`Login did not complete — still on ${url}. Page text: ${snippet.substring(0, 200)}`);
  }
}

// ── Seller mode activation ────────────────────────────────────────────────────
// Whatnot accounts distinguish buyer mode and seller mode. After login the
// session may land in buyer mode where /seller shows a marketing page and
// /seller/shows returns 404.
//
// The logged-in user (e.g. 65170453) is a TEAM MEMBER of a seller channel
// (e.g. 408585 = Vortex Breaks). To activate seller mode the session must
// switch to that team channel — NOT click "Start Selling" which starts a new
// seller application for the individual user.
//
// Strategy 1: Click the "Switch to Selling" nav element. Whatnot renders it as
//   a React component (not always a <button>/<a>), so use getByText().
// Strategy 2: Use switchToChannel() — opens the profile drawer, clicks
//   Switch Role, then selects the channel by name. Requires CHANNEL_NAME.
//
// If the account is already in seller mode this is a no-op.
// Throws if buyer mode is detected but can't be exited.

async function ensureSellerMode(page) {
  const pageText = await page.evaluate(() =>
    (document.body.innerText || '').substring(0, 600)
  ).catch(() => '');

  // "For Brands / Start Selling on Whatnot" = the buyer-mode marketing page.
  if (!/for brands|start selling on whatnot/i.test(pageText)) return;

  info('ensureSellerMode: buyer mode detected — opening nav drawer to click "Switch to Selling"');
  await debugShot(page, 'seller-mode-01-buyer-mode');

  // ── Step 1: navigate to homepage (not /seller marketing page) ────────────────
  // The regular homepage renders the full logged-in nav including the hamburger drawer.
  info('ensureSellerMode: navigating to https://www.whatnot.com');
  await page.goto('https://www.whatnot.com', { waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});
  await debugShot(page, 'seller-mode-02-homepage');

  // ── Step 2: open the hamburger / nav drawer ───────────────────────────────────
  // Confirmed (run #4): button[aria-label*="menu"] opens the nav drawer that has
  // "Switch to Selling" at the very top. The active role is already "vortexbreaks"
  // so no role switch is needed — just click "Switch to Selling" directly.
  const menuBtn = page.locator('button[aria-label*="menu" i]').first();
  if (await menuBtn.isVisible().catch(() => false)) {
    info('ensureSellerMode: opening nav drawer via menu button');
    await menuBtn.click({ force: true, timeout: 8000 }).catch(async () => {
      await menuBtn.evaluate(el => el.click()).catch(() => {});
    });
    await page.waitForTimeout(1500);
    await debugShot(page, 'seller-mode-03-drawer-open');
  } else {
    info('ensureSellerMode: menu button not found — dumping page text');
    const t = await page.evaluate(() => (document.body.innerText || '').substring(0, 400)).catch(() => '');
    info('ensureSellerMode: page text:', t);
    await debugShot(page, 'seller-mode-03-no-menu-btn');
  }

  // ── Step 3: click "Switch to Selling" from within the open drawer ────────────
  const switchLoc = page.getByText('Switch to Selling', { exact: true }).first();
  if (!await switchLoc.isVisible().catch(() => false)) {
    // Also try case-insensitive / inexact match as fallback
    const switchLocFuzzy = page.getByText('Switch to Selling', { exact: false }).first();
    const drawerText = await page.evaluate(() => (document.body.innerText || '').substring(0, 600)).catch(() => '');
    info('ensureSellerMode: "Switch to Selling" not found in drawer. Drawer text:', drawerText.substring(0, 400));
    await debugShot(page, 'seller-mode-03b-no-switch-to-selling');

    if (!await switchLocFuzzy.isVisible().catch(() => false)) {
      throw new Error(
        '"Switch to Selling" not found in nav drawer — check /tmp/whatnot-debug-seller-mode-*.png\n' +
        'Drawer text: ' + drawerText.substring(0, 300)
      );
    }
  }

  // IMPORTANT: click the element — do NOT navigate directly to its href.
  // "Switch to Selling" has a React onClick that makes a server-side mode-switch
  // API call before navigating. Direct page.goto(href) skips that call entirely
  // and leaves the session in buyer mode even though the URL changes to /dashboard.
  info('ensureSellerMode: clicking "Switch to Selling" — letting React fire mode-switch API call');
  const clicked = await switchLoc.click({ force: true, timeout: 10000 })
    .then(() => true)
    .catch(() => false);

  if (!clicked) {
    info('ensureSellerMode: force click failed — falling back to evaluate click on anchor/button');
    await switchLoc.evaluate(el => {
      let node = el;
      for (let i = 0; i < 8; i++) {
        if (!node) break;
        if (node.tagName === 'A' || node.tagName === 'BUTTON') { node.click(); return; }
        node = node.parentElement;
      }
      el.click();
    }).catch(() => {});
  }

  // Wait for the React mode-switch to complete and page to settle
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(2000);
  await debugShot(page, 'seller-mode-04-after-switch-to-selling');

  // ── Verify seller mode from the current page ──────────────────────────────────
  // Do NOT hard-navigate to /seller/shows for verification — that's a Next.js SSR
  // route that 404s when hit directly as a team member (server can't determine the
  // active channel context). Instead check the current URL/page after the click:
  // landing on /dashboard confirms the mode switch fired correctly.
  const postClickUrl  = page.url();
  const postClickText = await page.evaluate(() => (document.body.innerText || '').substring(0, 600)).catch(() => '');
  info('ensureSellerMode: post-click URL:', postClickUrl);
  info('ensureSellerMode: post-click text (first 200):', postClickText.substring(0, 200));
  await debugShot(page, 'seller-mode-05-post-click-verify');

  const sellerModeConfirmed =
    /\/dashboard|\/seller\/hub|\/creator/i.test(postClickUrl) ||
    /seller hub|your shows|schedule a show|go live/i.test(postClickText);

  if (!sellerModeConfirmed) {
    // Still showing buyer-mode marketing page
    if (/for brands|start selling on whatnot/i.test(postClickText)) {
      throw new Error(
        '"Switch to Selling" clicked but still in buyer mode.\n' +
        'URL: ' + postClickUrl + '\n' +
        'Page text: ' + postClickText.substring(0, 300)
      );
    }
    // Ambiguous — log and continue; the main scraping URL attempts will sort it out
    info('ensureSellerMode: could not confirm seller mode from URL/text — proceeding anyway');
    info('ensureSellerMode: URL:', postClickUrl, '| text:', postClickText.substring(0, 150));
  } else {
    info('ensureSellerMode: seller mode confirmed — URL:', postClickUrl);
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
  if (!channelName) return;
  log(`switching to channel: "${channelName}"`);

  await debugShot(page, 'role-switch-01-pre');
  info('switchToChannel: current URL before switch:', page.url());

  const SWITCH_ROLE_SEL = '#team-invite-switch-role-anchor';

  // The Switch Role button is inside the profile drawer — invisible until
  // the avatar button in the top-nav is clicked. Try the avatar first, then
  // fall back to other nav triggers.
  const isInViewport = async (el) => {
    if (!el) return false;
    return el.evaluate(node => {
      const r = node.getBoundingClientRect();
      return r.top >= 0 && r.bottom <= window.innerHeight && r.width > 0 && r.height > 0;
    }).catch(() => false);
  };

  // Step 1 — open the profile drawer via the avatar BUTTON
  // Only use button selectors here — <a> tags navigate instead of opening drawers.
  // Confirmed July 2026: button:has([style*="--avatar-size"]) opens the seller sidebar
  // and triggers seller GraphQL calls even in buyer mode on the logged-in homepage.
  const avatarTriggers = [
    'button:has([style*="--avatar-size"])',
    '[data-testid*="avatar"]',
    '[data-testid*="profile"]',
    'button[aria-label*="profile" i]',
    'button[aria-label*="account" i]',
    '[aria-label="Open navigation drawer"]',
    '[aria-label="Open sidebar"]',
    'button[aria-label*="menu" i]',
  ];

  let drawerOpened = false;
  for (const sel of avatarTriggers) {
    const trigger = await page.locator(sel).first().elementHandle().catch(() => null);
    if (!trigger || !await trigger.isVisible().catch(() => false)) continue;
    info('switchToChannel: clicking avatar/nav trigger:', sel);
    await trigger.click().catch(async () => {
      await page.locator(sel).first().click({ force: true }).catch(() => {});
    });
    await page.waitForTimeout(1500);
    // Check if drawer is open: Switch Role element in DOM OR "Switch Role" text visible.
    // Don't require viewport — it may be below the fold in a tall sidebar.
    const btnAfterClick = await page.$(SWITCH_ROLE_SEL).catch(() => null);
    const textAfterClick = await page.getByText('Switch Role', { exact: false }).first().isVisible().catch(() => false);
    if (btnAfterClick || textAfterClick) {
      info('switchToChannel: drawer opened after clicking', sel, '— Switch Role found in DOM');
      drawerOpened = true;
      break;
    }
    // Dismiss and try next trigger
    await page.keyboard.press('Escape').catch(() => {});
    await page.waitForTimeout(500);
  }

  if (!drawerOpened) {
    info('switchToChannel: no avatar trigger opened the drawer — trying to JS-click Switch Role directly');
  }

  // Step 2 — click Switch Role (by ID if available, then by text, then JS-click)
  await debugShot(page, 'role-switch-02-drawer-open');

  const switchBtn = await page.$(SWITCH_ROLE_SEL).catch(() => null);
  const switchRoleByText = page.getByText('Switch Role', { exact: false }).first();
  const switchRoleVisible = await switchRoleByText.isVisible().catch(() => false);

  if (switchRoleVisible) {
    info('switchToChannel: clicking "Switch Role" by text');
    await switchRoleByText.click({ force: true, timeout: 8000 }).catch(async () => {
      await switchRoleByText.evaluate(el => el.click()).catch(() => {});
    });
  } else if (switchBtn) {
    info('switchToChannel: JS-clicking Switch Role by selector (may be off-viewport)');
    await page.evaluate(sel => {
      const el = document.querySelector(sel);
      if (el) el.click();
    }, SWITCH_ROLE_SEL);
  } else {
    info('switchToChannel: WARNING — Switch Role not found (ID or text) — dumping page text for diagnosis');
    const pageSnippet = await page.evaluate(() => (document.body.innerText || '').substring(0, 500)).catch(() => '');
    info('switchToChannel: page text:', pageSnippet);
    await debugShot(page, 'role-switch-no-switch-role');
    return;
  }
  await page.waitForTimeout(2000);
  await debugShot(page, 'role-switch-03-role-list');

  // ── Click the target channel ──────────────────────────────────────────────────
  // Dump visible text so we can diagnose what the role picker shows.
  const roleListText = await page.evaluate(() => (document.body.innerText || '').substring(0, 600)).catch(() => '');
  info('switchToChannel: role list text:', roleListText.substring(0, 400));

  // Try multiple name variants — handles cases where the caller passes
  // "VortexBreaks" (camelCase) but the UI shows "Vortex Breaks" (spaced).
  const nameVariants = [...new Set([
    channelName,
    channelName.replace(/([a-z])([A-Z])/g, '$1 $2'),   // VortexBreaks → Vortex Breaks
    channelName.replace(/([A-Z])/g, ' $1').trim(),      // ABCBreaks → A B C Breaks
  ])];

  let target = null;
  for (const variant of nameVariants) {
    const loc = page.getByText(variant, { exact: false }).first();
    if (await loc.isVisible().catch(() => false)) {
      info(`switchToChannel: found channel option matching "${variant}"`);
      target = loc;
      break;
    }
  }

  if (!target) {
    await debugShot(page, 'role-switch-failed-channel');
    info(`switchToChannel: WARNING — channel "${channelName}" (variants: ${nameVariants.join(', ')}) not found in role list`);
    info('switchToChannel: role list text was:', roleListText.substring(0, 300));
    return;
  }

  info(`switchToChannel: clicking channel option`);
  await target.click({ force: true, timeout: 8000 }).catch(async () => {
    await target.evaluate(el => el.click()).catch(() => {});
  });

  // Wait for the role switch to take effect: the nav re-renders and "Seller Hub" appears.
  // Use waitForFunction instead of a fixed delay so we don't over-wait.
  await page.waitForFunction(
    () => document.body.innerText.includes('Seller Hub'),
    { timeout: 6000 }
  ).catch(() => {
    info('switchToChannel: Seller Hub not found after channel click (may need more time or click was wrong element)');
  });
  await debugShot(page, 'role-switch-04-done');
  info('switchToChannel: done, URL now:', page.url());
}

// ── Extract all metric cards from the current analytics page view ─────────────

async function extractAnalyticsMetrics(page) {
  return page.evaluate(({ SEL }) => {
    // Find a div by checking its inline style for a substring
    function styleIncludes(el, ...parts) {
      const s = el.getAttribute('style') || '';
      return parts.every(p => s.includes(p));
    }

    // ── Show title ────────────────────────────────────────────────────────────
    // Primary: nowrap/ellipsis div with max-width: 90% (original approach)
    let titleEl = document.querySelector(SEL.showTitle);
    // Fallback A: any div/h1-h4 with a large font-size that has meaningful text
    if (!titleEl) {
      titleEl = Array.from(document.querySelectorAll('h1, h2, h3, h4')).find(el =>
        el.textContent.trim().length > 4
      );
    }
    // Fallback B: any element styled as a large heading
    if (!titleEl) {
      titleEl = Array.from(document.querySelectorAll('div, span')).find(el => {
        const s = el.getAttribute('style') || '';
        const fs = parseInt((s.match(/font-size:\s*(\d+)px/) || [])[1] || '0', 10);
        return fs >= 20 && el.textContent.trim().length > 4 && el.childElementCount <= 2;
      });
    }
    const title = titleEl ? titleEl.textContent.trim() : null;

    // ── Show date ─────────────────────────────────────────────────────────────
    // Primary: div with font-size:14px + font-weight:600 containing a date
    let dateEl = Array.from(document.querySelectorAll('div')).find(el => {
      const s = el.getAttribute('style') || '';
      return s.includes('font-size: 14px') && s.includes('font-weight: 600') &&
             /\d{1,2}\/\d{1,2}\/\d{4}/.test(el.textContent);
    });
    // Fallback: any leaf element containing a date pattern
    if (!dateEl) {
      dateEl = Array.from(document.querySelectorAll('div, span, p, time')).find(el =>
        el.childElementCount === 0 && /\d{1,2}\/\d{1,2}\/\d{4}/.test(el.textContent)
      );
    }
    const dateText = dateEl ? dateEl.textContent.trim() : null;

    // ── Metric cards ──────────────────────────────────────────────────────────
    // Primary: height:160px + border-radius:16px (original approach)
    let cards = Array.from(document.querySelectorAll('div')).filter(el =>
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

    // Fallback: scan for known metric labels paired with a value sibling/child.
    // Whatnot sometimes changes card dimensions but metric label text stays stable.
    if (Object.keys(metrics).length === 0) {
      const knownLabels = [
        'Estimated Sales', 'Total Estimated Earnings', 'Completed Earnings',
        'Net Revenue', 'Gross Revenue', 'Revenue',
        'Orders', 'Average Order Value', 'AOV',
        'Giveaway Spend', 'Giveaways',
        'Buyers', 'First Time Buyers', 'New Buyers', 'Returning Buyers',
        'Shares', 'Likes', 'Views', 'Followers Gained',
        'Show Duration', 'Duration', 'Watch Time',
      ];

      const allLeaves = Array.from(document.querySelectorAll('*')).filter(
        el => el.childElementCount === 0 && el.textContent.trim().length > 0
      );

      for (const label of knownLabels) {
        const labelEl = allLeaves.find(el => el.textContent.trim() === label);
        if (!labelEl) continue;

        // Look for a value in the same card container (up to 5 levels up)
        let container = labelEl;
        for (let i = 0; i < 5; i++) {
          container = container.parentElement;
          if (!container) break;
          const valueEl = Array.from(container.querySelectorAll('*')).find(el =>
            el !== labelEl &&
            el.childElementCount === 0 &&
            /^\$?[\d,.]+(%|k|m|h|min)?$/i.test(el.textContent.trim())
          );
          if (valueEl) {
            metrics[label] = valueEl.textContent.trim();
            break;
          }
        }
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

// ── Shows-list DOM extractor ──────────────────────────────────────────────────
// Used when we land on a list-style page (/dashboard/shows, /seller/shows).
// Returns an array of show objects in the same shape as the analytics extractor,
// with whatever fields are available in the list view.

async function extractShowsListFromDom(page) {
  const shows = await page.evaluate(() => {
    const results = [];
    const addedUrls = new Set();

    // Find every anchor that looks like a show detail link.
    // The lookahead (?=[?#]|$) requires the ID to be the final path segment —
    // sub-page links like /seller/shows/<id>/analytics would otherwise match
    // and return "Analytics" as the show title.
    // Whatnot show URL patterns: /live/<user>/<id>, /show/<id>, /seller/shows/<id>
    const anchors = Array.from(document.querySelectorAll('a[href]'));
    for (const a of anchors) {
      const href = a.getAttribute('href') || '';
      if (!(/\/live\/[^/]+\/[^/?#\s]+(?=[?#]|$)/.test(href) ||
            /\/show\/[\w-]+(?=[?#]|$)/.test(href) ||
            /\/seller\/shows\/[\w-]+(?=[?#]|$)/.test(href))) continue;
      const fullUrl = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
      if (addedUrls.has(fullUrl)) continue;
      addedUrls.add(fullUrl);

      // Walk up to find a card/row container that contains meaningful text (title + date).
      let container = a;
      for (let i = 0; i < 10; i++) {
        if (!container.parentElement) break;
        container = container.parentElement;
        const t = (container.innerText || '').trim();
        if (t.length > 30) break;
      }

      const text = (container.innerText || container.textContent || '').trim();
      if (text.length < 5) continue;
      const lines = text.split('\n').map(l => l.trim()).filter(Boolean);

      // Date parsing — try formats in order of specificity
      let showDate = null;

      // ISO: 2026-07-05
      const iso = text.match(/\b(20\d\d)[-\/](0[1-9]|1[0-2])[-\/](0[1-9]|[12]\d|3[01])\b/);
      if (iso) {
        showDate = `${iso[1]}-${iso[2]}-${iso[3]}`;
      }

      // M/D/YYYY or M-D-YYYY: 7/5/2026
      if (!showDate) {
        const mdy = text.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/);
        if (mdy) {
          const year = mdy[3].length === 2 ? '20' + mdy[3] : mdy[3];
          showDate = `${year}-${mdy[1].padStart(2,'0')}-${mdy[2].padStart(2,'0')}`;
        }
      }

      // Month-name: "July 5, 2026" / "Jul 5 2026"
      if (!showDate) {
        const mo = {jan:1,feb:2,mar:3,apr:4,may:5,jun:6,jul:7,aug:8,sep:9,oct:10,nov:11,dec:12};
        const mn = text.match(/\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2})(?:st|nd|rd|th)?,?\s*(20\d\d)\b/i);
        if (mn) {
          const m = mo[mn[1].substring(0,3).toLowerCase()];
          showDate = `${mn[3]}-${String(m).padStart(2,'0')}-${mn[2].padStart(2,'0')}`;
        } else {
          // "5 July 2026"
          const alt = text.match(/\b(\d{1,2})(?:st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(20\d\d)\b/i);
          if (alt) {
            const m = mo[alt[2].substring(0,3).toLowerCase()];
            showDate = `${alt[3]}-${String(m).padStart(2,'0')}-${alt[1].padStart(2,'0')}`;
          }
        }
      }

      // Relative: "2 days ago", "yesterday", "3 weeks ago"
      if (!showDate) {
        const rel = text.match(/\b(\d+)\s+(day|week)s?\s+ago\b/i);
        if (rel) {
          const d = new Date();
          if (rel[2].toLowerCase() === 'week') d.setDate(d.getDate() - parseInt(rel[1]) * 7);
          else d.setDate(d.getDate() - parseInt(rel[1]));
          showDate = d.toISOString().substring(0, 10);
        } else if (/\byesterday\b/i.test(text)) {
          const d = new Date(); d.setDate(d.getDate() - 1);
          showDate = d.toISOString().substring(0, 10);
        } else if (/\btoday\b/i.test(text)) {
          showDate = new Date().toISOString().substring(0, 10);
        }
      }

      // Revenue: largest dollar amount in container
      const prices = [...text.matchAll(/\$[\d,]+\.?\d*/g)]
        .map(m => parseFloat(m[0].replace(/[^0-9.]/g, '')))
        .filter(v => !isNaN(v) && v > 0);

      // Orders / units
      const unitMatch = text.match(/(\d{1,6})\s*(?:orders?|lots?\s+sold|units?\s+sold|sales)/i);
      // Views
      const viewMatch = text.match(/(\d{1,6})\s*(?:viewers?|views?)/i);

      // Title: anchor text first, then longest meaningful line
      const anchorText = (a.innerText || a.textContent || '').trim();
      const title = anchorText.length > 5 ? anchorText : (
        lines.filter(l =>
          l.length > 5 &&
          !/^\d+$/.test(l) &&
          !/^\$/.test(l) &&
          !/^\d{1,2}[\/\-]/.test(l) &&
          !/^20\d\d/.test(l) &&
          !/^(Live|Ended|Cancelled|Completed|Upcoming)$/i.test(l)
        ).sort((a, b) => b.length - a.length)[0] || null
      );

      results.push({
        title:                  title || null,
        show_date:              showDate,
        show_date_raw:          mdy ? mdy[0] : null,
        detail_url:             fullUrl,
        gross_revenue:          prices.length > 0 ? Math.max(...prices) : null,
        whatnot_net:            null,
        completed_earnings:     null,
        units_sold:             unitMatch ? parseInt(unitMatch[1], 10) : null,
        avg_order_value:        null,
        giveaway_spend:         null,
        giveaways_count:        null,
        buyers_count:           null,
        first_time_buyers:      null,
        returning_buyers:       null,
        shares_count:           null,
        show_duration:          null,
        max_concurrent_viewers: null,
        total_views:            viewMatch ? parseInt(viewMatch[1], 10) : null,
        avg_order_rating:       null,
        _raw_metrics:           {},
        _list_source:           true,
      });
    }

    return results;
  });

  return shows;
}

// ── API interception helpers ──────────────────────────────────────────────────
// When Whatnot's React SPA loads the analytics page it makes API/GraphQL calls
// to fetch show data. Intercepting those responses gives us clean, structured
// JSON — no DOM scraping, no selector maintenance.

function scoreShowObject(obj) {
  if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return 0;
  const keys = Object.keys(obj).map(k => k.toLowerCase());
  let score = 0;
  if (keys.some(k => /\btitle\b|show_title|show_name/.test(k)))     score += 3;
  if (keys.some(k => /\bdate\b|started_at|created_at|show_date/.test(k))) score += 2;
  if (keys.some(k => /revenue|sales|gross|earnings|estimated/.test(k)))   score += 3;
  if (keys.some(k => /\borders\b|units_sold|buyers/.test(k)))        score += 2;
  if (keys.some(k => /\bviews\b|viewers|watch/.test(k)))             score += 1;
  if (keys.some(k => /\bduration\b/.test(k)))                        score += 1;
  return score;
}

function findShowsInJson(obj, url, depth = 0) {
  if (depth > 7) return null;
  if (Array.isArray(obj) && obj.length > 0 && typeof obj[0] === 'object') {
    const s = scoreShowObject(obj[0]);
    if (s >= 4) return { array: obj, score: s, url };
  }
  if (typeof obj === 'object' && obj !== null && !Array.isArray(obj)) {
    let best = null;
    for (const val of Object.values(obj)) {
      const found = findShowsInJson(val, url, depth + 1);
      if (found && (!best || found.score > best.score)) best = found;
    }
    return best;
  }
  return null;
}

function extractShowsFromCapture(captured) {
  let best = null;
  for (const { url, body } of captured) {
    const found = findShowsInJson(body, url);
    if (found && (!best || found.score > best.score)) best = found;
  }
  if (best) {
    info('API intercept: data found in', best.url.replace('https://www.whatnot.com', ''));
    info('API intercept:', best.array.length, 'records, score=' + best.score);
    info('API intercept: first record keys:', Object.keys(best.array[0]).join(', '));
  }
  return best ? best.array : null;
}

function normalizeApiShow(s) {
  const find = (...names) => {
    for (const n of names) {
      const lc = n.toLowerCase();
      const entry = Object.entries(s).find(([k]) => k.toLowerCase() === lc);
      if (entry && entry[1] !== null && entry[1] !== '') return entry[1];
    }
    return null;
  };

  const rawDate = String(find('date', 'show_date', 'started_at', 'created_at', 'scheduled_at') || '');
  const showDate = rawDate.includes('T') ? rawDate.substring(0, 10) : parseDateString(rawDate);

  return {
    title:                  find('title', 'show_title', 'name', 'show_name') || null,
    show_date:              showDate,
    show_date_raw:          rawDate || null,
    detail_url:             find('url', 'detail_url', 'show_url', 'link') || null,
    gross_revenue:          parseMoney(String(find('gross_revenue', 'gross', 'revenue', 'sales', 'estimated_sales', 'total_sales') || '')),
    whatnot_net:            parseMoney(String(find('net_revenue', 'net', 'earnings', 'total_estimated_earnings', 'estimated_earnings') || '')),
    completed_earnings:     parseMoney(String(find('completed_earnings', 'completed_revenue') || '')),
    units_sold:             parseInteger(String(find('units_sold', 'orders', 'total_orders', 'order_count') || '')),
    avg_order_value:        parseMoney(String(find('avg_order_value', 'aov', 'average_order_value') || '')),
    giveaway_spend:         parseMoney(String(find('giveaway_spend', 'giveaways_spend') || '')),
    giveaways_count:        parseInteger(String(find('giveaways', 'giveaway_count', 'giveaways_count') || '')),
    buyers_count:           parseInteger(String(find('buyers', 'buyer_count', 'unique_buyers') || '')),
    first_time_buyers:      parseInteger(String(find('first_time_buyers', 'new_buyers', 'first_buyers') || '')),
    returning_buyers:       parseInteger(String(find('returning_buyers', 'repeat_buyers') || '')),
    shares_count:           parseInteger(String(find('shares', 'share_count', 'shares_count') || '')),
    show_duration:          parseDurationToMinutes(String(find('duration', 'show_duration', 'duration_minutes') || '')),
    max_concurrent_viewers: parseInteger(String(find('max_concurrent_viewers', 'peak_viewers', 'max_viewers') || '')),
    total_views:            parseInteger(String(find('total_views', 'views', 'view_count', 'total_viewers') || '')),
    _raw_metrics:           {},
    _api_source:            true,
  };
}

// ── ws-explore standalone (no browser) ───────────────────────────────────────
// Called before launchPersistentContext so the shared profile lock is never
// needed. Launches a temporary Playwright browser, navigates to the seller hub,
// and intercepts the real Phoenix WS frames the app sends/receives.
// This avoids guessing topic names — we just listen to what the app does.
async function runWsExploreStandalone(cookiesFilePath) {
  const fs   = require('fs');
  const os   = require('os');
  const path = require('path');

  // Load cookies from file
  if (!fs.existsSync(cookiesFilePath)) {
    process.stderr.write('ws-explore: no cookie file found at ' + cookiesFilePath + '\n');
    process.stderr.write('Run: php artisan whatnot:login\n');
    process.exit(1);
  }
  let rawCookies;
  try {
    rawCookies = JSON.parse(fs.readFileSync(cookiesFilePath, 'utf8'));
  } catch (e) {
    process.stderr.write('ws-explore: failed to parse cookie file: ' + e.message + '\n');
    process.exit(1);
  }

  info('ws-explore: loaded', rawCookies.length, 'cookies from file');

  // Use a temporary Playwright browser context (fresh dir = no SingletonLock conflict).
  // Navigate to the seller hub and intercept real Phoenix WS frames — the app joins
  // the correct topics automatically, so we never have to guess topic names.
  const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'whatnot-ws-'));
  const _sameSiteMap = { no_restriction: 'None', strict: 'Strict', lax: 'Lax' };
  const playwrightCookies = rawCookies
    .filter(c => c.name && c.value)
    .map(c => ({
      name:     c.name,
      value:    c.value,
      domain:   c.domain || '.whatnot.com',
      path:     c.path   || '/',
      expires:  c.expirationDate ?? c.expires ?? -1,
      httpOnly: Boolean(c.httpOnly),
      secure:   Boolean(c.secure),
      sameSite: _sameSiteMap[(c.sameSite || '').toLowerCase()] || 'Lax',
    }));

  info('ws-explore: launching browser to sniff real Phoenix topic names…');
  const allMessages  = [];
  const topicsJoined = new Set();
  const httpCaptures = [];  // declared here so it's in scope after the try/finally

  const tempContext = await chromium.launchPersistentContext(tempDir, {
    executablePath: CHROMIUM_PATH,
    headless:       true,
    env: { ...process.env, HOME: '/tmp' },
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu',
           '--disable-crash-reporter', '--crash-dumps-dir=/tmp'],
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport:  { width: 1280, height: 900 },
  });
  try {
    await tempContext.addCookies(playwrightCookies);
    const tempPage = await tempContext.newPage();

    // Inject saved localStorage so the seller hub React app finds its auth tokens.
    // Whatnot stores session state in localStorage (not just cookies); without it
    // only the generic presence channel (general:XXXX) gets joined.
    const _lsFile = path.join(__dirname, '../storage/whatnot-localstorage.json');
    if (fs.existsSync(_lsFile)) {
      try {
        const savedLs = JSON.parse(fs.readFileSync(_lsFile, 'utf8'));
        // Navigate to whatnot.com first so the localStorage origin is correct
        await tempPage.goto('https://www.whatnot.com/', { waitUntil: 'domcontentloaded', timeout: 15000 });
        await tempPage.evaluate((data) => {
          for (const [k, v] of Object.entries(data)) {
            try { localStorage.setItem(k, v); } catch {}
          }
        }, savedLs);
        info(`ws-explore: injected ${Object.keys(savedLs).length} localStorage keys`);
      } catch (_lsErr) {
        info('ws-explore: localStorage inject failed:', _lsErr.message);
      }
    } else {
      info('ws-explore: no saved localStorage found — run a normal import first to generate it');
      info('ws-explore:   php artisan whatnot:import --channel=1');
      info('ws-explore: proceeding with cookies only (seller topics may not appear)');
    }

    // Capture HTTP API calls (GraphQL/REST JSON) — the shows list is likely REST, not WS
    tempPage.on('response', async (resp) => {
      try {
        const url = resp.url();
        if (!url.includes('whatnot.com')) return;
        if (url.includes('/reroute/') || url.includes('/_next/') || url.includes('/static/')) return;
        const ct = resp.headers()['content-type'] || '';
        if (!ct.includes('json') && !ct.includes('graphql')) return;
        const text = await resp.text().catch(() => '');
        if (text.length < 30) return;
        const path = url.replace('https://www.whatnot.com', '');
        info(`[http] ${resp.status()} ${resp.request().method()} ${path.substring(0, 100)}`);
        try {
          const body = JSON.parse(text);
          httpCaptures.push({ method: resp.request().method(), url: path, status: resp.status(), body });
        } catch {
          httpCaptures.push({ method: resp.request().method(), url: path, status: resp.status(), body: text.substring(0, 500) });
        }
      } catch {}
    });

    // Wire up WS interception BEFORE navigating so we catch the very first join frames
    tempPage.on('websocket', (ws) => {
      if (!ws.url().includes('whatnot.com')) return;
      info(`[ws] CONNECT ${ws.url().replace('wss://www.whatnot.com', '').substring(0, 120)}`);

      ws.on('framesent', ({ payload }) => {
        try {
          if (typeof payload !== 'string') return;
          const data = JSON.parse(payload);
          if (!Array.isArray(data) || data.length !== 5) return;
          const [, , topic, event, pl] = data;
          if (event === 'phx_join') topicsJoined.add(topic);
          info(`[ws] SENT  topic=${topic} event=${event} ${JSON.stringify(pl || {}).substring(0, 100)}`);
          allMessages.push({ dir: 'sent', topic, event, payload: pl });
        } catch {}
      });

      ws.on('framereceived', ({ payload }) => {
        try {
          if (typeof payload !== 'string') return;
          const data = JSON.parse(payload);
          if (!Array.isArray(data) || data.length !== 5) return;
          const [, , topic, event, pl] = data;
          info(`[ws] RECV  topic=${topic} event=${event} ${JSON.stringify(pl || {}).substring(0, 300)}`);
          allMessages.push({ dir: 'recv', topic, event, payload: pl });
        } catch {}
      });
    });

    // Navigate to the seller hub entry point (/seller) rather than /seller/shows
    // directly — the latter is a Next.js SSR route that 404s without a full session;
    // /seller client-side routes to shows and triggers the right API calls.
    info(`ws-explore: navigating to /seller…`);
    await tempPage.goto('https://www.whatnot.com/seller', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await tempPage.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
    let actualUrl = tempPage.url();
    info(`ws-explore: landed on ${actualUrl}`);
    let pageText = await tempPage.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
    info(`ws-explore: page preview: ${pageText.replace(/\n/g, ' ').substring(0, 200)}`);

    // If we landed in buyer mode (marketing page), try switching to seller mode.
    // Buyer mode = /seller shows "For Brands / Start Selling on Whatnot" marketing page.
    // Without seller mode, /seller/shows 404s and no seller GraphQL calls ever fire.
    const _buyerModeText = /for brands|start selling on whatnot/i.test(pageText);
    if (_buyerModeText) {
      info('ws-explore: buyer mode detected — attempting "Switch to Selling"');
      try {
        await ensureSellerMode(tempPage);
        info('ws-explore: seller mode activated');
        actualUrl = tempPage.url();
        pageText  = await tempPage.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
        info(`ws-explore: now on ${actualUrl}`);
      } catch (_smErr) {
        info('ws-explore: seller mode not activated:', _smErr.message.substring(0, 120));
        info('ws-explore: continuing — seller GraphQL ops will not appear');
        if (!CHANNEL_NAME) {
          info('ws-explore: TIP — re-run with --channel="Vortex Breaks" to try the profile-drawer switch');
          info('ws-explore:   php artisan whatnot:import --ws-explore --channel="Vortex Breaks"');
        }
      }
    }

    // Navigate to /seller/shows if not already there and page isn't a 404
    if (!actualUrl.includes('/shows') && !/404|page not found/i.test(pageText)) {
      info(`ws-explore: navigating to /seller/shows for show-specific API calls…`);
      await tempPage.goto('https://www.whatnot.com/seller/shows', { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {});
      await tempPage.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
      actualUrl = tempPage.url();
      pageText  = await tempPage.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
      info(`ws-explore: /seller/shows landed on ${actualUrl}`);
      info(`ws-explore: page preview: ${pageText.replace(/\n/g, ' ').substring(0, 200)}`);
    }

    // Final wait for lazy-loaded API calls
    await new Promise(r => setTimeout(r, 5000));
    info(`ws-explore: captured ${httpCaptures.length} HTTP API calls, ${allMessages.length} WS frames`);

  } finally {
    await tempContext.close().catch(() => {});
    fs.rm(tempDir, { recursive: true, force: true }, () => {});
  }

  // Group by topic + event for the summary table
  const byTopicEvent = {};
  for (const m of allMessages) {
    const key = `${m.dir}::${m.topic}::${m.event}`;
    if (!byTopicEvent[key]) {
      byTopicEvent[key] = { dir: m.dir, topic: m.topic, event: m.event, count: 0, sample: null };
    }
    byTopicEvent[key].count++;
    if (!byTopicEvent[key].sample) byTopicEvent[key].sample = m.payload;
  }

  // Output JSON to stdout for the PHP wrapper to parse
  const outFile = `/tmp/whatnot-ws-explore-${Date.now()}.json`;
  const result  = {
    total_messages: allMessages.length,
    topics_joined:  [...topicsJoined],
    topics:         Object.values(byTopicEvent),
    messages:       allMessages,
    http_captures:  httpCaptures,
  };
  fs.writeFileSync(outFile, JSON.stringify(result, null, 2));
  log(`ws-explore complete: ${allMessages.length} WS frames, ${httpCaptures.length} HTTP calls → ${outFile}`);
  process.stdout.write(JSON.stringify({
    output_file:    outFile,
    total_messages: allMessages.length,
    topics_joined:  [...topicsJoined],
    topics:         Object.values(byTopicEvent),
    http_captures:  httpCaptures,
  }) + '\n');
}

// ── Main ─────────────────────────────────────────────────────────────────────

(async () => {
  const email    = process.env.WHATNOT_EMAIL    || '';
  const password = process.env.WHATNOT_PASSWORD || '';
  const _cookiesFilePath = process.env.WHATNOT_COOKIES_FILE ||
    require('path').join(__dirname, '../storage/whatnot-cookies.json');
  const hasCookieFile = require('fs').existsSync(_cookiesFilePath);

  // Credentials are only required when we don't have a session cookie file
  // and we're not in a mode that only tests cookies.
  if (!hasCookieFile && !email && MODE !== 'cookie-test' && MODE !== 'dump-cookies') {
    process.stderr.write(
      'Error: WHATNOT_EMAIL and WHATNOT_PASSWORD are required (or provide storage/whatnot-cookies.json).\n' +
      'Run: php artisan whatnot:login\n'
    );
    process.exit(1);
  }

  // ── Mode: ws-explore (browser-free fast path) ──────────────────────────────
  // ws-explore only needs the session cookies + one HTTP call to get CSRF tokens.
  // It must NOT use the persistent browser profile because that profile may be
  // locked by a concurrent scraper process (SingletonLock). Instead it reads
  // cookies directly from storage/whatnot-cookies.json and uses Node.js https.
  if (MODE === 'ws-explore') {
    // Prefer live cookies saved by cookie-test mode (persistent browser session, passes SSR auth)
    // over the original export which may be stale. Fall back to original if live not present.
    const _liveCookiesPath = require('path').join(__dirname, '../storage/whatnot-live-cookies.json');
    const _wsExploreCookies = require('fs').existsSync(_liveCookiesPath) ? _liveCookiesPath : _cookiesFilePath;
    info('ws-explore using cookie file:', _wsExploreCookies);
    await runWsExploreStandalone(_wsExploreCookies);
    process.exit(0);
  }

  // ── Persistent browser profile ───────────────────────────────────────────────
  // launchPersistentContext stores cookies, localStorage, and service-worker
  // caches between runs. After the initial session is established, goto(/signin)
  // redirects straight to the dashboard and performLogin returns early — the
  // login page (and its bot detection) is never hit again.
  const USER_DATA_DIR = (() => {
    if (process.env.WHATNOT_USER_DATA_DIR) return process.env.WHATNOT_USER_DATA_DIR;
    return require('path').join(__dirname, '../storage/whatnot-browser-profile');
  })();
  require('fs').mkdirSync(USER_DATA_DIR, { recursive: true });
  info('browser profile dir:', USER_DATA_DIR);

  const context = await chromium.launchPersistentContext(USER_DATA_DIR, {
    executablePath: CHROMIUM_PATH,
    headless:       true,
    env: { ...process.env, HOME: '/tmp' },
    args: [
      '--no-sandbox',
      '--disable-dev-shm-usage',
      '--disable-crash-reporter',
      '--crash-dumps-dir=/tmp',
      '--disable-gpu',
    ],
    // Realistic Chrome/Windows UA — no "HeadlessChrome" in the string
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport:  { width: 1280, height: 900 },
    locale:    'en-US',
    timezoneId: 'America/Chicago',
    // Client Hints headers must match the UA — inconsistency is a detection signal
    extraHTTPHeaders: {
      'sec-ch-ua':          '"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"',
      'sec-ch-ua-mobile':   '?0',
      'sec-ch-ua-platform': '"Windows"',
      'Accept-Language':    'en-US,en;q=0.9',
    },
    // In discover mode, block service workers so that fetch() calls go through the real
    // network instead of being served from the SW cache (which bypasses page.on('response')).
    // Other modes leave SWs enabled so session/auth SWs keep working normally.
    ...(MODE === 'discover' ? { serviceWorkers: 'block' } : {}),
  });

  // Mask automation signals that trigger bot detection on sites like Whatnot.
  // navigator.webdriver = true is the primary signal headless Chrome sets.
  await context.addInitScript(() => {
    // Core: webdriver flag
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });

    // Chrome-specific object that headless Chrome doesn't set by default
    if (!window.chrome) window.chrome = { runtime: {} };

    // Realistic plugin list
    Object.defineProperty(navigator, 'plugins', {
      get: () => {
        const arr = [{ name: 'Chrome PDF Plugin' }, { name: 'Chrome PDF Viewer' }, { name: 'Native Client' }];
        arr.item = i => arr[i];
        arr.namedItem = n => arr.find(p => p.name === n) || null;
        arr.refresh = () => {};
        return arr;
      },
    });

    Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });

    // Screen dimensions matching the viewport (headless can leave these at 0)
    try {
      Object.defineProperty(screen, 'availWidth',  { get: () => 1280 });
      Object.defineProperty(screen, 'availHeight', { get: () => 900 });
    } catch (_) {}

    // Permissions API — headless denies notifications, real browsers default to "default"
    try {
      const origQuery = window.navigator.permissions.query.bind(window.navigator.permissions);
      window.navigator.permissions.query = (params) => {
        if (params && params.name === 'notifications') {
          return Promise.resolve({ state: 'default', onchange: null });
        }
        return origQuery(params);
      };
    } catch (_) {}

    // WebGL — headless reports "SwiftShader" which is detectable
    try {
      const getParam = WebGLRenderingContext.prototype.getParameter;
      WebGLRenderingContext.prototype.getParameter = function (parameter) {
        if (parameter === 37446) return 'Intel Inc.';   // UNMASKED_VENDOR_WEBGL
        if (parameter === 37445) return 'Intel Iris OpenGL Engine'; // UNMASKED_RENDERER_WEBGL
        return getParam.call(this, parameter);
      };
    } catch (_) {}
  });

  // ── Bootstrap session cookies (one-time first-run setup) ─────────────────────
  // Export cookies from your logged-in browser (Cookie-Editor extension → Export
  // as JSON) and save to storage/whatnot-cookies.json on the server. The scraper
  // loads them here so goto(/signin) redirects to the dashboard — login page
  // (and its bot detection) is never hit. Once the profile is primed, the file
  // can be deleted; the persistent context keeps the session alive.
  const _cookiesFile = process.env.WHATNOT_COOKIES_FILE ||
    require('path').join(__dirname, '../storage/whatnot-cookies.json');
  if (require('fs').existsSync(_cookiesFile)) {
    try {
      const _raw = JSON.parse(require('fs').readFileSync(_cookiesFile, 'utf8'));
      const _sameSiteMap = { no_restriction: 'None', strict: 'Strict', lax: 'Lax' };
      const _cookies = _raw
        .filter(c => typeof c.name === 'string' && typeof c.value === 'string')
        .map(c => ({
          name:     c.name,
          value:    c.value,
          domain:   c.domain || '.whatnot.com',
          path:     c.path   || '/',
          expires:  c.expirationDate ?? c.expires ?? -1,
          httpOnly: Boolean(c.httpOnly),
          secure:   Boolean(c.secure),
          sameSite: _sameSiteMap[(c.sameSite || '').toLowerCase()] || 'Lax',
        }));
      if (_cookies.length > 0) {
        await context.addCookies(_cookies);
        info('loaded', _cookies.length, 'session cookies from', _cookiesFile);
      }
    } catch (e) {
      info('cookie file found but failed to load:', e.message);
    }
  }

  const page = await context.newPage();

  // Capture every JSON response from whatnot.com during this session.
  // Analytics and GraphQL endpoints will appear here; extractShowsFromCapture()
  // searches them for show data before falling back to DOM scraping.
  const capturedApiResponses = [];
  page.on('response', async (response) => {
    try {
      const url = response.url();
      if (!url.includes('whatnot.com')) return;
      if (url.includes('/reroute/')) return;  // skip Datadog/Segment/third-party proxies
      if (response.status() < 200 || response.status() >= 300) return;
      const ct = response.headers()['content-type'] || '';
      // In discover mode: log EVERY non-asset response so we can see what protocol
      // Whatnot uses (GraphQL, gRPC, protobuf, unusual content-type, etc.)
      if (MODE === 'discover') {
        const isAsset = /\.(js|css|png|jpg|gif|svg|ico|woff2?|ttf|eot|map)(\?|$)/.test(url)
                     || ct.includes('javascript') || ct.includes('text/css') || ct.includes('image/');
        if (!isAsset) {
          info(`[net] ${response.status()} ${ct.split(';')[0].trim() || '(no ct)'} ${url.replace('https://www.whatnot.com', '').substring(0, 120)}`);
        }
      }
      if (!ct.includes('application/json') && !ct.includes('graphql')) return;
      const text = await response.text().catch(() => '');
      if (text.length < 50) return;
      const body = JSON.parse(text);
      capturedApiResponses.push({ url, body });
      log('API captured:', url.replace('https://www.whatnot.com', '').substring(0, 100));
    } catch {}
  });

  try {
    // If we have a cookie file, attempt a fast cookie-auth check before trying form login.
    // This skips the Kasada-blocked login page entirely on subsequent runs.
    if (hasCookieFile && MODE !== 'cookie-test' && MODE !== 'dump-cookies') {
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});
      const checkUrl = page.url();
      if (/\/(login|signin|auth)(\/|\?|$)/i.test(checkUrl)) {
        info('cookie auth check: cookies expired, falling back to credential login');
        if (!email || !password) {
          throw new Error(
            'Session cookies are expired and no WHATNOT_EMAIL/WHATNOT_PASSWORD set. ' +
            'Run: php artisan whatnot:login'
          );
        }
        await performLogin(page, email, password);
      } else {
        info('cookie auth check: seller hub reached without login (', checkUrl, ')');
        // Activate seller mode if the session landed in buyer mode.
        // Must be done before saving localStorage so the stored tokens reflect
        // the seller-mode session, not the buyer-mode one.
        if (MODE !== 'test' && MODE !== 'cookie-test' && MODE !== 'dump-cookies') {
          await ensureSellerMode(page);
        }
        // Persist localStorage so ws-explore can inject it into its temp browser.
        // The seller hub React app initialises seller-specific Phoenix topics only
        // after reading auth tokens from localStorage; cookies alone are not enough.
        try {
          const ls = await page.evaluate(() => {
            const out = {};
            for (let i = 0; i < localStorage.length; i++) {
              const k = localStorage.key(i);
              out[k] = localStorage.getItem(k);
            }
            return out;
          });
          const _lsFile = require('path').join(__dirname, '../storage/whatnot-localstorage.json');
          require('fs').writeFileSync(_lsFile, JSON.stringify(ls));
          info('saved localStorage (' + Object.keys(ls).length + ' keys) →', _lsFile);
        } catch (_lsErr) {
          info('localStorage save skipped:', _lsErr.message);
        }
      }
    } else if (MODE !== 'cookie-test' && MODE !== 'dump-cookies') {
      await performLogin(page, email, password);
      // After credential login, also ensure seller mode is active.
      if (MODE !== 'test') {
        await ensureSellerMode(page);
      }
    }

    // Switch to the target channel before scraping (skipped for test/cookie modes)
    if (MODE !== 'test' && MODE !== 'cookie-test' && MODE !== 'dump-cookies' && CHANNEL_NAME) {
      await switchToChannel(page, CHANNEL_NAME);
    }

    // ── Mode: cookie-test ────────────────────────────────────────────────────
    // Test whether the loaded session cookies grant access to the Seller Hub.
    // Returns { ok: true, url } on success, or exits 1 on failure.
    if (MODE === 'cookie-test') {
      info('cookie-test: navigating to seller hub');
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
      const url = page.url();
      info('cookie-test: landed on', url);

      if (/\/(login|signin|auth)(\/|\?|$)/i.test(url)) {
        const bodyText = await page.evaluate(() => (document.body.innerText || '').substring(0, 200)).catch(() => '');
        process.stderr.write('COOKIE_TEST_FAILED: redirected to login page — cookies are missing, expired, or invalid.\n');
        process.stderr.write('URL: ' + url + '\n');
        process.stderr.write('PAGE: ' + bodyText + '\n');
        process.exit(1);
      }

      const pageText = await page.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
      if (pageText.trim().length < 50) {
        process.stderr.write('COOKIE_TEST_FAILED: seller hub loaded but page appears empty — bot detection may still be active.\n');
        process.stderr.write('URL: ' + url + '\n');
        process.exit(1);
      }

      // Save localStorage + live cookies so ws-explore can inject them into its temp browser.
      // The persistent browser's cookies may differ from whatnot-cookies.json (session refreshed);
      // only these live cookies pass Next.js SSR auth checks.
      try {
        const ls = await page.evaluate(() => {
          const out = {};
          for (let i = 0; i < localStorage.length; i++) {
            const k = localStorage.key(i);
            out[k] = localStorage.getItem(k);
          }
          return out;
        });
        const _lsFile = require('path').join(__dirname, '../storage/whatnot-localstorage.json');
        require('fs').writeFileSync(_lsFile, JSON.stringify(ls));
        info('cookie-test: saved localStorage (' + Object.keys(ls).length + ' keys) →', _lsFile);
      } catch (_e) {
        info('cookie-test: localStorage save skipped:', _e.message);
      }
      try {
        const liveCookies = await context.cookies('https://www.whatnot.com');
        const _liveCookiesFile = require('path').join(__dirname, '../storage/whatnot-live-cookies.json');
        require('fs').writeFileSync(_liveCookiesFile, JSON.stringify(liveCookies, null, 2));
        info('cookie-test: saved live cookies (' + liveCookies.length + ' cookies) →', _liveCookiesFile);
      } catch (_e) {
        info('cookie-test: live cookie save skipped:', _e.message);
      }

      process.stdout.write(JSON.stringify({ ok: true, url, page_length: pageText.length }) + '\n');
      process.exit(0);
    }

    // ── Mode: dump-cookies ────────────────────────────────────────────────────
    // Dump the current Playwright context cookies for whatnot.com to stdout as JSON.
    // Used after a successful login to persist the session.
    if (MODE === 'dump-cookies') {
      const cookies = await context.cookies('https://www.whatnot.com');
      process.stdout.write(JSON.stringify(cookies, null, 2) + '\n');
      info('dump-cookies: dumped', cookies.length, 'cookies');
      process.exit(0);
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
          // Lookahead (?=[?#]|$) prevents matching sub-pages like /analytics, /orders
          if (!(/\/live\/[^/]+\/[^/?#\s]+(?=[?#]|$)/.test(href) || /\/show\/[\w-]+(?=[?#]|$)/.test(href) || /\/seller\/shows\/[\w-]+(?=[?#]|$)/.test(href))) continue;
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

    // ── Mode: discover ───────────────────────────────────────────────────────
    // Crawls every nav link found in the Seller Hub, capturing all JSON API
    // calls each page makes. Output lets you build targeted REST calls instead
    // of scraping HTML.
    //
    // Usage:  WHATNOT_MODE=discover php artisan whatnot:import --discover
    // Output: JSON with { nav_links, pages: [{ url, nav_label, api_endpoints }] }
    if (MODE === 'discover') {
      // Log every outbound request in discover mode (not just captured JSON responses).
      // This reveals API calls with unusual content-types, gRPC, or other protocols
      // that the response handler's JSON filter would otherwise silently skip.
      page.on('request', (request) => {
        try {
          const url = request.url();
          if (!url.includes('whatnot.com')) return;
          if (url.includes('/reroute/')) return;
          // Skip JS/CSS/font bundles and Next.js static chunks
          if (/\.(js|css|png|jpg|gif|svg|ico|woff2?|ttf|eot|map)(\?|$)/.test(url)) return;
          if (url.includes('/_next/static/') || url.includes('/_next/webpack')) return;
          info(`[req] ${request.method()} ${url.replace('https://www.whatnot.com', '').substring(0, 120)}`);
        } catch {}
      });

      // Intercept WebSocket frames — Whatnot uses Phoenix Channels (vsn=2.0.0) over WS
      // for all seller hub data (shows, orders, payouts, analytics, etc.).
      // Phoenix frame format: [join_ref, ref, topic, event, payload]
      // We log every SEND and RECV to stderr so we can read the channel/event names,
      // then push received data into capturedApiResponses for the discover output.
      page.on('websocket', (ws) => {
        if (!ws.url().includes('whatnot.com')) return;
        info(`[ws] CONNECT /services/live/socket/websocket (Phoenix vsn=2.0.0)`);
        ws.on('framereceived', ({ payload }) => {
          try {
            if (typeof payload !== 'string' || payload.length < 2) return;
            const data = JSON.parse(payload);
            // Phoenix Channels: [join_ref, ref, topic, event, payload]
            if (Array.isArray(data) && data.length === 5) {
              const [, ref, topic, event, wsPayload] = data;
              const summary = JSON.stringify(wsPayload || {}).substring(0, 120);
              info(`[ws] RECV  ref=${ref || '-'} topic=${topic} event=${event} ${summary}`);
              // Push with a stable URL key so deduplicate works: topic + event
              capturedApiResponses.push({
                url: `ws://phoenix/${topic}/${event}`,
                body: { topic, event, payload: wsPayload },
              });
            } else if (payload.length > 2) {
              info(`[ws] RECV  (non-phoenix) ${payload.substring(0, 120)}`);
              capturedApiResponses.push({ url: ws.url() + '#ws', body: data });
            }
          } catch {}
        });
        ws.on('framesent', ({ payload }) => {
          try {
            if (typeof payload !== 'string' || payload.length < 2) return;
            const data = JSON.parse(payload);
            if (Array.isArray(data) && data.length === 5) {
              const [, ref, topic, event, wsPayload] = data;
              const summary = JSON.stringify(wsPayload || {}).substring(0, 80);
              info(`[ws] SEND  ref=${ref || '-'} topic=${topic} event=${event} ${summary}`);
            } else {
              info(`[ws] SEND  ${payload.substring(0, 80)}`);
            }
          } catch {}
        });
        ws.on('close', () => info(`[ws] CLOSE`));
      });

      // Monkey-patch window.fetch so responses served from a service-worker cache
      // (which bypass page.on('response') since no real HTTP request is made) are
      // still captured. Runs on each page.goto() navigation as a page-level init script.
      await page.addInitScript(() => {
        window.__wn_captures = [];
        const _orig = window.fetch;
        if (!_orig) return;
        window.fetch = async function () {
          const resp = await _orig.apply(this, arguments);
          try {
            const req = arguments[0];
            const url = typeof req === 'string' ? req
                      : (req instanceof URL ? req.href : (req && req.url) || '');
            if (url.includes('whatnot.com') && !url.includes('/reroute/')) {
              const ct = resp.headers.get('content-type') || '';
              if (ct.includes('application/json') || ct.includes('graphql')) {
                resp.clone().text().then(t => {
                  try {
                    if (t.length > 50) window.__wn_captures.push({ url, body: JSON.parse(t) });
                  } catch {}
                }).catch(() => {});
              }
            }
          } catch {}
          return resp;
        };
      });

      // Helper: snapshot and summarise all NEW API calls captured since last call.
      // Also drains the in-page fetch interceptor (picks up SW-cached responses).
      let lastCaptureIdx = 0;
      async function drainCaptures() {
        // Poll the in-page fetch interceptor; splice(0) atomically empties the array
        const inPage = await page.evaluate(() => {
          const c = window.__wn_captures || [];
          window.__wn_captures = [];
          return c;
        }).catch(() => []);
        for (const c of inPage) {
          // Avoid duplicating what page.on('response') already captured via HTTP
          if (!capturedApiResponses.some(r => r.url === c.url)) {
            capturedApiResponses.push(c);
            info(`[fetch-intercept] ${c.url.replace('https://www.whatnot.com', '').substring(0, 100)}`);
          }
        }

        const fresh = capturedApiResponses.slice(lastCaptureIdx);
        lastCaptureIdx = capturedApiResponses.length;
        return fresh
          // Strip out third-party telemetry that Whatnot proxies via /reroute/
          // (Datadog, Segment, Amplitude, etc.) — not Whatnot's own APIs
          .filter(r => !r.url.includes('/reroute/'))
          .map(r => {
            const body = r.body;
            let topKeys;
            let method = 'GET';
            if (r.url.startsWith('ws://phoenix/')) {
              // Phoenix Channels capture: { topic, event, payload }
              // Keys = the payload keys so we can see what data fields arrived
              const pl = body && body.payload;
              topKeys = pl && typeof pl === 'object' && !Array.isArray(pl)
                ? Object.keys(pl).slice(0, 20)
                : (body ? ['topic:' + body.topic, 'event:' + body.event] : []);
              method = 'WS';
            } else if (r.url.startsWith('__dom__:')) {
              topKeys = ['tables', 'listItems'];
              method = 'DOM';
            } else {
              topKeys = typeof body === 'object' && body !== null
                ? Object.keys(Array.isArray(body) ? (body[0] || {}) : body).slice(0, 20)
                : [];
              if (r.url.includes('/graphql')) method = 'POST';
            }
            return {
              method,
              url:     r.url,
              keys:    topKeys,
              preview: JSON.stringify(body).substring(0, 800),
            };
          });
      }

      // Helper: visit a page, wait for API calls to settle, scroll once, drain
      async function visitAndCapture(url, label, shotSuffix) {
        info(`discover: visiting [${label}] ${url}`);
        try {
          await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
          await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
          // Extra wait for Phoenix Channel data pushes: the WS connection is established
          // during pageload and Phoenix immediately sends channel join replies + data,
          // but these arrive slightly after networkidle (which only tracks HTTP).
          await page.waitForTimeout(4000);
          if (shotSuffix) await debugShot(page, `discover-${shotSuffix}`);

          // Scroll to trigger lazy-load API calls
          for (let i = 0; i < 3; i++) {
            await page.evaluate(() => window.scrollBy(0, window.innerHeight));
            await page.waitForTimeout(600);
          }
          await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});
          await page.waitForTimeout(500);

          // Diagnostic: confirm the page actually loaded meaningful content
          const pageSnap = await page.evaluate(() => ({
            url:     location.href,
            title:   document.title,
            bodyLen: (document.body.innerText || '').length,
          })).catch(() => ({ url: '?', title: '?', bodyLen: 0 }));
          info(`  ├ loaded: "${pageSnap.title}" (${pageSnap.bodyLen} chars) @ ${pageSnap.url}`);

          // page.goto() triggers Next.js SSR: the server embeds all page data in
          // <script id="__NEXT_DATA__"> and no XHR/fetch calls fire for the initial
          // load. Pull that payload — it contains shows, orders, payouts, etc.
          const nextData = await page.evaluate(() => {
            const el = document.getElementById('__NEXT_DATA__');
            if (!el || !el.textContent) return null;
            try { return JSON.parse(el.textContent); } catch { return null; }
          }).catch(() => null);
          if (nextData) {
            capturedApiResponses.push({ url: '__next_ssr__:' + url, body: nextData });
            const propKeys = Object.keys(nextData.props?.pageProps || nextData.props || {}).join(', ');
            info(`  ├ SSR(__NEXT_DATA__) — pageProps keys: ${propKeys || '(empty)'}`);
          } else {
            info(`  ├ no __NEXT_DATA__ (client-only SPA page or not Next.js)`);
          }

          // DOM extraction — pull visible table/list data as a last-resort fallback.
          // If the app renders data server-side or from a SW cache with no observable
          // HTTP response, at least we can see what text is on screen.
          const domSnap = await page.evaluate(() => {
            const tables = [];
            for (const tbl of document.querySelectorAll('table')) {
              const rows = [];
              for (const tr of tbl.querySelectorAll('tr')) {
                const cells = Array.from(tr.querySelectorAll('td, th')).map(c => c.innerText.trim());
                if (cells.some(c => c.length > 0)) rows.push(cells);
              }
              if (rows.length > 1) tables.push(rows.slice(0, 20));
            }
            // Also grab any visible card/row text blocks (common in React list UIs)
            const listItems = Array.from(document.querySelectorAll('[class*="row"], [class*="card"], [class*="item"], li'))
              .slice(0, 30)
              .map(el => el.innerText.trim().replace(/\s+/g, ' ').substring(0, 200))
              .filter(t => t.length > 10);
            return { tables, listItems: [...new Set(listItems)] };
          }).catch(() => null);
          if (domSnap) {
            if (domSnap.tables.length > 0) {
              info(`  ├ DOM: ${domSnap.tables.length} table(s), largest ${domSnap.tables[0].length} rows`);
              capturedApiResponses.push({ url: '__dom__:' + url, body: domSnap });
            }
            if (domSnap.listItems.length > 0) {
              info(`  ├ DOM: ${domSnap.listItems.length} list items visible`);
              if (!domSnap.tables.length) {
                capturedApiResponses.push({ url: '__dom__:' + url, body: domSnap });
              }
            }
          }
        } catch (e) {
          info(`discover: error visiting ${url}: ${e.message}`);
        }
        return await drainCaptures();
      }

      // ── Step 1: land on Seller Hub home, grab all nav links ──────────────
      log('discover: landing on Seller Hub home to collect nav links');
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(2000);
      await debugShot(page, 'discover-00-seller-hub');
      await drainCaptures(); // discard hub-home API calls — we'll capture them properly below

      // Extract every link in the seller hub sidebar / nav
      const navLinks = await page.evaluate(() => {
        const seen  = new Set();
        const links = [];
        const allAs = Array.from(document.querySelectorAll('a[href], nav a, [role="navigation"] a, aside a'));
        for (const a of allAs) {
          const href  = (a.getAttribute('href') || '').trim();
          const label = (a.innerText || a.textContent || '').trim().replace(/\s+/g, ' ');
          // Keep only internal Whatnot seller/dashboard paths
          if (!href || href === '#' || href.startsWith('http') && !href.includes('whatnot.com')) continue;
          const full = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
          // Only seller hub or dashboard pages
          if (!/whatnot\.com\/(seller|dashboard|creator)/.test(full)) continue;
          if (seen.has(full)) continue;
          seen.add(full);
          links.push({ href: full, label: label.substring(0, 60) || '(no label)' });
        }
        return links;
      });

      info(`discover: found ${navLinks.length} nav links on Seller Hub`);

      // ── Step 2: also seed with known hub pages that may not be in nav ─────
      const seedPages = [
        { href: 'https://www.whatnot.com/seller',                       label: 'Seller Hub Home' },
        { href: 'https://www.whatnot.com/seller/shows',                 label: 'Shows List' },
        { href: 'https://www.whatnot.com/seller/orders',                label: 'Orders' },
        { href: 'https://www.whatnot.com/seller/payouts',               label: 'Payouts' },
        { href: 'https://www.whatnot.com/seller/analytics',             label: 'Analytics' },
        { href: 'https://www.whatnot.com/seller/inventory',             label: 'Inventory' },
        { href: 'https://www.whatnot.com/seller/listings',              label: 'Listings' },
        { href: 'https://www.whatnot.com/seller/shipping',              label: 'Shipping' },
        { href: 'https://www.whatnot.com/seller/buyers',                label: 'Buyers' },
        { href: 'https://www.whatnot.com/seller/marketing',             label: 'Marketing' },
        { href: 'https://www.whatnot.com/dashboard/analytics/overview', label: 'Dashboard Analytics' },
        { href: 'https://www.whatnot.com/dashboard/shows',              label: 'Dashboard Shows' },
        { href: 'https://www.whatnot.com/dashboard/orders',             label: 'Dashboard Orders' },
      ];

      // Merge nav links + seeds, deduplicate by href
      const allSeenHrefs = new Set(navLinks.map(l => l.href));
      for (const s of seedPages) {
        if (!allSeenHrefs.has(s.href)) {
          navLinks.push(s);
          allSeenHrefs.add(s.href);
        }
      }

      // ── Step 3: visit each page and capture its API calls ─────────────────
      const pageResults = [];
      let shotIdx = 1;

      for (const link of navLinks) {
        const apis = await visitAndCapture(link.href, link.label, `page-${String(shotIdx).padStart(2,'0')}`);
        shotIdx++;
        pageResults.push({
          url:           link.href,
          nav_label:     link.label,
          landed_url:    page.url(),
          api_count:     apis.length,
          api_endpoints: apis,
        });
        info(`  → ${apis.length} API endpoint(s) captured`);
      }

      // ── Step 4: Deep crawl — visit individual show detail pages ──────────
      // Now that we know what pages exist, drill into individual show pages and
      // click through every tab to trigger the data-fetch API calls that only
      // fire on interaction (Orders, Lots, Sales, Buyers, Analytics, etc.).
      info('discover: === PHASE 2: Deep crawl individual show pages ===');
      try {
        // Navigate to the shows list to extract show detail links
        await page.goto('https://www.whatnot.com/seller/shows', { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await page.waitForTimeout(2000);
        for (let i = 0; i < 3; i++) {
          await page.evaluate(() => window.scrollBy(0, window.innerHeight));
          await page.waitForTimeout(500);
        }
        await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});
        await drainCaptures(); // discard — already captured this page in Phase 1

        const showDetailLinks = await page.evaluate(() => {
          const links = [];
          const seen = new Set();
          for (const a of document.querySelectorAll('a[href]')) {
            const href = a.getAttribute('href') || '';
            if (!(/\/seller\/shows\/[\w-]+(?=[?#]|$)/.test(href) ||
                  /\/live\/[^/]+\/[^/?#\s]+(?=[?#]|$)/.test(href))) continue;
            const full = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
            if (seen.has(full)) continue;
            seen.add(full);
            links.push(full);
          }
          return links.slice(0, 5);
        });

        info(`discover: found ${showDetailLinks.length} show detail pages to deep-crawl`);

        const SHOW_TAB_SELECTORS = [
          ['Overview',  'button:has-text("Overview"), [role="tab"]:has-text("Overview")'],
          ['Orders',    'button:has-text("Orders"),   [role="tab"]:has-text("Orders")'],
          ['Lots',      'button:has-text("Lots"),     [role="tab"]:has-text("Lots")'],
          ['Sales',     'button:has-text("Sales"),    [role="tab"]:has-text("Sales")'],
          ['Buyers',    'button:has-text("Buyers"),   [role="tab"]:has-text("Buyers")'],
          ['Analytics', 'button:has-text("Analytics"),[role="tab"]:has-text("Analytics")'],
          ['Payouts',   'button:has-text("Payouts"),  [role="tab"]:has-text("Payouts")'],
        ];

        for (let showIdx = 0; showIdx < showDetailLinks.length; showIdx++) {
          const showUrl = showDetailLinks[showIdx];
          info(`discover: deep-crawl show ${showIdx + 1}/${showDetailLinks.length}: ${showUrl}`);
          try {
            await page.goto(showUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            await page.waitForTimeout(2000);
            if (DEBUG) await debugShot(page, `discover-show-${showIdx + 1}-load`);

            // Pull Next.js SSR data from the show detail page (same as nav pages)
            const showNextData = await page.evaluate(() => {
              const el = document.getElementById('__NEXT_DATA__');
              if (!el || !el.textContent) return null;
              try { return JSON.parse(el.textContent); } catch { return null; }
            }).catch(() => null);
            if (showNextData) {
              capturedApiResponses.push({ url: '__next_ssr__:' + showUrl, body: showNextData });
              const propKeys = Object.keys(showNextData.props?.pageProps || showNextData.props || {}).join(', ');
              info(`  ├ SSR(__NEXT_DATA__) — pageProps keys: ${propKeys || '(empty)'}`);
            }

            const loadApis = await drainCaptures();
            if (loadApis.length > 0) {
              pageResults.push({
                url:           showUrl,
                nav_label:     `Show Detail (page load) #${showIdx + 1}`,
                landed_url:    page.url(),
                api_count:     loadApis.length,
                api_endpoints: loadApis,
              });
              info(`  → page load: ${loadApis.length} API endpoint(s) — keys: ${loadApis.flatMap(e => e.keys).slice(0, 8).join(', ')}`);
            }

            // Click through each unique visible tab
            const clickedLabels = new Set();
            for (const [tabLabel, tabSel] of SHOW_TAB_SELECTORS) {
              const tab = await page.$(tabSel).catch(() => null);
              if (!tab || !await tab.isVisible().catch(() => false)) continue;
              if (clickedLabels.has(tabLabel)) continue;

              const isActive = await tab.evaluate(el =>
                el.getAttribute('aria-selected') === 'true' || el.getAttribute('aria-current') === 'page'
              ).catch(() => false);
              if (isActive) { clickedLabels.add(tabLabel); continue; }

              info(`  → clicking "${tabLabel}" tab`);
              clickedLabels.add(tabLabel);
              await tab.click();
              await page.waitForTimeout(2500);
              await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});
              for (let i = 0; i < 3; i++) {
                await page.evaluate(() => window.scrollBy(0, window.innerHeight));
                await page.waitForTimeout(400);
              }
              await page.waitForLoadState('networkidle', { timeout: 3000 }).catch(() => {});
              if (DEBUG) await debugShot(page, `discover-show-${showIdx + 1}-tab-${tabLabel.toLowerCase()}`);

              const tabApis = await drainCaptures();
              pageResults.push({
                url:           showUrl,
                nav_label:     `Show Detail - "${tabLabel}" tab #${showIdx + 1}`,
                landed_url:    page.url(),
                api_count:     tabApis.length,
                api_endpoints: tabApis,
              });
              if (tabApis.length > 0) {
                info(`    → ${tabApis.length} API endpoint(s) — keys: ${tabApis.flatMap(e => e.keys).slice(0, 8).join(', ')}`);
              } else {
                info(`    → 0 API endpoint(s) (tab may be DOM-rendered or empty)`);
              }
            }
          } catch (e) {
            info(`discover: error deep-crawling show ${showUrl}: ${e.message}`);
          }
        }
      } catch (e) {
        info(`discover: Phase 2 error: ${e.message}`);
      }

      // ── Step 5: Deep crawl /seller/orders ─────────────────────────────────
      info('discover: === PHASE 3: Deep crawl /seller/orders ===');
      try {
        await page.goto('https://www.whatnot.com/seller/orders', { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
        await page.waitForTimeout(2000);
        for (let i = 0; i < 3; i++) {
          await page.evaluate(() => window.scrollBy(0, window.innerHeight));
          await page.waitForTimeout(500);
        }
        await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});

        const ordersPageApis = await drainCaptures();
        if (ordersPageApis.length > 0) {
          pageResults.push({
            url:           'https://www.whatnot.com/seller/orders',
            nav_label:     'Orders List (deep crawl)',
            landed_url:    page.url(),
            api_count:     ordersPageApis.length,
            api_endpoints: ordersPageApis,
          });
          info(`  → orders list: ${ordersPageApis.length} API endpoint(s) — keys: ${ordersPageApis.flatMap(e => e.keys).slice(0, 8).join(', ')}`);
        }

        // Click into the first 2 order detail pages
        const orderDetailLinks = await page.evaluate(() => {
          const links = [];
          const seen = new Set();
          for (const a of document.querySelectorAll('a[href]')) {
            const href = a.getAttribute('href') || '';
            if (!/\/seller\/orders\/[\w-]+(?=[?#]|$)/.test(href)) continue;
            const full = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
            if (seen.has(full)) continue;
            seen.add(full);
            links.push(full);
          }
          return links.slice(0, 2);
        });

        info(`discover: found ${orderDetailLinks.length} order detail pages to crawl`);

        for (let oIdx = 0; oIdx < orderDetailLinks.length; oIdx++) {
          const orderUrl = orderDetailLinks[oIdx];
          info(`discover: deep-crawl order ${oIdx + 1}/${orderDetailLinks.length}: ${orderUrl}`);
          try {
            await page.goto(orderUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            await page.waitForTimeout(2000);
            const orderApis = await drainCaptures();
            if (orderApis.length > 0) {
              pageResults.push({
                url:           orderUrl,
                nav_label:     `Order Detail #${oIdx + 1}`,
                landed_url:    page.url(),
                api_count:     orderApis.length,
                api_endpoints: orderApis,
              });
              info(`  → order detail: ${orderApis.length} API endpoint(s) — keys: ${orderApis.flatMap(e => e.keys).slice(0, 8).join(', ')}`);
            }
          } catch (e) {
            info(`discover: error crawling order ${orderUrl}: ${e.message}`);
          }
        }
      } catch (e) {
        info(`discover: Phase 3 error: ${e.message}`);
      }

      // ── Step 6: output ───────────────────────────────────────────────────
      const allEndpoints = pageResults.flatMap(p => p.api_endpoints.map(e => ({ ...e, from_page: p.url })));
      // Deduplicate by URL for the summary
      const uniqueEndpoints = [];
      const seenUrls = new Set();
      for (const e of allEndpoints) {
        const key = e.method + ':' + e.url;
        if (!seenUrls.has(key)) { seenUrls.add(key); uniqueEndpoints.push(e); }
      }

      const result = {
        summary: {
          nav_links_found:       navLinks.length,
          pages_visited:         pageResults.length,
          unique_api_endpoints:  uniqueEndpoints.length,
          total_api_calls:       allEndpoints.length,
        },
        nav_links:        navLinks,
        unique_endpoints: uniqueEndpoints,
        pages:            pageResults,
      };

      // Write full JSON to a temp file — stdout pipe buffer can't handle large endpoint data
      const outFile = `/tmp/whatnot-discover-${Date.now()}.json`;
      require('fs').writeFileSync(outFile, JSON.stringify(result, null, 2));
      log(`discover complete: ${pageResults.length} pages, ${uniqueEndpoints.length} unique endpoints → ${outFile}`);
      // Stdout carries only a small envelope so PHP can find the file
      process.stdout.write(JSON.stringify({ output_file: outFile, summary: result.summary }) + '\n');
      process.exit(0);
    }

    // ── Mode: analytics (default) ─────────────────────────────────────────────
    if (MODE === 'analytics' || MODE === 'shows') {
      const isAnalytics = MODE === 'analytics';

      // URL priority for show data:
      //   1. /dashboard/shows  — team-member-accessible shows list (works with direct navigation)
      //   2. /seller/shows     — SSR route that 404s for team members on direct nav; kept as fallback
      //   3. analytics overview — individual show analytics cards (Kasada-blocked in practice)
      const analyticsUrlCandidates = isAnalytics ? [
        URLS.dashboardShows,
        URLS.shows,
        URLS.analytics,
      ] : [URLS.dashboardShows, URLS.shows];

      function isLoginUrl(u) {
        return /\/(login|signin|auth)(\/|\?|$)/i.test(u);
      }

      let targetUrl = analyticsUrlCandidates[0];
      let landed    = false;

      for (const candidate of analyticsUrlCandidates) {
        log(`trying URL: ${candidate}`);
        await page.goto(candidate, { waitUntil: 'domcontentloaded', timeout: 30000 });
        // Wait for the SPA to fire its data-fetch API calls before we read anything.
        // networkidle (no network activity for 500ms) is more reliable than a fixed delay.
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await page.waitForTimeout(1000);

        let currentUrl = page.url();

        if (isLoginUrl(currentUrl)) {
          info('analytics redirect to login — re-authenticating and re-switching channel');
          await performLogin(page, email, password);
          await page.waitForTimeout(1000);
          // Re-run channel switch after re-login
          if (CHANNEL_NAME) await switchToChannel(page, CHANNEL_NAME);
          // Retry this candidate
          await page.goto(candidate, { waitUntil: 'domcontentloaded', timeout: 30000 });
          await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
          await page.waitForTimeout(1000);
          currentUrl = page.url();
          info('after re-auth retry, URL:', currentUrl);
        }

        if (!isLoginUrl(currentUrl)) {
          // Check for Whatnot's 404 page before accepting this landing
          const pageTextCheck = await page.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
          if (/HTTP 404|Page Not Found|does not exist/i.test(pageTextCheck)) {
            info(`${candidate}: 404 page detected ("${pageTextCheck.trim().substring(0, 80)}"), trying next`);
          } else {
            targetUrl = candidate;
            landed    = true;
            log(`landed on: ${currentUrl}`);
            break;
          }
        } else {
          log(`${candidate} still redirects to ${currentUrl}, trying next`);
        }
      }

      if (!landed) {
        process.stderr.write('ERROR: All analytics URLs redirect to login — credentials may be invalid or account lacks analytics access.\n');
        process.exit(1);
      }

      // Wait for React to hydrate — poll until visible text appears (up to 25s).
      // Fixed timeouts and networkidle aren't reliable for SPAs with background polling.
      await page.waitForFunction(
        () => (document.body.innerText || '').trim().length > 100,
        { timeout: 25000 }
      ).catch(async () => {
        log('body still empty after 25s — may be iframe or anti-bot; trying extra 5s');
        await page.waitForTimeout(5000);
      });
      await debugShot(page, '05-analytics-page');

      // On the analytics overview page, look for a [role="tab"] Shows sub-tab
      // (NOT the sidebar nav link which navigates away to /dashboard/shows).
      // We only do this if we landed on the analytics URL — if we're already on
      // a shows list page, there's nothing to click.
      if (isAnalytics && targetUrl === URLS.analytics) {
        let showsTab = null;

        const tabCandidates = [
          // Role-tab / button variants for the analytics sub-tabs only — deliberately
          // excluding bare 'a:has-text("Shows")' which matches the sidebar nav link.
          '[role="tab"]:has-text("Shows")',
          'button:has-text("Shows")',
          '[role="tab"]:has-text("Past Shows")',
          'button:has-text("Past Shows")',
          '[role="tab"]:has-text("Stream")',
          'button:has-text("Stream")',
          '[role="tab"]:has-text("History")',
          'button:has-text("History")',
          // Historic attribute-based (MUI tab IDs)
          'button[aria-controls="simple-tabpanel-1"]',
          'button#simple-tab-1',
          '[role="tab"][data-value="shows"]',
          '[role="tab"][data-index="1"]',
        ];

        for (const sel of tabCandidates) {
          const el = await page.$(sel).catch(() => null);
          if (!el) continue;
          if (!await el.isVisible().catch(() => false)) continue;
          showsTab = el;
          log(`found Shows tab via: ${sel}`);
          break;
        }

        if (!showsTab) {
          for (const frame of page.frames()) {
            if (frame === page.mainFrame()) continue;
            for (const sel of tabCandidates) {
              const el = await frame.$(sel).catch(() => null);
              if (!el) continue;
              if (!await el.isVisible().catch(() => false)) continue;
              showsTab = el;
              log(`found Shows tab in iframe (${frame.url()}) via: ${sel}`);
              break;
            }
            if (showsTab) break;
          }
        }

        if (showsTab) {
          const isSelected = await showsTab.evaluate(el =>
            el.getAttribute('aria-selected') === 'true' || el.getAttribute('aria-current') === 'true'
          ).catch(() => false);
          if (!isSelected) {
            await showsTab.click();
            await page.waitForTimeout(1800);
          }
          await debugShot(page, '06-shows-tab');
        } else {
          info('analytics overview: no [role="tab"] Shows sub-tab found — will rely on API intercept / list DOM');
          await debugShot(page, '06-no-tab');
        }
      }

      // ── Try API interception first ───────────────────────────────────────────
      // Give the SPA a moment to complete its data-fetch.
      await page.waitForTimeout(1500);
      const apiShows = extractShowsFromCapture(capturedApiResponses);

      if (apiShows && apiShows.length > 0) {
        const normalized = apiShows.slice(0, LIMIT).map(normalizeApiShow)
          .filter(r => r.title || r.show_date || r.gross_revenue !== null);

        if (normalized.length > 0) {
          process.stdout.write(JSON.stringify(normalized, null, 2) + '\n');
          info(`API intercept: returned ${normalized.length} shows — no DOM scraping needed`);
          process.exit(0);
        }
        info('API intercept found array but normalization yielded nothing — falling back to DOM scraping');
      } else {
        if (capturedApiResponses.length > 0) {
          info('captured', capturedApiResponses.length, 'API response(s) but none matched show data:',
            capturedApiResponses.map(r => r.url.replace('https://www.whatnot.com', '')).join(' | '));
        } else {
          info('no API responses captured — Whatnot may be using non-JSON transport');
        }
        info('falling back to DOM scraping');
      }

      // ── Shows-list DOM extraction (for /dashboard/shows and /seller/shows) ──
      // Try this before the metric-card loop because list pages never have metric cards.
      const currentPageUrl = page.url();
      const isListPage = currentPageUrl.includes('/dashboard/shows') ||
                         currentPageUrl.includes('/seller/shows') ||
                         (targetUrl === URLS.dashboardShows) ||
                         (targetUrl === URLS.shows);

      if (isListPage) {
        // Scroll down to trigger lazy-load of additional show cards
        for (let s = 0; s < 4; s++) {
          await page.evaluate(() => window.scrollBy(0, window.innerHeight));
          await page.waitForTimeout(500);
        }
        await debugShot(page, '06-shows-list-scrolled');

        const listShows = await extractShowsListFromDom(page);
        info('shows-list DOM: found', listShows.length, 'show links on', currentPageUrl);

        if (listShows.length > 0) {
          const normalized = listShows.slice(0, LIMIT).filter(s => s.title || s.show_date);
          if (normalized.length > 0) {
            process.stdout.write(JSON.stringify(normalized, null, 2) + '\n');
            info(`shows-list DOM: returned ${normalized.length} shows`);
            process.exit(0);
          }
          info('shows-list DOM: found links but no title/date extracted — dumping diagnostics');
        }

        // Dump diagnostics so the selector can be updated
        const diag = await page.evaluate(() => ({
          url:      location.href,
          bodyText: (document.body.innerText || '').substring(0, 3000),
          links:    Array.from(document.querySelectorAll('a[href]'))
                      .filter(a => /\/live\/|\/show\/|\/seller\/shows|\/dashboard\/shows/.test(a.getAttribute('href') || ''))
                      .slice(0, 10)
                      .map(a => a.getAttribute('href')),
        }));
        process.stderr.write('SELECTOR_MISS: No shows found on list page.\n');
        process.stderr.write('CURRENT_URL: ' + diag.url + '\n');
        process.stderr.write('SHOW_LINKS_FOUND: ' + JSON.stringify(diag.links) + '\n');
        process.stderr.write('PAGE_TEXT:\n' + diag.bodyText + '\n');
        process.exit(2);
      }

      // ── Metric-card DOM scraping (for analytics detail pages) ────────────────
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
    await context.close();
  }
})();
