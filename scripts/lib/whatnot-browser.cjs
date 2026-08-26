'use strict';

/**
 * Launching Chromium the way that gets past Cloudflare.
 *
 * Lifted from scripts/whatnot-scraper.cjs, which reaches the Seller Hub from
 * this server every day, so that whatnot-production-sync.cjs can stop being
 * challenged on the same machine, address, profile and cookies.
 *
 * The difference was never the session or the IP. Playwright's
 * launchPersistentContext() spawns Chromium with --remote-debugging-pipe and
 * its full automation argument set — around thirty flags that together read as
 * a well-known bot fingerprint. This spawns Chromium ourselves with a small,
 * deliberate set and attaches over CDP-on-TCP instead; everything downstream
 * still receives an ordinary Playwright BrowserContext.
 *
 * (The pipe is also what breaks in restrictive containers: it relies on
 * inherited fd 3/4, and when that handshake never completes Playwright
 * force-kills a Chromium that started perfectly well, surfacing as an opaque
 * SIGTRAP.)
 *
 * Injected rather than imported: the caller passes its own `chromium` binding,
 * its resolved Chromium path, and its own logger, so this file has no opinion
 * about how either script finds those.
 */

const fs = require('fs');
const path = require('path');
const { spawn, execSync } = require('child_process');

function killProcessGroup(child, signal) {
  try {
    process.kill(-child.pid, signal);
  } catch {
    // Group kill fails if the child was never a group leader, or the group's
    // leader already exited — fall back to the single PID we hold directly.
    try { child.kill(signal); } catch {}
  }
}

/**
 * Send a kill signal and wait for the OS process to actually exit.
 *
 * Sending the signal alone returns immediately, which is not enough when the
 * profile's SingletonLock has to be verifiably released before the next launch
 * against the same directory — otherwise Chromium exits with code 21, "another
 * instance is already using this profile".
 */
function killAndWait(child, signal = 'SIGTERM', timeoutMs = 5000) {
  if (child.killed || child.exitCode !== null || child.signalCode !== null) return Promise.resolve();

  return new Promise((resolve) => {
    const forceTimer = setTimeout(() => killProcessGroup(child, 'SIGKILL'), timeoutMs);
    child.once('exit', () => { clearTimeout(forceTimer); resolve(); });
    killProcessGroup(child, signal);
  });
}

/**
 * Kill anything already running against this profile before launching into it.
 *
 * The PHP-side browser lock guarantees only one whatnot:* command is ever
 * legitimately in flight, so anything still alive against this exact profile is
 * leftover from a crashed or orphaned run. Matching Chromium's own
 * user-data-dir pattern reaps the whole tree — renderers and helpers carry it
 * on their command lines too.
 */
function killStaleProcessesForProfile(userDataDir, info) {
  try {
    execSync(`pkill -9 -f "user-data-dir=${userDataDir}"`, { stdio: 'ignore' });
    info(`killed stale chromium process(es) already using profile: ${userDataDir}`);
  } catch {
    // pkill exits 1 when nothing matched, which is the common case.
  }
}

/**
 * Clear a SingletonLock left by a Chromium that never reached its own cleanup.
 *
 * The lock is a symlink to "<hostname>-<pid>", so it is only cleared when that
 * pid is genuinely gone — a running instance is never disturbed, because two
 * Chromiums on one profile corrupt it.
 */
function clearStaleSingletonLock(userDataDir, info) {
  const lockPath = path.join(userDataDir, 'SingletonLock');

  let target;
  try { target = fs.readlinkSync(lockPath); } catch { return; }

  const pid = /-(\d+)$/.exec(target)?.[1];
  let alive = false;

  if (pid) {
    try {
      process.kill(Number(pid), 0);
      alive = true;
    } catch (e) {
      alive = e.code === 'EPERM'; // exists but not ours to signal — still alive
    }
  }

  if (alive) return;

  for (const name of ['SingletonLock', 'SingletonSocket', 'SingletonCookie']) {
    fs.rmSync(path.join(userDataDir, name), { force: true });
  }

  info(`cleared stale profile lock (recorded pid ${pid || 'unknown'} is gone)`);
}

