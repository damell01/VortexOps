/**
 * Whatnot Seller Dashboard Scraper
 *
 * Modes (WHATNOT_MODE env var):
 *   analytics    (default) — scrape per-show analytics from the dashboard, newest first
 *   test         — verify credentials only, output {connected, email}
 *   show-orders  — scrape order/lot list for one show (requires WHATNOT_SHOW_URL)
 *   orders-batch — scrape orders for many shows in one session (requires WHATNOT_ORDER_SOURCES_FILE)
 *   shipments-batch — refresh weight/dims/carrier/shipping-status for many shows already
 *                  imported, from /dashboard/shipments?source=<id> (requires WHATNOT_ORDER_SOURCES_FILE)
 *   shipments-live  — discover shows from /dashboard/lives and scrape shipments for each
 *                  (no sources file needed; discovers UUIDs on demand)
 *   shows        — legacy: scrape the /seller/shows list page (less data, kept as fallback)
 *   discover     — 3-phase deep crawl: (1) visit all Seller Hub nav pages, (2) drill into
 *                  individual show detail pages and click each tab (Orders/Lots/Sales/Buyers),
 *                  (3) crawl /seller/orders and individual order detail pages. Logs all
 *                  Phoenix Channel (WS) SEND/RECV frames so you can read topic + event names.
 *   ws-explore   — direct Phoenix Channels probe: skips browser scraping, opens WS directly,
 *                  joins candidate seller_hub:* channels, captures 20 s of messages.
 *                  Output: { topics, messages } — use to learn the real channel/event names.
 *
 * Called by app/Services/WhatnotScraper.php via Symfony Process.
 *
 * Env vars:
 *   WHATNOT_EMAIL        seller account email
 *   WHATNOT_PASSWORD     seller account password
 *   WHATNOT_LIMIT        max shows to return per run (default: 50)
 *   WHATNOT_MODE         analytics | test | show-orders | shows (default: analytics)
 *   WHATNOT_SHOW_URL     required for show-orders mode
 *   WHATNOT_CHANNEL_NAME whatnot_username of the channel to switch to before scraping
 *                        (leave blank to scrape the default/current active channel)
 *   WHATNOT_DEBUG        set to "1" to save debug screenshots to /tmp
 *
 * Exit codes:
 *   0  success, JSON on stdout
 *   1  login failed or nav error, error message on stderr
 *   2  selector miss — page structure changed, update SELECTORS below
 */

'use strict';

const { execSync } = require('child_process');

function loadPlaywright() {
  // 1. Try npm's global root (works on any system regardless of install prefix)
  try {
    const globalRoot = execSync('npm root -g', { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }).trim();
    return require(globalRoot + '/playwright');
  } catch {}
  // 2. Common fixed paths (Docker image, Debian/Ubuntu system npm, Homebrew)
  const candidates = [
    '/opt/node22/lib/node_modules/playwright',
    '/usr/lib/node_modules/playwright',
    '/usr/local/lib/node_modules/playwright',
    '/opt/homebrew/lib/node_modules/playwright',
  ];
  for (const p of candidates) {
    try { return require(p); } catch {}
  }
  throw new Error(
    'Playwright not found. Install it with:\n' +
    '  npm install -g playwright && npx playwright install chromium --with-deps'
  );
}

const { chromium } = loadPlaywright();

// ── Selectors ────────────────────────────────────────────────────────────────
// Analytics page selectors are based on the live Whatnot seller dashboard HTML
// (captured July 2026). Update if Whatnot changes their markup.
const SELECTORS = {
  // Login page — selectors from live HTML (July 2026)
  loginEmailInput:    '#input-login-email, [data-testid="input-login-email"], input[name="identifier"]',
  loginPasswordInput: '#input-login-password, [data-testid="input-login-password"], input[type="password"]',
  loginSubmitBtn:     '[data-testid="button-login-submit"], button[type="submit"]',

  // Analytics page — tabs
  // Text-based selectors are tried first in the scraper loop; these are the CSS fallbacks.
  analyticsShowsTab:  'button[aria-controls="simple-tabpanel-1"], button#simple-tab-1, [role="tab"][data-value="shows"], [role="tab"][data-index="1"]',

  // Analytics page — show navigation (aria-label is stable across deploys)
  showNavOlder:       'button[aria-label="See older show"]',
  showNavNewer:       'button[aria-label="See newer show"]',

  // Analytics page — show header
  // Title uses an inline nowrap/ellipsis style; the max-width:90% distinguishes
  // it from other large text on the page.
  showTitle:          'div[style*="white-space: nowrap"][style*="text-overflow: ellipsis"][style*="max-width: 90%"]',

  // Analytics page — metric cards
  // Each card is 160px tall with border-radius: 16px.
  // Within each card: name is font-size:16px bold, value is font-size:32px bold.
  metricCard:         'div[style*="height: 160px"][style*="border-radius: 16px"]',

  // Seller shows list page (/seller/shows)
  // Show cards on the seller dashboard list — try multiple patterns
  showCard:         '[data-testid="show-card"], [class*="ShowCard"], [class*="show-card"]',
  showCardLink:     'a[href*="/live/"], a[href*="/show/"]',

  // Show orders page — Whatnot uses dynamic CSS class names so we target
  // structural patterns instead of class names. Update if layout changes.
  // "Lots" tab on seller show page
  lotsTab:          'button:has-text("Lots"), [role="tab"]:has-text("Lots"), button:has-text("Orders"), [role="tab"]:has-text("Orders"), button:has-text("Sales"), [role="tab"]:has-text("Sales")',
};

const URLS = {
  home:            'https://www.whatnot.com',
  login:           'https://www.whatnot.com/login',
  analytics:       'https://www.whatnot.com/dashboard/analytics/overview',
  dashboardLives:  'https://www.whatnot.com/dashboard/lives',   // actual seller shows list
  dashboardShows:  'https://www.whatnot.com/dashboard/shows',   // historic alias (404s on direct nav)
  dashboard:       'https://www.whatnot.com/dashboard',
  shows:           'https://www.whatnot.com/seller/shows',
  // The hub's real entry point. /seller is a marketing page that renders in
  // buyer mode, which is what sent ensureSellerMode off to the homepage looking
  // for a "Switch to Selling" drawer — and the homepage is the one path that is
  // never served. Landing here instead skips both.
  sellerHub:       'https://www.whatnot.com/dashboard/home',
  sellerMarketing: 'https://www.whatnot.com/seller',
};

// Resolve Chromium binary.
// Priority: env override → build-time marker file → Playwright API → directory scan → system bins
const CHROMIUM_PATH = (() => {
  const fs = require('fs');

  // 1. Explicit env override (highest priority, covers dev/CI overrides)
  //    Verify existence — path may come from a marker written by root and be inaccessible to www-data.
  if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) {
    const p = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
    if (fs.existsSync(p)) return p;
    // Path is set but inaccessible from this process; fall through to scan.
  }

  // 2. Marker files written by `php artisan whatnot:setup-chromium` or Docker build
  const markerFiles = [
    `${__dirname}/../storage/chromium-path.txt`,                              // bare VPS (artisan)
    `${process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers'}/.chromium-path`, // Docker
  ];
  for (const markerFile of markerFiles) {
    try {
      const p = fs.readFileSync(markerFile, 'utf8').trim();
      if (p && fs.existsSync(p)) return p;
    } catch {}
  }

  // 3. Playwright's own API — works when PLAYWRIGHT_BROWSERS_PATH is set correctly
  try {
    const p = chromium.executablePath();
    if (p && fs.existsSync(p)) return p;
  } catch {}

  // 4. Scan PLAYWRIGHT_BROWSERS_PATH and common user cache dirs
  function findInDir(base) {
    if (!fs.existsSync(base)) return null;
    // Check direct paths first — Playwright may install without a version subdirectory
    // (e.g. PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers → binary at base/chrome-linux/chrome)
    for (const bin of ['chrome-linux64/headless_shell', 'chrome-linux64/chrome',
                        'chrome-linux/headless_shell', 'chrome-linux/chrome',
                        'headless_shell', 'chrome']) {
      const full = `${base}/${bin}`;
      if (fs.existsSync(full)) return full;
    }
    let dirs;
    try {
      dirs = fs.readdirSync(base)
        .filter(d => d.startsWith('chromium-') || d.startsWith('chromium_headless_shell-'))
        .sort().reverse();
    } catch { return null; }
    for (const dir of dirs) {
      for (const bin of ['chrome-linux64/headless_shell', 'chrome-linux64/chrome',
                          'chrome-linux/headless_shell', 'chrome-linux/chrome',
                          'headless_shell', 'chrome']) {
        const full = `${base}/${dir}/${bin}`;
        if (fs.existsSync(full)) return full;
      }
    }
    return null;
  }

  const searchRoots = [
    process.env.PLAYWRIGHT_BROWSERS_PATH,
    '/opt/pw-browsers',                      // shared location written by whatnot:setup-chromium
    ...[process.env.HOME, '/root', '/var/www', '/home/www-data']
      .filter(Boolean)
      .map(h => `${h}/.cache/ms-playwright`),
  ].filter(Boolean);

  for (const root of searchRoots) {
    const found = findInDir(root);
    if (found) return found;
  }

  // 5. System chromium (apt / snap)
  for (const bin of ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome']) {
    if (fs.existsSync(bin)) return bin;
  }

  exitAfterStderr('[whatnot-scraper] ERROR: Chromium not found.\n' +
    '  VPS:    php artisan whatnot:setup-chromium\n' +
    '  Manual: PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/path/to/chrome node scripts/whatnot-scraper.cjs\n', 2);
})();
const DEBUG          = process.env.WHATNOT_DEBUG === '1';
const LIMIT          = parseInt(process.env.WHATNOT_LIMIT || '50', 10);
const MODE           = process.env.WHATNOT_MODE || 'analytics';
const CHANNEL_NAME   = (process.env.WHATNOT_CHANNEL_NAME || '').trim();
const BROWSER_BACKEND = (process.env.WHATNOT_BROWSER_BACKEND || 'local').trim().toLowerCase();
const STEEL_BASE_URL  = (process.env.STEEL_BASE_URL || 'http://127.0.0.1:3000').trim().replace(/\/+$/, '');

// A livestream UUID to start the analytics walk from, supplied by the caller.
//
// The walk itself — /account/analytics, one show at a time via "See older
// show" — is the path that produced the revenue figures, and it is not under
// /dashboard, so the rule that refuses those pages does not touch it. All it
// ever needed was one UUID to begin at, and it was scraping that seed off a
// shows list that is now a challenge screen: a working scrape held up entirely
// by the one page it no longer needs.
//
// PHP passes the newest UUID it already knows for the channel, so once a show
// has been seen once the walk can always be started again.
const START_UUID     = (process.env.WHATNOT_START_UUID || '').trim();

// ── Helpers ──────────────────────────────────────────────────────────────────

function log(...args) {
  if (DEBUG) process.stderr.write('[whatnot-scraper] ' + args.join(' ') + '\n');
}

// Always-on milestone logging (visible in artisan output / error table)
function info(...args) {
  process.stderr.write('[whatnot] ' + args.join(' ') + '\n');
}

// Write a JSON result to stdout and exit 0 — but only AFTER the write flushes.
// stdout is a pipe under Symfony Process, so a large buffered write followed by
// an immediate process.exit(0) truncates the output (producing invalid JSON on
// the PHP side). The write callback fires once the data has drained to the OS,
// so we exit only then. Falls back to a timeout guard in case the callback is
// somehow missed.
function writeJsonAndExit(value) {
  const out = JSON.stringify(value, null, 2) + '\n';
  let exited = false;
  const done = () => { if (!exited) { exited = true; process.exit(0); } };
  process.stdout.write(out, done);
  setTimeout(done, 5000);
}

// The same hazard as writeJsonAndExit, on the other stream — and the reason
// every failure looked causeless from the PHP side.
//
// stderr is a pipe under Symfony Process, where writes are asynchronous, so
// process.exit() immediately after one drops whatever has not drained. Run in a
// terminal, stderr is synchronous and everything prints, which is why the same
// failure was fully explained by hand and arrived at the app as an empty
// diagnostics section. Exiting only once the write has landed makes the two
// agree.
function exitAfterStderr(text, code) {
  // fs.writeSync on fd 2 is synchronous even when stderr is a pipe, so the
  // bytes are gone before exit rather than sitting in a buffer nobody flushes.
  //
  // The first version of this waited for process.stderr.write's drain callback
  // and exited from there, which delivered the text but did not stop anything:
  // the function returned immediately and execution carried on past a fatal
  // error — "needs an X display" was followed by the browser launching anyway.
  // A function named for exiting has to actually exit.
  try {
    require('fs').writeSync(2, text);
  } catch {
    try { process.stderr.write(text); } catch { /* nothing left to try */ }
  }

  process.exit(code);
}

async function debugShot(page, name) {
  if (!DEBUG) return;
  const p = `/tmp/whatnot-debug-${name}.png`;
  try {
    await page.screenshot({ path: p, fullPage: false });
    log(`screenshot saved: ${p}`);
  } catch (e) {
    // A failed diagnostic screenshot (stale file left by a different user,
    // disk full, etc.) must never take down the run it's trying to help
    // debug — log it and move on.
    info(`WARNING: debug screenshot failed for "${name}": ${e.message}`);
  }
}

function parseMoney(str) {
  if (!str || str === 'N/A') return null;
  const cleaned = str.replace(/[^0-9.-]/g, '');
  const val = parseFloat(cleaned);
  return isNaN(val) ? null : val;
}

function parseDurationToMinutes(str) {
  if (!str) return null;
  // "7h 0m", "0h 53m", "1h 30m", "90 min", "1:30:00"
  const hm = str.match(/(\d+)\s*h(?:r|our)?s?\s*(?:(\d+)\s*m)?/i);
  if (hm) return parseInt(hm[1]) * 60 + (parseInt(hm[2] || '0'));
  const minOnly = str.match(/(\d+)\s*m(?:in)?/i);
  if (minOnly) return parseInt(minOnly[1]);
  const hms = str.match(/(\d+):(\d+):(\d+)/);
  if (hms) return parseInt(hms[1]) * 60 + parseInt(hms[2]);
  return null;
}

// Parse "7/1/2026, 7:42 PM CDT" → "2026-07-01"
function parseDateString(str) {
  if (!str) return null;
  const m = str.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
  if (!m) return null;
  const [, mo, d, y] = m;
  return `${y}-${mo.padStart(2, '0')}-${d.padStart(2, '0')}`;
}

// Parse "7/1/2026, 7:42 PM CDT" → "19:42:00" (24h HH:MM:SS for a MySQL TIME column).
// The show's actual stream start time — Whatnot renders it right alongside the
// date on the analytics page, but parseDateString above discards it.
function parseTimeString(str) {
  if (!str) return null;
  const m = str.match(/(\d{1,2}):(\d{2})\s*([AaPp][Mm])/);
  if (!m) return null;
  let hour = parseInt(m[1], 10);
  const minute = m[2];
  const isPM = /p/i.test(m[3]);
  if (isPM && hour !== 12) hour += 12;
  if (!isPM && hour === 12) hour = 0;
  return `${String(hour).padStart(2, '0')}:${minute}:00`;
}

// Extract HH:MM:SS from an ISO-8601 datetime string ("2026-07-01T19:42:00Z" → "19:42:00").
function parseTimeFromIso(str) {
  const m = (str || '').match(/T(\d{2}):(\d{2})/);
  return m ? `${m[1]}:${m[2]}:00` : null;
}

