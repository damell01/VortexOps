'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { spawn } = require('child_process');

const VERSION = '2.0.0';
const ROOT = __dirname;
const CONFIG_PATH = path.join(ROOT, 'config.json');
const EXAMPLE_PATH = path.join(ROOT, 'config.example.json');
const RUNNER = path.join(ROOT, 'scrapling_runner.py');
const APP_DIR = process.env.LOCALAPPDATA
  ? path.join(process.env.LOCALAPPDATA, 'VortexOps', 'WhatnotCollector')
  : path.join(os.homedir(), '.vortexops', 'whatnot-collector');
const DEFAULT_PROFILE = path.join(APP_DIR, 'ChromeProfile');
const TEMP_DIR = path.join(APP_DIR, 'tmp');
const LOCK_FILE = path.join(APP_DIR, 'collector.lock');

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
    fs.copyFileSync(EXAMPLE_PATH, CONFIG_PATH);
    die('config.json was created. Set api_url and api_token, then run Sync Whatnot again.');
  }

  let config;
  try { config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8')); }
  catch (e) { die('config.json is invalid JSON: ' + e.message); }

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
  config.python_path = String(config.python_path || process.env.PYTHON || 'python').trim();
  config.headless = config.headless !== false;

  if (!/^https?:\/\//i.test(config.api_url) || /YOUR-VORTEXOPS-DOMAIN/i.test(config.api_url)) {
    die('Set api_url in config.json to the VortexOps API base URL ending in /api.');
  }
  if (!config.api_token || config.api_token.includes('PASTE_SCRAPER_API_TOKEN')) {
    die('Set api_token in config.json to the VortexOps SCRAPER_API_TOKEN.');
  }
  if (!config.chrome_path || !fs.existsSync(config.chrome_path)) {
    die('Google Chrome was not found. Set chrome_path in config.json.');
  }
  if (!fs.existsSync(RUNNER)) die('scrapling_runner.py is missing. Pull the complete desktop-collector folder.');
  return config;
}

function processAlive(pid) {
  try { process.kill(Number(pid), 0); return true; }
  catch (e) { return e.code === 'EPERM'; }
}

function acquireLock() {
  fs.mkdirSync(APP_DIR, { recursive: true });
  if (fs.existsSync(LOCK_FILE)) {
    let current = null;
    try { current = JSON.parse(fs.readFileSync(LOCK_FILE, 'utf8')); } catch {}
    if (current?.pid && processAlive(current.pid)) die(`Another collector is already running (PID ${current.pid}).`);
    fs.rmSync(LOCK_FILE, { force: true });
  }
  fs.writeFileSync(LOCK_FILE, JSON.stringify({ pid: process.pid, started_at: new Date().toISOString() }), { flag: 'wx' });
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
  if (!response.ok) throw new Error(`VortexOps API ${response.status}: ${json?.message || json?.error || text.substring(0,500)}`);
  return json;
}

