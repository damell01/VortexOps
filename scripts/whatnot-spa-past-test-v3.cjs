'use strict';

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function loadPlaywright() {
  try {
    const root = execSync('npm root -g', { encoding: 'utf8', stdio: ['pipe','pipe','pipe'] }).trim();
    return require(root + '/playwright');
  } catch {}
  for (const p of ['/opt/node22/lib/node_modules/playwright','/usr/lib/node_modules/playwright','/usr/local/lib/node_modules/playwright']) {
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
  const marker = path.join(__dirname, '../storage/chromium-path.txt');
  try { const p = fs.readFileSync(marker,'utf8').trim(); if (p && fs.existsSync(p)) return p; } catch {}
  try { const p = chromium.executablePath(); if (p && fs.existsSync(p)) return p; } catch {}
  for (const p of ['/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome']) if (fs.existsSync(p)) return p;
  return undefined;
}

function isChallenge(text, title = '') {
  return /performing security verification|just a moment|verifying you are human|checking your browser|cf-chl|cf_chl|challenge-platform|ray id/i.test(`${title}\n${text}`);
}

async function bodyText(page) {
  return await page.locator('body').innerText().catch(() => '');
}

async function shot(page, name) {
  if (!DEBUG) return;
  try { await page.screenshot({ path: `/tmp/whatnot-spa-v3-${name}.png`, fullPage: false }); } catch {}
}

async function pageState(page) {
  const text = await bodyText(page);
  const title = await page.title().catch(() => '');
  return { url: page.url(), title, challenged: isChallenge(text, title), body: text.substring(0, 3000) };
}

async function waitForRealPage(page, timeoutMs = 20000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    const state = await pageState(page);
    if (!state.challenged && state.body.trim().length > 100) return state;
    await page.waitForTimeout(1500);
  }
  return await pageState(page);
}

