'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { spawn } = require('child_process');

const VERSION = '1.1.0';
const ROOT = __dirname;
const CONFIG_PATH = path.join(ROOT, 'config.json');
const EXAMPLE_PATH = path.join(ROOT, 'config.example.json');
const SCRAPER_PATH = path.resolve(ROOT, '..', 'scripts', 'whatnot-scraper.cjs');
const APP_DIR = process.env.LOCALAPPDATA
  ? path.join(process.env.LOCALAPPDATA, 'VortexOps', 'WhatnotCollector')
  : path.join(os.homedir(), '.vortexops', 'whatnot-collector');
const DEFAULT_PROFILE = path.join(APP_DIR, 'ChromeProfile');
const TEMP_DIR = path.join(APP_DIR, 'tmp');
const LOCK_FILE = path.join(APP_DIR, 'collector.lock');

// The shared scraper requires a cookie-file path in order to take its existing
// session-first auth branch. For the desktop collector the Chrome profile is the
// canonical session, so this file intentionally contains no cookies. Naming it
// like the scraper's own live snapshot also prevents the scraper from treating
// the dedicated desktop profile's Cloudflare cookies as foreign imported state.
const SESSION_MARKER = path.join(APP_DIR, 'whatnot-live-cookies.json');

function die(message) {
  console.error('\nERROR: ' + message + '\n');
  process.exit(1);
}

function detectChrome() {
  const candidates = [
    process.env.PROGRAMFILES && path.join(process.env.PROGRAMFILES, 'Google', 'Chrome', 'Application', 'chrome.exe'),
    process.env['PROGRAMFILES(X86)'] && path.join(process.env['PROGRAMFILES(X86)'], 'Google', 'Chrome', 'Application', 'chrome.exe'),
    process.env.LOCALAPPDATA && path.join(process.env.LOCALAPPDATA, 'Google', 'Chrome', 'Application', 'chrome.exe'),
  ].filter(Boolean);
  return candidates.find((candidate) => fs.existsSync(candidate)) || '';
}