function runScrapling(config, mode, channel, extras = {}, timeoutMs = 60 * 60 * 1000) {
  return new Promise((resolve, reject) => {
    const env = {
      ...process.env,
      PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH: config.chrome_path,
      WHATNOT_USER_DATA_DIR: config.profile_dir,
      WHATNOT_HEADLESS: config.headless ? 'true' : 'false',
      WHATNOT_MODE: mode,
      WHATNOT_CHANNEL_NAME: channel,
      ...extras,
    };

    const child = spawn(config.python_path, [RUNNER], {
      cwd: ROOT,
      env,
      windowsHide: config.headless,
    });
    const stdout = [];
    const stderr = [];
    let done = false;

    child.stdout.on('data', (chunk) => stdout.push(chunk));
    child.stderr.on('data', (chunk) => {
      stderr.push(chunk);
      const text = chunk.toString();
      for (const line of text.split(/\r?\n/)) {
        if (/CHANNEL_CONTEXT_VERIFIED|analytics:|orders-batch:|shipments-batch:|ledger:/i.test(line)) {
          console.log('    ' + line.trim());
        }
      }
    });

    const timer = setTimeout(() => {
      if (done) return;
      done = true;
      child.kill();
      reject(new Error(`${mode} timed out after ${Math.round(timeoutMs / 60000)} minutes.`));
    }, timeoutMs);

    child.on('error', (error) => {
      clearTimeout(timer);
      if (!done) { done = true; reject(error); }
    });

    child.on('close', (code) => {
      clearTimeout(timer);
      if (done) return;
      done = true;
      const out = Buffer.concat(stdout).toString('utf8').trim();
      const err = Buffer.concat(stderr).toString('utf8').trim();
      const verification = [...err.matchAll(/CHANNEL_CONTEXT_VERIFIED\s+requested=@([^\s]+)\s+active=@([^\s]+)/g)].pop();
      const verified = verification ? verification[2] : null;
      if (code !== 0) {
        const login = /LOGIN_REQUIRED|redirected to login/i.test(err);
        reject(Object.assign(new Error(login
          ? 'LOGIN_REQUIRED: run "Login to Whatnot.bat", sign in, close Chrome, then sync again.'
          : (err.split(/\r?\n/).slice(-12).join('\n') || `${mode} exited with code ${code}`)), { verified }));
        return;
      }
      try { resolve({ data: out ? JSON.parse(out) : [], verified, stderr: err }); }
      catch (e) { reject(new Error(`${mode} returned invalid JSON: ${e.message}`)); }
    });
  });
}

function liveId(show) {
  const match = String(show?.whatnot_live_id || show?.whatnot_show_id || show?.detail_url || '').match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
  return match ? match[0].toLowerCase() : null;
}

function chunk(items, size) {
  const out = [];
  for (let i = 0; i < items.length; i += size) out.push(items.slice(i, i + size));
  return out;
}

function mapBatch(data) {
  const out = {};
  for (const entry of Array.isArray(data) ? data : []) out[String(entry.show_key)] = Array.isArray(entry.orders) ? entry.orders : [];
  return out;
}

async function upload(config, runId, channel, verified, payload, componentStatus = {}) {
  if (!verified) throw new Error(`CHANNEL_CONTEXT_MISMATCH: no positive seller verification for @${channel.whatnot_username}; upload refused.`);
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
  if (Buffer.byteLength(JSON.stringify(body)) > 6_500_000) throw new Error('Collector upload exceeds 6.5 MB; reduce batch size.');
  return api(config, 'POST', '/whatnot/collector/import', body);
}

async function collectAnalytics(config, channel, full) {
  const rows = [];
  const seen = new Set();
  let seed = channel.latest_live_id || '';
  let verified = null;
  const batches = full ? config.full_history_max_batches : 1;
  const limit = full ? 500 : config.show_limit;

  for (let i = 0; i < batches; i++) {
    if (!seed) throw new Error(`No analytics seed exists yet for @${channel.whatnot_username}. Run the existing server show index once or seed latest_live_id.`);
    const result = await runScrapling(config, 'analytics', channel.whatnot_username, {
      WHATNOT_LIMIT: String(limit),
      WHATNOT_START_UUID: seed,
    }, 4 * 60 * 60 * 1000);
    verified = result.verified || verified;
    const batchRows = Array.isArray(result.data) ? result.data : [];
    let added = 0;
    for (const row of batchRows) {
      const id = liveId(row);
      const key = id || `${row?.title}|${row?.show_date}`;
      if (seen.has(key)) continue;
      seen.add(key); rows.push(row); added++;
    }
    if (!full || batchRows.length < limit || added === 0) break;
    const nextSeed = [...batchRows].reverse().map(liveId).find(Boolean);
    if (!nextSeed || nextSeed === seed) break;
    seed = nextSeed;
  }
  return { rows, verified };
}