// Add a duration (minutes) to a "HH:MM:SS" time string, wrapping past midnight.
// Used to derive end_time from start_time + Show Duration since Whatnot doesn't
// expose a separate "stream ended at" value anywhere we've found.
function addMinutesToTime(timeStr, minutes) {
  if (!timeStr || minutes == null) return null;
  const [h, m] = timeStr.split(':').map(Number);
  let total = ((h * 60 + m + minutes) % 1440 + 1440) % 1440;
  const hh = Math.floor(total / 60);
  const mm = total % 60;
  return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}:00`;
}

function parseInteger(str) {
  if (!str || str === 'N/A') return null;
  const cleaned = str.replace(/[^0-9]/g, '');
  const val = parseInt(cleaned, 10);
  return isNaN(val) ? null : val;
}

// ── Login helper (shared by all modes) ───────────────────────────────────────

/**
 * Exit codes, by what the caller should actually do about it.
 *
 *   0  success
 *   1  navigation or general failure — retry, then investigate
 *   2  selector miss — Whatnot changed their markup, update SELECTORS
 *   3  authentication expired or a bot challenge is in the way — a human has
 *      to sign in once (see `php artisan whatnot:login`)
 *   4  rate limited or temporarily refused — back off and retry later
 *
 * Kept distinct because the fix is different for each, and the log that
 * prompted this said "selectors didn't match" when the real page was a
 * Cloudflare interstitial. That sends you reading markup that never rendered.
 */
const EXIT = { OK: 0, GENERAL: 1, SELECTOR_MISS: 2, AUTH_REQUIRED: 3, RATE_LIMITED: 4 };

/**
 * What a page is really showing, when it is not what we asked for.
 *
 * Whatnot sits behind Cloudflare, which answers automated traffic with a short
 * interstitial instead of the page. It is recognisable by its own wording and
 * by being tiny — a few hundred characters where a real page is tens of
 * thousands.
 */
function classifyBlockingPage(url, bodyText) {
  const text = (bodyText || '').toLowerCase();

  const challenge = [
    'performing security verification',
    'checking your browser',
    'verify you are human',
    // Cloudflare's interactive (Turnstile) wording, as distinct from the static
    // interstitial above. "verifying" does not contain "verify", so the marker
    // above never matched it — only the Ray ID line did, by luck.
    'verifying you are human',
    'this may take a few seconds',
    'you are not a bot',
    'cf-browser-verification',
    'ray id',
    'attention required',
  ].some((marker) => text.includes(marker));

  if (challenge) {
    return {
      code: EXIT.AUTH_REQUIRED,
      error: 'BOT_CHALLENGE',
      message:
        'Cloudflare served a bot-protection challenge instead of the page. CURRENT_URL below ' +
        'separates the two causes. On /login the session has lapsed — renew it with ' +
        '`php artisan whatnot:login`. Anywhere else the session was accepted and the browser ' +
        'itself was challenged; what that does not say is why, since the network, the browser ' +
        'environment and some other client-side signal all produce the same page. The [nav] ' +
        'lines above narrow it: a challenge answering the document request directly is a ' +
        'different failure from one that replaced a page which had already loaded.',
    };
  }

  if (text.includes('too many requests') || text.includes('rate limit')) {
    return {
      code: EXIT.RATE_LIMITED,
      error: 'RATE_LIMITED',
      message: 'Whatnot is rate limiting this account. Back off and retry later.',
    };
  }

  // A near-empty body is the other shape bot protection takes: the challenge
  // renders through script we never execute, leaving nothing behind.
  if ((bodyText || '').trim().length < 100) {
    return {
      code: EXIT.AUTH_REQUIRED,
      error: 'EMPTY_PAGE',
      message:
        'Whatnot returned an effectively empty page (' + (bodyText || '').trim().length +
        ' characters), which is what its bot protection looks like when the challenge script ' +
        'does not run. Sign in once with `php artisan whatnot:login`.',
    };
  }

  return null;
}

/** Report a blocked page and stop, with the code that says what to do next. */
/**
 * The live browser context, for the exit paths.
 *
 * Chromium only writes its cookie store to disk on a clean close, and every
 * blocked exit here kills the process outright. So anything the run changed
 * about the profile — notably dropping an imported cf_clearance — was thrown
 * away, and the next run read the same stale token back off disk and failed the
 * same way. That is why dropping it appeared not to work.
 */
let liveContext = null;

/** Close the browser if it is open, so the profile keeps what this run learned. */
async function flushProfile() {
  if (! liveContext) return;

  const context = liveContext;
  liveContext = null;

  await context.close().catch(() => {});
}

function exitBlocked(blocked, url) {
  // Fire-and-forget on purpose: exitAfterStderr must stay synchronous, and a
  // close that hangs must not hold the process open. The common case finishes
  // in milliseconds, well inside the stderr write.
  flushProfile();

  exitAfterStderr(
    blocked.error + ': ' + blocked.message + '\n'
    + describeChallengeShape()
    + 'CURRENT_URL: ' + url + '\n',
    blocked.code,
  );
}

/**
 * What the main-frame navigations said about where the block was decided.
 *
 * Filled in by installNavigationLogging. Counting is enough — the individual
 * lines are already in the log, and what matters here is which of two shapes
 * they add up to.
 */
const navFindings = { documentRefusals: 0, challengeRoundTrips: 0, documentOk: 0 };

/**
 * The one sentence the [nav] lines are worth, written out.
 *
 * A refusal on the document request is decided before a line of page script
 * runs, so nothing about how this browser presents itself once loaded — its
 * WebGL strings, its platform, whether it is headless — took part in it. Only
 * the connection did: the address it came from, and what the handshake and
 * headers looked like. A page that loaded and was replaced afterwards is the
 * opposite, and would point back at the browser.
 *
 * Saying which one happened is the difference between a next step and a guess.
 */
function describeChallengeShape() {
  if (navFindings.documentRefusals === 0) return '';

  const rt = navFindings.challengeRoundTrips
    ? `, looping through ${navFindings.challengeRoundTrips} __cf_chl_rt_tk round-trip(s) without settling`
    : '';

  return 'CHALLENGE_SHAPE: the request for the page was itself refused — '
    + `${navFindings.documentRefusals} navigation response(s) came back 403/503${rt}. `
    + (navFindings.documentOk === 0
      ? 'No page ever loaded, so nothing this browser does after loading — its fingerprint, its '
        + 'scripts, whether it is headless — was involved in the decision. That happens at the '
        + 'connection: the address it came from, and what the TLS handshake and headers looked '
        + 'like. Test the address first: `php artisan whatnot:probe` makes the same request with '
        + 'no browser at all, and WHATNOT_PROXY routes it elsewhere.'
      : 'Some pages did load, so this is not a flat refusal of the connection — the edge changed '
        + 'its mind partway through the session.')
    + '\n';
}

// Cloudflare's managed challenge is not a wall — it is an interstitial that
// runs a few seconds of JS, sets cf_clearance, and reloads itself into the real
// page. The scraper navigated with waitUntil:'domcontentloaded' and read the DOM
// immediately, so it caught that interstitial mid-flight every time and called
// it a missing page. Waiting is the whole fix.
// Cloudflare's own cookies, none of which travel between machines.
//
// cf_clearance and __cf_bm are bound to the IP and User-Agent that earned them,
// so one exported from a laptop cannot be honoured from a server — and offering
// a clearance token that does not match the connection is what a replayed token
// looks like. The cf_chl_* pair record a challenge already in progress
// elsewhere, which is a state this browser is not in.
const CLOUDFLARE_EDGE_COOKIES = [
  'cf_clearance',
  '__cf_bm',
  '__cfwaitingroom',
  'cf_chl_2',
  'cf_chl_prog',
  'cf_chl_rc_i',
  'cf_chl_rc_ni',
  'cf_chl_rc_m',
];

const CHALLENGE_WAIT_MS = Number(process.env.WHATNOT_CHALLENGE_WAIT_MS || 45000);

/**
 * Body text, or null when the page is mid-navigation.
 *
 * The distinction matters: the challenge reloads itself, so evaluate() throws
 * "execution context was destroyed" exactly when it is working. Returning ''
 * there would read as an empty page and stop the wait one poll before it
 * succeeded.
 */
async function readBodyText(page, max = 2000) {
  try {
    return await page.evaluate(
      (m) => (document.body ? (document.body.innerText || '') : '').substring(0, m),
      max,
    );
  } catch {
    return null;
  }
}

function isChallengePage(bodyText) {
  const blocked = classifyBlockingPage('', bodyText);

  // Only a positively identified challenge counts. A near-empty body is also
  // what a React root looks like for the first moment after domcontentloaded,
  // and treating that as a block would fail every healthy navigation.
  return blocked !== null && blocked.error === 'BOT_CHALLENGE';
}

/**
 * Wait out a Cloudflare interstitial, if one is up.
 *
 * Returns true when the page is real. When the challenge outlasts the wait it
 * stops the run: continuing means scrolling, tab-hunting and DOM-probing a
 * verification page for another quarter of an hour before reporting the wrong
 * cause, which is exactly what a full sync used to do four times over.
 */
async function settleChallenge(page, { timeoutMs = CHALLENGE_WAIT_MS, fatal = true } = {}) {
  let body = await readBodyText(page);

  if (body !== null && !isChallengePage(body)) return true;

  const started = Date.now();
  let announced = false;

  while (Date.now() - started < timeoutMs) {
    await page.waitForTimeout(2000);
    body = await readBodyText(page);

    if (body === null) continue;          // reloading — that is the challenge working
    if (!isChallengePage(body)) {
      if (announced) {
        info('challenge cleared after', Math.round((Date.now() - started) / 1000) + 's');

        // Passing once only helps later runs if it left a clearance cookie
        // behind. If it did not, the next navigation starts the fight again.
        // Guarded because a page closed underneath us must not turn a win into
        // a crash on the way to reporting it.
        const context = page.context?.();
        if (context) await reportClearance(context);
      }

      return true;
    }

    if (!announced) {
      info('cloudflare interstitial — waiting up to', Math.round(timeoutMs / 1000) + 's for it to clear');
      announced = true;
    }
  }

  if (!announced) return true;            // never actually saw a challenge

  info('challenge did not clear within', Math.round(timeoutMs / 1000) + 's');

  if (fatal) {
    exitBlocked(
      classifyBlockingPage(page.url(), body) || {
        code: EXIT.AUTH_REQUIRED,
        error: 'BOT_CHALLENGE',
        message: 'Cloudflare held the browser on a verification page.',
      },
      page.url(),
    );
  }

  return false;
}

/**
 * Which saved session to bootstrap a fresh profile from.
 *
 * Two files can hold one: whatnot-cookies.json is what a human imported, and
 * whatnot-live-cookies.json is written from a live browser context on every
 * successful cookie-test. The live one is usually fresher, because Whatnot
 * rotates the session as you use it.
 *
 * That matters because the profile gets rebuilt whenever Chromium will not
 * start on it, which discards the refreshed session it had accumulated. Falling
 * back to a hand-imported export from hours earlier is a redirect to /login and
 * a re-export by hand — for cookies the scraper already had a newer copy of.
 *
 * An explicit WHATNOT_COOKIES_FILE always wins: naming a file means meaning it.
 */
function resolveCookiesFile() {
  if (process.env.WHATNOT_COOKIES_FILE) return process.env.WHATNOT_COOKIES_FILE;

  const fs = require('fs');
  const path = require('path');
  const bootstrap = path.join(__dirname, '../storage/whatnot-cookies.json');
  const live = path.join(__dirname, '../storage/whatnot-live-cookies.json');
  const mtime = (f) => (fs.existsSync(f) ? fs.statSync(f).mtimeMs : 0);

  return mtime(live) > mtime(bootstrap) ? live : bootstrap;
}

/**
 * Put the saved localStorage back after a profile is bootstrapped from file.
 *
 * Whatnot keeps session state in localStorage as well as in cookies, and a
 * persistent profile normally carries its own across runs — so this only
 * matters when the profile is new or was rebuilt. In that case cookies were
 * restored from file and localStorage was not, leaving the browser
 * half-authenticated in a way that looks like an expired session.
 *
 * localStorage is per-origin, so the page has to be on whatnot.com before any
 * of it can be written.
 */
async function restoreLocalStorage(page) {
  const fs = require('fs');
  const file = require('path').join(__dirname, '../storage/whatnot-localstorage.json');

  if (!fs.existsSync(file)) return 0;

  let saved;
  try {
    saved = JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (e) {
    info('localStorage file could not be read:', e.message);
    return 0;
  }

  const keys = Object.keys(saved || {});
  if (keys.length === 0) return 0;

  try {
    await page.evaluate((entries) => {
      for (const [k, v] of Object.entries(entries)) {
        try { localStorage.setItem(k, v); } catch { /* quota or blocked key */ }
      }
    }, saved);

    info('restored', keys.length, 'localStorage keys into the new profile');

    return keys.length;
  } catch (e) {
    info('localStorage could not be restored:', e.message);
    return 0;
  }
}

/**
 * Report whether this profile holds a Cloudflare clearance cookie.
 *
 * cf_clearance is what a browser receives for passing a challenge, and it is
 * bound to the IP and User-Agent that earned it — which is why one exported
 * from a laptop is worthless on a server. Its presence is the only direct
 * measure of whether the browser has ever actually got through: without it,
 * every run starts the fight from scratch. With it, the fight is already won
 * and stays won until it expires.
 */
async function reportClearance(context) {
  try {
    const cookies = await context.cookies('https://www.whatnot.com');
    const clearance = cookies.find((c) => c.name === 'cf_clearance');

    if (!clearance) {
      info('cf_clearance: absent — this profile has never passed a Cloudflare challenge');
      return false;
    }

    const minutes = clearance.expires > 0
      ? Math.round((clearance.expires * 1000 - Date.now()) / 60000)
      : null;

    info('cf_clearance: present, expires in', minutes === null ? 'session' : minutes + ' min');

    // Cloudflare issues clearance for the order of an hour. A token good for
    // months did not come from a challenge this profile passed — it came in
    // with an imported session, and it is bound to the address and user agent
    // that earned it, so from here it is at best ignored. Reported because
    // "cf_clearance: present" otherwise reads as reassurance.
    if (minutes !== null && minutes > 24 * 60) {
      info(
        'cf_clearance: that expiry is far longer than Cloudflare issues, so this token was almost',
        'certainly imported rather than earned here. It is bound to the address that earned it.',
        'Drop it with WHATNOT_DROP_CLEARANCE=1 to make this profile face the challenge on its own.',
      );
    }

    return true;
  } catch (e) {
    info('cf_clearance: could not be read —', e.message);
    return false;
  }
}

/**
 * Make every navigation on this page wait out a challenge before returning.
 *
 * Wrapping goto() beats editing twenty-five call sites and, more to the point,
 * covers the twenty-sixth.
 */
function installChallengeHandling(page) {
  const goto = page.goto.bind(page);

  page.goto = async (url, opts) => {
    const response = await goto(url, opts);
    await settleChallenge(page);

    return response;
  };
}

/** A whatnot.com URL with the origin stripped, for logging. */
function navPath(url) {
  return String(url || '').replace('https://www.whatnot.com', '').substring(0, 140) || '/';
}

/**
 * Log every main-frame navigation, and what answered it.
 *
 * Without this the log shows a page being asked for and, some time later, a
 * challenge — with nothing in between to say which of the two shapes happened:
 *
 *   • the page loaded and was replaced afterwards, once the app started
 *     fetching for itself, or
 *   • the request for it was answered with the challenge directly.
 *
 * They point at different causes, so guessing between them is how a diagnosis
 * ends up naming whichever one was already suspected. Navigation responses
 * carry the status — a 403 on the document is the second shape — and the
 * commit line shows where the frame actually ended up, redirects included.
 */
function installNavigationLogging(page) {
  page.on('response', (response) => {
    try {
      const request = response.request();

      if (!request.isNavigationRequest()) return;
      if (request.frame() !== page.mainFrame()) return;

      const status = response.status();

      // 403 and 503 are what Cloudflare answers a refused document with; the
      // challenge page is the body of that response, not a later redirect.
      if (status === 403 || status === 503) navFindings.documentRefusals++;
      else if (status < 400) navFindings.documentOk++;

      info(`[nav] ${status} ${request.method()} ${navPath(response.url())}`);
    } catch { /* the frame can go away mid-report; the log is not worth a crash */ }
  });

  page.on('framenavigated', (frame) => {
    try {
      if (frame !== page.mainFrame()) return;

      // Cloudflare's own retry token. Seeing it come round more than once or
      // twice means the challenge is being re-issued rather than passed.
      if (frame.url().includes('__cf_chl_rt_tk')) navFindings.challengeRoundTrips++;

      info(`[nav] committed → ${navPath(frame.url())}`);
    } catch {}
  });
}

const LOGIN_URL_RE = /\/(login|signin|auth)(\/|\?|$)/i;

// Text the Seller Hub renders for itself. Matching on what the hub says beats a
// structural selector here: the markup is generated class names that change
// between deploys, and these strings are the same ones the channel switch
// already waits on.
// Taken from the hub's own sidebar rather than guessed. Every one of these is
// a nav label that only renders once the Seller Hub is up, so matching any of
// them means the page is the hub and the session is in seller mode — which is
// the question ensureSellerMode was trying to answer the long way round.
const SELLER_HUB_MARKERS = [
  'Seller Hub', 'Account Health', 'Manage Shows', 'Seller Dashboard',
  'Rewards Club', 'Seller Resources', 'Shipments',
];

// How long to keep watching after the hub first renders. A challenge that
// arrives once the app starts fetching for itself lands a beat after the
// document is done, so a check that stops at "rendered" reports success and
// hands a challenged page to the next step.
const SELLER_HUB_SETTLE_MS = Number(process.env.WHATNOT_HUB_SETTLE_MS || 5000);

/**
 * Decide whether the Seller Hub is really up — after it has had a moment to
 * prove otherwise.
 *
 * `page.goto()` resolving means the document arrived. It does not mean the app
 * mounted, that the redirect chain has finished, or that the page will still be
 * there a second later. Reading the URL at that instant and announcing "seller
 * hub reached" was therefore a claim about the document, reported as a claim
 * about the session — and when the challenge landed during the step after this
 * one, that step got the blame.
 *
 * Returns why it failed rather than a bare false, because "the session lapsed"
 * and "the browser was challenged" need opposite responses.
 *
 * @returns {Promise<{ok: boolean, reason: string, url: string, body?: string}>}
 */
async function confirmSellerHub(page, { settleMs = SELLER_HUB_SETTLE_MS } = {}) {
  // Decisive on its own, and nothing is gained by waiting for a login page to
  // turn into something else.
  if (LOGIN_URL_RE.test(page.url())) {
    return { ok: false, reason: 'login', url: page.url() };
  }

  const seesHub = () => page.evaluate(
    (markers) => {
      const text = document.body ? (document.body.innerText || '') : '';
      return markers.some((marker) => text.includes(marker));
    },
    SELLER_HUB_MARKERS,
  ).catch(() => false);

  await page.waitForFunction(
    (markers) => {
      const text = document.body ? (document.body.innerText || '') : '';
      return markers.some((marker) => text.includes(marker));
    },
    SELLER_HUB_MARKERS,
    { timeout: 15000 },
  ).catch(() => {});

  // Then stand still and look again. This is the whole point of the function:
  // anything that replaces the hub does it here.
  await page.waitForTimeout(settleMs);

  let body = await readBodyText(page);

  if (body !== null && isChallengePage(body)) {
    // An interstitial that clears itself is not a failure, so give it the same
    // wait every navigation gets before calling it one.
    info('seller hub check: challenge appeared after the page loaded — waiting for it');

    if (await settleChallenge(page, { fatal: false })) {
      body = await readBodyText(page);
    } else {
      return { ok: false, reason: 'challenge', url: page.url(), body };
    }
  }

  if (LOGIN_URL_RE.test(page.url())) {
    return { ok: false, reason: 'login', url: page.url() };
  }

  if (body !== null && isChallengePage(body)) {
    return { ok: false, reason: 'challenge', url: page.url(), body };
  }

  return await seesHub()
    ? { ok: true, reason: 'hub', url: page.url() }
    : { ok: false, reason: 'unrecognised', url: page.url(), body };
}

async function performLogin(page, email, password) {
  log('navigating to login page');
  await page.goto(URLS.login, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await debugShot(page, '01-login-page');

  // Wait for React to render at least some content before checking for the form
  await page.waitForFunction(
    () => (document.body.innerText || '').trim().length > 30,
    { timeout: 10000 }
  ).catch(() => {});

  const currentUrl = page.url();
  info('performLogin: URL after goto:', currentUrl);

  // Already past the login page (session cookie still valid) — goto(/login) redirected away
  if (!currentUrl.includes('/login') && !currentUrl.includes('/signin') && !currentUrl.includes('/auth')) {
    info('performLogin: already logged in, redirected to', currentUrl);
    return;
  }

  const emailInput = await page.waitForSelector(SELECTORS.loginEmailInput, { timeout: 15000 })
    .catch(() => null);

  if (!emailInput) {
    const bodyText = await page.evaluate(() => (document.body.innerText || '').trim()).catch(() => '');
    info('performLogin: login form not found — URL:', currentUrl, '| body chars:', bodyText.length);

    // Almost always a challenge rather than a markup change. Saying "selectors
    // didn't match" here sent the last investigation looking for a form that
    // was never served.
    const blocked = classifyBlockingPage(currentUrl, bodyText);

    if (blocked) {
      info('performLogin:', blocked.error, '—', blocked.message);
      exitBlocked(blocked, currentUrl);
    }

    info('performLogin: body preview:', bodyText.substring(0, 300));
    throw new Error('Login form not found on ' + currentUrl + '. Page may have changed. Body: ' + bodyText.substring(0, 200));
  }

  info('filling login form — email length:', email.length, '| password length:', password.length);
  await emailInput.click();
  await emailInput.fill(email);
  await page.keyboard.press('Tab');
  await page.waitForTimeout(500);

  const passInput = await page.waitForSelector(SELECTORS.loginPasswordInput, { timeout: 10000 })
    .catch(() => null);

  if (!passInput) {
    await debugShot(page, '02-no-password-field');
    throw new Error('Password field not found after entering email. Whatnot may require a different login flow (phone number, magic link, etc.).');
  }

  await passInput.fill(password);
  await debugShot(page, '03-credentials-filled');

  const submitBtn = await page.$(SELECTORS.loginSubmitBtn);
  if (submitBtn) {
    await submitBtn.click();
  } else {
    await page.keyboard.press('Enter');
  }

  log('waiting for navigation after login');
  await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 })
    .catch(() => log('navigation timeout — continuing anyway'));

  await debugShot(page, '04-post-login');

  const url = page.url();
  info('post-login URL:', url);
  if (url.includes('/login') || url.includes('/signin') || url.includes('/auth') || url.includes('/verify')) {
    // innerText (not textContent) — textContent includes raw <script> tag
    // source, so a page still showing unrendered bot-detection loader scripts
    // (Kasada/Source Defense) gets misread as real page text, and a stray word
    // like "invalid" buried in that JS spuriously triggers the wrong branch
    // below. innerText only reflects what's actually rendered/visible.
    const pageText = await page.evaluate(() => (document.body.innerText || '').trim()).catch(() => '');
    const snippet  = pageText.replace(/\s+/g, ' ').trim().substring(0, 400);
    info('post-login page text:', snippet);

    if (pageText.length < 100) {
      await debugShot(page, 'post-login-blocked');
      throw new Error(
        `Login page never rendered real content after submitting (still on ${url}, only ${pageText.length} visible chars). ` +
        `This is almost always bot-detection (Kasada/Source Defense) blocking the automated login, not a bad password. ` +
        `Use a cookie bootstrap instead: php artisan whatnot:login --cookie-file=<cookies exported from a real logged-in browser session>.`
      );
    }
    if (pageText.toLowerCase().includes('incorrect') || pageText.toLowerCase().includes('invalid') ||
        pageText.toLowerCase().includes('wrong') || pageText.toLowerCase().includes('not found')) {
      throw new Error(`Login failed — credentials rejected by Whatnot. Page said: ${snippet.substring(0, 200)}`);
    }
    if (pageText.toLowerCase().includes('verify') || pageText.toLowerCase().includes('code') ||
        pageText.toLowerCase().includes('2fa') || pageText.toLowerCase().includes('authenticat')) {
      throw new Error('Login requires 2FA/verification. Disable 2FA on the Whatnot account or use the cookie bootstrap (storage/whatnot-cookies.json).');
    }
    throw new Error(`Login did not complete — still on ${url}. Page text: ${snippet.substring(0, 200)}`);
  }
}

// ── Seller mode activation ────────────────────────────────────────────────────
// Whatnot accounts distinguish buyer mode and seller mode. After login the
// session may land in buyer mode where /seller shows a marketing page and
// /seller/shows returns 404.
//
// The logged-in user (e.g. 65170453) is a TEAM MEMBER of a seller channel
// (e.g. 408585 = Vortex Breaks). To activate seller mode the session must
// switch to that team channel — NOT click "Start Selling" which starts a new
// seller application for the individual user.
//
// Strategy 1: Click the "Switch to Selling" nav element. Whatnot renders it as
//   a React component (not always a <button>/<a>), so use getByText().
// Strategy 2: Use switchToChannel() — opens the profile drawer, clicks
//   Switch Role, then selects the channel by name. Requires CHANNEL_NAME.
//
// If the account is already in seller mode this is a no-op.
// Throws if buyer mode is detected but can't be exited.

async function ensureSellerMode(page) {
  // For a channel-scoped run, do not navigate to Seller Hub merely to prove
  // seller mode. The role picker on the already-authenticated Whatnot shell is
  // enough to establish seller context, and switchToChannel verifies the active
  // username before any scrape is allowed to continue. This keeps attribution
  // fail-closed while avoiding an unnecessary /dashboard/home Cloudflare gate.
  if (CHANNEL_NAME) {
    info(`ensureSellerMode: channel requested — switching directly from current authenticated page to @${CHANNEL_NAME}`);
    await switchToChannel(page, CHANNEL_NAME);
    global._sellerModeActive = true;
    return;
  }

  const pageText = await page.evaluate(() =>
    (document.body.innerText || '').substring(0, 600)
  ).catch(() => '');

  // "For Brands / Start Selling on Whatnot" = the buyer-mode marketing page.
  if (!/for brands|start selling on whatnot/i.test(pageText)) return;

  info('ensureSellerMode: buyer mode detected — opening nav drawer to click "Switch to Selling"');
  await debugShot(page, 'seller-mode-01-buyer-mode');

  // ── Step 1: the hub, then the homepage ──────────────────────────────────
  //
  // This went straight to the homepage, which renders the logged-in nav — and
  // is also the one path Cloudflare never serves here. Every run ended in that
  // navigation, looping through challenge tokens until it gave up.
  //
  // /dashboard/home carries the same nav and is served, so it is tried first.
  // The homepage stays as a fallback rather than being removed: on an account
  // where the hub is genuinely unavailable it is the only place the drawer
  // exists, and failing there is no worse than not trying.
  info('ensureSellerMode: navigating to the seller hub');
  const hubResponse = await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null);

  if (! hubResponse || hubResponse.status() >= 400) {
    info('ensureSellerMode: hub unavailable — falling back to the homepage');
    await page.goto('https://www.whatnot.com', { waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => {});
  }
  await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});
  await debugShot(page, 'seller-mode-02-homepage');

  // ── Step 2: open the hamburger / nav drawer ───────────────────────────────────
  // Confirmed (run #4): button[aria-label*="menu"] opens the nav drawer that has
  // "Switch to Selling" at the very top. The active role is already "vortexbreaks"
  // so no role switch is needed — just click "Switch to Selling" directly.
  const menuBtn = page.locator('button[aria-label*="menu" i]').first();
  if (await menuBtn.isVisible().catch(() => false)) {
    info('ensureSellerMode: opening nav drawer via menu button');
    await menuBtn.click({ force: true, timeout: 8000 }).catch(async () => {
      await menuBtn.evaluate(el => el.click()).catch(() => {});
    });
    await page.waitForTimeout(1500);
    await debugShot(page, 'seller-mode-03-drawer-open');
  } else {
    info('ensureSellerMode: menu button not found — dumping page text');
    const t = await page.evaluate(() => (document.body.innerText || '').substring(0, 400)).catch(() => '');
    info('ensureSellerMode: page text:', t);
    await debugShot(page, 'seller-mode-03-no-menu-btn');
  }

  // ── Step 3: click the "Switch to Selling" anchor ────────────────────────────
  // The JS click sets the seller session cookie but does NOT trigger React Router
  // navigation (URL stays at /). We click, wait briefly, then navigate directly.
  info('ensureSellerMode: locating "Switch to Selling" <a> anchor in drawer');
  const drawerText = await page.evaluate(() => (document.body.innerText || '').substring(0, 600)).catch(() => '');
  if (!/switch to selling/i.test(drawerText)) {
    info('ensureSellerMode: "Switch to Selling" not in drawer text — this account has no individual seller channel of its own');
    await debugShot(page, 'seller-mode-03b-no-switch-to-selling');

    // Some accounts are team members ONLY — no personal seller channel, so
    // "Switch to Selling" never appears in the drawer. Fall back to Strategy 2:
    // open the role switcher directly and select the target channel by name.
    if (CHANNEL_NAME) {
      info(`ensureSellerMode: falling back to switchToChannel("${CHANNEL_NAME}")`);
      await page.keyboard.press('Escape').catch(() => {});
      await switchToChannel(page, CHANNEL_NAME);
      global._sellerModeActive = true;
      return;
    }

    throw new Error(
      '"Switch to Selling" not found in open nav drawer, and WHATNOT_CHANNEL_NAME is not set to fall back to Switch Role.\n' +
      'Drawer text: ' + drawerText.substring(0, 300)
    );
  }

  info('ensureSellerMode: JS-clicking <a> "Switch to Selling"');
  const anchorFound = await page.evaluate(() => {
    const anchors = Array.from(document.querySelectorAll('a'));
    const target   = anchors.find(a => /switch\s+to\s+selling/i.test((a.textContent || '').trim()));
    if (!target) return false;
    target.click();
    return true;
  }).catch(() => false);

  if (!anchorFound) {
    info('ensureSellerMode: no <a> anchor found, falling back to Playwright locator click');
    const switchLoc = page.getByText('Switch to Selling', { exact: false }).first();
    await switchLoc.click({ force: true, timeout: 10000 }).catch(async () => {
      await switchLoc.evaluate(el => {
        let node = el;
        for (let i = 0; i < 8; i++) {
          if (!node) break;
          if (node.tagName === 'A' || node.tagName === 'BUTTON') { node.click(); return; }
          node = node.parentElement;
        }
        el.click();
      }).catch(() => {});
    });
  }

  // Brief pause for the session cookie to be set, then navigate to /dashboard.
  // React Router navigation never fires from this click (URL stays at /), so we
  // always go directly — no point waiting for a URL change that won't happen.
  await page.waitForTimeout(500);
  info('ensureSellerMode: navigating to /dashboard to confirm seller mode');
  await page.goto('https://www.whatnot.com/dashboard', { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  await debugShot(page, 'seller-mode-04-dashboard-verify');

  const dashText = await page.evaluate(() => (document.body.innerText || '').substring(0, 400)).catch(() => '');
  info('ensureSellerMode: /dashboard text:', dashText.substring(0, 200));

  if (/switch to buying/i.test(dashText) || !/for brands|start selling on whatnot|switch to selling/i.test(dashText)) {
    info('ensureSellerMode: seller mode confirmed via /dashboard content');
    global._sellerModeActive = true;
    return;
  }

  throw new Error(
    '"Switch to Selling" clicked but /dashboard still shows buyer content.\n' +
    'URL: ' + page.url() + '\n' +
    'Text: ' + dashText.substring(0, 300)
  );
}

// ── Role / channel switcher ───────────────────────────────────────────────────
// Whatnot's navigation sidebar (the slide-out drawer) contains a "Switch Role"
// item (an h4 with that text, class ogVNN, inside a div.eCoev). Clicking it
// shows a list of the available channels. This function opens the sidebar,
// clicks Switch Role, then clicks the target channel by name.
//
// The sidebar is opened from a nav/header button (profile avatar or hamburger).
// Run with WHATNOT_DEBUG=1 to capture screenshots if the switch doesn't work.

// How long a channel switch may take before we fail the requested channel.
// Never continue scraping after this timeout: doing so can attribute the active
// channel's data to a different requested channel.
const SWITCH_CHANNEL_TIMEOUT_MS = Number(process.env.WHATNOT_SWITCH_TIMEOUT_MS || 75000);

async function switchToChannel(page, channelName) {
  if (!channelName) return;
  log(`switching to channel: "${channelName}"`);

  await debugShot(page, 'role-switch-01-pre');
  info('switchToChannel: current URL before switch:', page.url());

  // Do not let challenge detection consume the entire channel-switch budget.
  // On a healthy rendered Seller Hub, page.evaluate() has occasionally stalled
  // here even though the DOM is already usable. First inspect body text through
  // a locator with its own timeout; only invoke the longer challenge waiter when
  // the page positively looks like a Cloudflare interstitial.
  const switchBody = await page.locator('body').innerText({ timeout: 3000 }).catch(() => null);
  if (switchBody !== null && isChallengePage(switchBody.substring(0, 2000))) {
    // The auth preflight may have just cleared a Cloudflare challenge, but the
    // interstitial DOM can linger for a few seconds while the navigation token
    // settles. Six seconds was too short in production (the same challenge
    // cleared after ~12s immediately beforehand), so give the switcher a bounded
    // but realistic window before failing the requested channel.
    const switchChallengeWaitMs = Number(process.env.WHATNOT_SWITCH_CHALLENGE_WAIT_MS || 20000);
    info(`switchToChannel: Cloudflare interstitial detected before role switch — waiting up to ${Math.round(switchChallengeWaitMs / 1000)}s`);
    const challengeResult = await Promise.race([
      settleChallenge(page, { timeoutMs: switchChallengeWaitMs, fatal: false }),
      new Promise((resolve) => setTimeout(() => resolve(false), switchChallengeWaitMs + 1000)),
    ]);
    if (!challengeResult) {
      throw new Error(`CHANNEL_SWITCH_CHALLENGE: requested=@${channelName} could not clear verification within ${Math.round(switchChallengeWaitMs / 1000)}s`);
    }
    await page.waitForTimeout(500);
  } else if (switchBody === null) {
    info('switchToChannel: body inspection timed out; continuing with bounded DOM selectors');
  } else {
    info('switchToChannel: Seller Hub body is usable; skipping redundant challenge wait');
  }

  // Current Whatnot UI exposes everything we need directly: the active profile
  // avatar has alt="<username>", Switch Role has a stable id, and each role row
  // contains img.z-avatar-image[alt="<username>"]. Use that exact path first
  // instead of enumerating a dozen generic profile/menu selectors.
  const SWITCH_ROLE_SEL = '#team-invite-switch-role-anchor, div.eCoev h4.ogVNN';

  // Current Whatnot markup gives the profile trigger a stable id. Click the button
  // itself rather than the nested image so React receives the event on the element
  // that owns the menu handler.
  const directProfileButton = page.locator('#team-invite-profile-menu-anchor').first();
  if (await directProfileButton.isVisible({ timeout: 2500 }).catch(() => false)) {
    const activeBeforeDirect = await getActiveChannelUsername(page);
    info(`switchToChannel: direct path clicking #team-invite-profile-menu-anchor${activeBeforeDirect ? ` active=@${activeBeforeDirect}` : ''}`);

    await directProfileButton.click({ force: true, timeout: 3000 }).catch(async () => {
      await directProfileButton.evaluate(el => el.click()).catch(() => {});
    });

    const directSwitchRole = page.locator('#team-invite-switch-role-anchor').first();
    if (await directSwitchRole.isVisible({ timeout: 5000 }).catch(() => false)) {
      info('switchToChannel: direct path clicking #team-invite-switch-role-anchor');
      await directSwitchRole.click({ force: true, timeout: 3000 }).catch(async () => {
        await directSwitchRole.evaluate(el => el.click()).catch(() => {});
      });

      const targetAlt = channelName.toLowerCase();
      const directTarget = page.locator(`button:has(img.z-avatar-image[alt="${targetAlt}"])`).first();
      if (await directTarget.isVisible({ timeout: 5000 }).catch(() => false)) {
        info(`switchToChannel: direct path clicking target role @${channelName}`);

        // Whatnot currently redirects successful role switches to
        // /dashboard/home?toast=switched-roles. On this VPS that exact GET is
        // challenged by Cloudflare even though a clean /dashboard/home is 200.
        // Let the role-switch mutation itself happen, but suppress only that
        // toast navigation and immediately reload the clean Seller Hub route.
        // This avoids turning a successful account switch into an endless 403
        // challenge loop while still requiring a positive active-account check.
        let switchedRoleRedirectSeen = false;
        const switchedRoleRoutePattern = '**/dashboard/home?toast=switched-roles*';
        const suppressSwitchedRoleRedirect = async (route) => {
          const req = route.request();
          if (req.isNavigationRequest() && req.method() === 'GET') {
            switchedRoleRedirectSeen = true;
            info('switchToChannel: intercepted post-switch toast navigation; reloading clean Seller Hub route instead');
            await route.abort().catch(() => {});
            return;
          }
          await route.continue().catch(() => {});
        };

        await page.route(switchedRoleRoutePattern, suppressSwitchedRoleRedirect).catch(() => {});
        try {
          await directTarget.click({ force: true, timeout: 3000 }).catch(async () => {
            await directTarget.evaluate(el => el.click()).catch(() => {});
          });

          // Give the switch-role request/cookie update a moment to finish. If the
          // normal toast redirect fired, our route handler has already blocked it.
          await page.waitForTimeout(1200);
        } finally {
          await page.unroute(switchedRoleRoutePattern, suppressSwitchedRoleRedirect).catch(() => {});
        }

        if (switchedRoleRedirectSeen) {
          await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 15000 }).catch((e) => {
            info('switchToChannel: clean Seller Hub reload after role switch failed:', e.message);
          });
          await page.waitForTimeout(1200);
        }

        // If Whatnot changes the redirect behavior, verification below still
        // fails closed. Never accept the toast redirect alone as proof that the
        // requested account is active.
        const verifiedDirect = await waitForActiveChannel(page, channelName, Math.min(SWITCH_CHANNEL_TIMEOUT_MS, 20000));
        await debugShot(page, 'role-switch-04-verified-direct');
        info(`switchToChannel: VERIFIED requested=@${channelName} active=@${verifiedDirect} URL=${page.url()} (direct path)`);
        return verifiedDirect;
      }

      info(`switchToChannel: direct path could not find target role @${channelName}; falling back to generic role-picker logic`);
      await page.keyboard.press('Escape').catch(() => {});
    } else {
      info('switchToChannel: profile menu button click did not expose #team-invite-switch-role-anchor; falling back to generic trigger scan');
    }
  } else {
    info('switchToChannel: #team-invite-profile-menu-anchor not visible; falling back to generic trigger scan');
  }

  // The Switch Role button is inside the profile drawer — invisible until
  // the avatar button in the top-nav is clicked. Try the avatar first, then
  // fall back to other nav triggers.
  const isInViewport = async (el) => {
    if (!el) return false;
    return el.evaluate(node => {
      const r = node.getBoundingClientRect();
      return r.top >= 0 && r.bottom <= window.innerHeight && r.width > 0 && r.height > 0;
    }).catch(() => false);
  };

  // Step 1 — open the profile drawer via the avatar BUTTON
  // Only use button selectors here — <a> tags navigate instead of opening drawers.
  // Confirmed July 2026: button:has([style*="--avatar-size"]) opens the seller sidebar
  // and triggers seller GraphQL calls even in buyer mode on the logged-in homepage.
  const avatarTriggers = [
    '#team-invite-profile-menu-anchor',
    // Confirmed July 2026 (real markup): the actual top-nav trigger is a bare
    // <img alt="avatar" src="..."> with no wrapping button/aria-label/data-testid —
    // none of the selectors below matched it. Click the image directly; the click
    // bubbles to whatever handler (self or ancestor) opens the drawer.
    'img.z-avatar-image[alt][width="40"][height="40"]',
    'img[alt="avatar"]',
    'button:has([style*="--avatar-size"])',
    '[data-testid*="avatar"]',
    '[data-testid*="profile"]',
    'button[aria-label*="profile" i]',
    'button[aria-label*="account" i]',
    // Radix/headless menu triggers expose aria-haspopup — the profile menu that
    // holds the "Switch Role" item is one of these.
    'button[aria-haspopup="menu"]',
    'button[aria-haspopup="dialog"]',
    'button[aria-haspopup="true"]',
    // The top-nav avatar is a button wrapping an <img>.
    'header button:has(img)',
    'nav button:has(img)',
    '[aria-label="Open navigation drawer"]',
    '[aria-label="Open sidebar"]',
    'button[aria-label*="menu" i]',
  ];

  let drawerOpened = false;

  // Try the drawer twice. Whatnot's profile trigger occasionally ignores the first
  // synthetic click even though the Seller Hub is otherwise healthy. On the second
  // attempt, reset to the stable Seller Hub route and rebuild fresh element handles;
  // stale handles from the first render must never be re-used after navigation.
  for (let drawerAttempt = 1; drawerAttempt <= 2 && !drawerOpened; drawerAttempt++) {
    if (drawerAttempt > 1) {
      info(`switchToChannel: drawer attempt ${drawerAttempt}/2 — resetting Seller Hub before retry`);
      await page.keyboard.press('Escape').catch(() => {});
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 12000 }).catch(() => {});
      await page.waitForTimeout(1000);
      await debugShot(page, `role-switch-01-retry-${drawerAttempt}`);
    }

    // Try each fixed selector, plus a sweep over every aria-haspopup button, and
    // after each click check specifically for the Switch Role anchor appearing.
    const triggerHandles = [];
    const seenTriggers = new Set();
    for (const sel of avatarTriggers) {
      const h = await page.locator(sel).first().elementHandle({ timeout: 1200 }).catch(() => null);
      if (h) triggerHandles.push({ sel, h });
    }
    for (const extra of await page.$$('button[aria-haspopup], [role="button"][aria-haspopup]').catch(() => [])) {
      triggerHandles.push({ sel: 'sweep:aria-haspopup', h: extra });
    }

    for (const { sel, h: trigger } of triggerHandles) {
      if (!trigger || !await trigger.isVisible().catch(() => false)) continue;

      // A single DOM element may match several selectors above. Avoid clicking the
      // same element repeatedly during one attempt; that can open then immediately
      // close a menu and make the switcher look flaky.
      const triggerKey = await trigger.evaluate((el) => {
        const tag = el.tagName || '';
        const aria = el.getAttribute('aria-label') || '';
        const testid = el.getAttribute('data-testid') || '';
        const src = el.getAttribute('src') || '';
        const text = (el.innerText || el.textContent || '').trim().substring(0, 80);
        return [tag, aria, testid, src, text].join('|');
      }).catch(() => sel);
      if (seenTriggers.has(triggerKey)) continue;
      seenTriggers.add(triggerKey);

      info(`switchToChannel: drawer attempt ${drawerAttempt}/2 clicking avatar/nav trigger:`, sel);
      await trigger.click({ force: true, timeout: 4000 }).catch(async () => {
        await trigger.evaluate(el => el.click()).catch(() => {});
      });
      await page.waitForTimeout(900);

      // Check if drawer is open: Switch Role element in DOM OR "Switch Role" text visible.
      // Don't require viewport — it may be below the fold in a tall sidebar.
      const btnAfterClick = await page.$(SWITCH_ROLE_SEL).catch(() => null);
      const textAfterClick = await page.getByText('Switch Role', { exact: false }).first().isVisible().catch(() => false);
      if (btnAfterClick || textAfterClick) {
        info(`switchToChannel: drawer opened on attempt ${drawerAttempt}/2 after clicking`, sel, '— Switch Role found in DOM');
        drawerOpened = true;
        break;
      }

      // Dismiss and try the next distinct trigger.
      await page.keyboard.press('Escape').catch(() => {});
      await page.waitForTimeout(250);
    }
  }

  if (!drawerOpened) {
    info('switchToChannel: both drawer attempts failed — checking for Switch Role directly before failing closed');
  }

  // Step 2 — click Switch Role (by ID if available, then by text, then JS-click)
  await debugShot(page, 'role-switch-02-drawer-open');

  const switchBtn = await page.$(SWITCH_ROLE_SEL).catch(() => null);
  const switchRoleByText = page.getByText('Switch Role', { exact: false }).first();
  const switchRoleVisible = await switchRoleByText.isVisible().catch(() => false);

  if (switchRoleVisible) {
    info('switchToChannel: clicking "Switch Role" by text');
    await switchRoleByText.click({ force: true, timeout: 8000 }).catch(async () => {
      await switchRoleByText.evaluate(el => el.click()).catch(() => {});
    });
  } else if (switchBtn) {
    info('switchToChannel: JS-clicking Switch Role by selector (may be off-viewport)');
    await page.evaluate(sel => {
      const el = document.querySelector(sel);
      if (el) el.click();
    }, SWITCH_ROLE_SEL);
  } else {
    info('switchToChannel: WARNING — Switch Role not found — dumping interactive elements to locate the profile/role switcher');
    // Dump every button/link/menuitem with its label so we can see the real
    // avatar trigger and channel-switch controls in the current Whatnot UI.
    const controls = await page.evaluate(() => {
      const out = [];
      const els = document.querySelectorAll('button, a[href], [role="button"], [role="menuitem"], [aria-haspopup]');
      for (const el of els) {
        const label = (el.getAttribute('aria-label') || el.innerText || el.textContent || '').trim().replace(/\s+/g, ' ').substring(0, 40);
        const href  = el.getAttribute('href') || '';
        const role  = el.getAttribute('role') || '';
        const pop   = el.getAttribute('aria-haspopup') || '';
        if (label || href || pop) out.push({ tag: el.tagName, label, href, role, pop });
      }
      // De-dup and keep it readable
      const seen = new Set();
      return out.filter(o => { const k = o.tag + o.label + o.href; if (seen.has(k)) return false; seen.add(k); return true; }).slice(0, 60);
    }).catch(() => []);
    info('switchToChannel: controls:', JSON.stringify(controls));
    // Also dump anything mentioning the channel name or "switch"/"account"/"role".
    const hits = await page.evaluate((name) => {
      const rx = new RegExp(name.replace(/[^a-z0-9]/gi, '') + '|switch|account|role|profile', 'i');
      const out = [];
      for (const el of document.querySelectorAll('*')) {
        if (el.childElementCount > 0) continue;
        const t = (el.innerText || el.textContent || '').trim();
        if (t && t.length < 40 && rx.test(t)) out.push({ tag: el.tagName, text: t, cls: (el.className || '').toString().substring(0, 30) });
        if (out.length > 40) break;
      }
      return out;
    }, channelName).catch(() => []);
    info('switchToChannel: role/channel text hits:', JSON.stringify(hits));
    await debugShot(page, 'role-switch-no-switch-role');
    const current = await getActiveChannelUsername(page);
    throw new Error(
      `CHANNEL_SWITCH_FAILED: could not find "Switch Role". requested=@${channelName} active=@${current || '?'}`
    );
  }
  await page.waitForTimeout(2000);
  await debugShot(page, 'role-switch-03-role-list');

  // ── Click the target channel ──────────────────────────────────────────────────
  // Dump visible text so we can diagnose what the role picker shows.
  const roleListText = await page.evaluate(() => (document.body.innerText || '').substring(0, 600)).catch(() => '');
  info('switchToChannel: role list text:', roleListText.substring(0, 400));

  // Confirmed July 2026 (real markup): the account switcher is a plain HTML form
  // (POST /api/v1/auth/switch-role), one <button formaction="/api/v1/auth/switch-role"
  // name="id" value="<globalId>"> per account, with the username shown lowercase and
  // unspaced (e.g. "vortexbreaks", not "Vortex Breaks"). Match on that directly instead
  // of guessing spaced/camelCase variants — case-insensitive since getByText is
  // case-sensitive by default and the UI casing may not match whatever the DB has.
  const SWITCH_ROLE_BUTTON_SEL = 'button[formaction="/api/v1/auth/switch-role"]';
  const targetKey = channelName.replace(/[^a-z0-9]/gi, '').toLowerCase();

  let target = null;
  const exactAvatar = page.locator(`button:has(img.z-avatar-image[alt="${channelName.toLowerCase()}"])`).first();
  if (await exactAvatar.isVisible().catch(() => false)) {
    info(`switchToChannel: found exact role button by avatar alt="${channelName.toLowerCase()}"`);
    target = exactAvatar;
  }

  if (!target) {
    const roleButtons = await page.$$(SWITCH_ROLE_BUTTON_SEL).catch(() => []);
    for (const btn of roleButtons) {
      const btnText = await btn.innerText().catch(() => '');
      if (btnText.replace(/[^a-z0-9]/gi, '').toLowerCase().includes(targetKey)) {
        info(`switchToChannel: found channel option matching "${channelName}" (button text: ${btnText.replace(/\s+/g, ' ').trim()})`);
        target = btn;
        break;
      }
    }
  }

  // Fall back to the old spaced/camelCase text-match in case the account-switcher
  // markup isn't the one currently sampled (older accounts, different UI variant).
  if (!target) {
    const nameVariants = [...new Set([
      channelName,
      channelName.replace(/([a-z])([A-Z])/g, '$1 $2'),   // VortexBreaks → Vortex Breaks
      channelName.replace(/([A-Z])/g, ' $1').trim(),      // ABCBreaks → A B C Breaks
    ])];
    for (const variant of nameVariants) {
      const loc = page.getByText(new RegExp(variant.replace(/[^a-z0-9]/gi, '.?'), 'i')).first();
      if (await loc.isVisible().catch(() => false)) {
        info(`switchToChannel: found channel option matching "${variant}" (fallback text search)`);
        target = loc;
        break;
      }
    }
  }

  if (!target) {
    await debugShot(page, 'role-switch-failed-channel');
    info(`switchToChannel: WARNING — channel "${channelName}" not found among switch-role buttons or in role list`);
    info('switchToChannel: role list text was:', roleListText.substring(0, 300));
    const current = await getActiveChannelUsername(page);
    throw new Error(
      `CHANNEL_SWITCH_FAILED: requested channel "${channelName}" was not found in the role picker. active=@${current || '?'}`
    );
  }

  info(`switchToChannel: clicking channel option`);
  await target.click({ force: true, timeout: 8000 }).catch(async () => {
    await target.evaluate(el => el.click()).catch(() => {});
  });

  // Clicking a role is not success. Only the requested @username becoming
  // active is success; generic Seller Hub text only proves seller mode.
  const verifiedChannel = await waitForActiveChannel(
    page,
    channelName,
    Math.min(SWITCH_CHANNEL_TIMEOUT_MS, 12000),
  );

  await debugShot(page, 'role-switch-04-verified');
  info(`switchToChannel: VERIFIED requested=@${channelName} active=@${verifiedChannel} URL=${page.url()}`);
  return verifiedChannel;
}

