from pathlib import Path


def replace_once(text, old, new, label):
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly 1 occurrence, found {count}')
    return text.replace(old, new, 1)


# scripts/whatnot-scraper.cjs
p = Path('scripts/whatnot-scraper.cjs')
s = p.read_text()

s = replace_once(
    s,
    "const CHANNEL_NAME   = (process.env.WHATNOT_CHANNEL_NAME || '').trim();\n",
    "const CHANNEL_NAME   = (process.env.WHATNOT_CHANNEL_NAME || '').trim();\n"
    "const BROWSER_BACKEND = (process.env.WHATNOT_BROWSER_BACKEND || 'local').trim().toLowerCase();\n"
    "const STEEL_BASE_URL  = (process.env.STEEL_BASE_URL || 'http://127.0.0.1:3000').trim().replace(/\\/+$/, '');\n",
    'scraper backend constants',
)

steel_helper = r'''
async function launchSteelContext(opts = {}) {
  if (typeof fetch !== 'function') {
    throw new Error('STEEL_BACKEND_UNAVAILABLE: Node 18+ is required because the Steel backend uses fetch()');
  }

  const baseUrl = STEEL_BASE_URL;
  info(`browser backend: steel (${baseUrl})`);

  const createBody = {
    headless: true,
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

'''

s = replace_once(
    s,
    "async function launchWithProfileRecovery(userDataDir, opts = {}) {",
    steel_helper + "async function launchWithProfileRecovery(userDataDir, opts = {}) {",
    'insert Steel helper',
)

s = replace_once(
    s,
    "  const context = liveContext = await launchWithProfileRecovery(USER_DATA_DIR, {\n",
    "  const browserOptions = {\n",
    'scraper launch assignment',
)

launch_tail = (
    "    // NOTE: discover mode previously passed serviceWorkers: 'block' here (so\n"
    "    // fetch() calls hit the real network instead of the SW cache, which\n"
    "    // bypasses page.on('response')). That's a launchPersistentContext-only\n"
    "    // option with no equivalent once the context already exists — connectOverCDP\n"
    "    // attaches to a context Chromium created itself — so discover mode may miss\n"
    "    // some SW-cached responses now. Every other mode is unaffected.\n"
    "  });\n\n"
    "  // Mask automation signals that trigger bot detection on sites like Whatnot.\n"
)
launch_tail_new = (
    "    // NOTE: discover mode previously passed serviceWorkers: 'block' here (so\n"
    "    // fetch() calls hit the real network instead of the SW cache, which\n"
    "    // bypasses page.on('response')). That's a launchPersistentContext-only\n"
    "    // option with no equivalent once the context already exists — connectOverCDP\n"
    "    // attaches to a context Chromium created itself — so discover mode may miss\n"
    "    // some SW-cached responses now. Every other mode is unaffected.\n"
    "  };\n\n"
    "  if (!['local', 'steel'].includes(BROWSER_BACKEND)) {\n"
    "    throw new Error(`UNKNOWN_BROWSER_BACKEND: ${BROWSER_BACKEND} (expected local or steel)`);\n"
    "  }\n\n"
    "  const context = liveContext = BROWSER_BACKEND === 'steel'\n"
    "    ? await launchSteelContext(browserOptions)\n"
    "    : await launchWithProfileRecovery(USER_DATA_DIR, browserOptions);\n\n"
    "  // Mask automation signals that trigger bot detection on sites like Whatnot.\n"
)
s = replace_once(s, launch_tail, launch_tail_new, 'scraper launch tail')

mask_start = "  // navigator.webdriver = true is the primary signal headless Chrome sets.\n  await context.addInitScript(() => {\n"
mask_start_new = (
    "  // navigator.webdriver = true is the primary signal headless Chrome sets.\n"
    "  // Steel already manages its own coherent browser fingerprint; only apply the\n"
    "  // legacy local-browser shims to the local backend.\n"
    "  if (BROWSER_BACKEND !== 'steel') {\n"
    "    await context.addInitScript(() => {\n"
)
s = replace_once(s, mask_start, mask_start_new, 'mask block start')

mask_end = "  });\n\n  // ── Bootstrap session cookies (one-time first-run setup + manual re-auth) ────\n"
mask_end_new = "    });\n  }\n\n  // ── Bootstrap session cookies (one-time first-run setup + manual re-auth) ────\n"
s = replace_once(s, mask_end, mask_end_new, 'mask block end')
p.write_text(s)


# config/vortex.php
p = Path('config/vortex.php')
s = p.read_text()
s = replace_once(
    s,
    "        'cookies_file' => env('WHATNOT_COOKIES_FILE'),\n\n",
    "        'cookies_file' => env('WHATNOT_COOKIES_FILE'),\n\n"
    "        // Browser runtime: local keeps the existing VPS Chromium path; steel\n"
    "        // connects Playwright to the self-hosted Steel service over CDP.\n"
    "        'browser_backend' => env('WHATNOT_BROWSER_BACKEND', 'local'),\n"
    "        'steel_base_url'  => env('STEEL_BASE_URL', 'http://127.0.0.1:3000'),\n\n",
    'config Steel settings',
)
p.write_text(s)


# app/Services/WhatnotScraper.php
p = Path('app/Services/WhatnotScraper.php')
s = p.read_text()
headless_block = (
    "        if (! isset($env['WHATNOT_HEADLESS']) && ($headless = config('vortex.whatnot.headless')) !== null) {\n"
    "            $env['WHATNOT_HEADLESS'] = $headless ? 'true' : 'false';\n"
    "        }\n"
)
headless_new = (
    headless_block
    + "\n"
    + "        $env['WHATNOT_BROWSER_BACKEND'] = (string) config('vortex.whatnot.browser_backend', 'local');\n"
    + "        $env['STEEL_BASE_URL'] = (string) config('vortex.whatnot.steel_base_url', 'http://127.0.0.1:3000');\n"
)
s = replace_once(s, headless_block, headless_new, 'PHP env forwarding')
p.write_text(s)


# .env.example
p = Path('.env.example')
s = p.read_text()
s = replace_once(
    s,
    "WHATNOT_IMPORT_LIMIT=50\n",
    "WHATNOT_IMPORT_LIMIT=50\n"
    "# Browser backend: local (existing Chromium) or steel (self-hosted Steel Browser).\n"
    "# WHATNOT_BROWSER_BACKEND=steel\n"
    "# STEEL_BASE_URL=http://127.0.0.1:3000\n",
    '.env Steel settings',
)
p.write_text(s)
