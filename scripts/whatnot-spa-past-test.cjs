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

function challenged(text) {
  return /Performing security verification|protect against malicious bots|Ray ID:|cf_chl/i.test(text || '');
}

async function snapshot(page, name) {
  if (!DEBUG) return;
  try { await page.screenshot({ path: `/tmp/whatnot-spa-${name}.png`, fullPage: false }); } catch {}
}

async function bodyText(page) {
  return await page.locator('body').innerText().catch(() => '');
}

async function clickShows(page) {
  const choices = [
    page.locator('a[href="/dashboard/lives"]'),
    page.locator('a[href*="/dashboard/lives"]'),
    page.getByRole('link', { name: /^Shows$/i }),
    page.getByText(/^Shows$/i),
  ];
  for (const loc of choices) {
    const el = loc.first();
    if (await el.count().catch(() => 0)) {
      await el.click({ timeout: 8000 }).catch(() => null);
      await page.waitForTimeout(4000);
      return true;
    }
  }
  return false;
}

async function clickPast(page) {
  const choices = [
    page.getByRole('tab', { name: /^Past$/i }),
    page.getByRole('button', { name: /^Past$/i }),
    page.getByText(/^Past$/i),
  ];
  for (const loc of choices) {
    const el = loc.first();
    if (await el.count().catch(() => 0)) {
      await el.click({ timeout: 8000 }).catch(() => null);
      await page.waitForTimeout(4000);
      return true;
    }
  }
  return false;
}

async function findShowContainer(page) {
  const anchor = page.locator(`a[href*="${LIVE_ID}"]`).first();
  if (await anchor.count().catch(() => 0)) {
    const handle = await anchor.elementHandle();
    if (handle) {
      const info = await handle.evaluate((el) => {
        let n = el;
        for (let i=0;i<10 && n;i++,n=n.parentElement) {
          const text = (n.innerText || '').trim();
          if (n.tagName === 'TR' || /See Analytics|View Shipments|Open show/i.test(text)) {
            return {
              text: text.substring(0,2000),
              html: n.outerHTML.substring(0,20000),
              links: [...n.querySelectorAll('a')].map(a => ({ text:(a.innerText||'').trim(), href:a.href })),
              buttons: [...n.querySelectorAll('button')].map(b => ({ text:(b.innerText||'').trim(), aria:b.getAttribute('aria-label') })),
            };
          }
        }
        return null;
      });
      return info;
    }
  }
  return null;
}

async function extractAnalytics(page) {
  const text = await bodyText(page);
  const labels = ['Estimated Sales','Total Estimated Earnings','Completed Earnings','Orders','AOV','Average Order Value','Giveaway Spend','Buyers','Shares','Duration','Max Concurrent Viewers','Total Views'];
  const lines = text.split('\n').map(s=>s.trim()).filter(Boolean);
  const metrics = {};
  for (const label of labels) {
    const idx = lines.findIndex(l => l.toLowerCase() === label.toLowerCase());
    if (idx >= 0) metrics[label] = lines[idx+1] || null;
  }
  return { challenged: challenged(text), url: page.url(), metrics, body: text.substring(0,3000) };
}

async function clickRowAction(page, actionText) {
  const anchor = page.locator(`a[href*="${LIVE_ID}"]`).first();
  if (!(await anchor.count().catch(() => 0))) return false;
  const container = anchor.locator('xpath=ancestor-or-self::*[self::tr or self::li or self::div][.//*[contains(normalize-space(), "'+actionText+'")] ]').first();
  const action = container.getByText(new RegExp('^'+actionText+'$','i')).first();
  if (await action.count().catch(() => 0)) {
    await action.click({ timeout: 8000 }).catch(() => null);
    await page.waitForTimeout(5000);
    return true;
  }
  const broad = page.getByText(new RegExp('^'+actionText+'$','i')).first();
  if (await broad.count().catch(() => 0)) {
    await broad.click({ timeout: 8000 }).catch(() => null);
    await page.waitForTimeout(5000);
    return true;
  }
  return false;
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
    await page.waitForTimeout(5000);
    let text = await bodyText(page);
    out.stages.home = { status: resp ? resp.status() : null, url: page.url(), challenged: challenged(text) };
    await snapshot(page,'01-home');

    out.stages.shows_click = { clicked: await clickShows(page) };
    text = await bodyText(page);
    out.stages.shows = { url: page.url(), challenged: challenged(text), body: text.substring(0,1500) };
    await snapshot(page,'02-shows');

    out.stages.past_click = { clicked: await clickPast(page) };
    text = await bodyText(page);
    out.stages.past = { url: page.url(), challenged: challenged(text), body: text.substring(0,2000) };
    await snapshot(page,'03-past');

    out.stages.show_row = await findShowContainer(page);

    if (out.stages.show_row) {
      out.stages.analytics_click = { clicked: await clickRowAction(page, 'See Analytics') };
      if (out.stages.analytics_click.clicked) {
        out.stages.analytics = await extractAnalytics(page);
        await snapshot(page,'04-analytics');
      }

      if (await clickShows(page)) {
        await clickPast(page);
        out.stages.shipments_click = { clicked: await clickRowAction(page, 'View Shipments') };
        if (out.stages.shipments_click.clicked) {
          const shipText = await bodyText(page);
          out.stages.shipments = {
            url: page.url(),
            challenged: challenged(shipText),
            body: shipText.substring(0,4000),
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
