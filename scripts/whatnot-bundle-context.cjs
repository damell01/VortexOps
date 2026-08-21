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

const chunk = path.basename(process.argv[2] || '');
const needle = process.argv[3] || '';
const before = Math.max(0, parseInt(process.argv[4] || '8000', 10));
const after = Math.max(0, parseInt(process.argv[5] || '16000', 10));

if (!chunk.endsWith('.js') || !needle) {
  console.error('Usage: node whatnot-bundle-context.cjs <chunk.js> <needle> [before] [after]');
  process.exit(2);
}

function findChromium() {
  const explicit = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
  if (explicit && fs.existsSync(explicit)) return explicit;

  const marker = path.join(__dirname, '../storage/chromium-path.txt');
  try {
    const p = fs.readFileSync(marker, 'utf8').trim();
    if (p && fs.existsSync(p)) return p;
  } catch {}

  try {
    const p = chromium.executablePath();
    if (p && fs.existsSync(p)) return p;
  } catch {}

  for (const p of ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome']) {
    if (fs.existsSync(p)) return p;
  }
  return undefined;
}

(async () => {
  const userDataDir = process.env.WHATNOT_USER_DATA_DIR || path.join(__dirname, '../storage/whatnot-browser-profile');
  const executablePath = findChromium();

  const context = await chromium.launchPersistentContext(userDataDir, {
    headless: process.env.WHATNOT_HEADLESS !== 'false',
    executablePath,
    args: ['--no-sandbox', '--no-zygote', '--disable-dev-shm-usage', '--disable-gpu'],
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
  });

  try {
    const candidates = [
      `https://www.whatnot.com/_next/static/chunks/${chunk}`,
      `https://www.whatnot.com/_next/static/chunks/app/${chunk}`,
    ];

    let body = null;
    let usedUrl = null;
    let statuses = [];

    for (const url of candidates) {
      const response = await context.request.get(url).catch(() => null);
      statuses.push(`${response ? response.status() : 'ERR'} ${url}`);
      if (!response || !response.ok()) continue;
      const text = await response.text().catch(() => '');
      if (text.length < 100) continue;
      body = text;
      usedUrl = url;
      break;
    }

    if (!body) {
      console.error('Could not fetch chunk through Playwright session. Tried:\n' + statuses.join('\n'));
      process.exitCode = 1;
      return;
    }

    const offset = body.indexOf(needle);
    if (offset < 0) {
      console.error(`Needle "${needle}" not found in ${usedUrl}`);
      process.exitCode = 1;
      return;
    }

    const start = Math.max(0, offset - before);
    const end = Math.min(body.length, offset + needle.length + after);
    process.stdout.write(JSON.stringify({
      url: usedUrl,
      bundle_bytes: body.length,
      match_offset: offset,
      context: body.slice(start, end),
    }));
  } finally {
    await context.close().catch(() => {});
  }
})().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err));
  process.exit(1);
});
