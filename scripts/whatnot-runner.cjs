'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const projectRoot = path.resolve(__dirname, '..');
let backend = String(process.env.WHATNOT_BROWSER_BACKEND || 'local').trim().toLowerCase();
const mode = String(process.env.WHATNOT_MODE || 'analytics').trim();
const scraplingModes = new Set(['analytics', 'orders-batch', 'shipments-batch', 'ledger']);
const fallbackEnabled = String(process.env.WHATNOT_SCRAPER_FALLBACK || '1').trim() !== '0';
const httpPreflightEnabled = String(process.env.WHATNOT_HTTP_PREFLIGHT || '0').trim() === '1';
const httpPreflightStrict = String(process.env.WHATNOT_HTTP_PREFLIGHT_STRICT || '0').trim() === '1';
const autoBrowserEnabled = String(process.env.WHATNOT_AUTO_BROWSER || '1').trim() !== '0';

function normalizeChannel(value) {
  return String(value || '').trim().replace(/^@+/, '').toLowerCase();
}

function channelFromArgs() {
  const args = process.argv.slice(2);
  for (let i = 0; i < args.length; i += 1) {
    if (args[i].startsWith('--channel=')) return args[i].slice('--channel='.length);
    if (args[i] === '--channel' && args[i + 1]) return args[i + 1];
  }
  return '';
}

function scopedEnvironment() {
  const env = { ...process.env };
  const channel = normalizeChannel(env.WHATNOT_CHANNEL_NAME || channelFromArgs());

  if (channel) {
    if (!/^[a-z0-9._-]+$/.test(channel)) {
      console.error(`[whatnot] Invalid channel name: ${channel}`);
      process.exit(2);
    }
    env.WHATNOT_CHANNEL_NAME = channel;
  }

  const isolate = Boolean(channel)
    && String(env.WHATNOT_CHANNEL_ISOLATE || '0').trim() === '1';
  const configuredRoot = env.WHATNOT_STATE_DIR
    ? path.resolve(projectRoot, env.WHATNOT_STATE_DIR)
    : path.resolve(projectRoot, 'storage', 'whatnot-channels');

  if (isolate) {
    const channelRoot = path.join(configuredRoot, channel);
    env.WHATNOT_USER_DATA_DIR = path.join(channelRoot, 'browser-profile');
    env.WHATNOT_COOKIES_FILE = path.join(channelRoot, 'cookies.json');
    env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR = path.join(channelRoot, 'diagnostics');
    env.WHATNOT_HTTP_DIAGNOSTICS_DIR = path.join(channelRoot, 'diagnostics');
    fs.mkdirSync(env.WHATNOT_USER_DATA_DIR, { recursive: true });
    fs.mkdirSync(env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR, { recursive: true });
    console.error(`[whatnot] CHANNEL_SCOPE requested=@${channel} session=isolated state=${path.relative(projectRoot, channelRoot)}`);
  } else {
    const sharedProfile = path.resolve(projectRoot, 'storage', 'whatnot-browser-profile');
    env.WHATNOT_USER_DATA_DIR = env.WHATNOT_USER_DATA_DIR || sharedProfile;

    if (channel) {
      const channelRoot = path.join(configuredRoot, channel);
      env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR = env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR || path.join(channelRoot, 'diagnostics');
      env.WHATNOT_HTTP_DIAGNOSTICS_DIR = env.WHATNOT_HTTP_DIAGNOSTICS_DIR || path.join(channelRoot, 'diagnostics');
      fs.mkdirSync(env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR, { recursive: true });
      console.error(`[whatnot] CHANNEL_SCOPE requested=@${channel} session=shared-persistent profile=${path.relative(projectRoot, env.WHATNOT_USER_DATA_DIR)}`);
    } else {
      env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR = env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR || path.resolve(projectRoot, 'storage', 'logs', 'whatnot-scrapling');
      env.WHATNOT_HTTP_DIAGNOSTICS_DIR = env.WHATNOT_HTTP_DIAGNOSTICS_DIR || path.resolve(projectRoot, 'storage', 'logs', 'whatnot-http');
    }
  }

  return env;
}

const childEnv = scopedEnvironment();