async function collectOrdersShipments(config, channel, runId, verified, shows) {
  const sources = shows.map((show) => {
    const id = liveId(show);
    return id ? { live_id: id, show_key: id } : null;
  }).filter(Boolean);
  let orderCreated = 0, orderUpdated = 0, shipmentCreated = 0, shipmentUpdated = 0, failures = 0;

  let batchNo = 0;
  for (const batch of chunk(sources, config.order_batch_size)) {
    batchNo++;
    console.log(`    Order/shipment batch ${batchNo}/${Math.ceil(sources.length/config.order_batch_size)}`);
    const file = path.join(TEMP_DIR, `sources-${process.pid}-${Date.now()}-${batchNo}.json`);
    fs.writeFileSync(file, JSON.stringify(batch));
    let orders = null, shipments = null;
    const status = {};
    try {
      orders = await runScrapling(config, 'orders-batch', channel.whatnot_username, { WHATNOT_ORDER_SOURCES_FILE: file }, 2*60*60*1000);
      status.orders = { ok: true };
    } catch (e) { failures++; status.orders = { ok: false, error: e.message.substring(0,1000) }; console.warn('      Orders failed: ' + e.message.split('\n')[0]); }
    try {
      shipments = await runScrapling(config, 'shipments-batch', channel.whatnot_username, { WHATNOT_ORDER_SOURCES_FILE: file }, 2*60*60*1000);
      status.shipments = { ok: true };
    } catch (e) { failures++; status.shipments = { ok: false, error: e.message.substring(0,1000) }; console.warn('      Shipments failed: ' + e.message.split('\n')[0]); }
    fs.rmSync(file, { force: true });

    if (orders || shipments) {
      const response = await upload(config, runId, channel, verified, {
        orders_by_live_id: orders ? mapBatch(orders.data) : {},
        shipments_by_live_id: shipments ? mapBatch(shipments.data) : {},
      }, status);
      orderCreated += response?.result?.orders?.created || 0;
      orderUpdated += response?.result?.orders?.updated || 0;
      shipmentCreated += response?.result?.shipments?.created || 0;
      shipmentUpdated += response?.result?.shipments?.updated || 0;
    }
  }
  return { orderCreated, orderUpdated, shipmentCreated, shipmentUpdated, failures };
}

async function collectLedger(config, channel, runId, verified, shows, full) {
  const now = new Date();
  let from;
  if (full) {
    from = config.historical_ledger_from ? new Date(config.historical_ledger_from + 'T00:00:00Z') : null;
    if (!from || Number.isNaN(from.getTime())) {
      const dates = shows.map(s => new Date(String(s.show_date || '') + 'T00:00:00Z')).filter(d => !Number.isNaN(d.getTime())).sort((a,b)=>a-b);
      from = dates[0] || (channel.earliest_show_date ? new Date(channel.earliest_show_date + 'T00:00:00Z') : null);
    }
    if (!from) throw new Error('Set historical_ledger_from in config.json before full history sync.');
  } else {
    from = new Date(now.getTime() - (config.ledger_days - 1) * 86400000);
  }

  const end = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
  let cursor = new Date(Date.UTC(from.getUTCFullYear(), from.getUTCMonth(), from.getUTCDate()));
  let created = 0, skipped = 0, windows = 0;
  while (cursor <= end) {
    const windowEnd = new Date(Math.min(end.getTime(), cursor.getTime() + 30 * 86400000));
    const startText = cursor.toISOString().slice(0,10);
    const endText = windowEnd.toISOString().slice(0,10);
    windows++;
    const result = await runScrapling(config, 'ledger', channel.whatnot_username, {
      WHATNOT_LEDGER_FROM: startText,
      WHATNOT_LEDGER_TO: endText,
    }, 60*60*1000);
    const response = await upload(config, runId, channel, result.verified || verified, { ledger: result.data || [] }, {
      ledger: { ok: true, from: startText, to: endText, count: result.data?.length || 0 },
    });
    created += response?.result?.ledger?.created || 0;
    skipped += response?.result?.ledger?.skipped || 0;
    cursor = new Date(windowEnd.getTime() + 86400000);
  }
  return { created, skipped, windows };
}