function loadConfig() {
  if (!fs.existsSync(CONFIG_PATH)) {
    if (fs.existsSync(EXAMPLE_PATH)) fs.copyFileSync(EXAMPLE_PATH, CONFIG_PATH);
    die('config.json was created. Open it, set api_url and api_token, then run Sync Whatnot again.');
  }

  let config;
  try { config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8')); }
  catch (e) { die('config.json is not valid JSON: ' + e.message); }

  config.api_url = String(config.api_url || '').replace(/\/+$/, '');
  config.api_token = String(config.api_token || '').trim();
  config.show_limit = Math.max(1, Math.min(500, Number(config.show_limit || 50)));
  config.order_batch_size = Math.max(1, Math.min(25, Number(config.order_batch_size || 10)));
  config.ledger_days = Math.max(1, Math.min(3650, Number(config.ledger_days || 31)));
  config.full_history_max_batches = Math.max(1, Math.min(50, Number(config.full_history_max_batches || 10)));
  config.historical_ledger_from = String(config.historical_ledger_from || '').trim();
  config.channels = Array.isArray(config.channels) ? config.channels.map(String) : [];
  config.profile_dir = config.profile_dir ? path.resolve(config.profile_dir) : DEFAULT_PROFILE;
  config.chrome_path = config.chrome_path ? path.resolve(config.chrome_path) : detectChrome();
  config.headless = config.headless !== false;

  if (!/^https?:\/\//i.test(config.api_url) || /YOUR-VORTEXOPS-DOMAIN/i.test(config.api_url)) {
    die('Set api_url in desktop-collector/config.json to your VortexOps API base URL, for example https://example.com/api');
  }
  if (!config.api_token || config.api_token.includes('PASTE_SCRAPER_API_TOKEN')) {
    die('Set api_token in desktop-collector/config.json to the same SCRAPER_API_TOKEN configured in VortexOps.');
  }
  if (!config.chrome_path || !fs.existsSync(config.chrome_path)) {
    die('Google Chrome was not found. Set chrome_path in config.json to chrome.exe.');
  }
  if (!fs.existsSync(SCRAPER_PATH)) {
    die('Shared scraper not found at ' + SCRAPER_PATH + '. Keep desktop-collector inside the VortexOps project folder.');
  }
  return config;
}

function processAlive(pid) {
  if (!Number.isInteger(pid) || pid <= 0) return false;
  try { process.kill(pid, 0); return true; }
  catch (e) { return e.code === 'EPERM'; }
}

function acquireLock() {
  fs.mkdirSync(APP_DIR, { recursive: true });
  if (fs.existsSync(LOCK_FILE)) {
    let existing = null;
    try { existing = JSON.parse(fs.readFileSync(LOCK_FILE, 'utf8')); } catch {}
    if (existing?.pid && processAlive(Number(existing.pid))) {
      die(`Another VortexOps Whatnot collector is already running (PID ${existing.pid}).`);
    }
    fs.rmSync(LOCK_FILE, { force: true });
  }

  try {
    fs.writeFileSync(LOCK_FILE, JSON.stringify({ pid: process.pid, started_at: new Date().toISOString() }), { flag: 'wx' });
  } catch {
    die('Could not acquire the desktop collector lock. Another sync may be starting right now.');
  }
}

function releaseLock() {
  try {
    const current = JSON.parse(fs.readFileSync(LOCK_FILE, 'utf8'));
    if (Number(current?.pid) === process.pid) fs.rmSync(LOCK_FILE, { force: true });
  } catch {}
}

async function api(config, method, endpoint, body) {
  const response = await fetch(config.api_url + endpoint, {
    method,
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${config.api_token}`,
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const text = await response.text();
  let json = null;
  try { json = text ? JSON.parse(text) : null; } catch {}
  if (!response.ok) {
    const detail = json?.message || json?.error || text.substring(0, 500) || `HTTP ${response.status}`;
    throw new Error(`VortexOps API ${response.status}: ${detail}`);
  }
  return json;
}

function baseEnv(config, mode, channel, extras = {}) {
  fs.mkdirSync(config.profile_dir, { recursive: true });
  fs.mkdirSync(TEMP_DIR, { recursive: true });
  if (!fs.existsSync(SESSION_MARKER)) fs.writeFileSync(SESSION_MARKER, '[]\n');

  return {
    ...process.env,
    PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH: config.chrome_path,
    WHATNOT_USER_DATA_DIR: config.profile_dir,
    WHATNOT_BROWSER_HOME: path.join(APP_DIR, 'BrowserHome'),
    WHATNOT_COOKIES_FILE: SESSION_MARKER,
    WHATNOT_BROWSER_BACKEND: 'local',
    WHATNOT_HEADLESS: config.headless ? 'true' : 'false',
    // The shared scraper's headed-mode display guard is for Linux/X11. Chrome
    // on Windows does not use DISPLAY; a harmless marker lets its native headed
    // launch path run when the operator explicitly sets headless=false.
    ...(process.platform === 'win32' && !config.headless ? { DISPLAY: 'windows-desktop' } : {}),
    WHATNOT_MODE: mode,
    WHATNOT_CHANNEL_NAME: channel || '',
    WHATNOT_DEBUG: '0',
    ...extras,
  };
}

async function runScraper(config, mode, channel, extras = {}, timeoutMs = 30 * 60 * 1000) {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [SCRAPER_PATH], {
      cwd: path.dirname(SCRAPER_PATH),
      env: baseEnv(config, mode, channel, extras),
      windowsHide: config.headless,
    });

    const stdout = [];
    const stderr = [];
    let stdoutBytes = 0;
    const MAX_OUTPUT = 80 * 1024 * 1024;
    let settled = false;

    const fail = (error) => {
      if (settled) return;
      settled = true;
      reject(error);
    };

    child.stdout.on('data', (chunk) => {
      stdoutBytes += chunk.length;
      if (stdoutBytes > MAX_OUTPUT) {
        child.kill();
        fail(new Error(`${mode} produced more than 80 MB of output; reduce show_limit/order_batch_size.`));
        return;
      }
      stdout.push(chunk);
    });

    child.stderr.on('data', (chunk) => {
      stderr.push(chunk);
      for (const line of chunk.toString().split(/\r?\n/)) {
        if (/CHANNEL_CONTEXT_VERIFIED|show \d+:|orders-batch|shipments-batch|ledger/i.test(line)) {
          process.stdout.write('    ' + line.trim() + '\n');
        }
      }
    });

    const timer = setTimeout(() => {
      child.kill();
      fail(new Error(`${mode} timed out after ${Math.round(timeoutMs / 60000)} minutes.`));
    }, timeoutMs);

    child.on('error', (err) => { clearTimeout(timer); fail(err); });
    child.on('close', (code) => {
      clearTimeout(timer);
      if (settled) return;

      const out = Buffer.concat(stdout).toString('utf8').trim();
      const err = Buffer.concat(stderr).toString('utf8').trim();
      const verification = [...err.matchAll(/CHANNEL_CONTEXT_VERIFIED\s+requested=@([^\s]+)\s+active=@([^\s]+)/g)].pop();
      const verified = verification ? verification[2] : null;

      if (code !== 0) {
        const loginRequired = /redirected to login|cookies are missing, expired, or invalid|Session cookies are expired|AUTH_REQUIRED/i.test(err);
        const message = loginRequired
          ? 'LOGIN_REQUIRED: The dedicated Whatnot session is not signed in. Run "Login to Whatnot.bat", sign in, close Chrome, then sync again.'
          : (err.split(/\r?\n/).slice(-15).join('\n') || `${mode} exited with code ${code}`);
        fail(Object.assign(new Error(message), { code, stderr: err, verified }));
        return;
      }

      let data;
      try { data = out ? JSON.parse(out) : []; }
      catch (e) { fail(new Error(`${mode} returned invalid JSON: ${e.message}\n${out.substring(0, 500)}`)); return; }

      settled = true;
      resolve({ data, stderr: err, verified });
    });
  });
}

function liveIdFromShow(show) {
  const value = String(show?.whatnot_live_id || show?.whatnot_show_id || show?.detail_url || '');
  const match = value.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
  return match ? match[0].toLowerCase() : null;
}

function chunk(items, size) {
  const result = [];
  for (let i = 0; i < items.length; i += size) result.push(items.slice(i, i + size));
  return result;
}

function mapBatchOutput(output) {
  const map = {};
  for (const entry of Array.isArray(output) ? output : []) {
    if (entry?.show_key == null) continue;
    map[String(entry.show_key)] = Array.isArray(entry.orders) ? entry.orders : [];
  }
  return map;
}

function dateOnly(date) { return date.toISOString().slice(0, 10); }

function parseDate(value) {
  if (!value) return null;
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? null : d;
}

async function upload(config, runId, channel, verified, payload, componentStatus = {}) {
  if (!verified) {
    throw new Error(`CHANNEL_CONTEXT_MISMATCH: no positive seller verification was captured for @${channel.whatnot_username}; refusing upload.`);
  }

  const body = {
    collector_run_id: runId,
    collector_version: VERSION,
    computer_name: os.hostname(),
    requested_channel_username: channel.whatnot_username,
    verified_channel_username: verified,
    shows: payload.shows || [],
    orders_by_live_id: payload.orders_by_live_id || {},
    shipments_by_live_id: payload.shipments_by_live_id || {},
    ledger: payload.ledger || [],
    component_status: componentStatus,
  };

  const bytes = Buffer.byteLength(JSON.stringify(body));
  if (bytes > 6_500_000) {
    throw new Error(`Upload bundle is ${(bytes / 1024 / 1024).toFixed(1)} MB. Reduce order_batch_size or show_limit.`);
  }
  return api(config, 'POST', '/whatnot/collector/import', body);
}

async function collectAnalytics(config, channel, fullHistory) {
  const all = [];
  const seen = new Set();
  let verified = null;
  let seed = channel.latest_live_id || '';
  const iterations = fullHistory ? config.full_history_max_batches : 1;
  const limit = fullHistory ? 500 : config.show_limit;

  for (let batch = 1; batch <= iterations; batch++) {
    console.log(fullHistory ? `    Analytics history batch ${batch}/${iterations}` : '    Refreshing recent shows');
    const result = await runScraper(config, 'analytics', channel.whatnot_username, {
      WHATNOT_LIMIT: String(limit),
      ...(seed ? { WHATNOT_START_UUID: seed } : {}),
    }, fullHistory ? 4 * 60 * 60 * 1000 : 60 * 60 * 1000);

    verified = result.verified || verified;
    const rows = Array.isArray(result.data) ? result.data : [];
    let added = 0;
    for (const row of rows) {
      const id = liveIdFromShow(row);
      const key = id || `${row?.title || ''}|${row?.show_date || row?.show_date_raw || ''}`;
      if (!key || seen.has(key)) continue;
      seen.add(key);
      all.push(row);
      added++;
    }

    if (!fullHistory || rows.length < limit || added === 0) break;
    const nextSeed = [...rows].reverse().map(liveIdFromShow).find(Boolean);
    if (!nextSeed || nextSeed === seed) break;
    seed = nextSeed;
  }

  return { data: all, verified };
}

async function collectOrdersAndShipments(config, channel, runId, verified, shows) {
  const sources = shows
    .map((show) => {
      const liveId = liveIdFromShow(show);
      return liveId ? { live_id: liveId, show_key: liveId } : null;
    })
    .filter(Boolean);

  console.log(`  [2/4] Orders + [3/4] Shipments (${sources.length} show source(s))`);
  let orderCreated = 0;
  let orderUpdated = 0;
  let shipmentCreated = 0;
  let shipmentUpdated = 0;
  let failedBatches = 0;
  let batchNumber = 0;

  for (const batch of chunk(sources, config.order_batch_size)) {
    batchNumber++;
    console.log(`    Batch ${batchNumber}/${Math.ceil(sources.length / config.order_batch_size)}`);
    const sourceFile = path.join(TEMP_DIR, `sources-${process.pid}-${Date.now()}-${batchNumber}.json`);
    fs.writeFileSync(sourceFile, JSON.stringify(batch));

    let orders = null;
    let shipments = null;
    const batchStatus = {};
    try {
      orders = await runScraper(config, 'orders-batch', channel.whatnot_username, {
        WHATNOT_ORDER_SOURCES_FILE: sourceFile,
      }, 2 * 60 * 60 * 1000);
      batchStatus.orders = { ok: true };
    } catch (e) {
      failedBatches++;
      batchStatus.orders = { ok: false, error: e.message.substring(0, 1000) };
      console.warn(`      Orders failed: ${e.message.split('\n')[0]}`);
    }

    try {
      shipments = await runScraper(config, 'shipments-batch', channel.whatnot_username, {
        WHATNOT_ORDER_SOURCES_FILE: sourceFile,
      }, 2 * 60 * 60 * 1000);
      batchStatus.shipments = { ok: true };
    } catch (e) {
      failedBatches++;
      batchStatus.shipments = { ok: false, error: e.message.substring(0, 1000) };
      console.warn(`      Shipments failed: ${e.message.split('\n')[0]}`);
    } finally {
      fs.rmSync(sourceFile, { force: true });
    }

    if (orders || shipments) {
      const response = await upload(config, runId, channel, verified, {
        orders_by_live_id: orders ? mapBatchOutput(orders.data) : {},
        shipments_by_live_id: shipments ? mapBatchOutput(shipments.data) : {},
      }, batchStatus);
      orderCreated += response?.result?.orders?.created || 0;
      orderUpdated += response?.result?.orders?.updated || 0;
      shipmentCreated += response?.result?.shipments?.created || 0;
      shipmentUpdated += response?.result?.shipments?.updated || 0;
    }
  }

  return { orderCreated, orderUpdated, shipmentCreated, shipmentUpdated, failedBatches };
}

async function collectLedger(config, channel, runId, verified, shows, fullHistory) {
  const today = new Date();
  let from;

  if (fullHistory) {
    from = parseDate(config.historical_ledger_from);
    if (!from) {
      const dates = shows.map((s) => parseDate(s?.show_date || s?.show_date_raw)).filter(Boolean).sort((a, b) => a - b);
      from = dates[0] || parseDate(channel.earliest_show_date);
    }
    if (!from) {
      throw new Error('Full-history ledger needs historical_ledger_from in config.json because no earliest show date could be determined.');
    }
  } else {
    from = new Date(today.getTime() - (config.ledger_days - 1) * 86400000);
  }

  // Whatnot accepts a maximum 31-day ledger window. Split longer recurring or
  // historical ranges exactly like the existing server-side importLedger().
  let cursor = new Date(Date.UTC(from.getUTCFullYear(), from.getUTCMonth(), from.getUTCDate()));
  const end = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));
  let totalRows = 0;
  let created = 0;
  let skipped = 0;
  let windows = 0;

  while (cursor <= end) {
    const windowEnd = new Date(Math.min(end.getTime(), cursor.getTime() + 30 * 86400000));
    windows++;
    console.log(`    Ledger ${dateOnly(cursor)} through ${dateOnly(windowEnd)}`);

    const ledger = await runScraper(config, 'ledger', channel.whatnot_username, {
      WHATNOT_LEDGER_FROM: dateOnly(cursor),
      WHATNOT_LEDGER_TO: dateOnly(windowEnd),
    }, 60 * 60 * 1000);

    const rows = Array.isArray(ledger.data) ? ledger.data : [];
    totalRows += rows.length;
    const response = await upload(config, runId, channel, ledger.verified || verified, { ledger: rows }, {
      ledger: { ok: true, from: dateOnly(cursor), to: dateOnly(windowEnd), count: rows.length },
    });
    created += response?.result?.ledger?.created || 0;
    skipped += response?.result?.ledger?.skipped || 0;
    cursor = new Date(windowEnd.getTime() + 86400000);
  }

  return { totalRows, created, skipped, windows };
}

async function syncChannel(config, channel, runId, fullHistory) {
  console.log('\n============================================================');
  console.log(`${channel.name} (@${channel.whatnot_username})${fullHistory ? ' — FULL HISTORY' : ''}`);
  console.log('============================================================');

  console.log('  [1/4] Shows + analytics');
  const analytics = await collectAnalytics(config, channel, fullHistory);
  if (!analytics.verified) {
    throw new Error(`CHANNEL_CONTEXT_MISMATCH: scraper never positively verified @${channel.whatnot_username}.`);
  }

  const shows = analytics.data;
  const showUpload = await upload(config, runId, channel, analytics.verified, { shows }, {
    analytics: { ok: true, count: shows.length, full_history: fullHistory },
  });
  console.log(`    ${shows.length} show(s): ${showUpload?.result?.shows?.created || 0} created, ${showUpload?.result?.shows?.updated || 0} updated`);

  const orderStats = await collectOrdersAndShipments(config, channel, runId, analytics.verified, shows);
  console.log(`    Orders: ${orderStats.orderCreated} created, ${orderStats.orderUpdated} updated`);
  console.log(`    Shipments: ${orderStats.shipmentCreated} created, ${orderStats.shipmentUpdated} updated`);

  console.log(`  [4/4] Ledger${fullHistory ? ' — historical' : ` — last ${config.ledger_days} day(s)`}`);
  let ledgerStats;
  try {
    ledgerStats = await collectLedger(config, channel, runId, analytics.verified, shows, fullHistory);
    console.log(`    Ledger: ${ledgerStats.created} created, ${ledgerStats.skipped} existing across ${ledgerStats.windows} window(s)`);
  } catch (e) {
    ledgerStats = { error: e.message };
    console.warn(`    Ledger failed: ${e.message.split('\n')[0]}`);
  }

  return { channel: channel.whatnot_username, shows: shows.length, orders_shipments: orderStats, ledger: ledgerStats };
}

async function main() {
  console.log('============================================================');
  console.log(` VortexOps Whatnot Desktop Collector v${VERSION}`);
  console.log('============================================================');

  if (Number(process.versions.node.split('.')[0]) < 18) die('Node.js 18 or newer is required.');

  const fullHistory = process.argv.includes('--full');
  const config = loadConfig();
  fs.mkdirSync(APP_DIR, { recursive: true });
  fs.mkdirSync(TEMP_DIR, { recursive: true });
  acquireLock();

  try {
    console.log(`Mode: ${fullHistory ? 'FULL HISTORICAL SYNC' : 'incremental sync'}`);
    console.log(`Profile: ${config.profile_dir}`);
    console.log(`Browser: ${config.headless ? 'background/headless' : 'visible dedicated Chrome'}`);
    console.log('Connecting to VortexOps...');

    const bootstrap = await api(config, 'GET', '/whatnot/collector/bootstrap');
    let channels = Array.isArray(bootstrap?.channels) ? bootstrap.channels : [];
    if (config.channels.length) {
      const wanted = new Set(config.channels.map((c) => c.toLowerCase().replace(/[^a-z0-9]/g, '')));
      channels = channels.filter((channel) => wanted.has(String(channel.whatnot_username || '').toLowerCase().replace(/[^a-z0-9]/g, '')));
    }
    if (!channels.length) throw new Error('VortexOps returned no active Whatnot channels to collect.');

    console.log(`Channels: ${channels.map((c) => c.name).join(', ')}`);
    const runId = `${Date.now()}-${crypto.randomUUID()}`;
    let completed = 0;
    let failed = 0;

    for (const channel of channels) {
      try {
        await syncChannel(config, channel, runId, fullHistory);
        completed++;
      } catch (e) {
        failed++;
        console.error(`\n  FAILED ${channel.name}: ${e.message}`);
        if (/LOGIN_REQUIRED/.test(e.message)) {
          console.error('\n  Run "Login to Whatnot.bat", sign in to the dedicated Chrome profile, close Chrome, and run sync again.');
          break;
        }
      }
    }

    console.log('\n============================================================');
    console.log(` Sync finished: ${completed} channel(s) completed, ${failed} failed`);
    console.log('============================================================');
    if (failed) process.exitCode = 1;
  } finally {
    releaseLock();
  }
}

main().catch((e) => {
  releaseLock();
  die(e.stack || e.message);
});
