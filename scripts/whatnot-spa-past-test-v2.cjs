'use strict';

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function loadPlaywright() {
  try {
    const root = execSync('npm root -g', { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }).trim();
    return require(root + '/playwright');
  } catch {}
  for (const p of [
    '/opt/node22/lib/node_modules/playwright',
    '/usr/lib/node_modules/playwright',
    '/usr/local/lib/node_modules/playwright',
    '/opt/homebrew/lib/node_modules/playwright',
  ]) {
    try { return require(p); } catch {}
  }
  throw new Error('Playwright not found');
}

const { chromium } = loadPlaywright();
const LIVE_ID = (process.argv[2] || '183498e1-fc7d-436b-a4a0-c042efba09b8').trim();
const DEBUG = process.env.WHATNOT_DEBUG === '1';

function findChromium() {
  const explicit = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
  if (explicit && fs.existsSync(explicit)) return explicit;

  for (const marker of [
    path.join(__dirname, '../storage/chromium-path.txt'),
    path.join(process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers', '.chromium-path'),
  ]) {
    try {
      const p = fs.readFileSync(marker, 'utf8').trim();
      if (p && fs.existsSync(p)) return p;
    } catch {}
  }

  try {
    const p = chromium.executablePath();
    if (p && fs.existsSync(p)) return p;
  } catch {}

  for (const p of ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome']) {
    if (fs.existsSync(p)) return p;
  }
  return undefined;
}

function isChallenge(text, title = '') {
  return /Performing security verification|Just a moment\.\.\.|Checking your browser|verify you are human|verifying you are human|cf-chl|cf_chl|challenge-platform/i.test(`${title}\n${text || ''}`);
}

async function pageState(page, extra = {}) {
  const body = await page.locator('body').innerText().catch(() => '');
  const title = await page.title().catch(() => '');
  return {
    url: page.url(),
    title,
    challenged: isChallenge(body, title),
    body: body.substring(0, 7000),
    ...extra,
  };
}

async function shot(page, name) {
  if (!DEBUG) return;
  try { await page.screenshot({ path: `/tmp/whatnot-spa-v2-${name}.png`, fullPage: false }); } catch {}
}

async function restoreLocalStorage(page) {
  const file = path.join(__dirname, '../storage/whatnot-localstorage.json');
  if (!fs.existsSync(file)) return false;
  try {
    const data = JSON.parse(fs.readFileSync(file, 'utf8'));
    await page.evaluate((items) => {
      for (const [k, v] of Object.entries(items || {})) {
        try { localStorage.setItem(k, v); } catch {}
      }
    }, data);
    return true;
  } catch {
    return false;
  }
}

async function maybeLoadBootstrapCookies(context) {
  const existing = await context.cookies('https://www.whatnot.com').catch(() => []);
  if (existing.length > 0) return { loaded: false, existing: existing.length };

  const candidates = [
    process.env.WHATNOT_COOKIES_FILE,
    path.join(__dirname, '../storage/whatnot-live-cookies.json'),
    path.join(__dirname, '../storage/whatnot-cookies.json'),
  ].filter(Boolean);

  for (const file of candidates) {
    if (!fs.existsSync(file)) continue;
    try {
      const raw = JSON.parse(fs.readFileSync(file, 'utf8'));
      const sameSiteMap = { no_restriction: 'None', strict: 'Strict', lax: 'Lax' };
      const cookies = raw
        .filter(c => c && typeof c.name === 'string' && typeof c.value === 'string')
        .filter(c => !['cf_clearance', '__cf_bm', 'cf_chl_2', 'cf_chl_prog'].includes(c.name))
        .map(c => ({
          name: c.name,
          value: c.value,
          domain: c.domain || '.whatnot.com',
          path: c.path || '/',
          expires: c.expirationDate ?? c.expires ?? -1,
          httpOnly: Boolean(c.httpOnly),
          secure: Boolean(c.secure),
          sameSite: sameSiteMap[String(c.sameSite || '').toLowerCase()] || 'Lax',
        }));
      if (cookies.length) {
        await context.addCookies(cookies);
        return { loaded: true, existing: 0, file, count: cookies.length };
      }
    } catch {}
  }
  return { loaded: false, existing: 0 };
}

async function clickSellerHubShows(page) {
  if (/\/dashboard\/lives(?:[/?#]|$)/.test(page.url())) return { clicked: true, already: true };

  const choices = [
    ['href exact', page.locator('a[href="/dashboard/lives"]')],
    ['href contains', page.locator('a[href*="/dashboard/lives"]')],
    ['role', page.getByRole('link', { name: /^Shows$/i })],
  ];

  const tried = [];
  for (const [via, locator] of choices) {
    const count = await locator.count().catch(() => 0);
    tried.push({ via, count });
    if (!count) continue;
    const el = locator.first();
    const visible = await el.isVisible().catch(() => false);
    tried[tried.length - 1].visible = visible;
    if (!visible) continue;
    try {
      await el.click({ timeout: 10000 });
      await page.waitForURL(/\/dashboard\/lives(?:[/?#]|$)/, { timeout: 12000 }).catch(() => {});
      await page.waitForTimeout(3500);
      return { clicked: true, via, tried, landed: page.url() };
    } catch (e) {
      tried[tried.length - 1].error = String(e.message || e).substring(0, 220);
    }
  }
  return { clicked: false, tried, landed: page.url() };
}

async function clickPast(page) {
  const choices = [
    ['exact selector', page.locator('ul[role="tablist"] button[role="tab"]', { hasText: /^Past$/ })],
    ['role', page.getByRole('tab', { name: /^Past$/i })],
  ];
  const tried = [];
  for (const [via, locator] of choices) {
    const count = await locator.count().catch(() => 0);
    tried.push({ via, count });
    if (!count) continue;
    const el = locator.first();
    const visible = await el.isVisible().catch(() => false);
    const before = await el.getAttribute('aria-selected').catch(() => null);
    tried[tried.length - 1].visible = visible;
    tried[tried.length - 1].selected_before = before;
    if (!visible) continue;
    try {
      if (before !== 'true') await el.click({ timeout: 10000 });
      await page.waitForFunction(() => {
        const tabs = [...document.querySelectorAll('ul[role="tablist"] button[role="tab"]')];
        const past = tabs.find(el => (el.textContent || '').trim() === 'Past');
        return past && past.getAttribute('aria-selected') === 'true';
      }, { timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(3500);
      return {
        clicked: true,
        via,
        selected_before: before,
        selected_after: await el.getAttribute('aria-selected').catch(() => null),
        tried,
      };
    } catch (e) {
      tried[tried.length - 1].error = String(e.message || e).substring(0, 220);
    }
  }
  return { clicked: false, tried };
}

async function extractPastRows(page) {
  return await page.locator('[data-testid="show-list-item"]').evaluateAll((rows) => rows.map((row) => {
    const title = row.querySelector('[data-testid="show-list-item-title"]')?.textContent?.trim() || null;
    const open = row.querySelector('a[href^="/dashboard/live/"]');
    const shipments = [...row.querySelectorAll('a')].find(a => /View Shipments/i.test(a.textContent || ''));
    const analytics = [...row.querySelectorAll('a')].find(a => /See Analytics/i.test(a.textContent || ''));
    const openHref = open?.getAttribute('href') || null;
    const id = openHref?.match(/\/dashboard\/live\/([0-9a-f-]{36})/i)?.[1] || null;
    const dateTime = [...row.querySelectorAll('strong')].map(el => (el.textContent || '').trim()).filter(Boolean);
    return {
      id,
      title,
      open_href: openHref,
      shipments_href: shipments?.getAttribute('href') || null,
      analytics_href: analytics?.getAttribute('href') || null,
      text: (row.innerText || '').trim().replace(/\s+/g, ' ').substring(0, 1500),
      strong_text: dateTime,
    };
  })).catch(() => []);
}

async function clickRowLink(page, liveId, label) {
  const row = page.locator('[data-testid="show-list-item"]', {
    has: page.locator(`a[href="/dashboard/live/${liveId}"]`),
  }).first();
  if (!(await row.count().catch(() => 0))) return { clicked: false, reason: 'row not found' };

  const link = row.getByRole('link', { name: new RegExp(`^${label}$`, 'i') }).first();
  if (!(await link.count().catch(() => 0))) return { clicked: false, reason: `${label} link not found in row` };
  const href = await link.getAttribute('href').catch(() => null);
  try {
    await link.click({ timeout: 10000 });
    await page.waitForTimeout(6000);
    return { clicked: true, href, landed: page.url() };
  } catch (e) {
    return { clicked: false, href, reason: String(e.message || e).substring(0, 240) };
  }
}

async function extractAnalytics(page) {
  const text = await page.locator('body').innerText().catch(() => '');
  const lines = text.split('\n').map(x => x.trim()).filter(Boolean);
  const labels = [
    'Estimated Sales', 'Total Estimated Earnings', 'Completed Earnings', 'Orders',
    'Average Order Value', 'AOV', 'Giveaway Spend', 'Giveaways', 'Buyers',
    'First Time Buyers', 'Returning Buyers', 'Shares', 'Show Duration', 'Duration',
    'Max Concurrent Viewers', 'Total Views', 'Average Order Rating',
  ];
  const metrics = {};
  for (const label of labels) {
    const i = lines.findIndex(x => x.toLowerCase() === label.toLowerCase());
    if (i >= 0) metrics[label] = lines[i + 1] || null;
  }
  const title = await page.title().catch(() => '');
  return {
    url: page.url(),
    challenged: isChallenge(text, title),
    metrics,
    body: text.substring(0, 8000),
  };
}

(async () => {
  const profile = process.env.WHATNOT_USER_DATA_DIR || path.join(__dirname, '../storage/whatnot-browser-profile');
  fs.mkdirSync(profile, { recursive: true });

  const context = await chromium.launchPersistentContext(profile, {
    headless: process.env.WHATNOT_HEADLESS !== 'false',
    executablePath: findChromium(),
    args: ['--no-sandbox', '--no-zygote', '--disable-dev-shm-usage', '--disable-crash-reporter', '--crash-dumps-dir=/tmp', '--disable-gpu'],
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport: { width: 1280, height: 900 },
    locale: 'en-US',
    env: { TZ: 'America/Chicago' },
    extraHTTPHeaders: {
      'sec-ch-ua': '"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"',
      'sec-ch-ua-mobile': '?0',
      'sec-ch-ua-platform': '"Windows"',
      'Accept-Language': 'en-US,en;q=0.9',
    },
  });

  await context.addInitScript(() => {
    try { Object.defineProperty(navigator, 'webdriver', { get: () => undefined }); } catch {}
    if (!window.chrome) window.chrome = { runtime: {} };
    try { Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] }); } catch {}
    try { Object.defineProperty(navigator, 'platform', { get: () => 'Win32' }); } catch {}
    try {
      Object.defineProperty(screen, 'width', { get: () => 1920 });
      Object.defineProperty(screen, 'height', { get: () => 1080 });
      Object.defineProperty(screen, 'availWidth', { get: () => 1920 });
      Object.defineProperty(screen, 'availHeight', { get: () => 1040 });
      Object.defineProperty(screen, 'colorDepth', { get: () => 24 });
      Object.defineProperty(screen, 'pixelDepth', { get: () => 24 });
    } catch {}
    try {
      const orig = WebGLRenderingContext.prototype.getParameter;
      WebGLRenderingContext.prototype.getParameter = function(parameter) {
        if (parameter === 37446) return 'Google Inc. (Intel)';
        if (parameter === 37445) return 'ANGLE (Intel, Intel(R) UHD Graphics 620 (0x00003E9B) Direct3D11 vs_5_0 ps_5_0, D3D11)';
        return orig.call(this, parameter);
      };
    } catch {}
  });

  const out = { live_id: LIVE_ID, stages: {}, operations: [] };
  const operations = [];
  const page = await context.newPage();

  page.on('request', (req) => {
    const m = req.url().match(/operationName=([^&]+)/);
    if (m) operations.push(decodeURIComponent(m[1]));
  });

  try {
    out.stages.cookies = await maybeLoadBootstrapCookies(context);

    // If a localStorage snapshot exists, establish the origin then restore it exactly like production.
    const bootstrapPage = await page.goto('https://www.whatnot.com/dashboard/home', { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
    await page.waitForTimeout(7000);
    if ((await context.cookies('https://www.whatnot.com')).length > 0) {
      out.stages.local_storage_restored = await restoreLocalStorage(page);
      if (out.stages.local_storage_restored) {
        await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
        await page.waitForTimeout(7000);
      }
    }

    out.stages.home = await pageState(page, { status: bootstrapPage ? bootstrapPage.status() : null });
    await shot(page, '01-home');

    if (out.stages.home.challenged) {
      out.operations = [...new Set(operations)];
      process.stdout.write(JSON.stringify(out, null, 2) + '\n');
      return;
    }

    out.stages.shows_click = await clickSellerHubShows(page);
    out.stages.shows = await pageState(page, {
      tab_current_count: await page.locator('[data-testid="tab-current"]').count().catch(() => 0),
      tab_upcoming_count: await page.locator('[data-testid="tab-upcoming"]').count().catch(() => 0),
      tab_past_count: await page.locator('ul[role="tablist"] button[role="tab"]', { hasText: /^Past$/ }).count().catch(() => 0),
    });
    await shot(page, '02-shows');

    if (!out.stages.shows_click.clicked || out.stages.shows.challenged) {
      out.operations = [...new Set(operations)];
      process.stdout.write(JSON.stringify(out, null, 2) + '\n');
      return;
    }

    out.stages.past_click = await clickPast(page);
    const rows = await extractPastRows(page);
    out.stages.past = await pageState(page, { rows, row_count: rows.length });
    await shot(page, '03-past');

    const target = rows.find(row => row.id === LIVE_ID) || null;
    out.stages.target_row = target;

    if (target) {
      out.stages.analytics_click = await clickRowLink(page, LIVE_ID, 'See Analytics');
      if (out.stages.analytics_click.clicked) {
        out.stages.analytics = await extractAnalytics(page);
        await shot(page, '04-analytics');
      }

      // Return through the Seller Hub sidebar, not a fresh direct navigation.
      out.stages.back_to_shows = await clickSellerHubShows(page);
      if (out.stages.back_to_shows.clicked) {
        out.stages.back_to_past = await clickPast(page);
        out.stages.shipments_click = await clickRowLink(page, LIVE_ID, 'View Shipments');
        if (out.stages.shipments_click.clicked) {
          const shipText = await page.locator('body').innerText().catch(() => '');
          const shipTitle = await page.title().catch(() => '');
          out.stages.shipments = {
            url: page.url(),
            challenged: isChallenge(shipText, shipTitle),
            body: shipText.substring(0, 12000),
            table_rows: await page.locator('tbody tr').count().catch(() => 0),
          };
          await shot(page, '05-shipments');
        }
      }
    }

    out.operations = [...new Set(operations)];
    process.stdout.write(JSON.stringify(out, null, 2) + '\n');
  } finally {
    await context.close().catch(() => {});
  }
})().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err));
  process.exit(1);
});
