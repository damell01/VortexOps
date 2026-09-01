'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const projectRoot = path.resolve(__dirname, '..');
const backend = String(process.env.WHATNOT_BROWSER_BACKEND || 'local').trim().toLowerCase();
const mode = String(process.env.WHATNOT_MODE || 'analytics').trim();
const scraplingModes = new Set(['analytics', 'orders-batch', 'shipments-batch', 'ledger']);
const fallbackEnabled = String(process.env.WHATNOT_SCRAPER_FALLBACK || '1').trim() !== '0';
const httpPreflightEnabled = String(process.env.WHATNOT_HTTP_PREFLIGHT || '0').trim() === '1';
const httpPreflightStrict = String(process.env.WHATNOT_HTTP_PREFLIGHT_STRICT || '0').trim() === '1';

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

  // Reuse the human-authenticated persistent browser profile by default.
  //
  // Whatnot authentication belongs to the signed-in team member, not to an
  // individual seller channel. Creating a fresh profile for every requested
  // channel threw away the manually-established Google session and forced each
  // channel back through login/bootstrap. Channel attribution does not depend on
  // profile isolation: whatnot-scraper.cjs verifies the requested active
  // @username after every role switch and fails closed if it cannot prove it.
  //
  // Per-channel browser profiles remain available as an explicit diagnostic or
  // isolation mode with WHATNOT_CHANNEL_ISOLATE=1.
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
    console.error(
      `[whatnot] CHANNEL_SCOPE requested=@${channel} session=isolated state=${path.relative(projectRoot, channelRoot)}`,
    );
  } else {
    const sharedProfile = path.resolve(projectRoot, 'storage', 'whatnot-browser-profile');
    env.WHATNOT_USER_DATA_DIR = env.WHATNOT_USER_DATA_DIR || sharedProfile;

    // Keep diagnostics separated by requested channel even though authentication
    // state is shared. This makes a failed switch easy to diagnose without
    // sacrificing the persistent session that a human already authenticated.
    if (channel) {
      const channelRoot = path.join(configuredRoot, channel);
      env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR =
        env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR || path.join(channelRoot, 'diagnostics');
      env.WHATNOT_HTTP_DIAGNOSTICS_DIR =
        env.WHATNOT_HTTP_DIAGNOSTICS_DIR || path.join(channelRoot, 'diagnostics');
      fs.mkdirSync(env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR, { recursive: true });
      console.error(
        `[whatnot] CHANNEL_SCOPE requested=@${channel} session=shared-persistent profile=${path.relative(projectRoot, env.WHATNOT_USER_DATA_DIR)}`,
      );
    } else {
      env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR =
        env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR || path.resolve(projectRoot, 'storage', 'logs', 'whatnot-scrapling');
      env.WHATNOT_HTTP_DIAGNOSTICS_DIR =
        env.WHATNOT_HTTP_DIAGNOSTICS_DIR || path.resolve(projectRoot, 'storage', 'logs', 'whatnot-http');
    }
  }

  return env;
}

const childEnv = scopedEnvironment();

function runHttpHealth() {
  const python = String(childEnv.WHATNOT_PYTHON_BIN || 'python3').trim();
  const script = path.join(__dirname, 'whatnot-http-health.py');
  process.stderr.write(`[whatnot] http session health preflight (mode=${mode})\n`);
  return spawnSync(python, [script], {
    env: childEnv,
    cwd: projectRoot,
    stdio: 'inherit',
  });
}

function runScrapling() {
  const python = String(childEnv.WHATNOT_PYTHON_BIN || 'python3').trim();
  // Use the ordinary DynamicSession implementation. The older
  // whatnot-scrapling-stealth.py wrapper is intentionally not selected by the
  // application runner because it exposes anti-bot challenge-solving options.
  const script = path.join(__dirname, 'whatnot-scrapling.py');
  process.stderr.write(`[whatnot] browser backend: scrapling-dynamic (mode=${mode})\n`);
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

// Optional requests-level session-health check. This only measures normal HTTP
// session state and records diagnostics; it does not solve or manipulate an
// anti-bot challenge. By default it is advisory so a browser session that is
// healthier than plain HTTP is still allowed to run.
if (backend === 'http-health') {
  exitFor(runHttpHealth(), 'HTTP health adapter');
}

if (httpPreflightEnabled) {
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

if (backend === 'scrapling' && scraplingModes.has(mode)) {
  const scraplingResult = runScrapling();
  const status = scraplingResult.status == null ? 1 : scraplingResult.status;

  if (status === 0) process.exit(0);

  // Fail closed for auth/channel mismatch (3), rate limiting (4), and an
  // explicitly detected anti-bot challenge (5). Switching engines after any of
  // those conditions could hide the real failure or turn fallback into challenge
  // circumvention. Ordinary runtime/selector/start failures may still fall back
  // to the existing Playwright scraper, which performs its own channel checks.
  const terminalStatuses = new Set([3, 4, 5]);
  if (fallbackEnabled && !terminalStatuses.has(status)) {
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