// ── Detect the currently-active seller channel ────────────────────────────────
// The seller dashboard nav renders the active channel as an "@username" anchor
// pointing at /user/<username>. We read it to decide whether a channel switch is
// actually needed — ensureSellerMode always lands on the account's PRIMARY
// channel, so for any other channel we must still switch even though seller mode
// is already active.
async function getActiveChannelUsername(page) {
  const currentRole = page.locator('button:has(svg[aria-label="Current account"]) img.z-avatar-image[alt]').first();
  const currentRoleAlt = await currentRole.getAttribute('alt', { timeout: 1000 }).catch(() => null);
  if (currentRoleAlt) return currentRoleAlt;

  const visibleAvatars = page.locator('img.z-avatar-image[alt][width="40"][height="40"]:visible');
  const visibleAvatarCount = Math.min(await visibleAvatars.count().catch(() => 0), 10);
  if (visibleAvatarCount === 1) {
    const alt = await visibleAvatars.first().getAttribute('alt', { timeout: 1000 }).catch(() => null);
    if (alt) return alt;
  }

  // Prefer identity links in seller navigation, but do not require the visible
  // text to start with @. Whatnot's current dashboard sometimes renders only an
  // avatar/name while keeping the /user/<username> href.
  const navUser = await page.locator(
    'header a[href^="/user/"], nav a[href^="/user/"], aside a[href^="/user/"]'
  ).first().getAttribute('href', { timeout: 2000 }).catch(() => null);
  if (navUser) {
    const m = navUser.match(/^\/user\/([^/?#]+)/);
    if (m) return m[1];
  }

  // Fallback to any /user/ anchor whose own text names the account. Avoid an
  // unbounded page.evaluate() here: a wedged renderer must not eat the switch
  // timeout and hide where the failure actually occurred.
  const links = page.locator('a[href^="/user/"]');
  const count = Math.min(await links.count().catch(() => 0), 30);
  for (let i = 0; i < count; i++) {
    const a = links.nth(i);
    const href = await a.getAttribute('href', { timeout: 1000 }).catch(() => null);
    if (!href) continue;
    const m = href.match(/^\/user\/([^/?#]+)/);
    if (!m) continue;
    const text = (await a.innerText({ timeout: 1000 }).catch(() => '')).trim();
    if (text.startsWith('@') || normalizeChannelKey(text) === normalizeChannelKey(m[1])) {
      return m[1];
    }
  }

  return null;
}

// Normalize a channel name/username for comparison: lowercase, strip @, spaces,
// and punctuation so "Vortex Breaks", "@vortexbreaks", "vortex_breaks" all match.
function normalizeChannelKey(s) {
  return (s || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

// Wait until Whatnot's seller nav proves the requested account is active.
// If the nav cannot prove identity, fail closed rather than risk cross-channel data.
async function waitForActiveChannel(page, expectedChannel, timeoutMs = 12000) {
  const expected = normalizeChannelKey(expectedChannel);
  const started = Date.now();
  let lastSeen = null;

  // switchToChannel already checked for an interstitial. Do not perform another
  // unbounded challenge read here; verification itself is intentionally bounded.
  while (Date.now() - started < timeoutMs) {
    lastSeen = await getActiveChannelUsername(page);

    if (lastSeen && normalizeChannelKey(lastSeen) === expected) {
      info(`channel verification PASSED: requested=@${expectedChannel} active=@${lastSeen}`);
      return lastSeen;
    }

    await page.waitForTimeout(500);
  }

  throw new Error(
    `CHANNEL_SWITCH_VERIFICATION_FAILED: requested=@${expectedChannel} ` +
    `but active=@${lastSeen || '?'} after ${timeoutMs}ms`
  );
}

// ── Extract all metric cards from the current analytics page view ─────────────

async function extractAnalyticsMetrics(page) {
  return page.evaluate(({ SEL }) => {
    // Find a div by checking its inline style for a substring
    function styleIncludes(el, ...parts) {
      const s = el.getAttribute('style') || '';
      return parts.every(p => s.includes(p));
    }

    // ── Show title ────────────────────────────────────────────────────────────
    // Primary: nowrap/ellipsis div with max-width: 90% (original approach)
    let titleEl = document.querySelector(SEL.showTitle);
    // Fallback A: any div/h1-h4 with a large font-size that has meaningful text
    if (!titleEl) {
      titleEl = Array.from(document.querySelectorAll('h1, h2, h3, h4')).find(el =>
        el.textContent.trim().length > 4
      );
    }
    // Fallback B: any element styled as a large heading
    if (!titleEl) {
      titleEl = Array.from(document.querySelectorAll('div, span')).find(el => {
        const s = el.getAttribute('style') || '';
        const fs = parseInt((s.match(/font-size:\s*(\d+)px/) || [])[1] || '0', 10);
        return fs >= 20 && el.textContent.trim().length > 4 && el.childElementCount <= 2;
      });
    }
    const title = titleEl ? titleEl.textContent.trim() : null;

    // ── Show date ─────────────────────────────────────────────────────────────
    // Primary: div with font-size:14px + font-weight:600 containing a date
    let dateEl = Array.from(document.querySelectorAll('div')).find(el => {
      const s = el.getAttribute('style') || '';
      return s.includes('font-size: 14px') && s.includes('font-weight: 600') &&
             /\d{1,2}\/\d{1,2}\/\d{4}/.test(el.textContent);
    });
    // Fallback: any leaf element containing a date pattern
    if (!dateEl) {
      dateEl = Array.from(document.querySelectorAll('div, span, p, time')).find(el =>
        el.childElementCount === 0 && /\d{1,2}\/\d{1,2}\/\d{4}/.test(el.textContent)
      );
    }
    const dateText = dateEl ? dateEl.textContent.trim() : null;

    // ── Metric cards ──────────────────────────────────────────────────────────
    // Primary: height:160px + border-radius:16px (original approach)
    let cards = Array.from(document.querySelectorAll('div')).filter(el =>
      styleIncludes(el, 'height: 160px') && styleIncludes(el, 'border-radius: 16px')
    );

    const metrics = {};

    for (const card of cards) {
      // Metric name: first div with font-size: 16px and font-weight: 600
      const nameEl = Array.from(card.querySelectorAll('div')).find(el =>
        styleIncludes(el, 'font-size: 16px') && styleIncludes(el, 'font-weight: 600')
      );
      // Metric value: div with font-size: 32px
      const valueEl = Array.from(card.querySelectorAll('div')).find(el =>
        styleIncludes(el, 'font-size: 32px')
      );
      if (nameEl && valueEl) {
        metrics[nameEl.textContent.trim()] = valueEl.textContent.trim();
      }
    }

    // Fallback: scan for known metric labels paired with a value sibling/child.
    // Whatnot sometimes changes card dimensions but metric label text stays stable.
    if (Object.keys(metrics).length === 0) {
      const knownLabels = [
        'Estimated Sales', 'Total Estimated Earnings', 'Completed Earnings',
        'Net Revenue', 'Gross Revenue', 'Revenue',
        'Orders', 'Average Order Value', 'AOV',
        'Giveaway Spend', 'Giveaways',
        'Buyers', 'First Time Buyers', 'New Buyers', 'Returning Buyers',
        'Shares', 'Likes', 'Views', 'Followers Gained',
        'Show Duration', 'Duration', 'Watch Time',
      ];

      const allLeaves = Array.from(document.querySelectorAll('*')).filter(
        el => el.childElementCount === 0 && el.textContent.trim().length > 0
      );

      for (const label of knownLabels) {
        const labelEl = allLeaves.find(el => el.textContent.trim() === label);
        if (!labelEl) continue;

        // Look for a value in the same card container (up to 5 levels up)
        let container = labelEl;
        for (let i = 0; i < 5; i++) {
          container = container.parentElement;
          if (!container) break;
          const valueEl = Array.from(container.querySelectorAll('*')).find(el =>
            el !== labelEl &&
            el.childElementCount === 0 &&
            /^\$?[\d,.]+(%|k|m|h|min)?$/i.test(el.textContent.trim())
          );
          if (valueEl) {
            metrics[label] = valueEl.textContent.trim();
            break;
          }
        }
      }
    }

    // Check if older/newer show buttons are disabled
    const olderBtn  = document.querySelector(SEL.showNavOlder);
    const hasOlder  = olderBtn && !olderBtn.disabled;

    // Try to find the show's public/seller URL from any anchor on the page.
    // Whatnot show URLs contain "/live/" followed by a channel username and ID.
    const allAnchors = Array.from(document.querySelectorAll('a[href]'));
    const showLink = allAnchors.find(a => {
      const h = a.getAttribute('href') || '';
      return /\/live\/[^/]+\/[^/]+/.test(h) || /\/show\/\d+/.test(h);
    });
    const detailUrl = showLink
      ? (showLink.href.startsWith('http') ? showLink.href : 'https://www.whatnot.com' + showLink.getAttribute('href'))
      : null;

    return { title, dateText, metrics, hasOlder, cardCount: cards.length, detailUrl };
  }, { SEL: SELECTORS });
}

// ── Shows-list DOM extractor ──────────────────────────────────────────────────
// Used when we land on a list-style page (/dashboard/lives, /seller/shows).
// The seller sidebar "Shows" nav link goes to /dashboard/lives (not /dashboard/shows).
// Returns an array of show objects in the same shape as the analytics extractor,
// with whatever fields are available in the list view.

async function extractShowsListFromDom(page) {
  const shows = await page.evaluate(() => {
    const results = [];
    const addedUrls = new Set();

    // All action-button labels that must NOT be treated as a show title
    const genericActionLabel = /^(open show|edit show|clone items?|copy show link|start sharing|end show|enable private mode|schedule a show|show tools?|view show|show details?|see analytics|view shipments|restart show|cancel show|going live help|obs tools|schedule a show)$/i;

    function processAnchor(a, forcedUrl) {
      const href = a.getAttribute('href') || '';
      const fullUrl = forcedUrl || (href.startsWith('http') ? href : 'https://www.whatnot.com' + href);
      if (!fullUrl || addedUrls.has(fullUrl)) return;
      addedUrls.add(fullUrl);

      // Walk up to find the card container that includes title + date.
      // Action-buttons div is ~90 chars, so threshold 100 passes it and reaches the full card.
      let container = a;
      for (let i = 0; i < 12; i++) {
        if (!container.parentElement) break;
        container = container.parentElement;
        if ((container.innerText || '').trim().length > 100) break;
      }

      const text = (container.innerText || container.textContent || '').trim();
      if (text.length < 5) return;
      const lines = text.split('\n').map(l => l.trim()).filter(Boolean);

      // Date parsing — try formats in order of specificity
      let showDate = null;
      let mdy = null;

      // ISO: 2026-07-05
      const iso = text.match(/\b(20\d\d)[-\/](0[1-9]|1[0-2])[-\/](0[1-9]|[12]\d|3[01])\b/);
      if (iso) showDate = `${iso[1]}-${iso[2]}-${iso[3]}`;

      // M/D/YYYY or M-D-YYYY: 7/9/2026  (list page shows this format)
      if (!showDate) {
        mdy = text.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/);
        if (mdy) {
          const year = mdy[3].length === 2 ? '20' + mdy[3] : mdy[3];
          showDate = `${year}-${mdy[1].padStart(2,'0')}-${mdy[2].padStart(2,'0')}`;
        }
      }

      // Month-name: "July 5, 2026" / "Jul 5 2026"
      if (!showDate) {
        const mo = {jan:1,feb:2,mar:3,apr:4,may:5,jun:6,jul:7,aug:8,sep:9,oct:10,nov:11,dec:12};
        const mn = text.match(/\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2})(?:st|nd|rd|th)?,?\s*(20\d\d)\b/i);
        if (mn) {
          const m = mo[mn[1].substring(0,3).toLowerCase()];
          showDate = `${mn[3]}-${String(m).padStart(2,'0')}-${mn[2].padStart(2,'0')}`;
        } else {
          const alt = text.match(/\b(\d{1,2})(?:st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(20\d\d)\b/i);
          if (alt) {
            const m = mo[alt[2].substring(0,3).toLowerCase()];
            showDate = `${alt[3]}-${String(m).padStart(2,'0')}-${alt[1].padStart(2,'0')}`;
          }
        }
      }

      // Relative: "2 days ago", "yesterday", "3 weeks ago"
      if (!showDate) {
        const rel = text.match(/\b(\d+)\s+(day|week)s?\s+ago\b/i);
        if (rel) {
          const d = new Date();
          if (rel[2].toLowerCase() === 'week') d.setDate(d.getDate() - parseInt(rel[1]) * 7);
          else d.setDate(d.getDate() - parseInt(rel[1]));
          showDate = d.toISOString().substring(0, 10);
        } else if (/\byesterday\b/i.test(text)) {
          const d = new Date(); d.setDate(d.getDate() - 1);
          showDate = d.toISOString().substring(0, 10);
        } else if (/\btoday\b/i.test(text)) {
          showDate = new Date().toISOString().substring(0, 10);
        }
      }

      // Revenue / units / views from container text
      const prices = [...text.matchAll(/\$[\d,]+\.?\d*/g)]
        .map(m => parseFloat(m[0].replace(/[^0-9.]/g, '')))
        .filter(v => !isNaN(v) && v > 0);
      const unitMatch = text.match(/(\d{1,6})\s*(?:orders?|lots?\s+sold|units?\s+sold|sales)/i);
      const viewMatch = text.match(/(\d{1,6})\s*(?:viewers?|views?)/i);

      // Title extraction:
      // 1. Prefer anchor's own text (skip generic action labels)
      // 2. On /dashboard/lives list, first line is "TITLE — date • time" — split on " — "
      // 3. Fall back to longest non-date non-price line in container
      const anchorText = (a.innerText || a.textContent || '').trim();
      const usableAnchorText = anchorText.length > 5 && !genericActionLabel.test(anchorText) ? anchorText : null;
      const firstLine = lines[0] || '';
      const dashIdx = firstLine.indexOf(' — ');
      const titleFromHeader = (dashIdx > 3) ? firstLine.substring(0, dashIdx).trim() : null;
      const title = usableAnchorText || titleFromHeader || (
        lines.filter(l =>
          l.length > 5 && !/^\d+$/.test(l) && !/^\$/.test(l) &&
          !/^\d{1,2}[\/\-]/.test(l) && !/^20\d\d/.test(l) &&
          !/^(Live|Ended|Cancelled|Completed|Upcoming|—|•)$/i.test(l) &&
          !genericActionLabel.test(l)
        ).sort((a, b) => b.length - a.length)[0] || null
      );

      results.push({
        title,
        show_date:              showDate,
        show_date_raw:          mdy ? mdy[0] : null,
        detail_url:             fullUrl,
        gross_revenue:          prices.length > 0 ? Math.max(...prices) : null,
        whatnot_net:            null,
        completed_earnings:     null,
        units_sold:             unitMatch ? parseInt(unitMatch[1], 10) : null,
        avg_order_value:        null,
        giveaway_spend:         null,
        giveaways_count:        null,
        buyers_count:           null,
        first_time_buyers:      null,
        returning_buyers:       null,
        shares_count:           null,
        show_duration:          null,
        max_concurrent_viewers: null,
        total_views:            viewMatch ? parseInt(viewMatch[1], 10) : null,
        avg_order_rating:       null,
        _raw_metrics:           {},
        _list_source:           true,
      });
    }

    // Pass 1: standard show URL patterns (e.g. "Open show" → /dashboard/live/<uuid>)
    // Whatnot show URL patterns: /live/<user>/<id>, /live/<id> (confirmed July 2026 —
    // the real pattern on /dashboard/lives and /dashboard/home is this single-segment
    // form, not /live/<user>/<id>; the two-segment regex below never matched it),
    // /show/<id>, /seller/shows/<id>, /dashboard/shows/<id>, /dashboard/live/<id>
    for (const a of document.querySelectorAll('a[href]')) {
      const href = a.getAttribute('href') || '';
      const isKnownNonShow = /\/dashboard\/lives?\/(new|setup|edit|clone|schedule|preview|analytics)(?:[?#]|$)/i.test(href) ||
                             /\/account\/live\/[^/]+\/clone/.test(href);
      if (isKnownNonShow) continue;
      if (!(/\/live\/[^/]+\/[^/?#\s]+(?=[?#]|$)/.test(href) ||
            /\/live\/[\w-]+(?=[?#]|$)/.test(href) ||
            /\/show\/[\w-]+(?=[?#]|$)/.test(href) ||
            /\/seller\/shows\/[\w-]+(?=[?#]|$)/.test(href) ||
            /\/dashboard\/shows\/[\w-]+(?=[?#]|$)/.test(href) ||
            /\/dashboard\/lives\/[\w-]+(?=[?#]|$)/.test(href) ||
            /\/dashboard\/live\/[\w-]+(?=[?#]|$)/.test(href))) continue;
      processAnchor(a);
    }

    // Pass 2: "See Analytics" links — href contains live_id=<uuid>.
    // These reliably appear on every past show card even when "Open show" is a <button>.
    // The UUID in live_id IS the show ID, so we construct the canonical detail URL.
    for (const a of document.querySelectorAll('a[href*="live_id="]')) {
      const href = a.getAttribute('href') || '';
      let uuid = null;
      try { uuid = new URL(href, location.origin).searchParams.get('live_id'); }
      catch (e) { const m = href.match(/[?&]live_id=([\w-]+)/); uuid = m && m[1]; }
      if (!uuid) continue;
      processAnchor(a, `https://www.whatnot.com/dashboard/live/${uuid}`);
    }

    return results;
  });

  return shows;
}

// ── API interception helpers ──────────────────────────────────────────────────
// When Whatnot's React SPA loads the analytics page it makes API/GraphQL calls
// to fetch show data. Intercepting those responses gives us clean, structured
// JSON — no DOM scraping, no selector maintenance.

function scoreShowObject(obj) {
  if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return 0;
  const keys = Object.keys(obj).map(k => k.toLowerCase());
  let score = 0;
  if (keys.some(k => /\btitle\b|show_title|show_name/.test(k)))     score += 3;
  if (keys.some(k => /\bdate\b|started_at|start_time|startedat|starttime|created_at|createdat|show_date|scheduled_at|scheduledat/.test(k))) score += 2;
  if (keys.some(k => /revenue|sales|gross|earnings|estimated/.test(k)))   score += 3;
  if (keys.some(k => /\borders\b|units_sold|buyers/.test(k)))        score += 2;
  if (keys.some(k => /\bviews\b|viewers|watch/.test(k)))             score += 1;
  if (keys.some(k => /\bduration\b/.test(k)))                        score += 1;
  return score;
}

function findShowsInJson(obj, url, depth = 0) {
  if (depth > 7) return null;
  if (Array.isArray(obj) && obj.length > 0 && typeof obj[0] === 'object') {
    const s = scoreShowObject(obj[0]);
    if (s >= 4) return { array: obj, score: s, url };
    // Relay-style edge/node wrapper: [{node:{...}}, ...] or [{edge:{...}}, ...]
    // GraphQL pagination wraps each item in a {node} or {cursor,node} object.
    const wrapKey = ['node', 'edge'].find(k => obj[0][k] && typeof obj[0][k] === 'object');
    if (wrapKey) {
      const ns = scoreShowObject(obj[0][wrapKey]);
      if (ns >= 4) {
        return { array: obj.map(e => e[wrapKey]).filter(Boolean), score: ns, url };
      }
    }
  }
  if (typeof obj === 'object' && obj !== null && !Array.isArray(obj)) {
    let best = null;
    for (const val of Object.values(obj)) {
      const found = findShowsInJson(val, url, depth + 1);
      if (found && (!best || found.score > best.score)) best = found;
    }
    return best;
  }
  return null;
}

function extractShowsFromCapture(captured) {
  const candidates = [];
  for (const { url, body } of captured) {
    const found = findShowsInJson(body, url);
    if (found) candidates.push(found);
  }
  if (candidates.length === 0) return null;

  candidates.sort((a, b) => b.score - a.score);
  const topScore = candidates[0].score;

  // Merge all responses of the same data type (score within 1 of best) — handles pagination.
  const seen = new Set();
  const merged = [];
  for (const c of candidates.filter(c => c.score >= topScore - 1)) {
    for (const item of c.array) {
      const key = item.id || item.show_id || item.uuid ||
                  (item.title ? item.title.substring(0, 30) : null) ||
                  JSON.stringify(item).substring(0, 60);
      if (!seen.has(key)) {
        seen.add(key);
        merged.push(item);
      }
    }
  }

  info(`API intercept: merged ${merged.length} records from ${candidates.length} response(s), score=${topScore}`);
  if (merged.length > 0) info('API intercept: first record keys:', Object.keys(merged[0]).join(', '));
  return merged.length > 0 ? merged : null;
}

// Turn whatever a JSON API called a timestamp into an ISO string.
//
// The old code did String(value) and looked for a "T". That is right for
// "2026-08-21T23:00:00Z" and wrong for every other shape a GraphQL server
// hands back a time in: epoch seconds, epoch milliseconds, or a wrapper
// object. Each of those stringifies into something with no "T" in it, so it
// fell through to parseDateString, which does not read numbers — and the
// record arrived on the PHP side with a title and a null show_date.
function coerceDateish(value) {
  if (value === null || value === undefined || value === '') return '';

  if (typeof value === 'object') {
    // { iso: … } / { value: … } / { seconds: … } wrappers.
    for (const k of ['iso', 'isoString', 'value', 'datetime', 'dateTime', 'date', 'timestamp', 'seconds']) {
      if (value[k] !== undefined && value[k] !== null && value[k] !== '') return coerceDateish(value[k]);
    }
    return '';
  }

  if (typeof value === 'number' || /^\d{9,14}$/.test(String(value))) {
    const n = Number(value);
    // Seconds vs milliseconds: anything below ~1e11 is seconds, because as
    // milliseconds it would be 1973 and no show predates the platform.
    const ms = n < 1e11 ? n * 1000 : n;
    const d  = new Date(ms);
    return Number.isNaN(d.getTime()) ? '' : d.toISOString();
  }

  const str = String(value);
  if (str.includes('T')) return str;

  // "2026-08-21 23:00:00" — an ISO date with a space where the T belongs,
  // which is what a lot of servers emit and what Date happily parses.
  if (/^\d{4}-\d{2}-\d{2}[ ]\d{2}:\d{2}/.test(str)) return str.replace(' ', 'T');

  return str;
}

function normalizeApiShow(s) {
  const find = (...names) => {
    for (const n of names) {
      const lc = n.toLowerCase();
      const entry = Object.entries(s).find(([k]) => k.toLowerCase() === lc);
      if (entry && entry[1] !== null && entry[1] !== '') return entry[1];
    }
    return null;
  };

  const rawDate = coerceDateish(
    find('date', 'show_date', 'started_at', 'startTime', 'start_time', 'created_at', 'scheduled_at', 'scheduledAt')
  );
  const showDate = rawDate.includes('T') ? rawDate.substring(0, 10) : parseDateString(rawDate);
  const startTime = rawDate.includes('T') ? parseTimeFromIso(rawDate) : parseTimeString(rawDate);

  // A record with a title and no date is the shape that gets thrown away on
  // the PHP side ("has no show_date — cannot create"), and from the log alone
  // there is no way to tell whether the field was missing, empty, or in a
  // format nothing here parses. Say which.
  if (!showDate && find('title', 'show_title', 'name', 'show_name')) {
    const seen = Object.entries(s)
      .filter(([k]) => /date|time|start|created|scheduled/i.test(k))
      .map(([k, v]) => `${k}=${JSON.stringify(v)}`.substring(0, 80));
    info('API intercept: no date parsed from record —', seen.length ? seen.join(' ') : 'no date-like field present');
  }
  const durationMin = parseDurationToMinutes(String(find('duration', 'show_duration', 'duration_minutes') || ''));

  return {
    title:                  find('title', 'show_title', 'name', 'show_name') || null,
    show_date:              showDate,
    show_date_raw:          rawDate || null,
    start_time:             startTime,
    end_time:               startTime && durationMin ? addMinutesToTime(startTime, durationMin) : null,
    detail_url:             find('url', 'detail_url', 'show_url', 'permalink', 'livestreamUrl', 'livestream_url', 'link') ||
                            (s.id ? `https://www.whatnot.com/dashboard/live/${s.id}` : null),
    gross_revenue:          parseMoney(String(find('gross_revenue', 'gross', 'revenue', 'sales', 'estimated_sales', 'total_sales') || '')),
    whatnot_net:            parseMoney(String(find('net_revenue', 'net', 'earnings', 'total_estimated_earnings', 'estimated_earnings') || '')),
    completed_earnings:     parseMoney(String(find('completed_earnings', 'completed_revenue') || '')),
    units_sold:             parseInteger(String(find('units_sold', 'orders', 'total_orders', 'order_count') || '')),
    avg_order_value:        parseMoney(String(find('avg_order_value', 'aov', 'average_order_value') || '')),
    giveaway_spend:         parseMoney(String(find('giveaway_spend', 'giveaways_spend') || '')),
    giveaways_count:        parseInteger(String(find('giveaways', 'giveaway_count', 'giveaways_count') || '')),
    buyers_count:           parseInteger(String(find('buyers', 'buyer_count', 'unique_buyers') || '')),
    first_time_buyers:      parseInteger(String(find('first_time_buyers', 'new_buyers', 'first_buyers') || '')),
    returning_buyers:       parseInteger(String(find('returning_buyers', 'repeat_buyers') || '')),
    shares_count:           parseInteger(String(find('shares', 'share_count', 'shares_count') || '')),
    show_duration:          parseDurationToMinutes(String(find('duration', 'show_duration', 'duration_minutes') || '')),
    max_concurrent_viewers: parseInteger(String(find('max_concurrent_viewers', 'peak_viewers', 'max_viewers') || '')),
    total_views:            parseInteger(String(find('total_views', 'views', 'view_count', 'total_viewers') || '')),
    _raw_metrics:           {},
    _api_source:            true,
  };
}

// Signal the whole process group, not just the single PID Node handed us.
// Chromium forks its own renderer/utility/GPU-helper processes as separate
// OS processes we never get a handle to — signaling only `child`'s PID can
// kill the top-level browser process while leaving those orphaned, still
// holding the profile dir open (which is exactly what caused every
// subsequent launch to see the SingletonLock as "genuinely still in use").
// Requires spawn({ detached: true }), which makes child.pid the group id too.
function killProcessGroup(child, signal) {
  try {
    process.kill(-child.pid, signal);
  } catch (e) {
    // Group kill fails if the child was never a group leader, or the group's
    // leader already exited — fall back to the single PID we hold directly.
    try { child.kill(signal); } catch (_e) {}
  }
}

// Send a kill signal and wait for the OS process to actually exit — sending
// the signal alone returns immediately, which isn't enough when a caller
// needs the profile's SingletonLock to be verifiably released before
// proceeding (e.g. launching the next channel's Chromium against the same
// persistent profile dir).
function killAndWait(child, signal = 'SIGTERM', timeoutMs = 5000) {
  if (child.killed || child.exitCode !== null || child.signalCode !== null) return Promise.resolve();
  return new Promise((resolve) => {
    const forceTimer = setTimeout(() => {
      killProcessGroup(child, 'SIGKILL');
    }, timeoutMs);
    child.once('exit', () => {
      clearTimeout(forceTimer);
      resolve();
    });
    killProcessGroup(child, signal);
  });
}

// Unconditionally kill anything already running against this profile dir
// before we launch into it. This file's own withBrowserLock() (PHP side)
// guarantees only one whatnot:* command is ever legitimately in flight at a
// time, so by the time we get here, ANYTHING still alive against this exact
// profile is leftover garbage from a previous crashed/orphaned run — not a
// process we need to coexist with. Matches Chromium's own -f pattern
// (user-data-dir=<path>), which every renderer/utility/GPU-helper child
// process inherits on its command line too, not just the top-level browser
// process — one pkill reaps the whole stale tree regardless of how it was
// orphaned (killAndWait's process-group kill only helps for processes THIS
// script spawned; this also cleans up ones a cron/queue run before it left
// behind, e.g. from before that fix existed, or from a hard host-level kill
// that bypassed our cleanup entirely).
function killStaleProcessesForProfile(userDataDir) {
  const { execSync } = require('child_process');
  try {
    execSync(`pkill -9 -f "user-data-dir=${userDataDir}"`, { stdio: 'ignore' });
    info(`killed stale chromium process(es) already using profile: ${userDataDir}`);
  } catch (e) {
    // pkill exits 1 when nothing matched, which is the common/expected case — not an error.
  }
}

// If a prior Chromium against this profile got killed hard enough (SIGKILL
// from an external timeout, OOM, etc.) that it never reached its own cleanup,
// its SingletonLock survives and every subsequent launch fails immediately
// with exit code 21 (RESULT_CODE_NORMAL_EXIT_PROCESS_NOTIFICATION_FAILED) —
// this file's own killAndWait() prevents that going forward, but can't help
// with locks left by whatever killed things before that existed. Chrome's
// SingletonLock is a symlink to "<hostname>-<pid>"; only clear it if that pid
// isn't actually alive, so a genuinely-running instance is never disturbed.
function clearStaleSingletonLock(userDataDir) {
  const fs = require('fs');
  const path = require('path');
  const lockPath = path.join(userDataDir, 'SingletonLock');
  let target;
  try {
    target = fs.readlinkSync(lockPath);
  } catch {
    return;
  }
  const m = target.match(/-(\d+)$/);
  const pid = m ? parseInt(m[1], 10) : null;
  let alive = false;
  if (pid) {
    try {
      process.kill(pid, 0);
      alive = true;
    } catch (e) {
      alive = e.code === 'EPERM'; // exists but not ours to signal — still alive
    }
  }
  if (!alive) {
    info(`clearing stale SingletonLock (pid ${pid} not running): ${lockPath}`);
    for (const f of ['SingletonLock', 'SingletonSocket', 'SingletonCookie']) {
      fs.rmSync(path.join(userDataDir, f), { force: true });
    }
  }
}

// ── Launch Chromium and attach via CDP-over-TCP ─────────────────────────────
// launchPersistentContext() always spawns Chromium with --remote-debugging-pipe
// for its control channel, which relies on inherited fd 3/4. In some restrictive
// container environments that pipe handshake never completes: Chromium starts
// fine (confirmed independently with a plain --remote-debugging-port launch)
// but Playwright times out waiting on the pipe and force-kills the process,
// which surfaces as an opaque "signal=SIGTRAP" crash. TCP-based CDP works in
// that same environment, so here we spawn Chromium ourselves with a dynamic
// debugging port and attach via connectOverCDP() instead — everything
// downstream still gets a normal Playwright BrowserContext.
/**
 * Launch, and rebuild the profile if it turns out to be the thing that is broken.
 *
 * The persistent profile is the scraper's most valuable state — it holds the
 * refreshed session and any clearance cookie — and also its most fragile. Every
 * `pkill -9` that ends a wedged run risks killing Chromium mid-write, and a
 * half-written profile makes Chromium die on startup with SIGTRAP. That reads
 * as a browser or network fault and looks identical in every configuration, so
 * it masqueraded as headless, headed and proxied runs all failing the same way.
 *
 * The profile is moved aside rather than deleted: if it was not the problem,
 * it is still there. The session is re-bootstrapped from the cookie file on the
 * next launch, so a rebuild costs a re-login at worst, never data.
 */

async function launchSteelContext(opts = {}) {
  if (typeof fetch !== 'function') {
    throw new Error('STEEL_BACKEND_UNAVAILABLE: Node 18+ is required because the Steel backend uses fetch()');
  }

  const baseUrl = STEEL_BASE_URL;
  info(`browser backend: steel (${baseUrl})`);

  const createBody = {
    // Match the headed browser mode that has historically worked best for Whatnot.
    // Steel owns the display/browser process inside its container.
    headless: false,
    dimensions: { width: 1920, height: 1080 },
    inactivityTimeout: 120000,
  };

  if (process.env.WHATNOT_PROXY) {
    createBody.proxyUrl = process.env.WHATNOT_PROXY;
  }

  const createController = new AbortController();
  const createTimer = setTimeout(() => createController.abort(), 20000);
  let response;
  try {
    response = await fetch(`${baseUrl}/v1/sessions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(createBody),
      signal: createController.signal,
    });
  } finally {
    clearTimeout(createTimer);
  }

  const raw = await response.text();
  if (!response.ok) {
    throw new Error(`STEEL_SESSION_CREATE_FAILED: HTTP ${response.status} ${raw.substring(0, 500)}`);
  }

  let session;
  try {
    session = JSON.parse(raw);
  } catch {
    throw new Error(`STEEL_SESSION_CREATE_FAILED: invalid JSON from ${baseUrl}/v1/sessions`);
  }

  const sessionId = session.id || session.sessionId || session.session_id;
  let websocketUrl = session.websocketUrl || session.websocket_url || session.wsUrl || session.ws_url;
  if (!sessionId || !websocketUrl) {
    throw new Error(`STEEL_SESSION_CREATE_FAILED: response missing id/websocketUrl: ${raw.substring(0, 500)}`);
  }

  try {
    const apiUrl = new URL(baseUrl);
    const ws = new URL(websocketUrl);
    if (['localhost', '0.0.0.0', '127.0.0.1'].includes(ws.hostname)) {
      ws.hostname = apiUrl.hostname;
    }
    if (!ws.port && apiUrl.port) ws.port = apiUrl.port;
    ws.protocol = apiUrl.protocol === 'https:' ? 'wss:' : 'ws:';
    websocketUrl = ws.toString();
  } catch (e) {
    throw new Error(`STEEL_SESSION_CREATE_FAILED: invalid websocketUrl ${websocketUrl}: ${e.message}`);
  }

  info(`steel session created: ${sessionId}`);

  let browser;
  try {
    browser = await chromium.connectOverCDP(websocketUrl, { timeout: 20000 });
  } catch (e) {
    await fetch(`${baseUrl}/v1/sessions/${encodeURIComponent(sessionId)}/release`, { method: 'POST' }).catch(() => {});
    throw new Error(`STEEL_CDP_CONNECT_FAILED: ${e.message}`);
  }

  const contexts = browser.contexts();
  const context = contexts[0] || await browser.newContext({
    viewport: opts.viewport || { width: 1280, height: 900 },
    locale: opts.locale || 'en-US',
  });

  // Steel owns the browser fingerprint. Do not replay the local launcher's
  // hard-coded UA/client-hint fingerprint into Steel's different Chrome build.
  let closed = false;
  const steelClose = async () => {
    if (closed) return;
    closed = true;
    try { await browser.close(); } catch {}
    try {
      await fetch(`${baseUrl}/v1/sessions/${encodeURIComponent(sessionId)}/release`, { method: 'POST' });
    } catch {}
  };

  try {
    Object.defineProperty(context, 'close', {
      configurable: true,
      value: steelClose,
    });
  } catch {
    context.close = steelClose;
  }

  return context;
}

async function launchWithProfileRecovery(userDataDir, opts = {}) {
  const isStartupFailure = (e) => /died during startup|exited before DevTools listener was ready|Timed out waiting for Chromium DevTools listener/i.test(e?.message || '');

  try {
    return await launchPersistentContextViaCdp(userDataDir, opts);
  } catch (firstError) {
    if (!isStartupFailure(firstError)) throw firstError;

    info('chromium startup failed once — cleaning stale profile processes and retrying the same profile');
    killStaleProcessesForProfile(userDataDir);
    clearStaleSingletonLock(userDataDir);
    await new Promise(r => setTimeout(r, 1200));

    try {
      return await launchPersistentContextViaCdp(userDataDir, opts);
    } catch (secondError) {
      if (!isStartupFailure(secondError)) throw secondError;

      const fs = require('fs');
      const quarantine = userDataDir + '.broken';

      try {
        fs.rmSync(quarantine, { recursive: true, force: true });
        if (fs.existsSync(userDataDir)) {
          fs.renameSync(userDataDir, quarantine);
          info('chromium failed twice on this profile — moved it to', quarantine);
        }
        info('starting a clean profile; the session re-loads from the cookie file');
      } catch (moveError) {
        info('could not move the profile aside:', moveError.message);
        throw secondError;
      }

      return await launchPersistentContextViaCdp(userDataDir, opts);
    }
  }
}

async function launchPersistentContextViaCdp(userDataDir, opts = {}) {
  const { spawn } = require('child_process');
  const {
    args = [], userAgent, viewport, locale, extraHTTPHeaders, env: extraEnv = {},
  } = opts;

  killStaleProcessesForProfile(userDataDir);
  clearStaleSingletonLock(userDataDir);

  // Headless Chromium is the single loudest bot signal there is, and on a
  // datacenter IP it is the difference between a challenge that clears itself
  // and one that never does. Set WHATNOT_HEADLESS=false and run the command
  // under xvfb-run to present as an ordinary windowed browser instead.
  const headless = String(process.env.WHATNOT_HEADLESS ?? 'true').toLowerCase() !== 'false';

  if (!headless && !process.env.DISPLAY) {
    // Deliberately not xvfb-run: it runs its command as `"$@" 2>&1`, which
    // folds this script's stderr into the stdout carrying its JSON result.
    // scripts/with-xvfb.sh provides a display and leaves both streams alone.
    exitAfterStderr(
      'WHATNOT_HEADLESS=false needs an X display, and DISPLAY is not set.\n' +
      'Run it through the wrapper instead:\n' +
      '  ./scripts/with-xvfb.sh node scripts/whatnot-scraper.cjs\n' +
      'Artisan commands do this for you — no wrapper needed there.\n' +
      '(needs xvfb: apt-get install -y xvfb)\n',
      EXIT.GENERAL,
    );
  }

  // Say which mode we launched in. Without this a headed run and a headless one
  // produce identical logs, so "I tried headless-off and got the same result"
  // could equally mean the setting never reached the browser.
  info('launching chromium:', headless
    ? 'headless (set WHATNOT_HEADLESS=false under xvfb-run to run headed)'
    : 'headed, DISPLAY=' + process.env.DISPLAY);

  // Cloudflare blocks this server by IP, so the browser's own egress has to
  // move. Chromium resolves DNS through the proxy for socks5://, which matters:
  // resolving locally would leak the real network path and defeat the point.
  const proxy = process.env.WHATNOT_PROXY || '';
  if (proxy) info('routing browser traffic through proxy:', proxy);

  const chromeArgs = [
    ...args,
    ...(headless ? ['--headless'] : []),
    ...(proxy ? [`--proxy-server=${proxy}`] : []),
    '--remote-debugging-port=0',
    // Chrome ≥111 enforces an Origin/Host allowlist on the DevTools WebSocket
    // and silently drops connections that fail it — surfaces as a bare "socket
    // hang up" with no useful error from either side. This is a local loopback
    // connection we're deliberately making ourselves, so wildcard it.
    '--remote-allow-origins=*',
    `--user-data-dir=${userDataDir}`,
    ...(userAgent ? [`--user-agent=${userAgent}`] : []),
    ...(locale ? [`--lang=${locale}`] : []),
    'about:blank',
  ];

  // Chromium/Crashpad needs a real writable HOME/XDG tree. Forcing HOME=/tmp
  // makes chrome_crashpad_handler abort before DevTools starts under the
  // www-data queue user ("--database is required"). Keep this state beside
  // the persistent browser profile so scheduled jobs and manual probes launch
  // with the same writable runtime environment.
  const path = require('path');
  const fs = require('fs');
  const browserHome = process.env.WHATNOT_BROWSER_HOME || path.join(path.dirname(userDataDir), 'whatnot-browser-home');
  const xdgConfigHome = process.env.XDG_CONFIG_HOME || path.join(browserHome, '.config');
  const xdgCacheHome = process.env.XDG_CACHE_HOME || path.join(browserHome, '.cache');
  fs.mkdirSync(xdgConfigHome, { recursive: true });
  fs.mkdirSync(xdgCacheHome, { recursive: true });

  const child = spawn(CHROMIUM_PATH, chromeArgs, {
    env: {
      ...process.env,
      HOME: browserHome,
      XDG_CONFIG_HOME: xdgConfigHome,
      XDG_CACHE_HOME: xdgCacheHome,
      ...extraEnv,
    },
    stdio: ['ignore', 'ignore', 'pipe'],
    // Make this process the leader of its own process group so killAndWait()
    // can signal the whole Chromium tree (renderer/utility/GPU helper
    // processes it forks internally aren't tracked by our `child` handle —
    // killing only that single PID orphans them, and they keep the profile
    // dir's SingletonLock effectively alive even after "our" process exits).
    detached: true,
  });

  const wsEndpoint = await new Promise((resolve, reject) => {
    let buf = '';
    function cleanup() {
      clearTimeout(timer);
      child.stderr.off('data', onData);
      child.off('exit', onExit);
      child.off('error', onError);
    }
    function onData(chunk) {
      buf += chunk.toString();
      const m = buf.match(/DevTools listening on (ws:\/\/\S+)/);
      if (m) { cleanup(); resolve(m[1]); }
    }
    function onExit(code, signal) {
      cleanup();
      reject(new Error(`Chromium exited before DevTools listener was ready (code=${code} signal=${signal}). stderr: ${buf.trim().slice(-1200) || '(empty)'}`));
    }
    function onError(err) {
      cleanup();
      reject(err);
    }
    const timer = setTimeout(() => {
      cleanup();
      reject(new Error('Timed out waiting for Chromium DevTools listener'));
    }, 15000);
    child.stderr.on('data', onData);
    child.once('exit', onExit);
    child.once('error', onError);
  });

  // Keep draining stderr so a long scraping session (analytics mode can run
  // 20+ minutes) doesn't fill the OS pipe buffer and stall Chromium once our
  // one-time listener above is gone.
  child.stderr.on('data', () => {});

  // The startup-readiness listeners above are removed once wsEndpoint resolves,
  // so without this, Chromium dying later (mid-connect-retry, or mid-scrape)
  // shows up only as an opaque ECONNREFUSED/socket-hang-up with no indication
  // of why the process actually went away. Always log it, not just under DEBUG.
  let browserDeath = null;
  child.on('exit', (code, signal) => {
    browserDeath = { code, signal };
    info(`WARNING: chromium process exited unexpectedly (pid=${child.pid} code=${code} signal=${signal})`);
  });

  const port = new URL(wsEndpoint).port;

  // The "DevTools listening" line can print fractionally before the WebSocket
  // handler is actually ready to accept connections (observed on a resource-
  // throttled container: a manual curl attempt a few seconds later succeeded
  // cleanly with a proper 101 handshake, while connecting immediately here
  // got a bare "socket hang up"). Retry with a short backoff instead of
  // assuming the very first attempt lands.
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

    // "connect ECONNREFUSED 127.0.0.1:38549" describes the symptom of a browser
    // that died before it could listen, and reads like a networking fault in
    // the scraper. When we watched it die, say that instead — and name the
    // flags most likely to have killed it, since a crash on launch is nearly
    // always an argument Chromium would not accept in this configuration.
    if (browserDeath) {
      const { code, signal } = browserDeath;
      const proxyNote = proxy
        ? `\nIt was launched with --proxy-server=${proxy}. Retry without WHATNOT_PROXY to confirm whether the proxy is what it rejected.`
        : '';

      throw new Error(
        `Chromium died during startup (code=${code} signal=${signal}), so there was no DevTools port to connect to.`
        + `\nThis is a browser launch failure, not a Whatnot or network problem.${proxyNote}`
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

  // Every existing context.close() call site in this file should also reap the
  // OS process we spawned — a CDP-connected context's close() only disconnects
  // the session, it doesn't own (and won't kill) the underlying browser.
  //
  // Waiting for the actual OS exit (not just sending the signal) matters: modes
  // that import multiple channels/shows in one run launch a fresh Chromium
  // against the SAME persistent profile dir once per channel, sequentially.
  // If close() returned as soon as SIGTERM was sent, the next channel's launch
  // could race the previous Chromium's shutdown and find its SingletonLock
  // still held — Chromium then exits immediately with code 21
  // (RESULT_CODE_NORMAL_EXIT_PROCESS_NOTIFICATION_FAILED, "another instance
  // is already using this profile") instead of ever reaching the DevTools
  // listener. Blocking here until the process is actually gone closes that race.
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

// ── ws-explore standalone (no browser) ───────────────────────────────────────
// Called before launchPersistentContext so the shared profile lock is never
// needed. Launches a temporary Playwright browser, navigates to the seller hub,
// and intercepts the real Phoenix WS frames the app sends/receives.
// This avoids guessing topic names — we just listen to what the app does.
async function runWsExploreStandalone(cookiesFilePath) {
  const fs   = require('fs');
  const os   = require('os');
  const path = require('path');

  // Load cookies from file
  if (!fs.existsSync(cookiesFilePath)) {
    exitAfterStderr('ws-explore: no cookie file found at ' + cookiesFilePath + '\n' + 'Run: php artisan whatnot:login\n', 1);
  }
  let rawCookies;
  try {
    rawCookies = JSON.parse(fs.readFileSync(cookiesFilePath, 'utf8'));
  } catch (e) {
    exitAfterStderr('ws-explore: failed to parse cookie file: ' + e.message + '\n', 1);
  }

  info('ws-explore: loaded', rawCookies.length, 'cookies from file');

  // Use a temporary Playwright browser context (fresh dir = no SingletonLock conflict).
  // Navigate to the seller hub and intercept real Phoenix WS frames — the app joins
  // the correct topics automatically, so we never have to guess topic names.
  const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'whatnot-ws-'));
  const _sameSiteMap = { no_restriction: 'None', strict: 'Strict', lax: 'Lax' };
  const playwrightCookies = rawCookies
    .filter(c => c.name && c.value)
    .map(c => ({
      name:     c.name,
      value:    c.value,
      domain:   c.domain || '.whatnot.com',
      path:     c.path   || '/',
      expires:  c.expirationDate ?? c.expires ?? -1,
      httpOnly: Boolean(c.httpOnly),
      secure:   Boolean(c.secure),
      sameSite: _sameSiteMap[(c.sameSite || '').toLowerCase()] || 'Lax',
    }));

  info('ws-explore: launching browser to sniff real Phoenix topic names…');
  const allMessages  = [];
  const topicsJoined = new Set();
  const httpCaptures = [];  // declared here so it's in scope after the try/finally

  const tempContext = await launchPersistentContextViaCdp(tempDir, {
    args: ['--no-sandbox', '--no-zygote', '--disable-dev-shm-usage', '--disable-gpu',
           '--disable-crash-reporter', '--crash-dumps-dir=/tmp'],
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport:  { width: 1280, height: 900 },
  });
  try {
    await tempContext.addCookies(playwrightCookies);
    const tempPage = await tempContext.newPage();

    // Inject saved localStorage so the seller hub React app finds its auth tokens.
    // Whatnot stores session state in localStorage (not just cookies); without it
    // only the generic presence channel (general:XXXX) gets joined.
    const _lsFile = path.join(__dirname, '../storage/whatnot-localstorage.json');
    if (fs.existsSync(_lsFile)) {
      try {
        const savedLs = JSON.parse(fs.readFileSync(_lsFile, 'utf8'));
        // Navigate to whatnot.com first so the localStorage origin is correct
        await tempPage.goto('https://www.whatnot.com/', { waitUntil: 'domcontentloaded', timeout: 15000 });
        await tempPage.evaluate((data) => {
          for (const [k, v] of Object.entries(data)) {
            try { localStorage.setItem(k, v); } catch {}
          }
        }, savedLs);
        info(`ws-explore: injected ${Object.keys(savedLs).length} localStorage keys`);
      } catch (_lsErr) {
        info('ws-explore: localStorage inject failed:', _lsErr.message);
      }
    } else {
      info('ws-explore: no saved localStorage found — run a normal import first to generate it');
      info('ws-explore:   php artisan whatnot:import --channel=1');
      info('ws-explore: proceeding with cookies only (seller topics may not appear)');
    }

    // Capture HTTP API calls (GraphQL/REST JSON) — the shows list is likely REST, not WS
    tempPage.on('response', async (resp) => {
      try {
        const url = resp.url();
        if (!url.includes('whatnot.com')) return;
        if (url.includes('/reroute/') || url.includes('/_next/') || url.includes('/static/')) return;
        const ct = resp.headers()['content-type'] || '';
        if (!ct.includes('json') && !ct.includes('graphql')) return;
        const text = await resp.text().catch(() => '');
        if (text.length < 30) return;
        const path = url.replace('https://www.whatnot.com', '');
        info(`[http] ${resp.status()} ${resp.request().method()} ${path.substring(0, 100)}`);
        try {
          const body = JSON.parse(text);
          httpCaptures.push({ method: resp.request().method(), url: path, status: resp.status(), body });
        } catch {
          httpCaptures.push({ method: resp.request().method(), url: path, status: resp.status(), body: text.substring(0, 500) });
        }
      } catch {}
    });

    // Wire up WS interception BEFORE navigating so we catch the very first join frames
    tempPage.on('websocket', (ws) => {
      if (!ws.url().includes('whatnot.com')) return;
      info(`[ws] CONNECT ${ws.url().replace('wss://www.whatnot.com', '').substring(0, 120)}`);

      ws.on('framesent', ({ payload }) => {
        try {
          if (typeof payload !== 'string') return;
          const data = JSON.parse(payload);
          if (!Array.isArray(data) || data.length !== 5) return;
          const [, , topic, event, pl] = data;
          if (event === 'phx_join') topicsJoined.add(topic);
          info(`[ws] SENT  topic=${topic} event=${event} ${JSON.stringify(pl || {}).substring(0, 100)}`);
          allMessages.push({ dir: 'sent', topic, event, payload: pl });
        } catch {}
      });

      ws.on('framereceived', ({ payload }) => {
        try {
          if (typeof payload !== 'string') return;
          const data = JSON.parse(payload);
          if (!Array.isArray(data) || data.length !== 5) return;
          const [, , topic, event, pl] = data;
          info(`[ws] RECV  topic=${topic} event=${event} ${JSON.stringify(pl || {}).substring(0, 300)}`);
          allMessages.push({ dir: 'recv', topic, event, payload: pl });
        } catch {}
      });
    });

    // Navigate to the seller hub entry point (/seller) rather than /seller/shows
    // directly — the latter is a Next.js SSR route that 404s without a full session;
    // /seller client-side routes to shows and triggers the right API calls.
    info(`ws-explore: navigating to /seller…`);
    await tempPage.goto('https://www.whatnot.com/seller', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await tempPage.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
    let actualUrl = tempPage.url();
    info(`ws-explore: landed on ${actualUrl}`);
    let pageText = await tempPage.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
    info(`ws-explore: page preview: ${pageText.replace(/\n/g, ' ').substring(0, 200)}`);

    // If we landed in buyer mode (marketing page), try switching to seller mode.
    // Buyer mode = /seller shows "For Brands / Start Selling on Whatnot" marketing page.
    // Without seller mode, /seller/shows 404s and no seller GraphQL calls ever fire.
    const _buyerModeText = /for brands|start selling on whatnot/i.test(pageText);
    if (_buyerModeText) {
      info('ws-explore: buyer mode detected — attempting "Switch to Selling"');
      try {
        await ensureSellerMode(tempPage);
        info('ws-explore: seller mode activated');
        actualUrl = tempPage.url();
        pageText  = await tempPage.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
        info(`ws-explore: now on ${actualUrl}`);
      } catch (_smErr) {
        info('ws-explore: seller mode not activated:', _smErr.message.substring(0, 120));
        info('ws-explore: continuing — seller GraphQL ops will not appear');
        if (!CHANNEL_NAME) {
          info('ws-explore: TIP — re-run with --channel="Vortex Breaks" to try the profile-drawer switch');
          info('ws-explore:   php artisan whatnot:import --ws-explore --channel="Vortex Breaks"');
        }
      }
    }

    // Navigate to /seller/shows if not already there and page isn't a 404
    if (!actualUrl.includes('/shows') && !/404|page not found/i.test(pageText)) {
      info(`ws-explore: navigating to /seller/shows for show-specific API calls…`);
      await tempPage.goto('https://www.whatnot.com/seller/shows', { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {});
      await tempPage.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
      actualUrl = tempPage.url();
      pageText  = await tempPage.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
      info(`ws-explore: /seller/shows landed on ${actualUrl}`);
      info(`ws-explore: page preview: ${pageText.replace(/\n/g, ' ').substring(0, 200)}`);
    }

    // Final wait for lazy-loaded API calls
    await new Promise(r => setTimeout(r, 5000));
    info(`ws-explore: captured ${httpCaptures.length} HTTP API calls, ${allMessages.length} WS frames`);

  } finally {
    await tempContext.close().catch(() => {});
    fs.rm(tempDir, { recursive: true, force: true }, () => {});
  }

  // Group by topic + event for the summary table
  const byTopicEvent = {};
  for (const m of allMessages) {
    const key = `${m.dir}::${m.topic}::${m.event}`;
    if (!byTopicEvent[key]) {
      byTopicEvent[key] = { dir: m.dir, topic: m.topic, event: m.event, count: 0, sample: null };
    }
    byTopicEvent[key].count++;
    if (!byTopicEvent[key].sample) byTopicEvent[key].sample = m.payload;
  }

  // Output JSON to stdout for the PHP wrapper to parse
  const outFile = `/tmp/whatnot-ws-explore-${Date.now()}.json`;
  const result  = {
    total_messages: allMessages.length,
    topics_joined:  [...topicsJoined],
    topics:         Object.values(byTopicEvent),
    messages:       allMessages,
    http_captures:  httpCaptures,
  };
  fs.writeFileSync(outFile, JSON.stringify(result, null, 2));
  log(`ws-explore complete: ${allMessages.length} WS frames, ${httpCaptures.length} HTTP calls → ${outFile}`);
  process.stdout.write(JSON.stringify({
    output_file:    outFile,
    total_messages: allMessages.length,
    topics_joined:  [...topicsJoined],
    topics:         Object.values(byTopicEvent),
    http_captures:  httpCaptures,
  }) + '\n');
}

// ── Analytics page nav: iterate all channel shows via "See older show" ─────────
// /account/analytics?tab=livestream&live_id=<uuid> is channel-scoped:
// its "See older show" button walks through ALL past shows for the channel,
// not just shows hosted by the logged-in user. This is the only reliable way
// to import every show when the bot account is a team member but not the HOST
// of most shows (e.g. Tucker/Cate/Dennis/Matt are the actual hosts).
//
// startUuid — live_id UUID of the newest show (comes from initial API intercept).

async function scrapeViaAnalyticsPage(page, startUuid, limit) {
  // Open with a wide date range so the "See older show" nav can reach the full
  // channel history. Left to itself the app defaults to a ~2-week window
  // (start_dt/end_dt), which may cap how far back the walk can go.
  const _today  = new Date().toISOString().substring(0, 10);
  const analyticsUrl = `https://www.whatnot.com/account/analytics?tab=livestream&live_id=${startUuid}` +
                       `&start_dt=2019-01-01&end_dt=${_today}`;
  info(`analytics-nav: navigating to ${analyticsUrl}`);
  await page.goto(analyticsUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

  if (!/analytics/i.test(page.url())) {
    info(`analytics-nav: unexpected landing URL ${page.url()} — aborting`);
    return [];
  }

  // Capture each show's REAL livestream UUID. The URL live_id param is stale and
  // doesn't correspond to the displayed show, but every time a show's analytics
  // load the page fires a GraphQL query whose variables carry the true livestream
  // id. We track the most-recently-seen one so each extracted show can be tagged
  // with the correct id — used to build a valid detail_url (and later pull that
  // show's orders). Background polls don't carry a livestream-scoped id, so the
  // key-name filter keeps this from being clobbered between shows.
  let lastLiveId = null;
  const liveIdHandler = (req) => {
    try {
      if (req.method() !== 'POST' || !/graphql/i.test(req.url())) return;
      const body = req.postData();
      if (!body || body.indexOf('-') === -1) return;
      const parsed = JSON.parse(body);
      for (const op of (Array.isArray(parsed) ? parsed : [parsed])) {
        const vars = (op && op.variables) || {};
        for (const [k, v] of Object.entries(vars)) {
          if (typeof v === 'string' &&
              /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(v) &&
              /livestream|live_?id|show_?id/i.test(k)) {
            lastLiveId = v;
          }
        }
      }
    } catch {}
  };
  page.on('request', liveIdHandler);

  // Fallback id source: the analytics page likely loads all shows in one API
  // response (e.g. AccountAnalyticsSellerLivestreams) and navigates client-side
  // without a per-show request — so lastLiveId may never populate. Harvest real
  // ids straight from GraphQL RESPONSES: any object carrying a UUID `id` plus a
  // title/date. Index by normalized title and by date so each walked show can be
  // matched back to its real livestream id regardless of the response's shape.
  const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  const idByTitle = new Map();
  const idByDate  = new Map();
  const normTitle = (t) => (t || '').toLowerCase().replace(/[^a-z0-9]/g, '').slice(0, 40);
  function harvestIds(node, depth = 0) {
    if (!node || depth > 8) return;
    if (Array.isArray(node)) { for (const x of node) harvestIds(x, depth + 1); return; }
    if (typeof node !== 'object') return;
    const idVal = node.id || node.livestreamId || node.live_id || node.uuid;
    const title = node.title || node.show_title || node.name;
    if (typeof idVal === 'string' && UUID_RE.test(idVal) && title) {
      const nt = normTitle(title);
      if (nt && !idByTitle.has(nt)) idByTitle.set(nt, idVal);
      const rawDate = node.startTime || node.start_time || node.startedAt || node.started_at ||
                      node.date || node.show_date || node.createdAt || node.created_at;
      if (rawDate) {
        const s = String(rawDate);
        const d = s.includes('T') ? s.substring(0, 10) : (parseDateString(s) || null);
        if (d && !idByDate.has(d)) idByDate.set(d, idVal);
      }
    }
    for (const v of Object.values(node)) {
      if (v && typeof v === 'object') harvestIds(v, depth + 1);
    }
  }
  const respHandler = async (resp) => {
    try {
      if (!/graphql/i.test(resp.url())) return;
      const ct = resp.headers()['content-type'] || '';
      if (!ct.includes('json')) return;
      const json = await resp.json().catch(() => null);
      if (json) harvestIds(json);
    } catch {}
  };
  page.on('response', respHandler);

  // Wait for the per-show analytics view to actually render. Direct URL nav loads
  // the SPA shell first; the show metrics + nav buttons appear only after the
  // analytics data-fetch completes. Poll for a known metric label OR the show-nav
  // buttons OR the "Select Show" control (up to 20s) instead of a fixed delay.
  await page.waitForFunction(() => {
    const t = document.body.innerText || '';
    return /Estimated Sales|Completed Earnings|Show Duration|Select Show/i.test(t) ||
           document.querySelector('button[aria-label="See older show"], button[aria-label="See newer show"]') !== null;
  }, { timeout: 20000 }).catch(() => {});
  await page.waitForTimeout(1200);
  await debugShot(page, 'analytics-nav-01-start');

  // Diagnostic dump — shows exactly what the analytics page rendered so we can
  // fix the selectors/URL if extraction comes up empty.
  const diag = await page.evaluate(() => {
    const older = document.querySelector('button[aria-label="See older show"]');
    const newer = document.querySelector('button[aria-label="See newer show"]');
    const ariaBtns = Array.from(document.querySelectorAll('button[aria-label]'))
      .map(b => b.getAttribute('aria-label')).filter(Boolean).slice(0, 25);
    return {
      url:        location.href,
      bodyLen:    (document.body.innerText || '').length,
      bodySnippet:(document.body.innerText || '').replace(/\n+/g, ' | ').substring(0, 500),
      olderBtn:   older ? `present disabled=${older.disabled}` : 'ABSENT',
      newerBtn:   newer ? `present disabled=${newer.disabled}` : 'ABSENT',
      ariaLabels: ariaBtns,
    };
  }).catch(() => ({}));
  info('analytics-nav diag:', JSON.stringify(diag));

  // Rewind to the newest show first — the seed UUID may be an older user-hosted
  // show, and "See older show" only walks backward. Clicking "See newer show"
  // until it's disabled guarantees we start from the most recent channel show,
  // so the seed UUID doesn't have to be the newest.
  for (let r = 0; r < limit; r++) {
    const newerEnabled = await page.evaluate((sel) => {
      const btn = document.querySelector(sel);
      return btn && !btn.disabled;
    }, SELECTORS.showNavNewer).catch(() => false);
    if (!newerEnabled) break;
    const prevUrl = page.url();
    await page.click(SELECTORS.showNavNewer).catch(() => {});
    await page.waitForFunction(prev => location.href !== prev, prevUrl, { timeout: 6000 }).catch(() => {});
    await page.waitForTimeout(300);
  }
  info(`analytics-nav: rewound to newest show at ${page.url()}`);

  const results = [];
  const seenSignatures = new Set();

  for (let i = 0; i < limit; i++) {
    // Brief settle so the metric cards finish re-rendering to match the new show
    // before we extract (avoids pairing a new title with the prior show's metrics).
    // Kept small: the per-show cost dominates total runtime across a long history.
    await page.waitForTimeout(400);

    // Snapshot the current show's real livestream id BEFORE any further awaits
    // (which could let a background request change lastLiveId). At this point the
    // load query for the displayed show has already fired, and we haven't yet
    // navigated to the next one, so lastLiveId belongs to this show.
    const showLiveId = lastLiveId;

    // Primary: CSS-based extraction (height:160px metric cards + inline-style title)
    const data = await extractAnalyticsMetrics(page);
    info(`analytics-nav show ${i + 1}: title="${data.title}" date="${data.dateText}" cards=${data.cardCount} hasOlder=${data.hasOlder} liveId=${showLiveId || '?'}`);

    // Bodytext fallback for title + date — more resilient to style/layout changes.
    // On /account/analytics the selected show title + date appear just before the
    // "Select Show" dropdown label in the page text.
    let title    = data.title;
    let dateText = data.dateText;
    if (!title) {
      const bt = await page.evaluate(() => {
        const lines = (document.body.innerText || '').split('\n')
          .map(l => l.trim()).filter(Boolean);
        let foundTitle = null;
        let foundDate  = null;
        const selectIdx = lines.indexOf('Select Show');
        if (selectIdx >= 2) {
          const candidate     = lines[selectIdx - 2];
          const dateCandidate = lines[selectIdx - 1];
          if (candidate && candidate.length > 4) foundTitle = candidate;
          if (dateCandidate && /\d{1,2}\/\d{1,2}\/\d{4}/.test(dateCandidate)) foundDate = dateCandidate;
        }
        // Second pass: first non-trivial line that isn't a number/price/section header
        if (!foundTitle) {
          foundTitle = lines.find(l =>
            l.length > 10 && !/^\d/.test(l) && !/^[$€£¥]/.test(l) &&
            !/^(Sales Metrics|Stream Metrics|Select Show|Analytics|Dashboard|Whatnot|Home)$/i.test(l)
          ) || null;
        }
        return { title: foundTitle, dateText: foundDate };
      }).catch(() => ({ title: null, dateText: null }));
      title    = bt.title    || title;
      dateText = bt.dateText || dateText;
    }

    if (!title && data.cardCount === 0) {
      info('analytics-nav: no show data on page — stopping');
      break;
    }

    const m   = data.metrics;
    const get = (...labels) => {
      for (const l of labels) { if (m[l] !== undefined) return m[l]; }
      return null;
    };

    // Dedup / stall-guard on the CONTENT, not the URL. The analytics page swaps
    // the displayed show in place (title/date/metrics all change) WITHOUT updating
    // the live_id query param — the URL UUID is stale and unrelated to what's shown,
    // so it can't identify the current show. title+date is the reliable signature.
    const signature = `${(title || '').trim()}|${(dateText || '').trim()}`;
    if (signature.replace('|', '').trim() && seenSignatures.has(signature)) {
      info(`analytics-nav: revisited show "${signature}" — navigation stalled, stopping`);
      break;
    }
    if (signature.replace('|', '').trim()) seenSignatures.add(signature);

    // Resolve the show's REAL livestream id. Prefer a per-show request capture,
    // then the response-harvested id matched by title, then by date. The URL's
    // live_id is stale and never used here.
    const showDate = parseDateString(dateText);
    const startTime = parseTimeString(dateText);
    const showDurationMin = parseDurationToMinutes(get('Show Duration'));
    const resolvedLiveId = showLiveId
      || idByTitle.get(normTitle(title))
      || (showDate ? idByDate.get(showDate) : null)
      || null;

    results.push({
      title,
      show_date:               showDate,
      show_date_raw:           dateText,
      start_time:              startTime,
      // Whatnot doesn't expose a separate "stream ended at" value anywhere we've
      // found — derive it from the start time + the show's own reported duration.
      end_time:                startTime && showDurationMin ? addMinutesToTime(startTime, showDurationMin) : null,
      // detail_url is built from the REAL livestream id (per-show request capture
      // or response-harvested by title/date) — not the stale URL live_id. null when
      // no id resolved. This id also drives per-show order import.
      detail_url:              resolvedLiveId ? `https://www.whatnot.com/dashboard/live/${resolvedLiveId}` : null,
      whatnot_live_id:         resolvedLiveId,
      gross_revenue:           parseMoney(get('Estimated Sales')),
      whatnot_net:             parseMoney(get('Total Estimated Earnings')),
      completed_earnings:      parseMoney(get('Completed Earnings')),
      units_sold:              parseInteger(get('Orders')),
      avg_order_value:         parseMoney(get('Average Order Value', 'AOV')),
      giveaway_spend:          parseMoney(get('Giveaway Spend')),
      giveaways_count:         parseInteger(get('Giveaways')),
      buyers_count:            parseInteger(get('Buyers')),
      first_time_buyers:       parseInteger(get('First Time Buyers')),
      returning_buyers:        parseInteger(get('Returning Buyers')),
      shares_count:            parseInteger(get('Shares')),
      show_duration:           showDurationMin,
      max_concurrent_viewers:  parseInteger(get('Max Concurrent Viewers')),
      total_views:             parseInteger(get('Total Views')),
      avg_order_rating:        parseMoney(get('Average Order Rating')),
      _raw_metrics:            m,
    });

    if (!data.hasOlder) {
      info('analytics-nav: "See older show" is disabled — reached oldest show in channel history');
      break;
    }

    const prevUrl   = page.url();
    const prevTitle = title || '';
    try {
      await page.click(SELECTORS.showNavOlder);
      // Wait for the show to change — Whatnot may update the live_id param in the
      // URL OR swap the analytics content in place. Accept either signal.
      // NOTE: no networkidle wait here — analytics pages poll in the background and
      // rarely go idle, so waiting on it burned the full timeout every show (the
      // cause of the 240s process timeout). The content-change signal is enough.
      await page.waitForFunction(
        ({ prev, prevT }) => {
          if (location.href !== prev) return true;
          const el = document.querySelector('div[style*="white-space: nowrap"][style*="text-overflow: ellipsis"][style*="max-width: 90%"]');
          const t  = el ? el.textContent.trim() : '';
          return t && t !== prevT;
        },
        { prev: prevUrl, prevT: prevTitle },
        { timeout: 6000 }
      ).catch(() => {});
    } catch (navErr) {
      info(`analytics-nav: nav error at show ${i + 1}: ${navErr.message.substring(0, 100)}`);
      break;
    }
    info(`analytics-nav: advanced to show ${i + 2}`);
  }

  page.off('request', liveIdHandler);
  page.off('response', respHandler);
  const withId = results.filter(r => r.whatnot_live_id).length;
  info(`analytics-nav: harvested ${idByTitle.size} id(s) by title, ${idByDate.size} by date`);
  info(`analytics-nav: collected ${results.length} shows total (${withId} with a resolved livestream id)`);
  return results;
}

// ── Order-row extractor (shared by show-orders and orders-batch modes) ────────
// Whatnot uses dynamic class names, so we detect orders structurally: "Lot #N"
// text near a price/@buyer, else any table/grid row that carries a price. Returns
// an array of raw order objects, or { fallback:true, html, text } when nothing
// order-shaped was found (so the caller can log a diagnostic and move on).
async function extractOrdersFromPage(page) {
  return page.evaluate(() => {
    // Strategy 0 — the Seller Hub orders table (/dashboard/orders). Rows carry
    // data-testid="orders-N-row"; cells are positional:
    // [Order(title+Order #id), Date, Customer, Items(qty), Sales Channel, Price,
    //  Order Status, Earnings, Actions]. This is the most reliable shape.
    const testidRows = Array.from(document.querySelectorAll('tbody[data-testid="orders-table-body"] tr, tr[data-testid^="orders-"]'));
    if (testidRows.length > 0) {
      const parsePrice = (s) => {
        if (!s) return null;
        const neg = /-\s*\$/.test(s);
        const m = s.replace(/[^0-9.]/g, '');
        if (!m) return null;
        const v = parseFloat(m);
        if (isNaN(v)) return null;
        return neg ? -v : v;
      };
      const rows = [];
      for (const tr of testidRows) {
        const tds = Array.from(tr.querySelectorAll(':scope > td'));
        if (tds.length < 6) continue;
        const orderCell = tds[0];
        const titleEl = orderCell.querySelector('span[title]') || orderCell.querySelector('strong');
        const item_name = titleEl ? (titleEl.getAttribute('title') || titleEl.textContent || '').trim() : null;
        const orderIdMatch = (orderCell.innerText || '').match(/Order\s*#\s*(\d+)/i);
        const buyer = (tds[2]?.innerText || '').trim() || null;
        const qty = parseInt((tds[3]?.innerText || '').replace(/[^0-9]/g, ''), 10);
        const channel = (tds[4]?.innerText || '').trim() || null;
        const price = parsePrice(tds[5]?.innerText || '');
        const orderStatus = (tds[6]?.innerText || '').trim() || null;
        // Earnings cell: first strong is the amount, second line is status.
        const earnStrong = tds[7]?.querySelector('strong');
        const earnings = parsePrice(earnStrong ? earnStrong.textContent : (tds[7]?.innerText || ''));
        rows.push({
          order_id:    orderIdMatch ? orderIdMatch[1] : null,
          buyer,
          item_name,
          lot_number:  null,
          quantity:    isNaN(qty) ? 1 : qty,
          unit_price:  price,
          total_price: price,
          net_earnings: earnings,
          sales_channel: channel,
          status:      orderStatus || 'completed',
          raw_text:    (tr.innerText || '').replace(/\s+/g, ' ').trim().substring(0, 400),
        });
      }
      if (rows.length > 0) return rows;
    }

    // Strategy S — Whatnot Shipments tab (/dashboard/shipments). Each shipment gets
    // its own <tr data-testid="shipments-<id>-row"> carrying buyer/weight/dims/status
    // inline. The "Order #N" that ties it back to an existing WhatnotShowOrder only
    // renders in a nested detail <tr> after the row is expanded, but if Expand All
    // doesn't work, we still extract shipment metadata (weight, dims, carrier, tracking,
    // status) from the main row for manual matching.
    const shipmentRows = Array.from(document.querySelectorAll('tr[data-testid^="shipments-"]'));
    if (shipmentRows.length > 0) {
      const rows = [];
      for (const tr of shipmentRows) {
        const mainText = tr.innerText || '';
        const buyerLink = tr.querySelector('a[href*="/dashboard/inbox"]');
        const buyer = buyerLink ? (buyerLink.textContent || '').trim() : null;

        let detailText = '';
        const next = tr.nextElementSibling;
        if (next && next.tagName === 'TR' && next.querySelector('table')) {
          detailText = next.innerText || '';
        }

        const meta = extractShipmentMeta(mainText + '\n' + detailText);
        // Include row if it has buyer or any shipment metadata (weight, carrier, tracking, etc.)
        const hasShipmentData = meta.weight_oz || meta.shipping_carrier || meta.tracking_number;
        if (!buyer && !meta.order_id && !hasShipmentData) continue;

        rows.push({
          buyer,
          item_name:   null,
          lot_number:  null,
          quantity:    1,
          unit_price:  null,
          total_price: null,
          status:      'completed',
          raw_text:    mainText.replace(/\s+/g, ' ').trim().substring(0, 400),
          ...meta,
        });
      }
      if (rows.length > 0) return rows;
    }

    // Best-effort shipment metadata — weight, box dimensions, and carrier/service —
    // present only on the Shipments tab (not the Orders tab). Every field is
    // optional; a row missing all of them just yields an object of nulls, which
    // the caller drops via array_filter before it ever reaches the DB, so an
    // unmatched pattern here degrades gracefully instead of breaking anything.
    function extractShipmentMeta(text) {
      const weightMatch = text.match(/(\d+(?:\.\d+)?)\s*oz\b/i);
      const dimsMatch = text.match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*in\b/i);

      // Carrier regex — match "USPS", "UPS", "FedEx", "DHL" followed by service name.
      // Accept service names with spaces, dashes, slashes (e.g. "Ground Advantage", "2nd Day Air")
      const carrierMatch = text.match(/\b(USPS|UPS|FedEx|DHL)\b\s*([A-Za-z\d\s\-/]*[A-Za-z\d])?/i);

      // Tracking number — USPS: 20-22 digits; UPS/FedEx: 12+ digits. Look for bare numbers
      // that match carrier patterns, or numbers labeled as "tracking" / "tracking #" / "label"
      let trackingNumber = null;
      const trackingLabeled = text.match(/(?:tracking\s*#?|label\s*#?)\s*([0-9]{12,})/i);
      if (trackingLabeled) {
        trackingNumber = trackingLabeled[1];
      } else if (carrierMatch) {
        // Fall back to looking for long digit sequences after carrier name
        const afterCarrier = text.substring(text.indexOf(carrierMatch[0]) + carrierMatch[0].length);
        const digitsMatch = afterCarrier.match(/[\s\-]*([0-9]{12,})/);
        if (digitsMatch) trackingNumber = digitsMatch[1];
      }

      const orderIdMatch = text.match(/Order\s*#\s*(\d+)/i);

      let shippingStatus = null;
      if (/ready\s*to\s*ship/i.test(text)) shippingStatus = 'ready_to_ship';
      else if (/needs?\s*label/i.test(text)) shippingStatus = 'pending';
      else if (/label\s*created/i.test(text)) shippingStatus = 'label_created';
      else if (/\bdelivered\b/i.test(text)) shippingStatus = 'delivered';
      else if (/\breturned\b/i.test(text)) shippingStatus = 'returned';
      else if (/\bpacked\b/i.test(text)) shippingStatus = 'packed';
      else if (/\bshipped\b/i.test(text)) shippingStatus = 'shipped';
      else if (/in\s*transit/i.test(text)) shippingStatus = 'in_transit';
      else if (/out\s*for\s*delivery/i.test(text)) shippingStatus = 'out_for_delivery';

      return {
        order_id: orderIdMatch ? orderIdMatch[1] : null,
        weight_oz: weightMatch ? parseFloat(weightMatch[1]) : null,
        box_length_in: dimsMatch ? parseFloat(dimsMatch[1]) : null,
        box_width_in: dimsMatch ? parseFloat(dimsMatch[2]) : null,
        box_height_in: dimsMatch ? parseFloat(dimsMatch[3]) : null,
        shipping_carrier: carrierMatch ? carrierMatch[1].toUpperCase() : null,
        shipping_service: carrierMatch && carrierMatch[2] ? carrierMatch[2].trim() : null,
        shipping_status_scraped: shippingStatus,
        tracking_number: trackingNumber,
      };
    }

    function findParentWithText(el, maxLevels = 6) {
      let node = el;
      for (let i = 0; i < maxLevels; i++) {
        if (!node.parentElement) break;
        node = node.parentElement;
        if (node.tagName === 'TR' || node.tagName === 'LI' || node.tagName === 'ARTICLE') return node;
        const s = node.getAttribute('style') || '';
        if ((s.includes('display: flex') || s.includes('display:flex')) && s.match(/height:\s*(4|5|6|7|8)\d/)) return node;
      }
      return el.parentElement?.parentElement || el;
    }

    const results = [];
    const seen = new Set();

    // Strategy A — "Lot #N" text nodes
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let textNode;
    while ((textNode = walker.nextNode())) {
      const lotMatch = (textNode.textContent || '').match(/Lot\s*#?\s*(\d+)/i);
      if (!lotMatch) continue;
      const el = textNode.parentElement;
      if (!el) continue;
      const container = findParentWithText(el);
      if (!container || seen.has(container)) continue;
      seen.add(container);

      const containerText = container.innerText || container.textContent || '';
      const lines  = containerText.split('\n').map(l => l.trim()).filter(Boolean);
      const prices = [...containerText.matchAll(/\$[\d,]+\.?\d*/g)]
        .map(m => parseFloat(m[0].replace(/[^0-9.]/g, ''))).filter(v => !isNaN(v) && v > 0);
      const buyerMatch  = containerText.match(/@([\w.]+)/);
      const statusMatch = containerText.match(/\b(sold|completed|refunded|cancelled|pending|shipped)\b/i);
      const itemName = lines
        .filter(l => !l.startsWith('@') && !l.startsWith('$') && !l.startsWith('Lot') &&
                     !/^(sold|completed|refunded|cancelled|pending|shipped|view)$/i.test(l) && l.length > 3)
        .sort((a, b) => b.length - a.length)[0] || null;

      results.push({
        lot_number:  parseInt(lotMatch[1], 10),
        buyer:       buyerMatch ? buyerMatch[1] : null,
        item_name:   itemName,
        unit_price:  prices.length > 0 ? prices[prices.length - 1] : null,
        total_price: prices.length > 1 ? prices.reduce((a, b) => a + b, 0) : (prices[0] || null),
        status:      statusMatch ? statusMatch[1].toLowerCase() : 'completed',
        raw_text:    containerText.replace(/\s+/g, ' ').trim().substring(0, 400),
        ...extractShipmentMeta(containerText),
      });
    }

    // Strategy B — any table/grid row carrying a price (covers the shipments list,
    // which groups a show's orders by buyer rather than by lot).
    if (results.length === 0) {
      const rows = Array.from(document.querySelectorAll('tr, [role="row"], li'));
      for (const row of rows) {
        if (seen.has(row)) continue;
        const text = row.innerText || row.textContent || '';
        if (text.length < 5) continue;
        const prices = [...text.matchAll(/\$[\d,]+\.?\d*/g)]
          .map(m => parseFloat(m[0].replace(/[^0-9.]/g, ''))).filter(v => !isNaN(v) && v > 0);
        if (prices.length === 0) continue;
        seen.add(row);
        const lotMatch   = text.match(/(?:Lot\s*#?\s*|#)(\d+)/i);
        const buyerMatch = text.match(/@([\w.]+)/);
        const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
        const itemName = lines
          .filter(l => !l.startsWith('@') && !l.startsWith('$') && !/^#?\d+$/.test(l) && l.length > 3)
          .sort((a, b) => b.length - a.length)[0] || null;
        results.push({
          lot_number:  lotMatch ? parseInt(lotMatch[1], 10) : null,
          buyer:       buyerMatch ? buyerMatch[1] : null,
          item_name:   itemName,
          unit_price:  prices[prices.length - 1] || null,
          total_price: prices[0] || null,
          status:      'completed',
          raw_text:    text.replace(/\s+/g, ' ').trim().substring(0, 400),
          ...extractShipmentMeta(text),
        });
      }
    }

    if (results.length === 0) {
      return { fallback: true, html: document.body.innerHTML.substring(0, 6000), text: document.body.innerText.substring(0, 3000) };
    }
    return results;
  });
}

// Normalize raw extractor rows into the persisted order shape. Keeps a real
// order_id when the table exposed one (used for dedup), plus any extra fields
// (net earnings, sales channel) which are preserved in raw_data downstream.
function normalizeOrders(rows) {
  return (rows || [])
    .filter(o => o.order_id || o.lot_number || o.buyer || o.item_name)
    .map(o => ({
      order_id:      o.order_id || null,
      buyer:         o.buyer || null,
      item_name:     o.item_name || null,
      lot_number:    o.lot_number || null,
      quantity:      o.quantity || 1,
      unit_price:    o.unit_price ?? null,
      total_price:   o.total_price ?? null,
      net_earnings:  o.net_earnings ?? null,
      sales_channel: o.sales_channel || null,
      status:        o.status || 'completed',
      raw_text:      o.raw_text || null,
      // Shipments-tab-only fields — null on ordinary Orders-tab rows.
      weight_oz:               o.weight_oz ?? null,
      box_length_in:           o.box_length_in ?? null,
      box_width_in:            o.box_width_in ?? null,
      box_height_in:           o.box_height_in ?? null,
      shipping_carrier:        o.shipping_carrier || null,
      shipping_service:        o.shipping_service || null,
      shipping_status_scraped: o.shipping_status_scraped || null,
    }));
}

// ── Ledger-row extractor (/dashboard/ledger/overview) ─────────────────────────
// Columns are positional: [Created Date, Amount, Listing ID, Order ID (link),
// Message, Status, Transaction Type, Completed Date]. The Order ID cell links to
// /dashboard/orders/<hash>; we keep both the numeric id and the hash.
async function extractLedgerFromPage(page) {
  return page.evaluate(() => {
    const rows = [];
    for (const tr of document.querySelectorAll('table tbody tr')) {
      const tds = Array.from(tr.querySelectorAll(':scope > td'));
      if (tds.length < 7) continue;
      const txt = (i) => (tds[i]?.innerText || '').trim();
      const orderA = tds[3]?.querySelector('a[href*="/dashboard/orders/"]');
      const hash = orderA ? (orderA.getAttribute('href') || '').split('/dashboard/orders/')[1] : null;
      const created = txt(0);
      const amount  = txt(1);
      if (!created && !amount) continue;
      rows.push({
        created_date:     created || null,
        amount:           amount || null,
        listing_id:       txt(2) || null,
        order_id:         (orderA ? orderA.textContent : txt(3)).trim() || null,
        order_hash:       hash || null,
        message:          txt(4) || null,
        status:           txt(5) || null,
        transaction_type: txt(6) || null,
        completed_date:   txt(7) || null,
      });
    }
    return rows;
  });
}

// ── Main ─────────────────────────────────────────────────────────────────────

(async () => {
  const email    = process.env.WHATNOT_EMAIL    || '';
  const password = process.env.WHATNOT_PASSWORD || '';
  const _cookiesFilePath = process.env.WHATNOT_COOKIES_FILE ||
    require('path').join(__dirname, '../storage/whatnot-cookies.json');
  const hasCookieFile = require('fs').existsSync(_cookiesFilePath);

  // Credentials are only required when we don't have a session cookie file
  // and we're not in a mode that only tests cookies.
  if (!hasCookieFile && !email && MODE !== 'cookie-test' && MODE !== 'dump-cookies' && MODE !== 'path-probe' && MODE !== 'api-discover') {
    exitAfterStderr('Error: WHATNOT_EMAIL and WHATNOT_PASSWORD are required (or provide storage/whatnot-cookies.json).\n' +
      'Run: php artisan whatnot:login\n', 1);
  }

  // ── Mode: ws-explore (browser-free fast path) ──────────────────────────────
  // ws-explore only needs the session cookies + one HTTP call to get CSRF tokens.
  // It must NOT use the persistent browser profile because that profile may be
  // locked by a concurrent scraper process (SingletonLock). Instead it reads
  // cookies directly from storage/whatnot-cookies.json and uses Node.js https.
  if (MODE === 'ws-explore') {
    // Prefer live cookies saved by cookie-test mode (persistent browser session, passes SSR auth)
    // over the original export which may be stale. Fall back to original if live not present.
    const _liveCookiesPath = require('path').join(__dirname, '../storage/whatnot-live-cookies.json');
    const _wsExploreCookies = require('fs').existsSync(_liveCookiesPath) ? _liveCookiesPath : _cookiesFilePath;
    info('ws-explore using cookie file:', _wsExploreCookies);
    await runWsExploreStandalone(_wsExploreCookies);
    process.exit(0);
  }

  // ── Persistent browser profile ───────────────────────────────────────────────
  // launchPersistentContext stores cookies, localStorage, and service-worker
  // caches between runs. After the initial session is established, goto(/signin)
  // redirects straight to the dashboard and performLogin returns early — the
  // login page (and its bot detection) is never hit again.
  const USER_DATA_DIR = (() => {
    if (process.env.WHATNOT_USER_DATA_DIR) return process.env.WHATNOT_USER_DATA_DIR;
    return require('path').join(__dirname, '../storage/whatnot-browser-profile');
  })();
  require('fs').mkdirSync(USER_DATA_DIR, { recursive: true });
  info('browser profile dir:', USER_DATA_DIR);

  const browserOptions = {
    args: [
      '--no-sandbox',
      '--no-zygote',
      '--disable-dev-shm-usage',
      '--disable-crash-reporter',
      '--disable-breakpad',
      '--crash-dumps-dir=/tmp',
      '--disable-gpu',
    ],
    // Realistic Chrome/Windows UA — no "HeadlessChrome" in the string
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport:  { width: 1280, height: 900 },
    locale:    'en-US',
    env:       { TZ: 'America/Chicago' },
    // Client Hints headers must match the UA — inconsistency is a detection signal
    extraHTTPHeaders: {
      'sec-ch-ua':          '"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"',
      'sec-ch-ua-mobile':   '?0',
      'sec-ch-ua-platform': '"Windows"',
      'Accept-Language':    'en-US,en;q=0.9',
    },
    // NOTE: discover mode previously passed serviceWorkers: 'block' here (so
    // fetch() calls hit the real network instead of the SW cache, which
    // bypasses page.on('response')). That's a launchPersistentContext-only
    // option with no equivalent once the context already exists — connectOverCDP
    // attaches to a context Chromium created itself — so discover mode may miss
    // some SW-cached responses now. Every other mode is unaffected.
  };

  if (!['local', 'steel'].includes(BROWSER_BACKEND)) {
    throw new Error(`UNKNOWN_BROWSER_BACKEND: ${BROWSER_BACKEND} (expected local or steel)`);
  }

  const context = liveContext = BROWSER_BACKEND === 'steel'
    ? await launchSteelContext(browserOptions)
    : await launchWithProfileRecovery(USER_DATA_DIR, browserOptions);

  // Mask automation signals that trigger bot detection on sites like Whatnot.
  // navigator.webdriver = true is the primary signal headless Chrome sets.
  // Steel already manages its own coherent browser fingerprint; only apply the
  // legacy local-browser shims to the local backend.
  if (BROWSER_BACKEND !== 'steel') {
    await context.addInitScript(() => {
    // Core: webdriver flag
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });

    // Chrome-specific object that headless Chrome doesn't set by default
    if (!window.chrome) window.chrome = { runtime: {} };

    // Realistic plugin list
    Object.defineProperty(navigator, 'plugins', {
      get: () => {
        const arr = [{ name: 'Chrome PDF Plugin' }, { name: 'Chrome PDF Viewer' }, { name: 'Native Client' }];
        arr.item = i => arr[i];
        arr.namedItem = n => arr.find(p => p.name === n) || null;
        arr.refresh = () => {};
        return arr;
      },
    });

    Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });

    // Screen dimensions matching the viewport (headless can leave these at 0)
    try {
      Object.defineProperty(screen, 'availWidth',  { get: () => 1920 });
      Object.defineProperty(screen, 'availHeight', { get: () => 1040 });
    } catch (_) {}

    // Permissions API — headless denies notifications, real browsers default to "default"
    try {
      const origQuery = window.navigator.permissions.query.bind(window.navigator.permissions);
      window.navigator.permissions.query = (params) => {
        if (params && params.name === 'notifications') {
          return Promise.resolve({ state: 'default', onchange: null });
        }
        return origQuery(params);
      };
    } catch (_) {}

    // WebGL — headless reports "SwiftShader", which is detectable.
    //
    // The replacement has to agree with the User-Agent. These strings used to
    // say "Intel Iris OpenGL Engine", which is what macOS Chrome reports, on a
    // browser whose UA and Client Hints both claim Windows. A real Windows
    // Chrome reports an ANGLE/Direct3D string, so the old pair described a
    // machine that cannot exist.
    try {
      const getParam = WebGLRenderingContext.prototype.getParameter;
      WebGLRenderingContext.prototype.getParameter = function (parameter) {
        if (parameter === 37446) return 'Google Inc. (Intel)';   // UNMASKED_VENDOR_WEBGL
        if (parameter === 37445) {                               // UNMASKED_RENDERER_WEBGL
          return 'ANGLE (Intel, Intel(R) UHD Graphics 620 (0x00003E9B) Direct3D11 vs_5_0 ps_5_0, D3D11)';
        }
        return getParam.call(this, parameter);
      };
    } catch (_) {}

    // navigator.platform and userAgentData were left alone, so they reported
    // the truth — Linux — while the UA, the Client Hints headers and the WebGL
    // strings all claimed something else. Three operating systems in one
    // fingerprint is a stronger signal than any single unusual value, because
    // no real machine produces it.
    try {
      Object.defineProperty(navigator, 'platform', { get: () => 'Win32' });
    } catch (_) {}

    try {
      if (navigator.userAgentData) {
        Object.defineProperty(navigator.userAgentData, 'platform', { get: () => 'Windows' });
      }
    } catch (_) {}

    // A screen exactly the size of the viewport is not a shape real windows
    // take — there is always browser chrome and usually a taskbar.
    try {
      Object.defineProperty(screen, 'width',  { get: () => 1920 });
      Object.defineProperty(screen, 'height', { get: () => 1080 });
      Object.defineProperty(screen, 'colorDepth', { get: () => 24 });
      Object.defineProperty(screen, 'pixelDepth', { get: () => 24 });
    } catch (_) {}
    });
  }

  // ── Bootstrap session cookies (one-time first-run setup + manual re-auth) ────
  // Export cookies from your logged-in browser (Cookie-Editor extension → Export
  // as JSON) and save to storage/whatnot-cookies.json on the server. The scraper
  // loads them here so goto(/signin) redirects to the dashboard — login page
  // (and its bot detection) is never hit.
  //
  // launchPersistentContext's cookie jar accumulates Whatnot's own Set-Cookie
  // responses across runs — that's how the session actually stays alive past
  // whatever the original export's lifetime was. Unconditionally re-injecting
  // the bootstrap file on every run stomped that rotated state back to the
  // original snapshot each time, which is why cookies were expiring in about a
  // week instead of the 30-90 days sessions can otherwise last. So: only load
  // the file when the profile has no session yet, OR when the file itself has
  // been re-exported (mtime newer than the last time we loaded it) — the second
  // case is what makes `whatnot:login --cookie-file=...` recovery still work
  // after the profile's own session eventually does go stale.
  const _fs = require('fs');

  // Cookies alone leave a rebuilt profile half-authenticated — see the note on
  // restoreLocalStorage().
  let _bootstrappedFromFile = false;

  // Prefer whichever session is newest, not whichever file was typed by hand.
  //
  // The profile is rebuilt whenever Chromium will not start on it, and that
  // throws away the refreshed session it had accumulated — dropping the scraper
  // back to a bootstrap export that may be hours old and already rotated, which
  // is a redirect to /login and a re-export by hand. whatnot-live-cookies.json
  // is written from a live context on every successful cookie-test, so it is
  // usually the fresher of the two and costs nothing to prefer.
  //
  // An explicit WHATNOT_COOKIES_FILE still wins: someone naming a file means it.
  const _cookiesFile = resolveCookiesFile();
  const _cookiesLoadedMarker = _cookiesFile + '.loaded-mtime';
  // This distinction is also needed after the bootstrap block when deciding
  // whether an existing cf_clearance belongs to this server or came from a
  // human import, so keep it in the outer scope.
  const _isServerLiveSnapshot = _cookiesFile.endsWith('whatnot-live-cookies.json');
  if (_fs.existsSync(_cookiesFile)) {
    const _fileMtimeMs = _fs.statSync(_cookiesFile).mtimeMs;
    const _lastLoadedMtimeMs = _fs.existsSync(_cookiesLoadedMarker)
      ? Number(_fs.readFileSync(_cookiesLoadedMarker, 'utf8')) || 0
      : 0;
    const _existingCookies = await context.cookies('https://www.whatnot.com');

    // Only a human re-import counts as "newer, so load it".
    //
    // whatnot-live-cookies.json is written by this script at the end of every
    // successful run, which made it permanently the newest file — so the next
    // run read its own output back as a fresh import, re-bootstrapped, and
    // cleared the Cloudflare state the successful run had just earned. A run
    // that worked destroyed the conditions that made it work.
    //
    // Someone running whatnot:login means "use this now", so the bootstrap file
    // keeps that power. A machine-written dump of state we already have does
    // not, and is only reached for when the profile has nothing at all.
    const _isHumanImport = Boolean(process.env.WHATNOT_COOKIES_FILE)
      || _cookiesFile.endsWith('whatnot-cookies.json');

    // A server-generated live snapshot is trusted state from this exact browser/IP.
    // Reload it when it is newer than the marker so a stale persistent profile can
    // recover without falling all the way back to the older human export.
    const _shouldLoad = _existingCookies.length === 0
      || ((_isHumanImport || _isServerLiveSnapshot) && _fileMtimeMs > _lastLoadedMtimeMs);

    if (!_shouldLoad) {
      info('persistent profile already has', _existingCookies.length, 'whatnot.com cookies and bootstrap file is unchanged — skipping to preserve session refresh');
    } else {
      try {
        const _raw = JSON.parse(_fs.readFileSync(_cookiesFile, 'utf8'));
        const _sameSiteMap = { no_restriction: 'None', strict: 'Strict', lax: 'Lax' };
        const _cookies = _raw
          .filter(c => typeof c.name === 'string' && typeof c.value === 'string')
          .map(c => ({
            name:     c.name,
            value:    c.value,
            domain:   c.domain || '.whatnot.com',
            path:     c.path   || '/',
            expires:  c.expirationDate ?? c.expires ?? -1,
            httpOnly: Boolean(c.httpOnly),
            secure:   Boolean(c.secure),
            sameSite: _sameSiteMap[(c.sameSite || '').toLowerCase()] || 'Lax',
          }));
        if (_cookies.length > 0) {
          await context.addCookies(_cookies);
          _bootstrappedFromFile = true;
          _fs.writeFileSync(_cookiesLoadedMarker, String(_fileMtimeMs));

          // Human/browser exports come from a different machine, so their Cloudflare
          // edge tokens must not be replayed here. whatnot-live-cookies.json is
          // different: it is written by this server's own live Chromium context,
          // so its cf_clearance and related edge state belong to this exact IP/browser
          // and are the state we need to preserve when recovering a stale profile.
          if (!_isServerLiveSnapshot) {
            for (const _edgeCookie of CLOUDFLARE_EDGE_COOKIES) {
              await context.clearCookies({ name: _edgeCookie }).catch(() => {});
            }
          } else {
            info('preserving server-earned Cloudflare state from', _cookiesFile);
          }
          info('loaded', _cookies.length, 'session cookies from', _cookiesFile, _existingCookies.length === 0 ? '(first run)' : '(file re-exported since last load)');
        }
      } catch (e) {
        info('cookie file found but failed to load:', e.message);
      }
    }
  }

  // An escape hatch for the case reportClearance warns about: an imported
  // clearance token sits in the profile forever, because edge cookies are only
  // stripped on the bootstrap path and an established profile never takes it.
  // A clearance this profile did not earn is never useful and is actively
  // harmful: it is bound to the address and user agent that earned it, so
  // presenting it from here is a mismatched token rather than no token. It was
  // behind an opt-in flag, which meant it came back on the very next run —
  // and worse, dropping it did not stick, because the run ends by killing
  // Chromium and the cookie store is only flushed on a clean close.
  //
  // Cloudflare issues clearance for about an hour. Anything claiming months was
  // imported, so the expiry is enough to tell the two apart without guessing.
  const _importedClearance = await context.cookies('https://www.whatnot.com')
    .then((cookies) => cookies.find((c) => c.name === 'cf_clearance'
      && c.expires > 0
      && (c.expires * 1000 - Date.now()) > 24 * 60 * 60 * 1000))
    .catch(() => null);

  if ((!_isServerLiveSnapshot && _importedClearance) || process.env.WHATNOT_DROP_CLEARANCE === '1') {
    for (const _edgeCookie of CLOUDFLARE_EDGE_COOKIES) {
      await context.clearCookies({ name: _edgeCookie }).catch(() => {});
    }
    info(_importedClearance
      ? 'dropped an imported cf_clearance — its expiry says it was earned on another machine'
      : 'dropped Cloudflare edge cookies on request (WHATNOT_DROP_CLEARANCE=1)');
  }

  const page = await context.newPage();
  installChallengeHandling(page);
  installNavigationLogging(page);
  await reportClearance(context);

  // Only after a bootstrap: an established profile already has its own, and
  // overwriting that with an older export would undo the session refresh the
  // profile exists to preserve. localStorage is per-origin, so a visit to
  // whatnot.com has to come first.
  if (_bootstrappedFromFile) {
    await page.goto(URLS.home, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {});
    await restoreLocalStorage(page);
  }

  // Capture every JSON response from whatnot.com during this session.
  // Analytics and GraphQL endpoints will appear here; extractShowsFromCapture()
  // searches them for show data before falling back to DOM scraping.
  const capturedApiResponses = [];
  page.on('response', async (response) => {
    try {
      const url = response.url();
      if (!url.includes('whatnot.com')) return;
      if (url.includes('/reroute/')) return;  // skip Datadog/Segment/third-party proxies
      if (response.status() < 200 || response.status() >= 300) return;
      const ct = response.headers()['content-type'] || '';
      // In discover mode: log EVERY non-asset response so we can see what protocol
      // Whatnot uses (GraphQL, gRPC, protobuf, unusual content-type, etc.)
      if (MODE === 'discover') {
        const isAsset = /\.(js|css|png|jpg|gif|svg|ico|woff2?|ttf|eot|map)(\?|$)/.test(url)
                     || ct.includes('javascript') || ct.includes('text/css') || ct.includes('image/');
        if (!isAsset) {
          info(`[net] ${response.status()} ${ct.split(';')[0].trim() || '(no ct)'} ${url.replace('https://www.whatnot.com', '').substring(0, 120)}`);
        }
      }
      if (!ct.includes('application/json') && !ct.includes('graphql')) return;
      const text = await response.text().catch(() => '');
      if (text.length < 50) return;
      const body = JSON.parse(text);
      capturedApiResponses.push({ url, body });
      log('API captured:', url.replace('https://www.whatnot.com', '').substring(0, 100));
    } catch {}
  });

  try {
    // If we have a cookie file, attempt a fast cookie-auth check before trying form login.
    // This skips the Kasada-blocked login page entirely on subsequent runs.
    if (hasCookieFile && MODE !== 'cookie-test' && MODE !== 'dump-cookies' && MODE !== 'path-probe' && MODE !== 'api-discover') {
      // Validate the restored session on the public Whatnot shell first. Seller Hub
      // itself can be Cloudflare-challenged even when the authenticated session is
      // valid, which used to stop channel switching before it even had a chance to
      // run. A redirect from / to /login is still decisive evidence of an expired
      // session; otherwise channel-specific runs verify the requested role directly.
      // Use the account analytics shell for the authenticated preflight.
      // Both /dashboard/home and / are currently Cloudflare-challenged on this
      // server even with a valid imported session, while /account/analytics is
      // the already-established route used by the working historical analytics
      // scraper and is outside the challenged /dashboard surface.
      await page.goto('https://www.whatnot.com/dashboard/home', { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});

      // Not just "did goto resolve" — see confirmSellerHub. The answer this
      // gives is the one the rest of the run is built on, so it is worth the
      // few seconds it costs to be sure of it.
      const hub      = await confirmSellerHub(page);
      const checkUrl = hub.url;

      if (hub.reason === 'login') {
        info('cookie auth check: cookies expired, falling back to credential login');
        if (!email || !password) {
          throw new Error(
            'Session cookies are expired and no WHATNOT_EMAIL/WHATNOT_PASSWORD set. ' +
            'Run: php artisan whatnot:login'
          );
        }
        await performLogin(page, email, password);
      } else if (hub.reason === 'challenge') {
        // Stopping here is the point of the stabilised check: the alternative
        // is another quarter of an hour of clicking around a verification page
        // before failing somewhere that had nothing to do with it.
        info('cookie auth check: the session restored, but the hub was challenged before it settled');
        exitBlocked(
          classifyBlockingPage(checkUrl, hub.body) || {
            code: EXIT.AUTH_REQUIRED,
            error: 'BOT_CHALLENGE',
            message: 'Cloudflare replaced the Seller Hub with a verification page.',
          },
          checkUrl,
        );
      } else {
        if (hub.reason === 'unrecognised') {
          // Not fatal: this used to pass on the URL alone, and a marker that
          // went stale is a worse reason to stop a working scrape than it is
          // to press on and let the next step say what it found.
          info(
            'cookie auth check: no Seller Hub marker on', checkUrl,
            '— continuing, but the session or the page markup may have moved',
          );
        } else {
          info('cookie auth check: seller hub reached and settled (', checkUrl, ')');
        }
        // Seller mode, but only when the hub did not already answer.
        //
        // ensureSellerMode exists for a session that lands in buyer mode, and
        // its route out goes through the homepage to find a "Switch to Selling"
        // drawer. The homepage is the one path that is never served here, so
        // that route cannot complete — every run died in it.
        //
        // Landing on /dashboard/home makes the question mostly moot: the hub
        // only renders for a seller, so its nav being present is the answer.
        // The fallback stays for the case where it genuinely is not.
        const alreadySelling = hub.reason === 'hub';

        if (alreadySelling) {
          info('cookie auth check: the hub rendered, so this session is already selling — no switch needed');
        }

        if (! alreadySelling
          && MODE !== 'test' && MODE !== 'cookie-test' && MODE !== 'dump-cookies'
          && MODE !== 'path-probe' && MODE !== 'api-discover') {
          await ensureSellerMode(page);
        }
        // Persist localStorage so ws-explore can inject it into its temp browser.
        // The seller hub React app initialises seller-specific Phoenix topics only
        // after reading auth tokens from localStorage; cookies alone are not enough.
        try {
          const ls = await page.evaluate(() => {
            const out = {};
            for (let i = 0; i < localStorage.length; i++) {
              const k = localStorage.key(i);
              out[k] = localStorage.getItem(k);
            }
            return out;
          });
          const _lsFile = require('path').join(__dirname, '../storage/whatnot-localstorage.json');
          require('fs').writeFileSync(_lsFile, JSON.stringify(ls));
          info('saved localStorage (' + Object.keys(ls).length + ' keys) →', _lsFile);
        } catch (_lsErr) {
          info('localStorage save skipped:', _lsErr.message);
        }
      }
    } else if (MODE !== 'cookie-test' && MODE !== 'dump-cookies' && MODE !== 'path-probe' && MODE !== 'api-discover') {
      await performLogin(page, email, password);
      // After credential login, also ensure seller mode is active.
      if (MODE !== 'test') {
        await ensureSellerMode(page);
      }
    }

    // Switch to the target channel before scraping. ensureSellerMode always lands
    // on the account's PRIMARY channel, so for all-channels imports we must still
    // switch when the requested channel differs — checking the active @username
    // instead of blindly skipping whenever seller mode is active (which would
    // scrape the primary channel for every channel).
    if (MODE !== 'test' && MODE !== 'cookie-test' && MODE !== 'dump-cookies' && MODE !== 'path-probe' && MODE !== 'api-discover' && CHANNEL_NAME) {
      const active = await getActiveChannelUsername(page);
      if (active && normalizeChannelKey(active) === normalizeChannelKey(CHANNEL_NAME)) {
        info(`switchToChannel: already on target channel @${active} — no switch needed`);
      } else {
        info(`switchToChannel: active=@${active || '?'} target=@${CHANNEL_NAME} — switching`);

        // Bound the switch, but fail this requested channel if it cannot be
        // verified. Continuing on whichever account happens to be active is the
        // exact cross-channel contamination path this guard is preventing.
        await Promise.race([
          switchToChannel(page, CHANNEL_NAME),
          new Promise((_, reject) => setTimeout(
            () => reject(new Error(
              `CHANNEL_SWITCH_TIMEOUT: requested=@${CHANNEL_NAME} exceeded ${SWITCH_CHANNEL_TIMEOUT_MS}ms`
            )),
            SWITCH_CHANNEL_TIMEOUT_MS,
          )),
        ]);
      }

      // Absolute safety boundary: no scraping starts until the requested seller
      // identity is proven, even if switchToChannel changes again in the future.
      const verified = await getActiveChannelUsername(page);
      if (!verified || normalizeChannelKey(verified) !== normalizeChannelKey(CHANNEL_NAME)) {
        throw new Error(
          `CHANNEL_CONTEXT_MISMATCH: refusing to scrape. requested=@${CHANNEL_NAME} active=@${verified || '?'}`
        );
      }
      info(`CHANNEL_CONTEXT_VERIFIED requested=@${CHANNEL_NAME} active=@${verified}`);
    }

    // ── Mode: path-probe ─────────────────────────────────────────────────────
    // Which pages will this browser actually be served?
    //
    // Cloudflare rules are scoped to paths, and this account's are not uniform:
    // /seller answers 200 while / answers 403, from the same profile, seconds
    // apart. That is not something to reason about — it is something to measure,
    // because it decides which route through the site is worth writing.
    //
    // Deliberately not using the wrapped goto: waiting out a challenge is what
    // the rest of the scraper wants, and exactly what a survey must not do.
    if (MODE === 'path-probe') {
      const targets = (process.env.WHATNOT_PROBE_URLS || '')
        .split(',')
        .map((u) => u.trim())
        .filter(Boolean);

      const rawGoto = Object.getPrototypeOf(page).goto.bind(page);
      const results = [];

      for (const target of targets) {
        let status = null;

        try {
          const response = await rawGoto(target, { waitUntil: 'domcontentloaded', timeout: 25000 });
          status = response ? response.status() : null;
        } catch (e) {
          results.push({ url: target, status: null, challenged: null, note: e.message.substring(0, 120) });
          continue;
        }

        // Wait the challenge out, the way the scraper itself does.
        //
        // This used to sample the page four seconds after the request and
        // record whatever it saw. That measures the challenge *arriving*, not
        // whether the browser can pass it — and Cloudflare's managed challenge
        // is designed to be passed: it runs, sets a clearance, and reloads into
        // the real page. So every refused row here was a page the browser might
        // well have reached, and five rounds of conclusions were drawn from it.
        const settled     = await settleChallenge(page, { fatal: false });
        const body        = await readBodyText(page);
        const stillBlocked = body !== null && isChallengePage(body);

        results.push({
          url:        target,
          status,
          challenged: stillBlocked,
          // Worth keeping apart: a page served immediately and a page served
          // after the browser worked for it are both usable, and only the
          // second says the challenge is passable at all.
          hadChallenge: status === 403 || status === 503 || ! settled,
          landedOn:     page.url(),
        });

        info(`[probe] ${status} ${stillBlocked ? 'BLOCKED' : (status >= 400 ? 'cleared after challenge' : 'ok')} ${navPath(target)}`);
      }

      // ── Second question: does the block apply to the app's own requests?
      //
      // A 403 on a document navigation is not the same as the connection being
      // refused. While /seller was open, its GraphQL and socket calls came back
      // fine — so the edge is judging top-level navigations, and a route that
      // never issues one may go straight through.
      //
      // Measured, not assumed: load the one page that is served, then ask for
      // each refused path as a same-origin fetch from inside it.
      const soft = [];

      if (process.env.WHATNOT_PROBE_SOFT === '1') {
        await rawGoto('https://www.whatnot.com/seller', { waitUntil: 'domcontentloaded', timeout: 25000 })
          .catch(() => null);
        await page.waitForTimeout(4000);

        // The API surface, which is not the same surface as the HTML routes.
        //
        // Fetching /dashboard/lives asks Cloudflare for a *page*, and pages are
        // what the rule protects. The requests that demonstrably went through
        // while /seller was open were neither — they were the app's own calls
        // to /services/*. Testing page URLs and concluding "everything is
        // blocked" would retire a route that was never tried.
        // POST for GraphQL. The captured call the hub makes is a POST, and
        // asking a GraphQL endpoint with a GET is how the first version of
        // this got a 404 that said nothing about whether the surface was open.
        const apiTargets = [
          { url: 'https://www.whatnot.com/services/graphql/?operationName=__probe&ssr=0', method: 'POST' },
          { url: 'https://www.whatnot.com/api/v1/me', method: 'GET' },
        ];

        for (const target of apiTargets) {
          const probe = await page.evaluate(async ({ url, method }) => {
            try {
              const response = await fetch(url, {
                method,
                credentials: 'include',
                headers: method === 'POST' ? { 'content-type': 'application/json' } : {},
                body: method === 'POST'
                  ? JSON.stringify({ operationName: '__probe', variables: {}, query: 'query __probe { __typename }' })
                  : undefined,
              });
              const body = await response.text();

              return {
                status: response.status,
                bytes: body.length,
                challenged: /Performing security verification|Just a moment|cf_chl|Ray ID/i.test(body),
                // A GraphQL endpoint answering "no such operation" is the
                // endpoint working. Only the edge refusing it is a block.
                reachedTheApp: /errors|operationName|json/i.test(body) || response.headers.get('content-type')?.includes('json'),
              };
            } catch (e) {
              return { status: null, bytes: 0, challenged: null, note: String(e).substring(0, 120) };
            }
          }, target);

          soft.push({ url: target.url, api: true, ...probe });

          info(`[probe:api] ${probe.status ?? 'ERR'} ${probe.challenged ? 'CHALLENGED' : (probe.reachedTheApp ? 'reached the app' : 'ok')} ${probe.bytes}B ${navPath(target.url)}`);
        }

        for (const target of targets) {
          if (target.replace(/\/$/, '').endsWith('/seller')) continue;

          const probe = await page.evaluate(async (url) => {
            try {
              const response = await fetch(url, { credentials: 'include', redirect: 'follow' });
              const body = await response.text();

              return {
                status: response.status,
                bytes: body.length,
                // The challenge is served as a page like any other, so the
                // status alone would record a 200 interstitial as working.
                challenged: /Performing security verification|Just a moment|cf_chl|Ray ID/i.test(body),
              };
            } catch (e) {
              return { status: null, bytes: 0, challenged: null, note: String(e).substring(0, 120) };
            }
          }, target);

          soft.push({ url: target, ...probe });

          info(`[probe:fetch] ${probe.status ?? 'ERR'} ${probe.challenged ? 'CHALLENGED' : 'ok'} ${probe.bytes}B ${navPath(target)}`);
        }
      }

      await context.close().catch(() => {});
      writeJsonAndExit({ ok: true, results, soft });

      // writeJsonAndExit defers process.exit to the write callback, so the
      // synchronous code after it keeps running — which is how a probe that had
      // already finished went on to report "Unknown WHATNOT_MODE: path-probe".
      return;
    }

    // ── Mode: api-discover ───────────────────────────────────────────────────
    //
    // The pages are refused and the API is not, so the route is to call the API
    // directly — which means knowing what to call. The operations are in the
    // bundles /seller already loads, and those are same-origin static assets on
    // the surface that answers.
    //
    // Two sources, because they fail differently. The bundles give every
    // operation the app knows about, including ones only reached from pages we
    // cannot open; the live network shows which are actually in use and what
    // their variables look like. Neither alone is enough to write a call with.
    if (MODE === 'api-discover') {
      const rawGoto = Object.getPrototypeOf(page).goto.bind(page);

      const seen = [];
      page.on('request', (request) => {
        const url = request.url();

        if (! /\/services\/|\/api\//.test(url)) return;

        seen.push({
          url:    url.substring(0, 300),
          method: request.method(),
          // The body is where a GraphQL call keeps its query and variables.
          body:   (request.postData() || '').substring(0, 2000),
        });
      });

      // Bundles read from the Node side, not from inside the page.
      //
      // The first attempt fetched them with page.evaluate and filtered to
      // location.origin, which found nothing: the app's JavaScript is served
      // from elsewhere, and a cross-origin fetch could not have read the body
      // anyway — CORS would refuse it. Playwright sees every response the
      // browser received, whatever host it came from and whatever the page is
      // allowed to read, so the bodies are collected here instead.
      const bundleOps = new Map();

      page.on('response', async (response) => {
        const url = response.url();

        if (! /\.js(\?|$)/.test(url)) return;

        try {
          const text = await response.text();

          for (const m of text.matchAll(/\b(query|mutation)\s+([A-Z][A-Za-z0-9_]{3,})\s*[({]/g)) {
            if (! bundleOps.has(m[2])) {
              bundleOps.set(m[2], { name: m[2], kind: m[1], from: url.split('/').pop().substring(0, 60) });
            }
          }

          for (const m of text.matchAll(/operationName["'\s:=]+([A-Z][A-Za-z0-9_]{3,})/g)) {
            if (! bundleOps.has(m[1])) {
              bundleOps.set(m[1], { name: m[1], kind: 'operationName', from: url.split('/').pop().substring(0, 60) });
            }
          }
        } catch { /* a body already consumed or a redirect; nothing to read */ }
      });

      // /dashboard/home first: it is the hub's real entry point, and its code
      // is what loads the operations worth having. /seller is the fallback,
      // being the page known to answer.
      let landedOn = 'https://www.whatnot.com/dashboard/home';
      let landing  = await rawGoto(landedOn, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);

      if (! landing || landing.status() >= 400) {
        info(`[discover] /dashboard/home answered ${landing ? landing.status() : 'nothing'} — falling back to /seller`);
        landedOn = 'https://www.whatnot.com/seller';
        landing  = await rawGoto(landedOn, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
      } else {
        info('[discover] /dashboard/home answered 200 — reading the hub itself');
      }

      // Long enough for the hub to finish its own start-up calls.
      await page.waitForTimeout(12000);

      const inPageOps = await page.evaluate(async () => {
        const scripts = [...new Set([
          ...performance.getEntriesByType('resource')
            .map((e) => e.name)
            .filter((n) => n.endsWith('.js') && n.startsWith(location.origin)),
          ...[...document.querySelectorAll('script[src]')]
            .map((el) => el.src)
            .filter((src) => src.startsWith(location.origin)),
        ])];

        const found = new Map();

        for (const src of scripts.slice(0, 40)) {
          let text = '';

          try {
            const response = await fetch(src, { credentials: 'include' });
            if (! response.ok) continue;
            text = await response.text();
          } catch { continue; }

          // Two shapes: a named document (`query ShowsList(`) and an explicit
          // operationName on a request the bundle builds.
          for (const m of text.matchAll(/\b(query|mutation)\s+([A-Z][A-Za-z0-9_]{3,})\s*[({]/g)) {
            found.set(m[2], { name: m[2], kind: m[1], from: src.split('/').pop() });
          }

          for (const m of text.matchAll(/operationName["'\s:=]+([A-Z][A-Za-z0-9_]{3,})/g)) {
            if (! found.has(m[1])) found.set(m[1], { name: m[1], kind: 'operationName', from: src.split('/').pop() });
          }
        }

        return [...found.values()];
      }).catch(() => []);

      // Third source, and the one that does not depend on reading bundles at
      // all: ask the endpoint what it supports.
      //
      // Scraping names out of JavaScript is guesswork about minified code.
      // Introspection, where it is enabled, is the schema saying so itself —
      // every query the API accepts, authoritatively, in one call. When it is
      // switched off the error says that plainly, which is also worth knowing.
      const introspection = await page.evaluate(async () => {
        try {
          const response = await fetch('/services/graphql/?operationName=IntrospectionQuery&ssr=0', {
            method: 'POST',
            credentials: 'include',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({
              operationName: 'IntrospectionQuery',
              variables: {},
              query: 'query IntrospectionQuery { __schema { queryType { fields { name } } } }',
            }),
          });

          const body = await response.text();
          let json = null;
          try { json = JSON.parse(body); } catch { /* not JSON: an edge page, not the app */ }

          return {
            status: response.status,
            fields: json?.data?.__schema?.queryType?.fields?.map((f) => f.name) ?? null,
            error: json?.errors?.[0]?.message ?? null,
            preview: json ? null : body.substring(0, 200),
          };
        } catch (e) {
          return { status: null, error: String(e).substring(0, 160) };
        }
      }).catch((e) => ({ status: null, error: String(e).substring(0, 160) }));

      info(`[introspection] ${introspection.status ?? 'ERR'} ${introspection.fields ? introspection.fields.length + ' query fields' : (introspection.error || 'no schema')}`);

      // Bundles fetched from Node rather than read off the wire.
      //
      // The response listener found nothing because this profile is warm: the
      // bundles come from disk cache, so there is no response body to read.
      // context.request goes out over the network with the same cookies, past
      // both the page cache and CORS.
      const scriptUrls = await page.evaluate(() => [...new Set([
        ...performance.getEntriesByType('resource').map((e) => e.name),
        ...[...document.querySelectorAll('script[src]')].map((el) => el.src),
      ])].filter((u) => /\.js(\?|$)/.test(u))).catch(() => []);

      info(`[bundles] ${scriptUrls.length} script(s) referenced by the page`);

      const needle     = (process.env.WHATNOT_FIND || '').trim();
      const needleHits = [];

      // Chunks this page never loaded.
      //
      // /seller pulls in its own code and nothing else, so the operations that
      // list shows are in the bundle for /dashboard/lives — a page that is
      // refused. The bundle is not: it is a static asset on the surface that
      // answers, and nothing about being blocked from a page stops us reading
      // the JavaScript that page would have run.
      //
      // Next.js publishes the map itself. _buildManifest.js lists every route
      // and the chunks it needs, so this asks for the routes we cannot open and
      // reads what they would have loaded.
      const buildId = await page.evaluate(() => window.__NEXT_DATA__?.buildId ?? null).catch(() => null);

      if (buildId) {
        const manifestUrl = `https://www.whatnot.com/_next/static/${buildId}/_buildManifest.js`;
        const manifest    = await context.request.get(manifestUrl).catch(() => null);

        if (manifest && manifest.ok()) {
          const text  = await manifest.text().catch(() => '');
          const paths = [...new Set([...text.matchAll(/["'](static\/[^"']+\.js)["']/g)].map((m) => m[1]))];

          info(`[bundles] build manifest lists ${paths.length} chunk(s) across every route`);

          for (const path of paths) {
            const url = `https://www.whatnot.com/_next/${path}`;

            if (scriptUrls.includes(url)) continue;

            scriptUrls.push(url);
          }
        } else {
          info('[bundles] no build manifest — falling back to what the page loaded');
        }
      } else {
        info('[bundles] no buildId on the page (App Router publishes none)');
      }

      // Follow chunk references found in the code itself.
      //
      // The manifest route assumes the Pages Router. App Router ships neither
      // __NEXT_DATA__ nor _buildManifest.js, so that lookup can come back empty
      // on a perfectly normal Next app — and then discovery only ever sees the
      // chunks this one page happened to load.
      //
      // Every bundle names the ones it can pull in. Following those references
      // reaches the code for routes we cannot open without needing to know
      // which framework published them, or how.
      const chunkQueue = new Set(scriptUrls);
      let discoveredChunks = 0;

      const queueChunkRefs = (text) => {
        for (const m of text.matchAll(/["'`](?:\.\/)?(static\/chunks\/[A-Za-z0-9_\-./]+?\.js)["'`]/g)) {
          const url = `https://www.whatnot.com/_next/${m[1]}`;

          if (! chunkQueue.has(url) && chunkQueue.size < 500) {
            chunkQueue.add(url);
            discoveredChunks++;
          }
        }
      };

      let scanned = 0;

      // A Set iterates entries added while looping, so references found in one
      // chunk are picked up in the same pass.
      for (const url of chunkQueue) {
        if (scanned >= 400) break;

        const fetched = await context.request.get(url).catch(() => null);

        // Several hundred silent fetches is indistinguishable from a hang.
        if (++scanned % 50 === 0) info(`[bundles] read ${scanned}, ${chunkQueue.size} known…`);

        if (! fetched || ! fetched.ok()) continue;

        const text = await fetched.text().catch(() => '');

        queueChunkRefs(text);

        for (const m of text.matchAll(/\b(query|mutation)\s+([A-Z][A-Za-z0-9_]{3,})\s*[({]/g)) {
          if (! bundleOps.has(m[2])) bundleOps.set(m[2], { name: m[2], kind: m[1], from: url.split('/').pop().substring(0, 60) });
        }

        for (const m of text.matchAll(/operationName["'\s:=]+([A-Z][A-Za-z0-9_]{3,})/g)) {
          if (! bundleOps.has(m[1])) bundleOps.set(m[1], { name: m[1], kind: 'operationName', from: url.split('/').pop().substring(0, 60) });
        }

        // The shape a compiled GraphQL document takes. graphql-tag and friends
        // turn `query Foo { … }` into an AST at build time, so the source text
        // the two patterns above look for is gone by the time it ships — which
        // is why bundles that certainly contain operations matched nothing.
        for (const m of text.matchAll(/kind:\s*["']Name["']\s*,\s*value:\s*["']([A-Z][A-Za-z0-9_]{3,})["']/g)) {
          if (! bundleOps.has(m[1])) bundleOps.set(m[1], { name: m[1], kind: 'document', from: url.split('/').pop().substring(0, 60) });
        }

        for (const m of text.matchAll(/["']([A-Z][A-Za-z0-9_]{3,})["']\s*,\s*(?:kind|operation)\s*:\s*["'](?:query|mutation)["']/g)) {
          if (! bundleOps.has(m[1])) bundleOps.set(m[1], { name: m[1], kind: 'document', from: url.split('/').pop().substring(0, 60) });
        }

        // A literal to hunt for, so "found nothing" can be told apart from
        // "read the wrong files". Given a name known to exist, this reports
        // which bundle holds it and what surrounds it — which is the syntax the
        // patterns above have to match.
        if (needle && text.includes(needle)) {
          const at = text.indexOf(needle);

          needleHits.push({
            from:    url.split('/').pop().substring(0, 60),
            context: text.substring(Math.max(0, at - 220), at + 220),
          });
        }
      }

      // Whichever source saw it.
      const operations = [...new Map([
        ...(introspection.fields ?? []).map((name) => [name, { name, kind: 'schema', from: 'introspection' }]),
        ...[...bundleOps.values()].map((op) => [op.name, op]),
        ...inPageOps.map((op) => [op.name, op]),
      ]).values()];

      await flushProfile();

      writeJsonAndExit({
        ok: true,
        needle,
        needleHits,
        introspection,
        landedOn,
        landingStatus: landing ? landing.status() : null,
        scriptCount: scriptUrls.length,
        chunksScanned: scanned,
        chunksDiscovered: discoveredChunks,
        buildId,
        operations,
        // Deduped by URL and method: the hub polls, and forty copies of one
        // call is not more information than one.
        liveCalls: [...new Map(seen.map((c) => [c.method + ' ' + c.url.split('&')[0], c])).values()],
      });

      return;
    }

    // ── Mode: cookie-test ────────────────────────────────────────────────────
    // Test whether the loaded session cookies grant access to the Seller Hub.
    // Returns { ok: true, url } on success, or exits 1 on failure.
    if (MODE === 'cookie-test') {
      info('cookie-test: navigating to seller hub');
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
      const url = page.url();
      info('cookie-test: landed on', url);

      if (/\/(login|signin|auth)(\/|\?|$)/i.test(url)) {
        const bodyText = await page.evaluate(() => (document.body.innerText || '').substring(0, 200)).catch(() => '');
        // Close (not exit) so the persistent profile's on-disk cookie store gets
        // whatever the bootstrap load actually set, flushed properly — an abrupt
        // process.exit() can kill Chromium before it writes newly-set cookies to
        // disk, so the NEXT launch silently sees stale pre-import state.
        await context.close().catch(() => {});
        exitAfterStderr(
            'COOKIE_TEST_FAILED: redirected to login page — cookies are missing, expired, or invalid.\n'
            + 'URL: ' + url + '\n'
            + 'PAGE: ' + bodyText + '\n',
            1,
        );
      }

      const pageText = await page.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
      if (pageText.trim().length < 50) {
        await context.close().catch(() => {});
        exitAfterStderr(
            'COOKIE_TEST_FAILED: seller hub loaded but page appears empty — bot detection may still be active.\n'
            + 'URL: ' + url + '\n',
            1,
        );
      }

      // Save localStorage + live cookies so ws-explore can inject them into its temp browser.
      // The persistent browser's cookies may differ from whatnot-cookies.json (session refreshed);
      // only these live cookies pass Next.js SSR auth checks.
      try {
        const ls = await page.evaluate(() => {
          const out = {};
          for (let i = 0; i < localStorage.length; i++) {
            const k = localStorage.key(i);
            out[k] = localStorage.getItem(k);
          }
          return out;
        });
        const _lsFile = require('path').join(__dirname, '../storage/whatnot-localstorage.json');
        require('fs').writeFileSync(_lsFile, JSON.stringify(ls));
        info('cookie-test: saved localStorage (' + Object.keys(ls).length + ' keys) →', _lsFile);
      } catch (_e) {
        info('cookie-test: localStorage save skipped:', _e.message);
      }
      try {
        const liveCookies = await context.cookies('https://www.whatnot.com');
        const _liveCookiesFile = require('path').join(__dirname, '../storage/whatnot-live-cookies.json');
        require('fs').writeFileSync(_liveCookiesFile, JSON.stringify(liveCookies, null, 2));
        info('cookie-test: saved live cookies (' + liveCookies.length + ' cookies) →', _liveCookiesFile);
      } catch (_e) {
        info('cookie-test: live cookie save skipped:', _e.message);
      }

      process.stdout.write(JSON.stringify({ ok: true, url, page_length: pageText.length }) + '\n');
      // Close cleanly so Chromium flushes the (possibly just-updated) cookie jar
      // to the persistent profile's on-disk store before the process dies —
      // see the note above the failure-path exits for why this matters.
      await context.close().catch(() => {});
      process.exit(0);
    }

    // ── Mode: dump-cookies ────────────────────────────────────────────────────
    // Dump the current Playwright context cookies for whatnot.com to stdout as JSON.
    // Used after a successful login to persist the session.
    if (MODE === 'dump-cookies') {
      const cookies = await context.cookies('https://www.whatnot.com');
      process.stdout.write(JSON.stringify(cookies, null, 2) + '\n');
      info('dump-cookies: dumped', cookies.length, 'cookies');
      process.exit(0);
    }

    // ── Mode: test ───────────────────────────────────────────────────────────
    if (MODE === 'test') {
      log('test mode — verifying seller access');
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(1500);
      await debugShot(page, '05-seller-hub');

      const currentUrl = page.url();
      const isSeller   = currentUrl.includes('/seller') || currentUrl.includes('/creator') || currentUrl.includes('/dashboard');
      const pageTitle  = await page.title();

      if (!isSeller) {
        throw new Error(`Credentials valid but seller hub not accessible. Landed on: ${currentUrl}.`);
      }

      process.stdout.write(JSON.stringify({ connected: true, email, page_title: pageTitle, seller_url: currentUrl }) + '\n');
      log('test complete — connected');
      process.exit(0);
    }

    // ── Mode: show-orders ────────────────────────────────────────────────────
    if (MODE === 'show-orders') {
      const showUrl = process.env.WHATNOT_SHOW_URL;
      if (!showUrl) {
        exitAfterStderr('Error: WHATNOT_SHOW_URL is required for show-orders mode\n', 1);
      }

      log(`navigating to show detail: ${showUrl}`);
      await page.goto(showUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(2500);
      await debugShot(page, 'orders-01-show-page');

      // ── Step 1: Try to find and click a Lots / Orders / Sales tab ───────────
      const tabCandidates = [
        'button:has-text("Lots")',
        '[role="tab"]:has-text("Lots")',
        'button:has-text("Orders")',
        '[role="tab"]:has-text("Orders")',
        'button:has-text("Sales")',
        '[role="tab"]:has-text("Sales")',
        'button:has-text("Sold")',
        '[role="tab"]:has-text("Sold")',
        'a:has-text("Lots")',
        'a:has-text("Orders")',
      ];

      for (const sel of tabCandidates) {
        const tab = await page.$(sel).catch(() => null);
        if (!tab) continue;
        const visible = await tab.isVisible().catch(() => false);
        if (!visible) continue;
        const selected = await tab.evaluate(el => el.getAttribute('aria-selected')).catch(() => null);
        if (selected === 'true') { log(`tab already selected: ${sel}`); break; }
        await tab.click();
        await page.waitForTimeout(1800);
        log(`clicked tab: ${sel}`);
        await debugShot(page, 'orders-02-tab-clicked');
        break;
      }

      // ── Step 2: Wait for order-like content to appear ────────────────────────
      // Whatnot uses dynamic class names; look for structural signals instead.
      // "Lot #" text or "$" price values appearing in a repeated pattern is the signal.
      await page.waitForTimeout(1000);

      // ── Step 3: Extract order data from the page ─────────────────────────────
      await page.waitForTimeout(1000);
      const orders = await extractOrdersFromPage(page);

      if (orders && orders.fallback) {
        exitAfterStderr(
            'SELECTOR_MISS: Could not find order/lot rows on the show page.\n'
            + 'PAGE_TEXT_SAMPLE: ' + (orders.text || '') + '\n'
            + (DEBUG ? 'PAGE_HTML: ' + (orders.html || '') + '\n' : ''),
            2,
        );
      }

      const normalized = normalizeOrders(orders);

      if (normalized.length === 0) {
        exitAfterStderr('SELECTOR_MISS: Orders array was empty after normalization.\n', 2);
      }

      log(`show-orders: returned ${normalized.length} orders`);
      writeJsonAndExit(normalized);
      return;
    }

    // ── Mode: orders-batch ────────────────────────────────────────────────────
    // Scrape orders for MANY shows in one authenticated session (login + seller
    // mode + channel switch already happened above). Input is a JSON file of
    // [{ live_id, show_key }]; output is [{ show_key, live_id, order_count,
    // orders:[...] }]. Past-show orders live on the shipments page grouped by
    // buyer; we try that first, then fall back to the show detail page.
    if (MODE === 'orders-batch') {
      const srcFile = process.env.WHATNOT_ORDER_SOURCES_FILE;
      if (!srcFile || !require('fs').existsSync(srcFile)) {
        exitAfterStderr('Error: WHATNOT_ORDER_SOURCES_FILE (existing JSON) is required for orders-batch mode\n', 1);
      }
      let sources;
      try {
        sources = JSON.parse(require('fs').readFileSync(srcFile, 'utf8'));
      } catch (e) {
        exitAfterStderr('orders-batch: failed to parse sources file: ' + e.message + '\n', 1);
      }
      if (!Array.isArray(sources)) sources = [];
      info(`orders-batch: ${sources.length} show(s) to scrape orders for`);

      // The Seller Hub orders table (/dashboard/orders) filters by show via the
      // ?source=<livestreamId> param (same param the shipments view uses). Rows
      // are paginated (~20/page) via a "Next page" button. We scrape each page,
      // dedup by order id, and click Next until it's disabled or a page cap.
      const PAGE_CAP = 40; // ~800 orders/show ceiling to bound runtime
      const out = [];
      for (let i = 0; i < sources.length; i++) {
        const { live_id, show_key } = sources[i] || {};
        if (!live_id) { out.push({ show_key, live_id: null, order_count: 0, orders: [] }); continue; }

        const candidateUrls = [
          `https://www.whatnot.com/dashboard/orders?source=${live_id}`,
          `https://www.whatnot.com/dashboard/shipments?source=${live_id}`,
        ];

        const byId = new Map();
        const noId = [];
        let landed = false;

        for (const url of candidateUrls) {
          try {
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 25000 });
          } catch { continue; }
          // Wait for the orders table (or at least a price) to render.
          await page.waitForFunction(
            () => document.querySelector('tbody[data-testid="orders-table-body"] tr, tr[data-testid$="-row"]') !== null
                  || /\$[\d,]+\.?\d*/.test(document.body.innerText || ''),
            { timeout: 7000 }
          ).catch(() => {});
          await page.waitForTimeout(400);

          let pages = 0;
          while (pages < PAGE_CAP) {
            const extracted = await extractOrdersFromPage(page);
            if (extracted && !extracted.fallback && extracted.length) {
              landed = true;
              for (const o of normalizeOrders(extracted)) {
                if (o.order_id) byId.set(o.order_id, o);
                else noId.push(o);
              }
            } else if (pages === 0 && DEBUG) {
              info(`orders-batch: [${i + 1}/${sources.length}] no rows on ${url.replace('https://www.whatnot.com', '')} — text: ${((extracted && extracted.text) || '').replace(/\s+/g, ' ').substring(0, 140)}`);
            }
            // Advance to the next page if the button is enabled. The "Next page"
            // aria-label sits on the SVG, not the button, so locate the button via
            // its child svg (fall back to a button carrying the label directly).
            const advanced = await page.evaluate(() => {
              const svg = document.querySelector('svg[aria-label="Next page"]');
              let btn = svg ? svg.closest('button') : null;
              if (!btn) btn = document.querySelector('button[aria-label="Next page"]');
              if (btn && !btn.disabled && btn.getAttribute('aria-disabled') !== 'true') { btn.click(); return true; }
              return false;
            }).catch(() => false);
            pages++;
            if (!advanced) break;
            await page.waitForTimeout(700);
          }

          if (landed) break; // got orders from this URL; don't try the fallback
        }

        const orders = [...byId.values(), ...noId];
        info(`orders-batch: [${i + 1}/${sources.length}] show ${show_key} live_id=${live_id} → ${orders.length} order(s)`);
        out.push({ show_key, live_id, order_count: orders.length, orders });
      }

      const total = out.reduce((n, s) => n + s.order_count, 0);
      info(`orders-batch: done — ${total} order(s) across ${out.length} show(s)`);
      writeJsonAndExit(out);
      return;
    }

    // ── Mode: shipments-batch ─────────────────────────────────────────────────
    // Refreshes weight/dimensions/carrier/shipping-status for shows that ALREADY
    // have orders imported. Unlike orders-batch (which tries the orders table
    // first and only falls back to shipments when that's empty), this mode goes
    // straight to /dashboard/shipments?source=<id> every time, since that's the
    // only view carrying shipment-level detail. Rows without a resolvable
    // "Order #N" are dropped — merge-matching in persistShowOrders requires it.
    if (MODE === 'shipments-batch') {
      const srcFile = process.env.WHATNOT_ORDER_SOURCES_FILE;
      if (!srcFile || !require('fs').existsSync(srcFile)) {
        exitAfterStderr('Error: WHATNOT_ORDER_SOURCES_FILE (existing JSON) is required for shipments-batch mode\n', 1);
      }
      let sources;
      try {
        sources = JSON.parse(require('fs').readFileSync(srcFile, 'utf8'));
      } catch (e) {
        exitAfterStderr('shipments-batch: failed to parse sources file: ' + e.message + '\n', 1);
      }
      if (!Array.isArray(sources)) sources = [];
      info(`shipments-batch: ${sources.length} show(s) to refresh shipment data for`);

      const PAGE_CAP = 40;
      const out = [];
      for (let i = 0; i < sources.length; i++) {
        const { live_id, show_key } = sources[i] || {};
        if (!live_id) { out.push({ show_key, live_id: null, order_count: 0, orders: [] }); continue; }

        const url = `https://www.whatnot.com/dashboard/shipments?source=${live_id}`;
        const byId = new Map();

        try {
          await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 25000 });
          await page.waitForFunction(
            () => /\$[\d,]+\.?\d*/.test(document.body.innerText || ''),
            { timeout: 7000 }
          ).catch(() => {});
          await page.waitForTimeout(400);

          let pages = 0;
          while (pages < PAGE_CAP) {
            // Each shipment row's linked Order # only renders once expanded — click
            // "Expand All" so every row's nested Item/Order # table is in the DOM
            // before we read it. Re-run on every page since pagination re-renders
            // the table collapsed. Safe no-op if the button isn't present.
            await page.evaluate(() => {
              const btn = Array.from(document.querySelectorAll('button[aria-label="Expand All"]'))[0];
              if (btn) btn.click();
            }).catch(() => {});
            await page.waitForTimeout(300);

            const extracted = await extractOrdersFromPage(page);
            if (extracted && !extracted.fallback && extracted.length) {
              for (const o of normalizeOrders(extracted)) {
                // Only rows we can match back to an existing order are useful here.
                if (o.order_id) byId.set(o.order_id, o);
              }
            } else if (pages === 0 && DEBUG) {
              info(`shipments-batch: [${i + 1}/${sources.length}] no rows on ${url.replace('https://www.whatnot.com', '')} — text: ${((extracted && extracted.text) || '').replace(/\s+/g, ' ').substring(0, 140)}`);
            }
            const advanced = await page.evaluate(() => {
              const svg = document.querySelector('svg[aria-label="Next page"]');
              let btn = svg ? svg.closest('button') : null;
              if (!btn) btn = document.querySelector('button[aria-label="Next page"]');
              if (btn && !btn.disabled && btn.getAttribute('aria-disabled') !== 'true') { btn.click(); return true; }
              return false;
            }).catch(() => false);
            pages++;
            if (!advanced) break;
            await page.waitForTimeout(700);
          }
        } catch (navErr) {
          info(`shipments-batch: [${i + 1}/${sources.length}] nav error: ${navErr.message.substring(0, 100)}`);
        }

        const orders = [...byId.values()];
        info(`shipments-batch: [${i + 1}/${sources.length}] show ${show_key} live_id=${live_id} → ${orders.length} shipment row(s)`);
        out.push({ show_key, live_id, order_count: orders.length, orders });
      }

      const total = out.reduce((n, s) => n + s.order_count, 0);
      info(`shipments-batch: done — ${total} shipment row(s) across ${out.length} show(s)`);
      writeJsonAndExit(out);
      return;
    }

    // ── Mode: shipments-live ──────────────────────────────────────────────────
    // Discovers shows from /dashboard/lives and scrapes shipments for each,
    // extracting livestream UUIDs directly from the page (no sources file needed).
    // Useful when shows don't have stored livestream IDs.
    if (MODE === 'shipments-live') {
      info('shipments-live: navigating to /dashboard/lives to discover shows');
      await page.goto(URLS.dashboardLives, { waitUntil: 'domcontentloaded', timeout: 25000 });
      await page.waitForTimeout(800);

      // Click on "Past" tab to see completed shows
      info('shipments-live: clicking "Past" tab to view completed shows');
      await page.evaluate(() => {
        const tabs = Array.from(document.querySelectorAll('button, [role="tab"], a'));
        const pastTab = tabs.find(el => /\bpast\b/i.test(el.textContent || ''));
        if (pastTab) {
          pastTab.click();
          return true;
        }
        return false;
      }).catch(() => {});

      await page.waitForTimeout(1000);
      await page.waitForFunction(
        () => document.querySelectorAll('a[href*="/dashboard/live/"], a[href*="/live/"]').length > 0,
        { timeout: 10000 }
      ).catch(() => {});
      await page.waitForTimeout(500);

      // Extract livestream UUIDs from the live shows page.
      // Pattern: href="/dashboard/live/38c4c01d-a1a8-4ee1-9aca-fc51e814742a"
      const liveIds = await page.evaluate(() => {
        const seen = new Set();
        const ids = [];
        for (const a of document.querySelectorAll('a[href]')) {
          const href = a.getAttribute('href') || '';
          const m = href.match(/\/(?:dashboard\/)?live\/([\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12})/i);
          if (m && !seen.has(m[1])) {
            seen.add(m[1]);
            ids.push(m[1]);
          }
        }
        return ids;
      });

      info(`shipments-live: discovered ${liveIds.length} unique show(s) from /dashboard/lives`);

      const PAGE_CAP = 40;
      const out = [];
      for (let i = 0; i < liveIds.length; i++) {
        const live_id = liveIds[i];
        const url = `https://www.whatnot.com/dashboard/shipments?source=${live_id}`;
        const byId = new Map();

        try {
          await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 25000 });
          await page.waitForFunction(
            () => /\$[\d,]+\.?\d*/.test(document.body.innerText || ''),
            { timeout: 7000 }
          ).catch(() => {});
          await page.waitForTimeout(400);

          let pages = 0;
          while (pages < PAGE_CAP) {
            // Click Expand All and wait for nested rows to render (up to 2 seconds)
            await page.evaluate(() => {
              const btn = Array.from(document.querySelectorAll('button[aria-label="Expand All"]'))[0];
              if (btn) btn.click();
            }).catch(() => {});

            // Wait for expanded content to render
            await page.waitForFunction(
              () => {
                const expandedRows = document.querySelectorAll('tr[data-testid^="shipments-"] + tr');
                return expandedRows.length > 0;
              },
              { timeout: 2000 }
            ).catch(() => {
              // If no expanded rows found, that's ok — page might have no shipments
            });
            await page.waitForTimeout(500);

            const extracted = await extractOrdersFromPage(page);
            if (extracted && !extracted.fallback && extracted.length) {
              for (const o of normalizeOrders(extracted)) {
                if (o.order_id) byId.set(o.order_id, o);
              }
            }

            const advanced = await page.evaluate(() => {
              const svg = document.querySelector('svg[aria-label="Next page"]');
              let btn = svg ? svg.closest('button') : null;
              if (!btn) btn = document.querySelector('button[aria-label="Next page"]');
              if (btn && !btn.disabled && btn.getAttribute('aria-disabled') !== 'true') { btn.click(); return true; }
              return false;
            }).catch(() => false);
            pages++;
            if (!advanced) break;
            await page.waitForTimeout(700);
          }
        } catch (navErr) {
          info(`shipments-live: [${i + 1}/${liveIds.length}] nav error: ${navErr.message.substring(0, 100)}`);
        }

        const orders = [...byId.values()];
        info(`shipments-live: [${i + 1}/${liveIds.length}] live_id=${live_id} → ${orders.length} shipment row(s)`);
        out.push({ live_id, order_count: orders.length, orders });
      }

      const total = out.reduce((n, s) => n + s.order_count, 0);
      info(`shipments-live: done — ${total} shipment row(s) across ${out.length} show(s)`);
      writeJsonAndExit(out);
      return;
    }

    // ── Mode: ledger ──────────────────────────────────────────────────────────
    // Scrape the Whatnot financial ledger (/dashboard/ledger/overview) for a date
    // window (<=31 days, enforced by Whatnot). Sets the Start/End dates via the
    // "Edit Dates" dialog, then paginates the table. Login/seller-mode/channel
    // switch already ran above, so this is authenticated on the active channel.
    if (MODE === 'ledger') {
      const from = (process.env.WHATNOT_LEDGER_FROM || '').trim();
      const to   = (process.env.WHATNOT_LEDGER_TO   || '').trim();
      info(`ledger: scraping window ${from || '(default)'} .. ${to || '(default)'}`);

      await page.goto('https://www.whatnot.com/dashboard/ledger/overview', { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForFunction(
        () => document.querySelector('table tbody tr') !== null || /Ledger|Finances/i.test(document.body.innerText || ''),
        { timeout: 15000 }
      ).catch(() => {});
      await page.waitForTimeout(700);

      // Apply the date window via the "Edit Dates" dialog.
      if (from && to) {
        const opened = await page.evaluate(() => {
          const btn = Array.from(document.querySelectorAll('button'))
            .find(b => (b.getAttribute('title') || '').trim() === 'Edit Dates' || /edit dates/i.test((b.textContent || '').trim()));
          if (btn) { btn.click(); return true; }
          return false;
        }).catch(() => false);
        // The dialog renders in a portal after a short delay — wait for its date
        // inputs to actually mount before filling (a fixed 600ms was too short).
        await page.waitForSelector('input[type="date"]', { timeout: 6000 }).catch(() => {});

        // Resolve the Start/End inputs by their labels (ids are dynamic); fall back
        // to the last two date inputs on the page. Use Playwright's fill() ONLY —
        // it drives the native value setter that React's controlled input tracks.
        // (A manual el.value=… + dispatch does NOT trigger React onChange and was
        // leaving the applied range empty, so every window returned nothing.)
        const inputForLabel = async (labelRx) => {
          const h = await page.evaluateHandle((rx) => {
            const re = new RegExp(rx, 'i');
            const lbl = Array.from(document.querySelectorAll('label')).find(l => re.test((l.textContent || '').trim()));
            if (lbl) { const id = lbl.getAttribute('for'); if (id) return document.getElementById(id); }
            return null;
          }, labelRx);
          return h.asElement();
        };

        let startEl = await inputForLabel('^start date$');
        let endEl   = await inputForLabel('^end date$');
        if (!startEl || !endEl) {
          const inputs = await page.$$('input[type="date"]').catch(() => []);
          if (inputs.length >= 2) { startEl = inputs[inputs.length - 2]; endEl = inputs[inputs.length - 1]; }
        }

        if (startEl && endEl) {
          await startEl.fill(from).catch(() => {});
          await endEl.fill(to).catch(() => {});
          await page.waitForTimeout(300);
          const updated = await page.evaluate(() => {
            const btn = Array.from(document.querySelectorAll('button')).find(b => /^update$/i.test((b.textContent || '').trim()) && !b.disabled);
            if (btn) { btn.click(); return true; }
            return false;
          }).catch(() => false);
          info(`ledger: applied date window ${from}..${to} (update clicked=${updated})`);
          // Let the filtered table re-fetch and settle before extracting.
          await page.waitForTimeout(2500);
        } else {
          info(`ledger: date inputs not found (opened dialog=${opened}) — scraping default range`);
        }
      }

      const byKey = new Map();
      for (let p = 0; p < 60; p++) {
        await page.waitForFunction(() => document.querySelector('table tbody tr') !== null, { timeout: 6000 }).catch(() => {});
        await page.waitForTimeout(350);
        const rows = await extractLedgerFromPage(page);
        for (const r of rows) {
          const key = [r.order_id || '', r.listing_id || '', r.created_date || '', r.amount || '', r.transaction_type || ''].join('|');
          if (!byKey.has(key)) byKey.set(key, r);
        }
        const advanced = await page.evaluate(() => {
          const svg = document.querySelector('svg[aria-label="Next page"]');
          let btn = svg ? svg.closest('button') : null;
          if (!btn) btn = document.querySelector('button[aria-label="Next page"]');
          if (btn && !btn.disabled && btn.getAttribute('aria-disabled') !== 'true') { btn.click(); return true; }
          return false;
        }).catch(() => false);
        if (!advanced) break;
        await page.waitForTimeout(700);
      }

      const out = [...byKey.values()];
      info(`ledger: extracted ${out.length} entries for ${from || '?'}..${to || '?'}`);

      // Diagnose an empty result: where did we land, is there a table, what's on
      // the page? (Only logged when nothing was found, to keep noise down.)
      if (out.length === 0) {
        const diag = await page.evaluate(() => ({
          url:       location.href,
          tables:    document.querySelectorAll('table').length,
          tbodyRows: document.querySelectorAll('table tbody tr').length,
          hasEditDates: /edit dates/i.test(document.body.innerText || ''),
          hasLedgerTab: /Ledger/i.test(document.body.innerText || ''),
          dateInputs: document.querySelectorAll('input[type="date"]').length,
          bodySnippet: (document.body.innerText || '').replace(/\n+/g, ' | ').substring(0, 500),
        })).catch(() => ({}));
        info('ledger diag:', JSON.stringify(diag));
        await debugShot(page, 'ledger-empty');
      }

      writeJsonAndExit(out);
      return;
    }

    // ── Mode: seller-shows ────────────────────────────────────────────────────
    // Scrapes /seller/shows to capture show URLs for each past show.
    // Returns [{title, show_date, detail_url}].
    if (MODE === 'seller-shows') {
      log('navigating to seller shows list');
      await page.goto(URLS.shows, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(2500);
      await debugShot(page, 'seller-shows-01-list');

      // Scroll to load more shows
      for (let i = 0; i < 5; i++) {
        await page.evaluate(() => window.scrollBy(0, window.innerHeight));
        await page.waitForTimeout(600);
      }
      await debugShot(page, 'seller-shows-02-scrolled');

      const shows = await page.evaluate(() => {
        const results = [];
        const seen = new Set();

        // Find all links that look like show detail pages
        const anchors = Array.from(document.querySelectorAll('a[href]'));
        for (const a of anchors) {
          const href = a.getAttribute('href') || '';
          // Show URLs: /live/<username>/<id> or /show/<id> or /seller/shows/<id>
          // Lookahead (?=[?#]|$) prevents matching sub-pages like /analytics, /orders
          if (!(/\/live\/[^/]+\/[^/?#\s]+(?=[?#]|$)/.test(href) || /\/show\/[\w-]+(?=[?#]|$)/.test(href) || /\/seller\/shows\/[\w-]+(?=[?#]|$)/.test(href))) continue;
          const fullUrl = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
          if (seen.has(fullUrl)) continue;
          seen.add(fullUrl);

          // Walk up from the anchor to find its container
          let container = a;
          for (let i = 0; i < 6; i++) {
            container = container.parentElement;
            if (!container) break;
            const t = container.innerText || container.textContent || '';
            if (t.length > 20) break;
          }

          const containerText = container ? (container.innerText || container.textContent || '') : a.textContent || '';
          const dateMatch = containerText.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/);
          let showDate = null;
          if (dateMatch) {
            const [, m, d, y] = dateMatch;
            const year = y.length === 2 ? '20' + y : y;
            showDate = `${year}-${m.padStart(2,'0')}-${d.padStart(2,'0')}`;
          }

          // Title: the anchor text or longest non-date, non-price line in container
          const anchorText = (a.textContent || '').trim();
          const title = anchorText.length > 3 ? anchorText : (
            (containerText.split('\n').map(l => l.trim()).filter(l => l.length > 5 && !/^\$/.test(l) && !/^\d{1,2}\//.test(l))[0]) || null
          );

          results.push({ title, show_date: showDate, detail_url: fullUrl });
        }

        return results.length > 0 ? results : {
          fallback: true,
          text: document.body.innerText.substring(0, 3000),
        };
      });

      if (shows && shows.fallback) {
        exitAfterStderr('SELECTOR_MISS: No show links found on /seller/shows.\n' + 'PAGE_TEXT: ' + (shows.text || '') + '\n', 2);
      }

      log(`seller-shows: returned ${shows.length} show URLs`);
      writeJsonAndExit(shows);
      return;
    }

    // ── Mode: discover ───────────────────────────────────────────────────────
    // Crawls every nav link found in the Seller Hub, capturing all JSON API
    // calls each page makes. Output lets you build targeted REST calls instead
    // of scraping HTML.
    //
    // Usage:  WHATNOT_MODE=discover php artisan whatnot:import --discover
    // Output: JSON with { nav_links, pages: [{ url, nav_label, api_endpoints }] }
    if (MODE === 'discover') {
      // Log every outbound request in discover mode (not just captured JSON responses).
      // This reveals API calls with unusual content-types, gRPC, or other protocols
      // that the response handler's JSON filter would otherwise silently skip.
      page.on('request', (request) => {
        try {
          const url = request.url();
          if (!url.includes('whatnot.com')) return;
          if (url.includes('/reroute/')) return;
          // Skip JS/CSS/font bundles and Next.js static chunks
          if (/\.(js|css|png|jpg|gif|svg|ico|woff2?|ttf|eot|map)(\?|$)/.test(url)) return;
          if (url.includes('/_next/static/') || url.includes('/_next/webpack')) return;
          info(`[req] ${request.method()} ${url.replace('https://www.whatnot.com', '').substring(0, 120)}`);
        } catch {}
      });

      // Intercept WebSocket frames — Whatnot uses Phoenix Channels (vsn=2.0.0) over WS
      // for all seller hub data (shows, orders, payouts, analytics, etc.).
      // Phoenix frame format: [join_ref, ref, topic, event, payload]
      // We log every SEND and RECV to stderr so we can read the channel/event names,
      // then push received data into capturedApiResponses for the discover output.
      page.on('websocket', (ws) => {
        if (!ws.url().includes('whatnot.com')) return;
        info(`[ws] CONNECT /services/live/socket/websocket (Phoenix vsn=2.0.0)`);
        ws.on('framereceived', ({ payload }) => {
          try {
            if (typeof payload !== 'string' || payload.length < 2) return;
            const data = JSON.parse(payload);
            // Phoenix Channels: [join_ref, ref, topic, event, payload]
            if (Array.isArray(data) && data.length === 5) {
              const [, ref, topic, event, wsPayload] = data;
              const summary = JSON.stringify(wsPayload || {}).substring(0, 120);
              info(`[ws] RECV  ref=${ref || '-'} topic=${topic} event=${event} ${summary}`);
              // Push with a stable URL key so deduplicate works: topic + event
              capturedApiResponses.push({
                url: `ws://phoenix/${topic}/${event}`,
                body: { topic, event, payload: wsPayload },
              });
            } else if (payload.length > 2) {
              info(`[ws] RECV  (non-phoenix) ${payload.substring(0, 120)}`);
              capturedApiResponses.push({ url: ws.url() + '#ws', body: data });
            }
          } catch {}
        });
        ws.on('framesent', ({ payload }) => {
          try {
            if (typeof payload !== 'string' || payload.length < 2) return;
            const data = JSON.parse(payload);
            if (Array.isArray(data) && data.length === 5) {
              const [, ref, topic, event, wsPayload] = data;
              const summary = JSON.stringify(wsPayload || {}).substring(0, 80);
              info(`[ws] SEND  ref=${ref || '-'} topic=${topic} event=${event} ${summary}`);
            } else {
              info(`[ws] SEND  ${payload.substring(0, 80)}`);
            }
          } catch {}
        });
        ws.on('close', () => info(`[ws] CLOSE`));
      });

      // Monkey-patch window.fetch so responses served from a service-worker cache
      // (which bypass page.on('response') since no real HTTP request is made) are
      // still captured. Runs on each page.goto() navigation as a page-level init script.
      await page.addInitScript(() => {
        window.__wn_captures = [];
        const _orig = window.fetch;
        if (!_orig) return;
        window.fetch = async function () {
          const resp = await _orig.apply(this, arguments);
          try {
            const req = arguments[0];
            const url = typeof req === 'string' ? req
                      : (req instanceof URL ? req.href : (req && req.url) || '');
            if (url.includes('whatnot.com') && !url.includes('/reroute/')) {
              const ct = resp.headers.get('content-type') || '';
              if (ct.includes('application/json') || ct.includes('graphql')) {
                resp.clone().text().then(t => {
                  try {
                    if (t.length > 50) window.__wn_captures.push({ url, body: JSON.parse(t) });
                  } catch {}
                }).catch(() => {});
              }
            }
          } catch {}
          return resp;
        };
      });

      // Helper: snapshot and summarise all NEW API calls captured since last call.
      // Also drains the in-page fetch interceptor (picks up SW-cached responses).
      let lastCaptureIdx = 0;
      async function drainCaptures() {
        // Poll the in-page fetch interceptor; splice(0) atomically empties the array
        const inPage = await page.evaluate(() => {
          const c = window.__wn_captures || [];
          window.__wn_captures = [];
          return c;
        }).catch(() => []);
        for (const c of inPage) {
          // Avoid duplicating what page.on('response') already captured via HTTP
          if (!capturedApiResponses.some(r => r.url === c.url)) {
            capturedApiResponses.push(c);
            info(`[fetch-intercept] ${c.url.replace('https://www.whatnot.com', '').substring(0, 100)}`);
          }
        }

        const fresh = capturedApiResponses.slice(lastCaptureIdx);
        lastCaptureIdx = capturedApiResponses.length;
        return fresh
          // Strip out third-party telemetry that Whatnot proxies via /reroute/
          // (Datadog, Segment, Amplitude, etc.) — not Whatnot's own APIs
          .filter(r => !r.url.includes('/reroute/'))
          .map(r => {
            const body = r.body;
            let topKeys;
            let method = 'GET';
            if (r.url.startsWith('ws://phoenix/')) {
              // Phoenix Channels capture: { topic, event, payload }
              // Keys = the payload keys so we can see what data fields arrived
              const pl = body && body.payload;
              topKeys = pl && typeof pl === 'object' && !Array.isArray(pl)
                ? Object.keys(pl).slice(0, 20)
                : (body ? ['topic:' + body.topic, 'event:' + body.event] : []);
              method = 'WS';
            } else if (r.url.startsWith('__dom__:')) {
              topKeys = ['tables', 'listItems'];
              method = 'DOM';
            } else {
              topKeys = typeof body === 'object' && body !== null
                ? Object.keys(Array.isArray(body) ? (body[0] || {}) : body).slice(0, 20)
                : [];
              if (r.url.includes('/graphql')) method = 'POST';
            }
            return {
              method,
              url:     r.url,
              keys:    topKeys,
              preview: JSON.stringify(body).substring(0, 800),
            };
          });
      }

      // Helper: visit a page, wait for API calls to settle, scroll once, drain
      async function visitAndCapture(url, label, shotSuffix) {
        info(`discover: visiting [${label}] ${url}`);
        try {
          await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
          await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
          // Extra wait for Phoenix Channel data pushes: the WS connection is established
          // during pageload and Phoenix immediately sends channel join replies + data,
          // but these arrive slightly after networkidle (which only tracks HTTP).
          await page.waitForTimeout(4000);
          if (shotSuffix) await debugShot(page, `discover-${shotSuffix}`);

          // Scroll to trigger lazy-load API calls
          for (let i = 0; i < 3; i++) {
            await page.evaluate(() => window.scrollBy(0, window.innerHeight));
            await page.waitForTimeout(600);
          }
          await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});
          await page.waitForTimeout(500);

          // Diagnostic: confirm the page actually loaded meaningful content
          const pageSnap = await page.evaluate(() => ({
            url:     location.href,
            title:   document.title,
            bodyLen: (document.body.innerText || '').length,
          })).catch(() => ({ url: '?', title: '?', bodyLen: 0 }));
          info(`  ├ loaded: "${pageSnap.title}" (${pageSnap.bodyLen} chars) @ ${pageSnap.url}`);

          // page.goto() triggers Next.js SSR: the server embeds all page data in
          // <script id="__NEXT_DATA__"> and no XHR/fetch calls fire for the initial
          // load. Pull that payload — it contains shows, orders, payouts, etc.
          const nextData = await page.evaluate(() => {
            const el = document.getElementById('__NEXT_DATA__');
            if (!el || !el.textContent) return null;
            try { return JSON.parse(el.textContent); } catch { return null; }
          }).catch(() => null);
          if (nextData) {
            capturedApiResponses.push({ url: '__next_ssr__:' + url, body: nextData });
            const propKeys = Object.keys(nextData.props?.pageProps || nextData.props || {}).join(', ');
            info(`  ├ SSR(__NEXT_DATA__) — pageProps keys: ${propKeys || '(empty)'}`);
          } else {
            info(`  ├ no __NEXT_DATA__ (client-only SPA page or not Next.js)`);
          }

          // DOM extraction — pull visible table/list data as a last-resort fallback.
          // If the app renders data server-side or from a SW cache with no observable
          // HTTP response, at least we can see what text is on screen.
          const domSnap = await page.evaluate(() => {
            const tables = [];
            for (const tbl of document.querySelectorAll('table')) {
              const rows = [];
              for (const tr of tbl.querySelectorAll('tr')) {
                const cells = Array.from(tr.querySelectorAll('td, th')).map(c => c.innerText.trim());
                if (cells.some(c => c.length > 0)) rows.push(cells);
              }
              if (rows.length > 1) tables.push(rows.slice(0, 20));
            }
            // Also grab any visible card/row text blocks (common in React list UIs)
            const listItems = Array.from(document.querySelectorAll('[class*="row"], [class*="card"], [class*="item"], li'))
              .slice(0, 30)
              .map(el => el.innerText.trim().replace(/\s+/g, ' ').substring(0, 200))
              .filter(t => t.length > 10);
            return { tables, listItems: [...new Set(listItems)] };
          }).catch(() => null);
          if (domSnap) {
            if (domSnap.tables.length > 0) {
              info(`  ├ DOM: ${domSnap.tables.length} table(s), largest ${domSnap.tables[0].length} rows`);
              capturedApiResponses.push({ url: '__dom__:' + url, body: domSnap });
            }
            if (domSnap.listItems.length > 0) {
              info(`  ├ DOM: ${domSnap.listItems.length} list items visible`);
              if (!domSnap.tables.length) {
                capturedApiResponses.push({ url: '__dom__:' + url, body: domSnap });
              }
            }
          }
        } catch (e) {
          info(`discover: error visiting ${url}: ${e.message}`);
        }
        return await drainCaptures();
      }

      // ── Step 1: land on Seller Hub home, grab all nav links ──────────────
      log('discover: landing on Seller Hub home to collect nav links');
      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(2000);
      await debugShot(page, 'discover-00-seller-hub');
      await drainCaptures(); // discard hub-home API calls — we'll capture them properly below

      // Extract every link in the seller hub sidebar / nav
      const navLinks = await page.evaluate(() => {
        const seen  = new Set();
        const links = [];
        const allAs = Array.from(document.querySelectorAll('a[href], nav a, [role="navigation"] a, aside a'));
        for (const a of allAs) {
          const href  = (a.getAttribute('href') || '').trim();
          const label = (a.innerText || a.textContent || '').trim().replace(/\s+/g, ' ');
          // Keep only internal Whatnot seller/dashboard paths
          if (!href || href === '#' || href.startsWith('http') && !href.includes('whatnot.com')) continue;
          const full = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
          // Only seller hub or dashboard pages
          if (!/whatnot\.com\/(seller|dashboard|creator)/.test(full)) continue;
          if (seen.has(full)) continue;
          seen.add(full);
          links.push({ href: full, label: label.substring(0, 60) || '(no label)' });
        }
        return links;
      });

      info(`discover: found ${navLinks.length} nav links on Seller Hub`);

      // ── Step 2: also seed with known hub pages that may not be in nav ─────
      const seedPages = [
        { href: 'https://www.whatnot.com/seller',                       label: 'Seller Hub Home' },
        { href: 'https://www.whatnot.com/seller/shows',                 label: 'Shows List' },
        { href: 'https://www.whatnot.com/seller/orders',                label: 'Orders' },
        { href: 'https://www.whatnot.com/seller/payouts',               label: 'Payouts' },
        { href: 'https://www.whatnot.com/seller/analytics',             label: 'Analytics' },
        { href: 'https://www.whatnot.com/seller/inventory',             label: 'Inventory' },
        { href: 'https://www.whatnot.com/seller/listings',              label: 'Listings' },
        { href: 'https://www.whatnot.com/seller/shipping',              label: 'Shipping' },
        { href: 'https://www.whatnot.com/seller/buyers',                label: 'Buyers' },
        { href: 'https://www.whatnot.com/seller/marketing',             label: 'Marketing' },
        { href: 'https://www.whatnot.com/dashboard/analytics/overview', label: 'Dashboard Analytics' },
        { href: 'https://www.whatnot.com/dashboard/shows',              label: 'Dashboard Shows' },
        { href: 'https://www.whatnot.com/dashboard/orders',             label: 'Dashboard Orders' },
      ];

      // Merge nav links + seeds, deduplicate by href
      const allSeenHrefs = new Set(navLinks.map(l => l.href));
      for (const s of seedPages) {
        if (!allSeenHrefs.has(s.href)) {
          navLinks.push(s);
          allSeenHrefs.add(s.href);
        }
      }

      // ── Step 3: visit each page and capture its API calls ─────────────────
      const pageResults = [];
      let shotIdx = 1;

      for (const link of navLinks) {
        const apis = await visitAndCapture(link.href, link.label, `page-${String(shotIdx).padStart(2,'0')}`);
        shotIdx++;
        pageResults.push({
          url:           link.href,
          nav_label:     link.label,
          landed_url:    page.url(),
          api_count:     apis.length,
          api_endpoints: apis,
        });
        info(`  → ${apis.length} API endpoint(s) captured`);
      }

      // ── Step 4: Deep crawl — visit individual show detail pages ──────────
      // Now that we know what pages exist, drill into individual show pages and
      // click through every tab to trigger the data-fetch API calls that only
      // fire on interaction (Orders, Lots, Sales, Buyers, Analytics, etc.).
      info('discover: === PHASE 2: Deep crawl individual show pages ===');
      try {
        // Navigate to the shows list to extract show detail links
        await page.goto('https://www.whatnot.com/seller/shows', { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await page.waitForTimeout(2000);
        for (let i = 0; i < 3; i++) {
          await page.evaluate(() => window.scrollBy(0, window.innerHeight));
          await page.waitForTimeout(500);
        }
        await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});
        await drainCaptures(); // discard — already captured this page in Phase 1

        const showDetailLinks = await page.evaluate(() => {
          const links = [];
          const seen = new Set();
          for (const a of document.querySelectorAll('a[href]')) {
            const href = a.getAttribute('href') || '';
            if (!(/\/seller\/shows\/[\w-]+(?=[?#]|$)/.test(href) ||
                  /\/live\/[^/]+\/[^/?#\s]+(?=[?#]|$)/.test(href))) continue;
            const full = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
            if (seen.has(full)) continue;
            seen.add(full);
            links.push(full);
          }
          return links.slice(0, 5);
        });

        info(`discover: found ${showDetailLinks.length} show detail pages to deep-crawl`);

        const SHOW_TAB_SELECTORS = [
          ['Overview',  'button:has-text("Overview"), [role="tab"]:has-text("Overview")'],
          ['Orders',    'button:has-text("Orders"),   [role="tab"]:has-text("Orders")'],
          ['Lots',      'button:has-text("Lots"),     [role="tab"]:has-text("Lots")'],
          ['Sales',     'button:has-text("Sales"),    [role="tab"]:has-text("Sales")'],
          ['Buyers',    'button:has-text("Buyers"),   [role="tab"]:has-text("Buyers")'],
          ['Analytics', 'button:has-text("Analytics"),[role="tab"]:has-text("Analytics")'],
          ['Payouts',   'button:has-text("Payouts"),  [role="tab"]:has-text("Payouts")'],
        ];

        for (let showIdx = 0; showIdx < showDetailLinks.length; showIdx++) {
          const showUrl = showDetailLinks[showIdx];
          info(`discover: deep-crawl show ${showIdx + 1}/${showDetailLinks.length}: ${showUrl}`);
          try {
            await page.goto(showUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            await page.waitForTimeout(2000);
            if (DEBUG) await debugShot(page, `discover-show-${showIdx + 1}-load`);

            // Pull Next.js SSR data from the show detail page (same as nav pages)
            const showNextData = await page.evaluate(() => {
              const el = document.getElementById('__NEXT_DATA__');
              if (!el || !el.textContent) return null;
              try { return JSON.parse(el.textContent); } catch { return null; }
            }).catch(() => null);
            if (showNextData) {
              capturedApiResponses.push({ url: '__next_ssr__:' + showUrl, body: showNextData });
              const propKeys = Object.keys(showNextData.props?.pageProps || showNextData.props || {}).join(', ');
              info(`  ├ SSR(__NEXT_DATA__) — pageProps keys: ${propKeys || '(empty)'}`);
            }

            const loadApis = await drainCaptures();
            if (loadApis.length > 0) {
              pageResults.push({
                url:           showUrl,
                nav_label:     `Show Detail (page load) #${showIdx + 1}`,
                landed_url:    page.url(),
                api_count:     loadApis.length,
                api_endpoints: loadApis,
              });
              info(`  → page load: ${loadApis.length} API endpoint(s) — keys: ${loadApis.flatMap(e => e.keys).slice(0, 8).join(', ')}`);
            }

            // Click through each unique visible tab
            const clickedLabels = new Set();
            for (const [tabLabel, tabSel] of SHOW_TAB_SELECTORS) {
              const tab = await page.$(tabSel).catch(() => null);
              if (!tab || !await tab.isVisible().catch(() => false)) continue;
              if (clickedLabels.has(tabLabel)) continue;

              const isActive = await tab.evaluate(el =>
                el.getAttribute('aria-selected') === 'true' || el.getAttribute('aria-current') === 'page'
              ).catch(() => false);
              if (isActive) { clickedLabels.add(tabLabel); continue; }

              info(`  → clicking "${tabLabel}" tab`);
              clickedLabels.add(tabLabel);
              await tab.click();
              await page.waitForTimeout(2500);
              await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});
              for (let i = 0; i < 3; i++) {
                await page.evaluate(() => window.scrollBy(0, window.innerHeight));
                await page.waitForTimeout(400);
              }
              await page.waitForLoadState('networkidle', { timeout: 3000 }).catch(() => {});
              if (DEBUG) await debugShot(page, `discover-show-${showIdx + 1}-tab-${tabLabel.toLowerCase()}`);

              const tabApis = await drainCaptures();
              pageResults.push({
                url:           showUrl,
                nav_label:     `Show Detail - "${tabLabel}" tab #${showIdx + 1}`,
                landed_url:    page.url(),
                api_count:     tabApis.length,
                api_endpoints: tabApis,
              });
              if (tabApis.length > 0) {
                info(`    → ${tabApis.length} API endpoint(s) — keys: ${tabApis.flatMap(e => e.keys).slice(0, 8).join(', ')}`);
              } else {
                info(`    → 0 API endpoint(s) (tab may be DOM-rendered or empty)`);
              }
            }
          } catch (e) {
            info(`discover: error deep-crawling show ${showUrl}: ${e.message}`);
          }
        }
      } catch (e) {
        info(`discover: Phase 2 error: ${e.message}`);
      }

      // ── Step 5: Deep crawl /seller/orders ─────────────────────────────────
      info('discover: === PHASE 3: Deep crawl /seller/orders ===');
      try {
        await page.goto('https://www.whatnot.com/seller/orders', { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
        await page.waitForTimeout(2000);
        for (let i = 0; i < 3; i++) {
          await page.evaluate(() => window.scrollBy(0, window.innerHeight));
          await page.waitForTimeout(500);
        }
        await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});

        const ordersPageApis = await drainCaptures();
        if (ordersPageApis.length > 0) {
          pageResults.push({
            url:           'https://www.whatnot.com/seller/orders',
            nav_label:     'Orders List (deep crawl)',
            landed_url:    page.url(),
            api_count:     ordersPageApis.length,
            api_endpoints: ordersPageApis,
          });
          info(`  → orders list: ${ordersPageApis.length} API endpoint(s) — keys: ${ordersPageApis.flatMap(e => e.keys).slice(0, 8).join(', ')}`);
        }

        // Click into the first 2 order detail pages
        const orderDetailLinks = await page.evaluate(() => {
          const links = [];
          const seen = new Set();
          for (const a of document.querySelectorAll('a[href]')) {
            const href = a.getAttribute('href') || '';
            if (!/\/seller\/orders\/[\w-]+(?=[?#]|$)/.test(href)) continue;
            const full = href.startsWith('http') ? href : 'https://www.whatnot.com' + href;
            if (seen.has(full)) continue;
            seen.add(full);
            links.push(full);
          }
          return links.slice(0, 2);
        });

        info(`discover: found ${orderDetailLinks.length} order detail pages to crawl`);

        for (let oIdx = 0; oIdx < orderDetailLinks.length; oIdx++) {
          const orderUrl = orderDetailLinks[oIdx];
          info(`discover: deep-crawl order ${oIdx + 1}/${orderDetailLinks.length}: ${orderUrl}`);
          try {
            await page.goto(orderUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            await page.waitForTimeout(2000);
            const orderApis = await drainCaptures();
            if (orderApis.length > 0) {
              pageResults.push({
                url:           orderUrl,
                nav_label:     `Order Detail #${oIdx + 1}`,
                landed_url:    page.url(),
                api_count:     orderApis.length,
                api_endpoints: orderApis,
              });
              info(`  → order detail: ${orderApis.length} API endpoint(s) — keys: ${orderApis.flatMap(e => e.keys).slice(0, 8).join(', ')}`);
            }
          } catch (e) {
            info(`discover: error crawling order ${orderUrl}: ${e.message}`);
          }
        }
      } catch (e) {
        info(`discover: Phase 3 error: ${e.message}`);
      }

      // ── Step 6: output ───────────────────────────────────────────────────
      const allEndpoints = pageResults.flatMap(p => p.api_endpoints.map(e => ({ ...e, from_page: p.url })));
      // Deduplicate by URL for the summary
      const uniqueEndpoints = [];
      const seenUrls = new Set();
      for (const e of allEndpoints) {
        const key = e.method + ':' + e.url;
        if (!seenUrls.has(key)) { seenUrls.add(key); uniqueEndpoints.push(e); }
      }

      const result = {
        summary: {
          nav_links_found:       navLinks.length,
          pages_visited:         pageResults.length,
          unique_api_endpoints:  uniqueEndpoints.length,
          total_api_calls:       allEndpoints.length,
        },
        nav_links:        navLinks,
        unique_endpoints: uniqueEndpoints,
        pages:            pageResults,
      };

      // Write full JSON to a temp file — stdout pipe buffer can't handle large endpoint data
      const outFile = `/tmp/whatnot-discover-${Date.now()}.json`;
      require('fs').writeFileSync(outFile, JSON.stringify(result, null, 2));
      log(`discover complete: ${pageResults.length} pages, ${uniqueEndpoints.length} unique endpoints → ${outFile}`);
      // Stdout carries only a small envelope so PHP can find the file
      process.stdout.write(JSON.stringify({ output_file: outFile, summary: result.summary }) + '\n');
      process.exit(0);
    }

    // ── Mode: analytics (default) ─────────────────────────────────────────────
    if (MODE === 'analytics' || MODE === 'shows') {
      const isAnalytics = MODE === 'analytics';

      // Given a seed, go straight to the walk that works.
      //
      // Everything below this — the URL candidates, the sidebar click, the
      // scroll loop, the API intercept — exists to find one livestream UUID to
      // start the analytics walk from. All of it runs against /dashboard
      // pages, which is the surface currently being refused, and none of it is
      // needed when the caller already knows a UUID.
      //
      // Going first also means the run no longer depends on those pages coming
      // back. If the walk returns shows, the refused list page never mattered.
      if (isAnalytics && START_UUID) {
        info(`analytics-nav: starting from the seed supplied by the caller (live_id=${START_UUID})`);
        const seeded = await scrapeViaAnalyticsPage(page, START_UUID, LIMIT)
          .catch((e) => { info('analytics-nav: seeded walk threw —', e.message); return []; });

        const usable = seeded
          .slice(0, LIMIT)
          .filter(s => s.title || s.show_date || s.gross_revenue !== null);

        if (usable.length > 0) {
          info(`analytics-nav: seeded walk returned ${usable.length} show(s)`);
          writeJsonAndExit(usable);
          return;
        }

        // Not fatal. The seed can be stale — a UUID for a show that has since
        // been deleted still resolves to a page with nothing on it — so fall
        // through and let the discovery below try to find a fresher one.
        info('analytics-nav: seeded walk produced nothing — falling back to page discovery');
      }

      // URL priority for show data.  When ensureSellerMode already landed us on
      // /dashboard, start there so we stay in seller-mode SPA context.
      //   1. /dashboard        — seller dashboard (we're already here after Switch to Selling)
      //   2. /dashboard/shows  — shows list within dashboard
      //   3. /seller/shows     — SSR route that 404s for team members on direct nav; kept as fallback
      //   4. analytics overview — individual show analytics cards (Kasada-blocked in practice)
      const analyticsUrlCandidates = isAnalytics ? [
        global._sellerModeActive ? URLS.dashboard : URLS.dashboardLives,
        URLS.dashboardLives,
        URLS.dashboardShows,
        URLS.shows,
        URLS.analytics,
      ] : [
        global._sellerModeActive ? URLS.dashboard : URLS.dashboardLives,
        URLS.dashboardLives,
        URLS.dashboardShows,
        URLS.shows,
      ];

      function isLoginUrl(u) {
        return /\/(login|signin|auth)(\/|\?|$)/i.test(u);
      }

      let targetUrl = analyticsUrlCandidates[0];
      let landed    = false;

      // Do not throw away a hub we are already standing on.
      //
      // The auth check leaves the browser on /dashboard/home with a rendered
      // Seller Hub. The first thing this loop used to do was goto() somewhere
      // else — and a goto() is a *document* request, which is the one shape
      // Cloudflare refuses here. Every run since the routing fix went:
      //
      //   [nav] 200 GET /dashboard/home          ← the page we wanted
      //   [nav] 403 GET /dashboard/lives         ← thrown away for this
      //   [nav] 403 GET /dashboard/home?toast=…  ← and now the hub is gone too
      //
      // leaving the sidebar-click path below reading a challenge page, which
      // is why its anchor dump came back as [Cloudflare, Privacy]. In-page
      // navigation from a hub that is already loaded never asks for a document
      // at all, so it is not subject to the rule that refuses them.
      const standingOn = page.url();
      if (/\/dashboard(\/|\?|#|$)/.test(standingOn)) {
        const hubIsRendered = await page.evaluate((markers) => {
          const text = document.body?.innerText || '';
          return markers.some((m) => text.includes(m));
        }, SELLER_HUB_MARKERS).catch(() => false);

        if (hubIsRendered) {
          info(`already on a rendered hub (${standingOn}) — using it instead of navigating`);
          targetUrl = standingOn;
          landed    = true;
        }
      }

      for (const candidate of landed ? [] : analyticsUrlCandidates) {
        log(`trying URL: ${candidate}`);
        // Uncaught net::ERR_ABORTED here crashed the whole scrape (confirmed
        // live): switchToChannel()'s role-switch is a real HTML form POST that
        // triggers its own browser navigation, which can still be settling
        // when this explicit goto() fires right after — Chromium aborts the
        // newer request. Non-fatal: page.url() below still reflects wherever
        // we actually landed, and the loop just tries the next candidate if
        // this one didn't stick.
        await page.goto(candidate, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {});
        // Wait for the SPA to fire its data-fetch API calls before we read anything.
        // networkidle (no network activity for 500ms) is more reliable than a fixed delay.
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await page.waitForTimeout(1000);

        let currentUrl = page.url();

        if (isLoginUrl(currentUrl)) {
          info('analytics redirect to login — re-authenticating and re-switching channel');
          await performLogin(page, email, password);
          await page.waitForTimeout(1000);
          // Re-run channel switch after re-login
          if (CHANNEL_NAME) await switchToChannel(page, CHANNEL_NAME);
          // Retry this candidate
          await page.goto(candidate, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {});
          await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
          await page.waitForTimeout(1000);
          currentUrl = page.url();
          info('after re-auth retry, URL:', currentUrl);
        }

        if (!isLoginUrl(currentUrl)) {
          // Check for Whatnot's 404 page before accepting this landing
          const pageTextCheck = await page.evaluate(() => (document.body.innerText || '').substring(0, 300)).catch(() => '');
          if (/HTTP 404|Page Not Found|does not exist/i.test(pageTextCheck)) {
            info(`${candidate}: 404 page detected ("${pageTextCheck.trim().substring(0, 80)}"), trying next`);
          } else {
            targetUrl = candidate;
            landed    = true;
            log(`landed on: ${currentUrl}`);
            break;
          }
        } else {
          log(`${candidate} still redirects to ${currentUrl}, trying next`);
        }
      }

      if (!landed) {
        exitAfterStderr('ERROR: All analytics URLs redirect to login — credentials may be invalid or account lacks analytics access.\n', 1);
      }

      // If we landed on the seller dashboard home (/dashboard/home or /dashboard),
      // client-side navigate to the Shows list via the sidebar link. Direct-nav to
      // /dashboard/shows returns a 404; we must click from within the SPA.
      {
        const landedUrl = page.url();
        if (/\/dashboard\/home|\/dashboard\/?(?:[?#]|$)/.test(landedUrl)) {
          info('landed on seller dashboard home — navigating to Shows via sidebar link');
          await debugShot(page, '05a-dashboard-home');

          // Wait for nav links to render (any links at all)
          await page.waitForFunction(
            () => document.querySelectorAll('a[href]').length > 5,
            { timeout: 10000 }
          ).catch(() => {});

          // Dump all anchors so we can see what href the Shows nav link actually has
          const allAnchors = await page.evaluate(() =>
            Array.from(document.querySelectorAll('a[href]'))
              .map(a => ({ text: (a.textContent || '').trim().replace(/\s+/g, ' ').substring(0, 40), href: a.getAttribute('href') }))
              .filter(l => l.text && l.href)
          ).catch(() => []);
          info('dashboard home anchors:', JSON.stringify(allAnchors.slice(0, 30)));

          // Find the "Shows" sidebar nav link.
          //
          // The href is /dashboard/lives, not /dashboard/shows — the pattern
          // this used to match. That left the exact-text fallback carrying the
          // whole thing, and it only accepted the literal string "Shows", so a
          // sidebar labelled "Lives" or "My shows" found nothing at all.
          const showsHref = await page.evaluate(() => {
            const links = Array.from(document.querySelectorAll('a[href]'));
            const href  = (a) => a.getAttribute('href') || '';
            const text  = (a) => (a.textContent || '').trim().toLowerCase();

            // href first: it is the thing that does not change with copy edits.
            let match = links.find(a => /\/dashboard\/(lives|shows)\b|\/seller\/shows\b/i.test(href(a)));
            if (!match) match = links.find(a => ['shows', 'lives', 'my shows'].includes(text(a)));
            if (!match) match = links.find(a => /^(shows|lives)\b/.test(text(a)));

            if (match) { match.click(); return href(match); }
            return null;
          }).catch(() => null);

          if (showsHref) {
            info('sidebar Shows link clicked, href was:', showsHref);
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            await page.waitForTimeout(1000);
            targetUrl = page.url();
            info('after Shows sidebar click, URL:', targetUrl);
            await debugShot(page, '05b-after-shows-click');
          } else {
            info('no Shows link found by text or href — dumping all anchor hrefs and continuing');
            await debugShot(page, '05b-no-shows-link');
          }
        }
      }

      // Wait for React to hydrate — poll until visible text appears (up to 25s).
      // Fixed timeouts and networkidle aren't reliable for SPAs with background polling.
      await page.waitForFunction(
        () => (document.body.innerText || '').trim().length > 100,
        { timeout: 25000 }
      ).catch(async () => {
        log('body still empty after 25s — may be iframe or anti-bot; trying extra 5s');
        await page.waitForTimeout(5000);
      });
      await debugShot(page, '05-analytics-page');

      // On the analytics overview page, look for a [role="tab"] Shows sub-tab
      // (NOT the sidebar nav link which navigates away to /dashboard/shows).
      // We only do this if we landed on the analytics URL — if we're already on
      // a shows list page, there's nothing to click.
      if (isAnalytics && targetUrl === URLS.analytics) {
        let showsTab = null;

        const tabCandidates = [
          // Role-tab / button variants for the analytics sub-tabs only — deliberately
          // excluding bare 'a:has-text("Shows")' which matches the sidebar nav link.
          '[role="tab"]:has-text("Shows")',
          'button:has-text("Shows")',
          '[role="tab"]:has-text("Past Shows")',
          'button:has-text("Past Shows")',
          '[role="tab"]:has-text("Stream")',
          'button:has-text("Stream")',
          '[role="tab"]:has-text("History")',
          'button:has-text("History")',
          // Historic attribute-based (MUI tab IDs)
          'button[aria-controls="simple-tabpanel-1"]',
          'button#simple-tab-1',
          '[role="tab"][data-value="shows"]',
          '[role="tab"][data-index="1"]',
        ];

        for (const sel of tabCandidates) {
          const el = await page.$(sel).catch(() => null);
          if (!el) continue;
          if (!await el.isVisible().catch(() => false)) continue;
          showsTab = el;
          log(`found Shows tab via: ${sel}`);
          break;
        }

        if (!showsTab) {
          for (const frame of page.frames()) {
            if (frame === page.mainFrame()) continue;
            for (const sel of tabCandidates) {
              const el = await frame.$(sel).catch(() => null);
              if (!el) continue;
              if (!await el.isVisible().catch(() => false)) continue;
              showsTab = el;
              log(`found Shows tab in iframe (${frame.url()}) via: ${sel}`);
              break;
            }
            if (showsTab) break;
          }
        }

        if (showsTab) {
          const isSelected = await showsTab.evaluate(el =>
            el.getAttribute('aria-selected') === 'true' || el.getAttribute('aria-current') === 'true'
          ).catch(() => false);
          if (!isSelected) {
            await showsTab.click();
            await page.waitForTimeout(1800);
          }
          await debugShot(page, '06-shows-tab');
        } else {
          info('analytics overview: no [role="tab"] Shows sub-tab found — will rely on API intercept / list DOM');
          await debugShot(page, '06-no-tab');
        }
      }

      // ── On /dashboard/lives, click the "Completed" / "Past" tab ────────────
      // The page defaults to upcoming/scheduled shows. Completed shows are under
      // a separate tab — click it so the list (and API calls) actually load.
      if (page.url().includes('/dashboard/lives')) {
        // Dump visible tabs/buttons so we can see what's available
        const livesPageTabs = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[role="tab"], button'))
            .map(el => ({ tag: el.tagName, text: (el.innerText || el.textContent || '').trim().replace(/\s+/g,' ').substring(0,40), role: el.getAttribute('role') }))
            .filter(el => el.text)
        ).catch(() => []);
        info('/dashboard/lives tabs/buttons:', JSON.stringify(livesPageTabs.slice(0, 20)));

        const completedTabSels = [
          '[role="tab"]:has-text("Completed")',
          '[role="tab"]:has-text("Past")',
          '[role="tab"]:has-text("Ended")',
          '[role="tab"]:has-text("Past Shows")',
          '[role="tab"]:has-text("History")',
          'button:has-text("Completed")',
          'button:has-text("Past Shows")',
          'button:has-text("Ended")',
        ];

        let clickedTab = null;
        for (const sel of completedTabSels) {
          const el = await page.$(sel).catch(() => null);
          if (!el || !await el.isVisible().catch(() => false)) continue;
          const isSelected = await el.evaluate(e =>
            e.getAttribute('aria-selected') === 'true' || e.getAttribute('aria-current') === 'true'
          ).catch(() => false);
          if (!isSelected) {
            await el.click().catch(() => {});
            info('/dashboard/lives: clicked tab via:', sel);
            await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
            await page.waitForTimeout(1500);
          } else {
            info('/dashboard/lives: tab already selected via:', sel);
          }
          clickedTab = sel;
          await debugShot(page, '06a-lives-completed-tab');
          break;
        }

        if (!clickedTab) {
          // This used to goto() ?status=completed, and that is now a way to
          // lose the page: a document request for anything under /dashboard
          // other than the hub itself comes back refused, so the fallback
          // traded a rendered list for a challenge screen.
          //
          // Not finding the tab is survivable — the list is already on screen,
          // just unfiltered. Reading it as-is beats navigating out of it.
          info('/dashboard/lives: no Completed tab found — reading the list unfiltered');
          info('/dashboard/lives: not re-navigating for ?status=completed; a document request here is refused');
          await debugShot(page, '06a-lives-no-tab');
        }
      }

      // ── Shows-list DOM extraction (for /dashboard/shows and /seller/shows) ──
      // Try this before the metric-card loop because list pages never have metric cards.
      // NOTE: we do NOT do an early API exit here — the scroll loop below must run first
      // to trigger all paginated GetDashboardLivestreamsByUserId requests before we check.
      const currentPageUrl = page.url();
      const isListPage = currentPageUrl.includes('/dashboard/lives') ||
                         currentPageUrl.includes('/dashboard/shows') ||
                         currentPageUrl.includes('/seller/shows') ||
                         (targetUrl === URLS.dashboardLives) ||
                         (targetUrl === URLS.dashboardShows) ||
                         (targetUrl === URLS.shows);

      if (isListPage) {
        // Scroll-until-stable: keep scrolling and clicking "Load More" until no new
        // show cards appear between passes (or LIMIT is reached).
        let prevLinkCount = 0;
        let stableRounds = 0;
        const LINK_SEL = 'a[href*="/dashboard/live/"], a[href*="/live/"], a[href*="/dashboard/shows/"], a[href*="live_id="]';
        for (let scrollRound = 0; scrollRound < 40; scrollRound++) {
          const currentLinks = await page.evaluate(
            sel => document.querySelectorAll(sel).length, LINK_SEL
          ).catch(() => 0);
          info(`scroll round ${scrollRound}: ${currentLinks} show link(s) visible`);
          if (currentLinks >= LIMIT) { info('scroll: LIMIT reached'); break; }

          // Click "Load More" / "View more" if present
          const clicked = await page.evaluate(() => {
            const btn = Array.from(document.querySelectorAll('button, a[role="button"], a'))
              .find(el => /load more|view more|show more|see more/i.test((el.textContent || '').trim()));
            if (btn) { btn.click(); return true; }
            return false;
          }).catch(() => false);
          if (clicked) {
            info('scroll: clicked Load More');
            await page.waitForTimeout(1500);
            stableRounds = 0;
            prevLinkCount = currentLinks;
            continue;
          }

          await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
          await page.waitForTimeout(900);

          const newLinks = await page.evaluate(
            sel => document.querySelectorAll(sel).length, LINK_SEL
          ).catch(() => 0);
          if (newLinks <= currentLinks) {
            stableRounds++;
            if (stableRounds >= 3) { info(`scroll: stable after ${scrollRound + 1} rounds`); break; }
          } else {
            stableRounds = 0;
          }
          prevLinkCount = newLinks;
        }
        await page.waitForTimeout(800);
        await debugShot(page, '06-shows-list-scrolled');

        // Capture the DOM list HERE, while still on the fully-scrolled "Past"
        // tab, not after the analytics-nav detour below. That detour navigates
        // this same `page` away to /account/analytics?... and, if it falls
        // through to the DOM fallback, re-navigating back to currentPageUrl
        // reloads the page fresh on its DEFAULT tab (confirmed live: 535
        // scrolled-through links collapsed to 1, matching whatever the default
        // tab happens to show) — the Past-tab click + scroll state doesn't
        // survive a fresh page load. Grabbing it now avoids needing to replay
        // any of that.
        const listShowsFromPastTab = await extractShowsListFromDom(page);
        info('shows-list DOM (pre-analytics): found', listShowsFromPastTab.length, 'show links on', currentPageUrl);

        // ── PRIMARY: analytics-page navigation (channel-scoped, gets ALL shows) ──
        // /dashboard/lives renders show action buttons ("Open show", "See Analytics")
        // as <button> elements with onClick handlers — NOT <a> tags — so link-based
        // DOM extraction finds nothing, and the GetDashboardLivestreamsByUserId API is
        // user-scoped (only shows the bot account hosted). The ONLY source that exposes
        // every past channel show is /account/analytics?tab=livestream&live_id=<uuid>,
        // whose "See older show" button walks the full channel history.
        //
        // We just need ONE valid live_id UUID to seed it; scrapeViaAnalyticsPage rewinds
        // to the newest show before walking backward, so any show's UUID works.
        //
        // Seed priority (most-reliable first):
        //   1. Anchor href carrying live_id= / source= — guaranteed a show UUID
        //   2. Captured API response id — the user-hosted show, guaranteed a show UUID
        //   3. Any UUID in the rendered HTML (thumbnail URLs etc.) — may be unrelated
        const anchorSeed = await page.evaluate(() => {
          for (const a of document.querySelectorAll('a[href*="live_id="], a[href*="source="]')) {
            const href = a.getAttribute('href') || '';
            const m = href.match(/[?&](?:live_id|source)=([0-9a-f-]{36})/i);
            if (m) return m[1];
          }
          return null;
        }).catch(() => null);

        let apiSeed = null;
        if (!anchorSeed) {
          const apiShows = extractShowsFromCapture(capturedApiResponses);
          if (apiShows && apiShows.length > 0) {
            const first = apiShows.find(s => s.id || s.uuid || s.show_id);
            apiSeed = first && (first.id || first.uuid || first.show_id);
          }
        }

        let htmlSeed = null;
        if (!anchorSeed && !apiSeed) {
          htmlSeed = await page.evaluate(() => {
            const m = (document.body.innerHTML || '')
              .match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
            return m ? m[0] : null;
          }).catch(() => null);
        }

        // START_UUID is not repeated here — if it was set, the walk already ran
        // from it above and only got this far by producing nothing.
        const startUuid = anchorSeed || apiSeed || htmlSeed;
        if (startUuid) {
          info(`shows-list: seeding analytics-nav with live_id=${startUuid}`);
          const analyticsShows = await scrapeViaAnalyticsPage(page, startUuid, LIMIT);
          const normalized = analyticsShows
            .slice(0, LIMIT)
            .filter(s => s.title || s.show_date || s.gross_revenue !== null);
          const withResolvedId = normalized.filter(s => s.detail_url).length;
          // Analytics-nav extracts financial metrics (whatnot_net, tips, etc) from
          // the detail pages. When GraphQL id-harvesting fails (0 ids resolved), we
          // still have the analytics data — try to fill in missing detail_urls by
          // matching against DOM list by title+date, then use the enriched results.
          if (normalized.length > 0) {
            if (withResolvedId > 0) {
              info(`analytics-nav: returned ${normalized.length} shows (${withResolvedId} with a resolved id)`);
              writeJsonAndExit(normalized);
              return;
            }
            // ID resolution failed — try to fill in detail_urls from DOM list by title+date
            const normTitle = (t) => (t || '').toLowerCase().replace(/[^a-z0-9]/g, '').slice(0, 40);
            const domByTitle = new Map();
            const domByDate = new Map();
            for (const show of listShowsFromPastTab) {
              const nt = normTitle(show.title);
              if (nt && !domByTitle.has(nt)) domByTitle.set(nt, show);
              const sd = show.show_date;
              if (sd && !domByDate.has(sd)) domByDate.set(sd, show);
            }
            const enriched = normalized.map(s => ({
              ...s,
              detail_url: s.detail_url ||
                         domByTitle.get(normTitle(s.title))?.detail_url ||
                         (s.show_date ? domByDate.get(s.show_date)?.detail_url : null)
            }));
            const enrichedWithId = enriched.filter(s => s.detail_url).length;
            if (enrichedWithId > 0) {
              info(`analytics-nav: enriched ${enrichedWithId} of ${enriched.length} shows with detail_url from DOM list`);
              writeJsonAndExit(enriched);
              return;
            }
            info(`analytics-nav: produced ${normalized.length} shows but couldn't resolve or enrich ids — falling back to DOM/API extraction`);
          }
        } else {
          info('shows-list: no seed UUID found for analytics-nav — falling back to DOM/API extraction');
        }

        // Use the list captured before the analytics-nav detour — see the
        // comment above that capture for why we don't re-extract here.
        const listShows = listShowsFromPastTab;
        info('shows-list DOM: using', listShows.length, 'show link(s) captured before analytics-nav ran');
        if (listShows.length > 0) {
          info('shows-list DOM: first 3 raw results:', JSON.stringify(
            listShows.slice(0, 3).map(s => ({ title: s.title, show_date: s.show_date, detail_url: s.detail_url }))
          ));
        }

        if (listShows.length > 0) {
          const normalized = listShows.slice(0, LIMIT).filter(s => s.title || s.show_date);
          if (normalized.length > 0) {
            info(`shows-list DOM: returned ${normalized.length} shows`);
            writeJsonAndExit(normalized);
            return;
          }
          info('shows-list DOM: found links but no title/date extracted — falling back to API');
        }

        // API fallback — only if DOM found nothing at all
        const postScrollApiShows = extractShowsFromCapture(capturedApiResponses);
        if (postScrollApiShows && postScrollApiShows.length > 0) {
          const normalized = postScrollApiShows.slice(0, LIMIT).map(normalizeApiShow)
            .filter(r => r.title || r.show_date || r.gross_revenue !== null);
          if (normalized.length > 0) {
            info(`API intercept (post-scroll): returned ${normalized.length} shows`);
            writeJsonAndExit(normalized);
            return;
          }
          info('API intercept (post-scroll): array found but normalization yielded nothing');
        }

        // Dump diagnostics so the selector can be updated
        const diag = await page.evaluate(() => ({
          url:      location.href,
          bodyText: (document.body.innerText || '').substring(0, 3000),
          links:    Array.from(document.querySelectorAll('a[href]'))
                      .filter(a => /\/live\/|\/show\/|\/seller\/shows|\/dashboard\/shows|\/dashboard\/live\//.test(a.getAttribute('href') || ''))
                      .slice(0, 10)
                      .map(a => a.getAttribute('href')),
        }));
        // "No shows found" is only a selector problem when the page we searched
        // was actually the shows page. Reporting a verification screen as a
        // markup change sends whoever reads this hunting through SELECTORS for
        // a list that was never served.
        const blocked = classifyBlockingPage(diag.url, diag.bodyText);

        if (blocked) {
          // Folded into the one flushed write rather than written separately —
          // an unflushed write before an exiting one is dropped on a pipe.
          exitAfterStderr(
              blocked.error + ': ' + blocked.message + '\n'
              + 'CURRENT_URL: ' + diag.url + '\n'
              + 'SHOW_LINKS_FOUND: ' + JSON.stringify(diag.links) + '\n'
              + 'PAGE_TEXT:\n' + diag.bodyText + '\n',
              blocked.code,
          );
        }

        exitAfterStderr('SELECTOR_MISS: No shows found on list page.\n' + 'CURRENT_URL: ' + diag.url + '\n' + 'SHOW_LINKS_FOUND: ' + JSON.stringify(diag.links) + '\n' + 'PAGE_TEXT:\n' + diag.bodyText + '\n', 2);
      }

      // ── Metric-card DOM scraping (for analytics detail pages) ────────────────
      const results = [];

      for (let i = 0; i < LIMIT; i++) {
        await page.waitForTimeout(800);
        await debugShot(page, `07-show-${i}`);

        const data = await extractAnalyticsMetrics(page);
        log(`show ${i}: title="${data.title}" date="${data.dateText}" cards=${data.cardCount}`);

        if (!data.title && data.cardCount === 0) {
          log('no show data visible — stopping');
          break;
        }

        const m = data.metrics;

        // Map metric labels to normalized field names
        // Labels vary slightly between shows ("Total Estimated Earnings" vs "Completed Earnings")
        // so we check multiple possible label strings.
        const get = (...labels) => {
          for (const l of labels) { if (m[l] !== undefined) return m[l]; }
          return null;
        };

        results.push({
          title:                   data.title,
          show_date:               parseDateString(data.dateText),
          show_date_raw:           data.dateText,
          detail_url:              data.detailUrl || null,

          // Sales Metrics
          gross_revenue:           parseMoney(get('Estimated Sales')),
          whatnot_net:             parseMoney(get('Total Estimated Earnings')),
          completed_earnings:      parseMoney(get('Completed Earnings')),
          units_sold:              parseInteger(get('Orders')),
          avg_order_value:         parseMoney(get('Average Order Value')),
          giveaway_spend:          parseMoney(get('Giveaway Spend')),
          giveaways_count:         parseInteger(get('Giveaways')),

          // Stream Metrics
          buyers_count:            parseInteger(get('Buyers')),
          first_time_buyers:       parseInteger(get('First Time Buyers')),
          returning_buyers:        parseInteger(get('Returning Buyers')),
          shares_count:            parseInteger(get('Shares')),
          show_duration:           parseDurationToMinutes(get('Show Duration')),
          max_concurrent_viewers:  parseInteger(get('Max Concurrent Viewers')),
          total_views:             parseInteger(get('Total Views')),
          avg_order_rating:        parseMoney(get('Average Order Rating')),

          // Raw metrics map for debugging
          _raw_metrics: m,
        });

        if (!data.hasOlder) {
          log('no older shows — reached end of history');
          break;
        }

        // Navigate to the next older show
        await page.click(SELECTORS.showNavOlder);
        log(`navigated to older show (${i + 1} of ${LIMIT})`);
      }

      if (results.length === 0) {
        const html = await page.evaluate(() => document.body.innerHTML.substring(0, 5000));
        exitAfterStderr('SELECTOR_MISS: No show data extracted from analytics page.\n' + 'PAGE_SNAPSHOT: ' + html + '\n', 2);
      }

      log(`done — returned ${results.length} shows`);
      writeJsonAndExit(results);
      return;
    }

    exitAfterStderr(`Unknown WHATNOT_MODE: ${MODE}\n`, 1);

  } catch (err) {
    await debugShot(page, 'error');
    exitAfterStderr('Error: ' + err.message + '\n', 1);
  } finally {
    await context.close();
  }
})();