/**
 * @param {object} deps  {chromium, chromiumPath, info}
 */
async function launchPersistentContextViaCdp(userDataDir, opts, deps) {
  const { chromium, chromiumPath, info } = deps;
  const { args = [], userAgent, viewport, locale, extraHTTPHeaders, env: extraEnv = {} } = opts;

  killStaleProcessesForProfile(userDataDir, info);
  clearStaleSingletonLock(userDataDir, info);

  // Headless Chromium is the single loudest bot signal there is, and on a
  // datacenter address it is the difference between a challenge that clears
  // itself and one that never does. WHATNOT_HEADLESS=false under
  // scripts/with-xvfb.sh presents as an ordinary windowed browser.
  const headless = String(process.env.WHATNOT_HEADLESS ?? 'true').toLowerCase() !== 'false';

  if (!headless && !process.env.DISPLAY) {
    throw new Error(
      'WHATNOT_HEADLESS=false needs an X display, and DISPLAY is not set.\n'
      + 'Run it through ./scripts/with-xvfb.sh (artisan commands do this for you).',
    );
  }

  info('launching chromium:', headless
    ? 'headless (set WHATNOT_HEADLESS=false under xvfb for a windowed browser)'
    : 'headed, DISPLAY=' + process.env.DISPLAY);

  // Chromium resolves DNS through the proxy for socks5://, which matters:
  // resolving locally would leak the real network path and defeat the point.
  const proxy = process.env.WHATNOT_PROXY || '';
  if (proxy) info('routing browser traffic through proxy:', proxy);

  const chromeArgs = [
    ...args,
    ...(headless ? ['--headless'] : []),
    ...(proxy ? [`--proxy-server=${proxy}`] : []),
    '--remote-debugging-port=0',
    // Chrome ≥111 enforces an Origin/Host allowlist on the DevTools WebSocket
    // and silently drops connections that fail it — a bare "socket hang up"
    // with no useful error. This is a loopback connection we are deliberately
    // making ourselves.
    '--remote-allow-origins=*',
    `--user-data-dir=${userDataDir}`,
    ...(userAgent ? [`--user-agent=${userAgent}`] : []),
    ...(locale ? [`--lang=${locale}`] : []),
    'about:blank',
  ];

  const child = spawn(chromiumPath, chromeArgs, {
    env: { ...process.env, HOME: '/tmp', ...extraEnv },
    stdio: ['ignore', 'ignore', 'pipe'],
    // Lead our own process group so killAndWait can signal the whole Chromium
    // tree; renderer and helper processes are not tracked by the child handle,
    // and killing only that PID orphans them holding the profile lock.
    detached: true,
  });

  const wsEndpoint = await new Promise((resolve, reject) => {
    let buf = '';
    const cleanup = () => {
      clearTimeout(timer);
      child.stderr.off('data', onData);
      child.off('exit', onExit);
      child.off('error', onError);
    };
    const onData = (chunk) => {
      buf += chunk.toString();
      const m = buf.match(/DevTools listening on (ws:\/\/\S+)/);
      if (m) { cleanup(); resolve(m[1]); }
    };
    const onExit = (code, signal) => {
      cleanup();
      reject(new Error(`Chromium exited before DevTools listener was ready (code=${code} signal=${signal})`));
    };
    const onError = (err) => { cleanup(); reject(err); };
    const timer = setTimeout(() => {
      cleanup();
      reject(new Error('Timed out waiting for Chromium DevTools listener'));
    }, 15000);

    child.stderr.on('data', onData);
    child.once('exit', onExit);
    child.once('error', onError);
  });

  // Keep draining stderr so a long session does not fill the OS pipe buffer and
  // stall Chromium once the one-time listener above is gone.
  child.stderr.on('data', () => {});

  let browserDeath = null;
  child.on('exit', (code, signal) => {
    browserDeath = { code, signal };
    info(`WARNING: chromium process exited unexpectedly (pid=${child.pid} code=${code} signal=${signal})`);
  });

  const port = new URL(wsEndpoint).port;

  // "DevTools listening" can print fractionally before the WebSocket handler
  // accepts connections, so retry rather than assuming the first attempt lands.
  let browser, lastConnectError;
  for (let attempt = 0; attempt < 6; attempt++) {
    if (attempt > 0) await new Promise(r => setTimeout(r, 500));
    try {
      browser = await chromium.connectOverCDP(`http://127.0.0.1:${port}`);
      break;
    } catch (e) {
      lastConnectError = e;
    }
  }

  if (!browser) {
    await killAndWait(child, 'SIGKILL');

    if (browserDeath) {
      const { code, signal } = browserDeath;
      throw new Error(
        `Chromium died during startup (code=${code} signal=${signal}), so there was no DevTools port to connect to.`
        + `\nThis is a browser launch failure, not a Whatnot or network problem.`
        + (proxy ? `\nIt was launched with --proxy-server=${proxy}; retry without WHATNOT_PROXY to rule that out.` : ''),
      );
    }

    throw lastConnectError;
  }

  let context = browser.contexts()[0];
  for (let i = 0; i < 20 && !context; i++) {
    await new Promise(r => setTimeout(r, 100));
    context = browser.contexts()[0];
  }

  if (!context) {
    await killAndWait(child, 'SIGKILL');
    throw new Error('No browser context available after connectOverCDP');
  }

  if (extraHTTPHeaders) await context.setExtraHTTPHeaders(extraHTTPHeaders).catch(() => {});

  if (viewport) {
    for (const page of context.pages()) await page.setViewportSize(viewport).catch(() => {});
    context.on('page', (page) => { page.setViewportSize(viewport).catch(() => {}); });
  }

  // A CDP-connected context's close() only disconnects the session; it does not
  // own the browser and will not kill it. Waiting for the actual OS exit is what
  // guarantees the profile lock is released before anything launches again.
  const originalClose = context.close.bind(context);
  context.close = async (...closeArgs) => {
    try {
      await originalClose(...closeArgs);
    } finally {
      await killAndWait(child);
    }
  };

  return context;
}