function localCdpAvailable() {
  const endpoint = String(childEnv.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222').trim();
  let parsed;
  try { parsed = new URL(endpoint); } catch { return false; }
  const host = (parsed.hostname || '').toLowerCase();
  if (!['127.0.0.1', 'localhost', '::1'].includes(host)) return false;

  const healthUrl = endpoint.replace(/\/$/, '') + '/json/version';
  const result = spawnSync('curl', ['-fsS', '--max-time', '2', healthUrl], {
    cwd: projectRoot,
    encoding: 'utf8',
  });
  if (result.status !== 0) return false;
  try {
    const data = JSON.parse(result.stdout || '{}');
    return Boolean(data.webSocketDebuggerUrl);
  } catch {
    return false;
  }
}

function ensurePersistentBrowser() {
  if (!autoBrowserEnabled || backend !== 'local' || localCdpAvailable()) return;
  if (process.platform !== 'linux') return;

  const starter = path.join(__dirname, 'whatnot-browser-start.sh');
  if (!fs.existsSync(starter)) {
    process.stderr.write('[whatnot] browser auto-start helper is missing; continuing with normal local launch\n');
    return;
  }

  process.stderr.write('[whatnot] no attached Chromium detected; starting the persistent shared browser\n');
  const result = spawnSync('/bin/bash', [starter], {
    env: {
      ...childEnv,
      WHATNOT_PROJECT_ROOT: projectRoot,
      WHATNOT_ATTACH_CDP_URL: childEnv.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222',
    },
    cwd: projectRoot,
    stdio: 'inherit',
    timeout: 30000,
  });

  if (result.error) {
    process.stderr.write(`[whatnot] browser auto-start failed to execute: ${result.error.message}\n`);
    return;
  }

  if (result.status !== 0) {
    process.stderr.write(`[whatnot] browser auto-start exited ${result.status}; continuing with normal local launch\n`);
    return;
  }

  if (!localCdpAvailable()) {
    process.stderr.write('[whatnot] browser auto-start returned success but CDP is still unavailable; continuing with normal local launch\n');
  }
}

// Production uses one long-lived, human-authenticated browser profile for every
// Whatnot channel. Start it automatically when needed, then always attach to it.
// This is session/browser lifecycle automation only; it does not solve, suppress,
// or manipulate any site verification. Challenge detection remains in the scraper.
ensurePersistentBrowser();

// A live browser on our loopback CDP port owns the shared profile. Never start
// another Chromium against that profile: borrow the already-running browser.
if (backend === 'local' && localCdpAvailable()) {
  backend = 'attached';
  process.stderr.write('[whatnot] persistent Chromium detected on local CDP; auto-selecting attached mode (will not launch/kill Chromium)\n');
}

function runHttpHealth() {
  const python = String(childEnv.WHATNOT_PYTHON_BIN || 'python3').trim();
  const script = path.join(__dirname, 'whatnot-http-health.py');
  process.stderr.write(`[whatnot] http session health preflight (mode=${mode})\n`);
  return spawnSync(python, [script], { env: childEnv, cwd: projectRoot, stdio: 'inherit' });
}

function runScrapling() {
  const python = String(childEnv.WHATNOT_PYTHON_BIN || 'python3').trim();
  const script = path.join(__dirname, 'whatnot-scrapling.py');
  process.stderr.write(`[whatnot] browser backend: scrapling-dynamic (mode=${mode})\n`);
  return spawnSync(python, [script], { env: childEnv, cwd: projectRoot, stdio: 'inherit' });
}

function runAttachedBrowser() {
  const script = path.join(__dirname, 'whatnot-attached-runner.cjs');
  const env = {
    ...childEnv,
    WHATNOT_BROWSER_BACKEND: 'local',
    WHATNOT_ATTACH_EXISTING_BROWSER: '1',
    WHATNOT_ATTACH_CDP_URL: childEnv.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222',
  };
  process.stderr.write(`[whatnot] browser backend: attached-persistent-chromium (mode=${mode}, cdp=${env.WHATNOT_ATTACH_CDP_URL})\n`);
  return spawnSync(process.execPath, [script, ...process.argv.slice(2)], { env, cwd: projectRoot, stdio: 'inherit' });
}

function runPlaywright(reason = '') {
  if (localCdpAvailable()) {
    process.stderr.write('[whatnot] persistent Chromium became available before launch; attaching instead of starting a second browser\n');
    return runAttachedBrowser();
  }

  const script = path.join(__dirname, 'whatnot-scraper.cjs');
  const env = { ...childEnv, WHATNOT_BROWSER_BACKEND: 'local' };
  const suffix = reason ? ` fallback_reason=${reason}` : '';
  process.stderr.write(`[whatnot] browser backend: playwright-local (mode=${mode})${suffix}\n`);
  return spawnSync(process.execPath, [script, ...process.argv.slice(2)], { env, cwd: projectRoot, stdio: 'inherit' });
}

function exitFor(result, label) {
  if (result.error) {
    process.stderr.write(`[whatnot] ${label} failed to start: ${result.error.message}\n`);
    process.exit(1);
  }
  process.exit(result.status == null ? 1 : result.status);
}

if (backend === 'http-health') exitFor(runHttpHealth(), 'HTTP health adapter');

if (httpPreflightEnabled && backend !== 'attached') {
  const preflight = runHttpHealth();
  const preflightStatus = preflight.status == null ? 1 : preflight.status;
  if (preflight.error) {
    process.stderr.write(`[whatnot] HTTP preflight failed to start: ${preflight.error.message}\n`);
    if (httpPreflightStrict) process.exit(1);
  } else if (preflightStatus !== 0) {
    process.stderr.write(`[whatnot] HTTP preflight reported exit=${preflightStatus}; browser run ${httpPreflightStrict ? 'stopped' : 'will continue'}\n`);
    if (httpPreflightStrict) process.exit(preflightStatus);
  }
}

if (backend === 'attached') exitFor(runAttachedBrowser(), 'Attached browser runner');

if (backend === 'scrapling' && scraplingModes.has(mode)) {
  const scraplingResult = runScrapling();
  const status = scraplingResult.status == null ? 1 : scraplingResult.status;
  if (status === 0) process.exit(0);

  const terminalStatuses = new Set([3, 4, 5]);
  if (fallbackEnabled && !terminalStatuses.has(status)) {
    const reason = scraplingResult.error ? 'scrapling-start-failure' : `scrapling-exit-${status}`;
    exitFor(runPlaywright(reason), 'Playwright fallback');
  }
  exitFor(scraplingResult, 'Scrapling runner');
}

if (backend === 'scrapling') {
  process.stderr.write(`[whatnot] Scrapling does not replace mode=${mode}; using the existing Node scraper for this utility/auth mode.\n`);
}

exitFor(runPlaywright(), 'Playwright runner');