async function syncChannel(config, channel, runId, full) {
  console.log(`\n============================================================`);
  console.log(`${channel.name} (@${channel.whatnot_username})${full ? ' — FULL HISTORY' : ''}`);
  console.log('============================================================');

  console.log('  [1/4] Shows + analytics via Scrapling');
  const analytics = await collectAnalytics(config, channel, full);
  if (!analytics.verified) throw new Error('CHANNEL_CONTEXT_MISMATCH: no verified seller context returned.');

  for (const showBatch of chunk(analytics.rows, 250)) {
    await upload(config, runId, channel, analytics.verified, { shows: showBatch }, { analytics: { ok: true, count: showBatch.length } });
  }
  console.log(`    ${analytics.rows.length} show(s) uploaded`);

  console.log('  [2/4] Orders + [3/4] Shipments via Scrapling');
  const orderStats = await collectOrdersShipments(config, channel, runId, analytics.verified, analytics.rows);
  console.log(`    Orders: ${orderStats.orderCreated} created, ${orderStats.orderUpdated} updated`);
  console.log(`    Shipments: ${orderStats.shipmentCreated} created, ${orderStats.shipmentUpdated} updated`);

  console.log('  [4/4] Ledger via Scrapling');
  let ledgerStats = null;
  let ledgerFailure = null;
  try { ledgerStats = await collectLedger(config, channel, runId, analytics.verified, analytics.rows, full); }
  catch (e) { ledgerFailure = e; console.warn('    Ledger failed: ' + e.message.split('\n')[0]); }

  if (orderStats.failures || ledgerFailure) {
    throw new Error(`PARTIAL_SYNC: ${orderStats.failures} order/shipment component failure(s)${ledgerFailure ? '; ledger failed: ' + ledgerFailure.message : ''}. Successful imports were kept.`);
  }
  return { shows: analytics.rows.length, orders: orderStats, ledger: ledgerStats };
}

async function main() {
  console.log('============================================================');
  console.log(` VortexOps Whatnot Collector v${VERSION} — Scrapling`);
  console.log('============================================================');
  const config = loadConfig();
  fs.mkdirSync(TEMP_DIR, { recursive: true });
  acquireLock();
  try {
    const full = process.argv.includes('--full');
    console.log(`Engine: Scrapling DynamicSession + installed Google Chrome`);
    console.log(`Mode: ${full ? 'FULL HISTORICAL SYNC' : 'incremental sync'}`);
    console.log(`Profile: ${config.profile_dir}`);
    const bootstrap = await api(config, 'GET', '/whatnot/collector/bootstrap');
    let channels = Array.isArray(bootstrap?.channels) ? bootstrap.channels : [];
    if (config.channels.length) {
      const wanted = new Set(config.channels.map(c => c.toLowerCase().replace(/[^a-z0-9]/g,'')));
      channels = channels.filter(c => wanted.has(String(c.whatnot_username || '').toLowerCase().replace(/[^a-z0-9]/g,'')));
    }
    if (!channels.length) throw new Error('VortexOps returned no active Whatnot channels.');

    const runId = `${Date.now()}-${crypto.randomUUID()}`;
    let completed = 0, failed = 0;
    for (const channel of channels) {
      try { await syncChannel(config, channel, runId, full); completed++; }
      catch (e) {
        failed++;
        console.error(`\n  FAILED ${channel.name}: ${e.message}`);
        if (/LOGIN_REQUIRED/.test(e.message)) break;
      }
    }
    console.log(`\nSync finished: ${completed} completed, ${failed} failed`);
    if (failed) process.exitCode = 1;
  } finally { releaseLock(); }
}

main().catch((e) => { releaseLock(); die(e.stack || e.message); });