/**
 * Launch, and rebuild the profile if it turns out to be the thing that is broken.
 *
 * The persistent profile is this scraper's most valuable state and its most
 * fragile: any hard kill risks ending Chromium mid-write, and a half-written
 * profile makes it die on startup in a way that looks identical to a network or
 * browser fault in every configuration.
 *
 * Moved aside rather than deleted, so if it was not the problem it is still
 * there. The session re-bootstraps from the cookie file on the next launch, so
 * a rebuild costs a re-login at worst, never data.
 */
async function launchWithProfileRecovery(userDataDir, opts, deps) {
  try {
    return await launchPersistentContextViaCdp(userDataDir, opts, deps);
  } catch (e) {
    if (!/died during startup/.test(e.message || '')) throw e;

    const quarantine = userDataDir + '.broken';

    try {
      fs.rmSync(quarantine, { recursive: true, force: true });   // keep only the latest
      fs.renameSync(userDataDir, quarantine);
      deps.info('chromium would not start on this profile — moved it to', quarantine);
      deps.info('starting a clean profile; the session re-loads from the cookie file');
    } catch (moveError) {
      deps.info('could not move the profile aside:', moveError.message);
      throw e;
    }

    return await launchPersistentContextViaCdp(userDataDir, opts, deps);
  }
}

module.exports = {
  killAndWait,
  killStaleProcessesForProfile,
  clearStaleSingletonLock,
  launchPersistentContextViaCdp,
  launchWithProfileRecovery,
};
