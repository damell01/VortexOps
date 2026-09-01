'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const projectRoot = path.resolve(__dirname, '..');
const backend = String(process.env.WHATNOT_BROWSER_BACKEND || 'local').trim().toLowerCase();
const mode = String(process.env.WHATNOT_MODE || 'analytics').trim();
const scraplingModes = new Set(['analytics', 'orders-batch', 'shipments-batch', 'ledger']);
const fallbackEnabled = String(process.env.WHATNOT_SCRAPER_FALLBACK || '1').trim() !== '0';

function normalizeChannel(value) {
  return String(value || '')
    .trim()
    .replace(/^@+/, '')
    .toLowerCase();
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

  const isolate = channel && String(env.WHATNOT_CHANNEL_ISOLATE || '1').trim() !== '0';
  const configuredRoot = env.WHATNOT_STATE_DIR
    ? path.resolve(projectRoot, env.WHATNOT_STATE_DIR)
    : path.resolve(projectRoot, 'storage', 'whatnot-channels');

  if (isolate) {
    const channelRoot = path.join(configuredRoot, channel);
    env.WHATNOT_USER_DATA_DIR = path.join(channelRoot, 'browser-profile');
    env.WHATNOT_COOKIES_FILE = path.join(channelRoot, 'cookies.json');
    env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR = path.join(channelRoot, 'diagnostics');
    fs.mkdirSync(env.WHATNOT_USER_DATA_DIR, { recursive: true });
    fs.mkdirSync(env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR, { recursive: true });
    console.error(
      `[whatnot] CHANNEL_SCOPE requested=@${channel} state=${path.relative(projectRoot, channelRoot)}`,
    );
  } else {
    env.WHATNOT_USER_DATA_DIR =
      env.WHATNOT_USER_DATA_DIR || path.resolve(projectRoot, 'storage', 'whatnot-browser-profile');
    env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR =
      env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR || path.resolve(projectRoot, 'storage', 'logs', 'whatnot-scrapling');
  }

  return env;
}

const childEnv = scopedEnvironment();

function runScrapling() {
  const python = String(childEnv.WHATNOT_PYTHON_BIN || 'python3').trim();
  const script = path.join(__dirname, 'whatnot-scrapling-stealth.py');
  process.stderr.write(`[whatnot] browser backend: scrapling-stealth (mode=${mode})\n`);
  return spawnSync(python, [script], {
    env: childEnv,
    cwd: projectRoot,
    stdio: 'inherit',
  });
}

function runPlaywright(reason = '') {
  const script = path.join(__dirname, 'whatnot-scraper.cjs');
  const env = { ...childEnv, WHATNOT_BROWSER_BACKEND: 'local' };
  const suffix = reason ? ` fallback_reason=${reason}` : '';
  process.stderr.write(`[whatnot] browser backend: playwright-local (mode=${mode})${suffix}\n`);
  return spawnSync(process.execPath, [script, ...process.argv.slice(2)], {
    env,
    cwd: projectRoot,
    stdio: 'inherit',
  });
}

function exitFor(result, label) {
  if (result.error) {
    process.stderr.write(`[whatnot] ${label} failed to start: ${result.error.message}\n`);
    process.exit(1);
  }
  process.exit(result.status == null ? 1 : result.status);
}

if (backend === 'scrapling' && scraplingModes.has(mode)) {
  const scraplingResult = runScrapling();
  const status = scraplingResult.status == null ? 1 : scraplingResult.status;

  if (status === 0) process.exit(0);

  // Exit code 3 is reserved by the Scrapling scraper for login/channel-context
  // failures. Never retry those with another backend: doing so could mask an
  // account mismatch. Other backend/challenge failures may fall back to the
  // existing Playwright scraper, which performs its own channel verification.
  if (fallbackEnabled && status !== 3) {
    const reason = scraplingResult.error
      ? 'scrapling-start-failure'
      : `scrapling-exit-${status}`;
    const playwrightResult = runPlaywright(reason);
    exitFor(playwrightResult, 'Playwright fallback');
  }

  exitFor(scraplingResult, 'Scrapling runner');
}

if (backend === 'scrapling') {
  process.stderr.write(
    `[whatnot] Scrapling does not replace mode=${mode}; using the existing Node scraper for this utility/auth mode.\n`,
  );
}

const localResult = runPlaywright();
exitFor(localResult, 'Playwright runner');