async function clickShows(page) {
  if (/\/dashboard\/lives(?:[/?#]|$)/.test(page.url())) return true;
  const candidates = [
    page.locator('a[href="/dashboard/lives"]'),
    page.locator('a[href*="/dashboard/lives"]'),
  ];
  for (const loc of candidates) {
    if (!(await loc.count().catch(() => 0))) continue;
    const el = loc.first();
    if (!(await el.isVisible().catch(() => false))) continue;
    await el.click({ timeout: 8000 }).catch(() => null);
    await page.waitForURL(/\/dashboard\/lives(?:[/?#]|$)/, { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(2500);
    if (/\/dashboard\/lives(?:[/?#]|$)/.test(page.url())) return true;
  }
  return false;
}

async function clickPast(page) {
  const past = page.locator('ul[role="tablist"] button[role="tab"]', { hasText: /^Past$/ }).first();
  if (!(await past.count().catch(() => 0))) return false;
  if (!(await past.isVisible().catch(() => false))) return false;
  if ((await past.getAttribute('aria-selected').catch(() => null)) !== 'true') {
    await past.click({ timeout: 8000 }).catch(() => null);
  }
  await page.waitForFunction(() => {
    const b = [...document.querySelectorAll('ul[role="tablist"] button[role="tab"]')]
      .find(x => (x.textContent || '').trim() === 'Past');
    return b && b.getAttribute('aria-selected') === 'true';
  }, { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(2500);
  return (await past.getAttribute('aria-selected').catch(() => null)) === 'true';
}

async function extractPastRows(page) {
  return await page.locator('[data-testid="show-list-item"]').evaluateAll((rows) => rows.map(row => {
    const title = row.querySelector('[data-testid="show-list-item-title"]')?.textContent?.trim() || null;
    const open = row.querySelector('a[href^="/dashboard/live/"]');
    const shipments = [...row.querySelectorAll('a')].find(a => /View Shipments/i.test(a.textContent || ''));
    const analytics = [...row.querySelectorAll('a')].find(a => /See Analytics/i.test(a.textContent || ''));
    const openHref = open?.getAttribute('href') || null;
    const m = openHref?.match(/\/dashboard\/live\/([0-9a-f-]{36})/i);
    const strongs = [...row.querySelectorAll('strong')].map(s => (s.textContent || '').trim()).filter(Boolean);
    return {
      live_id: m ? m[1] : null,
      title,
      date_time_parts: strongs,
      open_url: openHref,
      shipments_url: shipments?.getAttribute('href') || null,
      analytics_url: analytics?.getAttribute('href') || null,
      text: (row.innerText || '').trim().replace(/\s+/g, ' '),
    };
  }));
}

async function extractAnalytics(page) {
  const text = await bodyText(page);
  const lines = text.split('\n').map(x => x.trim()).filter(Boolean);
  const labels = [
    'Estimated Sales','Total Estimated Earnings','Completed Earnings','Orders',
    'Average Order Value','AOV','Giveaway Spend','Giveaways','Buyers','First Time Buyers',
    'Returning Buyers','Shares','Show Duration','Duration','Max Concurrent Viewers','Total Views',
    'Average Order Rating'
  ];
  const metrics = {};
  for (const label of labels) {
    const i = lines.findIndex(x => x.toLowerCase() === label.toLowerCase());
    if (i >= 0) metrics[label] = lines[i + 1] || null;
  }
  const state = await pageState(page);
  return { ...state, metrics };
}

(async () => {
  const userDataDir = process.env.WHATNOT_USER_DATA_DIR || path.join(__dirname, '../storage/whatnot-browser-profile');
  fs.mkdirSync(userDataDir, { recursive: true });

  const context = await chromium.launchPersistentContext(userDataDir, {
    headless: process.env.WHATNOT_HEADLESS !== 'false',
    executablePath: findChromium(),
    args: ['--no-sandbox','--no-zygote','--disable-dev-shm-usage','--disable-crash-reporter','--crash-dumps-dir=/tmp','--disable-gpu'],
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport: { width: 1280, height: 900 },
    locale: 'en-US',
    timezoneId: 'America/Chicago',
    extraHTTPHeaders: {
      'sec-ch-ua': '"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"',
      'sec-ch-ua-mobile': '?0',
      'sec-ch-ua-platform': '"Windows"',
      'Accept-Language': 'en-US,en;q=0.9',
    },
  });

  await context.addInitScript(() => {
    try { Object.defineProperty(navigator, 'webdriver', { get: () => undefined }); } catch {}
    try { if (!window.chrome) window.chrome = { runtime: {} }; } catch {}
    try { Object.defineProperty(navigator, 'languages', { get: () => ['en-US','en'] }); } catch {}
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
      const getParam = WebGLRenderingContext.prototype.getParameter;
      WebGLRenderingContext.prototype.getParameter = function (parameter) {
        if (parameter === 37446) return 'Google Inc. (Intel)';
        if (parameter === 37445) return 'ANGLE (Intel, Intel(R) UHD Graphics 620 (0x00003E9B) Direct3D11 vs_5_0 ps_5_0, D3D11)';
        return getParam.call(this, parameter);
      };
    } catch {}
  });

  // If the persistent profile is empty, seed it from the freshest saved live cookies.
  const existing = await context.cookies('https://www.whatnot.com').catch(() => []);
  if (!existing.length) {
    for (const file of [
      path.join(__dirname, '../storage/whatnot-live-cookies.json'),
      path.join(__dirname, '../storage/whatnot-cookies.json'),
    ]) {
      if (!fs.existsSync(file)) continue;
      try {
        const raw = JSON.parse(fs.readFileSync(file, 'utf8'));
        const cookies = raw.filter(c => c && c.name && typeof c.value === 'string').map(c => ({
          name: c.name,
          value: c.value,
          domain: c.domain || '.whatnot.com',
          path: c.path || '/',
          expires: c.expirationDate ?? c.expires ?? -1,
          httpOnly: !!c.httpOnly,
          secure: !!c.secure,
          sameSite: ({ no_restriction:'None', strict:'Strict', lax:'Lax' })[(c.sameSite || '').toLowerCase()] || 'Lax',
        }));
        if (cookies.length) await context.addCookies(cookies);
        break;
      } catch {}
    }
  }

  // Never replay a clearance token that claims a lifetime longer than a normal edge challenge.
  const now = Date.now();
  const edge = await context.cookies('https://www.whatnot.com').catch(() => []);
  const importedClearance = edge.find(c => c.name === 'cf_clearance' && c.expires > 0 && c.expires * 1000 - now > 24 * 60 * 60 * 1000);
  if (importedClearance) {
    for (const name of ['cf_clearance','__cf_bm','cf_chl_2','cf_chl_prog']) {
      await context.clearCookies({ name }).catch(() => {});
    }
  }

  // Restore the saved seller localStorage into the origin before the app initializes.
  const lsFile = path.join(__dirname, '../storage/whatnot-localstorage.json');
  if (fs.existsSync(lsFile)) {
    try {
      const saved = JSON.parse(fs.readFileSync(lsFile, 'utf8'));
      await context.addInitScript((entries) => {
        if (!/whatnot\.com$/i.test(location.hostname)) return;
        try { for (const [k,v] of Object.entries(entries || {})) localStorage.setItem(k, v); } catch {}
      }, saved);
    } catch {}
  }

  const page = await context.newPage();
  const operations = [];
  page.on('request', req => {
    const m = req.url().match(/operationName=([^&]+)/);
    if (m) operations.push(decodeURIComponent(m[1]));
  });

  const out = { live_id: LIVE_ID, stages: {}, past_rows: [], target: null, operations: [] };
  try {
    const resp = await page.goto('https://www.whatnot.com/dashboard/home', { waitUntil:'domcontentloaded', timeout:30000 }).catch(() => null);
    await page.waitForLoadState('networkidle', { timeout: 7000 }).catch(() => {});
    out.stages.home = { status: resp ? resp.status() : null, ...(await waitForRealPage(page, 12000)) };
    await shot(page, '01-home');

    if (!out.stages.home.challenged) {
      out.stages.shows_click = { clicked: await clickShows(page) };
      out.stages.shows = await waitForRealPage(page, 10000);
      out.stages.shows.tabs = {
        current: await page.locator('[data-testid="tab-current"]').count().catch(() => 0),
        upcoming: await page.locator('[data-testid="tab-upcoming"]').count().catch(() => 0),
        past: await page.locator('ul[role="tablist"] button[role="tab"]', { hasText: /^Past$/ }).count().catch(() => 0),
      };
      await shot(page, '02-shows');

      if (!out.stages.shows.challenged && /\/dashboard\/lives(?:[/?#]|$)/.test(page.url())) {
        out.stages.past_click = { clicked: await clickPast(page) };
        out.stages.past = await waitForRealPage(page, 8000);
        out.past_rows = await extractPastRows(page);
        out.target = out.past_rows.find(r => r.live_id === LIVE_ID) || null;
        await shot(page, '03-past');

        if (out.target?.analytics_url) {
          const row = page.locator('[data-testid="show-list-item"]').filter({ has: page.locator(`a[href="${out.target.open_url}"]`) }).first();
          const analytics = row.locator('a', { hasText: /^See Analytics$/ }).first();
          if (await analytics.count().catch(() => 0)) {
            await analytics.click({ timeout: 8000 }).catch(() => null);
            await page.waitForTimeout(5000);
            out.stages.analytics = await extractAnalytics(page);
            await shot(page, '04-analytics');
          }
        }

        // Return to Shows through the SPA and test the exact shipment href from the row.
        if (out.target?.shipments_url) {
          await page.goBack({ waitUntil:'domcontentloaded', timeout:15000 }).catch(() => null);
          await page.waitForTimeout(3000);
          if (!/\/dashboard\/lives(?:[/?#]|$)/.test(page.url())) await clickShows(page);
          await clickPast(page);
          const row = page.locator('[data-testid="show-list-item"]').filter({ has: page.locator(`a[href="${out.target.open_url}"]`) }).first();
          const shipments = row.locator('a', { hasText: /^View Shipments$/ }).first();
          if (await shipments.count().catch(() => 0)) {
            await shipments.click({ timeout: 8000 }).catch(() => null);
            await page.waitForTimeout(5000);
            const state = await pageState(page);
            out.stages.shipments = {
              ...state,
              body: (await bodyText(page)).substring(0, 7000),
              shipment_like_rows: await page.locator('tbody tr').count().catch(() => 0),
            };
            await shot(page, '05-shipments');
          }
        }
      }
    }

    out.operations = [...new Set(operations)];
    process.stdout.write(JSON.stringify(out, null, 2) + '\n');
  } finally {
    await context.close().catch(() => {});
  }
})().catch(err => {
  console.error(err && err.stack ? err.stack : String(err));
  process.exit(1);
});
