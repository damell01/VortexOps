'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { spawn } = require('child_process');

const VERSION = '1.0.0';
const ROOT = __dirname;
const CONFIG_PATH = path.join(ROOT, 'config.json');
const EXAMPLE_PATH = path.join(ROOT, 'config.example.json');
const SCRAPER_PATH = path.resolve(ROOT, '..', 'scripts', 'whatnot-scraper.cjs');
const APP_DIR = process.env.LOCALAPPDATA
  ? path.join(process.env.LOCALAPPDATA, 'VortexOps', 'WhatnotCollector')
  : path.join(os.homedir(), '.vortexops', 'whatnot-collector');
const DEFAULT_PROFILE = path.join(APP_DIR, 'ChromeProfile');
const SESSION_MARKER = path.join(APP_DIR, 'session-marker.json');
const TEMP_DIR = path.join(APP_DIR, 'tmp');

function die(message) {
  console.error('\nERROR: ' + message + '\n');
  process.exit(1);
}

function loadConfig() {
  if (!fs.existsSync(CONFIG_PATH)) {
    if (fs.existsSync(EXAMPLE_PATH)) fs.copyFileSync(EXAMPLE_PATH, CONFIG_PATH);
    die('config.json was created. Open it, set api_url and api_token, then run Sync Whatnot again.');
  }

  let config;
  try {
    config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
  } catch (e) {
    die('config.json is not valid JSON: ' + e.message);
  }

  config.api_url = String(config.api_url || '').replace(/\/+$/, '');
  config.api_token = String(config.api_token || '').trim();
  config.show_limit = Math.max(1, Math.min(500, Number(config.show_limit || 50)));
  config.order_batch_size = Math.max(1, Math.min(25, Number(config.order_batch_size || 10)));
  config.ledger_days = Math.max(1, Math.min(3650, Number(config.ledger_days || 31)));
  config.channels = Array.isArray(config.channels) ? config.channels.map(String) : [];
  config.profile_dir = config.profile_dir ? path.resolve(config.profile_dir) : DEFAULT_PROFILE;
  config.chrome_path = config.chrome_path ? path.resolve(config.chrome_path) : detectChrome();

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

function detectChrome() {
  const candidates = [
    process.env.PROGRAMFILES && path.join(process.env.PROGRAMFILES, 'Google', 'Chrome', 'Application', 'chrome.exe'),
    process.env['PROGRAMFILES(X86)'] && path.join(process.env['PROGRAMFILES(X86)'], 'Google', 'Chrome', 'Application', 'chrome.exe'),
    process.env.LOCALAPPDATA && path.join(process.env.LOCALAPPDATA, 'Google', 'Chrome', 'Application', 'chrome.exe'),
  ].filter(Boolean);
  return candidates.find((candidate) => fs.existsSync(candidate)) || '';
}

async function api(config, method, endpoint, body) {
  const response = await fetch(config.api_url + endpoint, {
    method,
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${config.api_token}`,
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
    WHATNOT_HEADLESS: 'true',
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
      windowsHide: true,
    });

    const stdout = [];
    const stderr = [];
    let stdoutBytes = 0;
    const MAX_OUTPUT = 80 * 1024 * 1024;

    child.stdout.on('data', (chunk) => {
      stdoutBytes += chunk.length;
      if (stdoutBytes > MAX_OUTPUT) {
        child.kill();
        reject(new Error(`${mode} produced more than 80 MB of output; reduce show_limit/order_batch_size.`));
        return;
      }
      stdout.push(chunk);
    });
    child.stderr.on('data', (chunk) => {
      stderr.push(chunk);
      const text = chunk.toString();
      for (const line of text.split(/\r?\n/)) {
        if (/CHANNEL_CONTEXT_VERIFIED|Fetched|show \d+:|orders|shipments|ledger/i.test(line)) {
          process.stdout.write('    ' + line.trim() + '\n');
        }
      }
    });

    const timer = setTimeout(() => {
      child.kill();
      reject(new Error(`${mode} timed out after ${Math.round(timeoutMs / 60000)} minutes.`));
    }, timeoutMs);

    child.on('error', (err) => {
      clearTimeout(timer);
      reject(err);
    });

    child.on('close', (code) => {
      clearTimeout(timer);
      const out = Buffer.concat(stdout).toString('utf8').trim();
      const err = Buffer.concat(stderr).toString('utf8').trim();
      const verification = [...err.matchAll(/CHANNEL_CONTEXT_VERIFIED\s+requested=@([^\s]+)\s+active=@([^\s]+)/g)].pop();
      const verified = verification ? verification[2] : null;

      if (code !== 0) {
        const loginRequired = /redirected to login|cookies are missing, expired, or invalid|Session cookies are expired|AUTH_REQUIRED/i.test(err);
        const message = loginRequired
          ? 'LOGIN_REQUIRED: The dedicated Whatnot session is not signed in. Run "Login to Whatnot.bat", sign in, close Chrome, then sync again.'
          : (err.split(/\r?\n/).slice(-15).join('\n') || `${mode} exited with code ${code}`);
        reject(Object.assign(new Error(message), { code, stderr: err, verified }));
        return;
      }

      let data = null;
      try { data = out ? JSON.parse(out) : []; }
      catch (e) {
        reject(new Error(`${mode} returned invalid JSON: ${e.message}\n${out.substring(0, 500)}`));
        return;
      }

      resolve({ data, stderr: err, verified });
    });
  });
}

function liveIdFromShow(show) {
  const value = String(show.whatnot_live_id || show.whatnot_show_id || show.detail_url || '');
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

function dateOnly(date) {
  return date.toISOString().slice(0, 10);
}

async function upload(config, runId, channel, verified, payload, componentStatus) {
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
    component_status: componentStatus || {},
  };

  const bytes = Buffer.byteLength(JSON.stringify(body));
  if (bytes > 6_500_000) {
    throw new Error(`Upload bundle is ${(bytes / 1024 / 1024).toFixed(1)} MB. Reduce order_batch_size or show_limit.`);
  }
  return api(config, 'POST', '/whatnot/collector/import', body);
}

async function syncChannel(config, channel, runId) {
  console.log(`\n============================================================`);
  console.log(`${channel.name} (@${channel.whatnot_username})`);
  console.log(`============================================================`);

  const status = {};
  let analytics;
  try {
    console.log('  [1/4] Shows + analytics');
    analytics = await runScraper(config, 'analytics', channel.whatnot_username, {
      WHATNOT_LIMIT: String(config.show_limit),
      ...(channel.latest_live_id ? { WHATNOT_START_UUID: channel.latest_live_id } : {}),
    });
    status.analytics = { ok: true, count: Array.isArray(analytics.data) ? analytics.data.length : 0 };
  } catch (e) {
    status.analytics = { ok: false, error: e.message.substring(0, 1000) };
    throw e;
  }

  const verified = analytics.verified;
  const shows = Array.isArray(analytics.data) ? analytics.data : [];
  const showUpload = await upload(config, runId, channel, verified, { shows }, status);
  console.log(`    Uploaded shows: ${showUpload?.result?.shows?.created || 0} created, ${showUpload?.result?.shows?.updated || 0} updated`);

  const sources = shows
    .map((show) => {
      const liveId = liveIdFromShow(show);
      return liveId ? { live_id: liveId, show_key: liveId } : null;
    })
    .filter(Boolean);

  console.log(`  [2/4] Orders (${sources.length} show source(s))`);
  console.log(`  [3/4] Shipments`);
  let orderCreated = 0;
  let shipmentCreated = 0;
  let batchNumber = 0;

  for (const batch of chunk(sources, config.order_batch_size)) {
    batchNumber++;
    const sourceFile = path.join(TEMP_DIR, `sources-${process.pid}-${Date.now()}-${batchNumber}.json`);
    fs.writeFileSync(sourceFile, JSON.stringify(batch));

    let orders = null;
    let shipments = null;
    const batchStatus = {};
    try {
      orders = await runScraper(config, 'orders-batch', channel.whatnot_username, {
        WHATNOT_ORDER_SOURCES_FILE: sourceFile,
      });
      batchStatus.orders = { ok: true };
    } catch (e) {
      batchStatus.orders = { ok: false, error: e.message.substring(0, 1000) };
      console.warn(`    Orders batch ${batchNumber} failed: ${e.message.split('\n')[0]}`);
    }

    try {
      shipments = await runScraper(config, 'shipments-batch', channel.whatnot_username, {
        WHATNOT_ORDER_SOURCES_FILE: sourceFile,
      });
      batchStatus.shipments = { ok: true };
    } catch (e) {
      batchStatus.shipments = { ok: false, error: e.message.substring(0, 1000) };
      console.warn(`    Shipments batch ${batchNumber} failed: ${e.message.split('\n')[0]}`);
    } finally {
      fs.rmSync(sourceFile, { force: true });
    }

    if (orders || shipments) {
      const response = await upload(config, runId, channel, verified, {
        orders_by_live_id: orders ? mapBatchOutput(orders.data) : {},
        shipments_by_live_id: shipments ? mapBatchOutput(shipments.data) : {},
      }, batchStatus);
      orderCreated += response?.result?.orders?.created || 0;
      shipmentCreated += response?.result?.shipments?.created || 0;
    }
  }

  status.orders = { ok: true, created: orderCreated };
  status.shipments = { ok: true, created: shipmentCreated };
  console.log(`    Orders created: ${orderCreated}; shipments created: ${shipmentCreated}`);

  console.log(`  [4/4] Ledger (last ${config.ledger_days} days)`);
  const to = new Date();
  const from = new Date(to.getTime() - (config.ledger_days - 1) * 86400000);
  try {
    const ledger = await runScraper(config, 'ledger', channel.whatnot_username, {
      WHATNOT_LEDGER_FROM: dateOnly(from),
      WHATNOT_LEDGER_TO: dateOnly(to),
    });
    status.ledger = { ok: true, count: Array.isArray(ledger.data) ? ledger.data.length : 0 };
    const response = await upload(config, runId, channel, ledger.verified || verified, {
      ledger: Array.isArray(ledger.data) ? ledger.data : [],
    }, { ledger: status.ledger });
    console.log(`    Ledger created: ${response?.result?.ledger?.created || 0}; existing: ${response?.result?.ledger?.skipped || 0}`);
  } catch (e) {
    status.ledger = { ok: false, error: e.message.substring(0, 1000) };
    console.warn(`    Ledger failed: ${e.message.split('\n')[0]}`);
  }

  return { channel: channel.whatnot_username, status };
}

async function main() {
  console.log('============================================================');
  console.log(` VortexOps Whatnot Desktop Collector v${VERSION}`);
  console.log('============================================================');

  if (Number(process.versions.node.split('.')[0]) < 18) {
    die('Node.js 18 or newer is required.');
  }

  const config = loadConfig();
  fs.mkdirSync(APP_DIR, { recursive: true });
  fs.mkdirSync(TEMP_DIR, { recursive: true });

  console.log(`Profile: ${config.profile_dir}`);
  console.log('Connecting to VortexOps...');

  let bootstrap;
  try {
    bootstrap = await api(config, 'GET', '/whatnot/collector/bootstrap');
  } catch (e) {
    die(e.message);
  }

  let channels = Array.isArray(bootstrap?.channels) ? bootstrap.channels : [];
  if (config.channels.length) {
    const wanted = new Set(config.channels.map((c) => c.toLowerCase().replace(/[^a-z0-9]/g, '')));
    channels = channels.filter((channel) => wanted.has(String(channel.whatnot_username || '').toLowerCase().replace(/[^a-z0-9]/g, '')));
  }
  if (!channels.length) die('VortexOps returned no active Whatnot channels to collect.');

  console.log(`Channels: ${channels.map((c) => c.name).join(', ')}`);
  const runId = `${Date.now()}-${crypto.randomUUID()}`;
  const results = [];
  let failed = 0;

  for (const channel of channels) {
    try {
      results.push(await syncChannel(config, channel, runId));
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
  console.log(` Sync finished: ${channels.length - failed} channel(s) completed, ${failed} failed`);
  console.log('============================================================');
  if (failed) process.exitCode = 1;
}

main().catch((e) => die(e.stack || e.message));
