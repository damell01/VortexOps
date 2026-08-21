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

async function challengeState(page, text = null) {
  text ??= await page.locator('body').innerText().catch(() => '');
  const title = await page.title().catch(() => '');
  const html = await page.locator('html').getAttribute('class').catch(() => '') || '';
  const hard = /Performing security verification|Just a moment\.\.\.|cf-chl|cf_chl|challenge-platform/i;
  const challenged = hard.test(title) || hard.test(text) || hard.test(html);
  return { challenged, title, body_sample: text.substring(0, 1200) };
}

async function snapshot(page, name) {
  if (!DEBUG) return;
  try { await page.screenshot({ path: `/tmp/whatnot-spa-${name}.png`, fullPage: false }); } catch {}
}

async function bodyText(page) {
  return await page.locator('body').innerText().catch(() => '');
}

async function pageLinks(page) {
  return await page.locator('a').evaluateAll((els) => els.slice(0, 150).map(a => ({
    text: (a.innerText || a.textContent || '').trim().replace(/\s+/g, ' ').substring(0, 120),
    href: a.getAttribute('href'),
  })).filter(x => x.text || x.href)).catch(() => []);
}

async function clickShows(page) {
  const choices = [
    ['href exact', page.locator('a[href="/dashboard/lives"]')],
    ['href contains', page.locator('a[href*="/dashboard/lives"]')],
    ['role link', page.getByRole('link', { name: /^Shows$/i })],
    ['text', page.getByText(/^Shows$/i)],
  ];
  const tried = [];
  for (const [name, loc] of choices) {
    const count = await loc.count().catch(() => 0);
    tried.push({ name, count });
    if (!count) continue;
    const el = loc.first();
    const visible = await el.isVisible().catch(() => false);
    tried[tried.length - 1].visible = visible;
    if (!visible) continue;
    const href = await el.getAttribute('href').catch(() => null);
    tried[tried.length - 1].href = href;
    try {
      await el.click({ timeout: 8000 });
      await page.waitForTimeout(5000);
      return { clicked: true, via: name, href, tried };
    } catch (e) {
      tried[tried.length - 1].error = String(e.message || e).substring(0, 180);
    }
  }
  return { clicked: false, tried };
}

async function clickPast(page) {
  const choices = [
    ['role tab', page.getByRole('tab', { name: /^Past$/i })],
    ['role button', page.getByRole('button', { name: /^Past$/i })],
    ['text', page.getByText(/^Past$/i)],
  ];
  const tried = [];
  for (const [name, loc] of choices) {
    const count = await loc.count().catch(() => 0);
    tried.push({ name, count });
    if (!count) continue;
    const el = loc.first();
    const visible = await el.isVisible().catch(() => false);
    tried[tried.length - 1].visible = visible;
    if (!visible) continue;
    try {
      await el.click({ timeout: 8000 });
      await page.waitForTimeout(5000);
      return { clicked: true, via: name, tried };
    } catch (e) {
      tried[tried.length - 1].error = String(e.message || e).substring(0, 180);
    }
  }
  return { clicked: false, tried };
}

async function findShowContainer(page) {
  const anchor = page.locator(`a[href*="${LIVE_ID}"]`).first();
  if (await anchor.count().catch(() => 0)) {
    const handle = await anchor.elementHandle();
    if (handle) {
      return await handle.evaluate((el) => {
        let n = el;
        for (let i=0;i<12 && n;i++,n=n.parentElement) {
          const text = (n.innerText || '').trim();
          if (n.tagName === 'TR' || /See Analytics|View Shipments|Open show/i.test(text)) {
            return {
              text: text.substring(0,3000),
              html: n.outerHTML.substring(0,25000),
              links: [...n.querySelectorAll('a')].map(a => ({ text:(a.innerText||'').trim(), href:a.href })),
              buttons: [...n.querySelectorAll('button')].map(b => ({ text:(b.innerText||'').trim(), aria:b.getAttribute('aria-label') })),
            };
          }
        }
        return null;
      });
    }
  }
  return null;
}

async function extractAnalytics(page) {
  const text = await bodyText(page);
  const state = await challengeState(page, text);
  const labels = ['Estimated Sales','Total Estimated Earnings','Completed Earnings','Orders','AOV','Average Order Value','Giveaway Spend','Buyers','Shares','Duration','Max Concurrent Viewers','Total Views'];
  const lines = text.split('\n').map(s=>s.trim()).filter(Boolean);
  const metrics = {};
  for (const label of labels) {
    const idx = lines.findIndex(l => l.toLowerCase() === label.toLowerCase());
    if (idx >= 0) metrics[label] = lines[idx+1] || null;
  }
  return { ...state, url: page.url(), metrics, body: text.substring(0,4000) };
}

async function clickRowAction(page, actionText) {
  const anchor = page.locator(`a[href*="${LIVE_ID}"]`).first();
  if (!(await anchor.count().catch(() => 0))) return { clicked: false, reason: 'show anchor missing' };
  const container = anchor.locator('xpath=ancestor-or-self::*[self::tr or self::li or self::div][.//*[contains(normalize-space(), "'+actionText+'")] ]').first();
  const action = container.getByText(new RegExp('^'+actionText+'$','i')).first();
  if (await action.count().catch(() => 0)) {
    try {
      await action.click({ timeout: 8000 });
      await page.waitForTimeout(5000);
      return { clicked: true, scope: 'row' };
    } catch (e) { return { clicked: false, reason: String(e.message || e).substring(0,180) }; }
  }
  const broad = page.getByText(new RegExp('^'+actionText+'$','i')).first();
  if (await broad.count().catch(() => 0)) {
    try {
      await broad.click({ timeout: 8000 });
      await page.waitForTimeout(5000);
      return { clicked: true, scope: 'page' };
    } catch (e) { return { clicked: false, reason: String(e.message || e).substring(0,180) }; }
  }
  return { clicked: false, reason: 'action text missing' };
}

(async () => {
  const userDataDir = process.env.WHATNOT_USER_DATA_DIR || path.join(__dirname, '../storage/whatnot-browser-profile');
  const context = await chromium.launchPersistentContext(userDataDir, {
    headless: process.env.WHATNOT_HEADLESS !== 'false',
    executablePath: findChromium(),
    args: ['--no-sandbox','--no-zygote','--disable-dev-shm-usage','--disable-gpu'],
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport: { width: 1440, height: 1000 },
  });

  const page = await context.newPage();
  const operations = [];
  page.on('request', req => {
    const u = req.url();
    const m = u.match(/operationName=([^&]+)/);
    if (m) operations.push(decodeURIComponent(m[1]));
  });

  const out = { live_id: LIVE_ID, stages: {}, operations: [] };
  try {
    const resp = await page.goto('https://www.whatnot.com/dashboard/home', { waitUntil:'domcontentloaded', timeout:30000 }).catch(() => null);
    await page.waitForTimeout(8000);
    let text = await bodyText(page);
    const homeState = await challengeState(page, text);
    out.stages.home = {
      status: resp ? resp.status() : null,
      url: page.url(),
      ...homeState,
      body: text.substring(0,2500),
      links: await pageLinks(page),
    };
    await snapshot(page,'01-home');

    out.stages.shows_click = await clickShows(page);
    await page.waitForTimeout(2000);
    text = await bodyText(page);
    out.stages.shows = { url: page.url(), ...(await challengeState(page, text)), body: text.substring(0,2500), links: await pageLinks(page) };
    await snapshot(page,'02-shows');

    out.stages.past_click = await clickPast(page);
    await page.waitForTimeout(2000);
    text = await bodyText(page);
    out.stages.past = { url: page.url(), ...(await challengeState(page, text)), body: text.substring(0,3500), links: await pageLinks(page) };
    await snapshot(page,'03-past');

    out.stages.show_row = await findShowContainer(page);

    if (out.stages.show_row) {
      out.stages.analytics_click = await clickRowAction(page, 'See Analytics');
      if (out.stages.analytics_click.clicked) {
        out.stages.analytics = await extractAnalytics(page);
        await snapshot(page,'04-analytics');
      }

      const backToShows = await clickShows(page);
      out.stages.back_to_shows = backToShows;
      if (backToShows.clicked) {
        out.stages.back_to_past = await clickPast(page);
        out.stages.shipments_click = await clickRowAction(page, 'View Shipments');
        if (out.stages.shipments_click.clicked) {
          const shipText = await bodyText(page);
          out.stages.shipments = {
            url: page.url(),
            ...(await challengeState(page, shipText)),
            body: shipText.substring(0,5000),
            row_count: await page.locator('tr[data-testid^="shipments-"]').count().catch(() => 0),
          };
          await snapshot(page,'05-shipments');
        }
      }
    }

    out.operations = [...new Set(operations)];
    process.stdout.write(JSON.stringify(out, null, 2) + '\n');
  } finally {
    await context.close().catch(() => {});
  }
})().catch(err => { console.error(err && err.stack ? err.stack : String(err)); process.exit(1); });
