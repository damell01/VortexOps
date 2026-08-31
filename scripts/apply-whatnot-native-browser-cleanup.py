from pathlib import Path

p = Path('scripts/whatnot-scraper.cjs')
s = p.read_text()

old = '''    // Realistic Chrome/Windows UA — no "HeadlessChrome" in the string
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport:  { width: 1280, height: 900 },
    locale:    'en-US',
    env:       { TZ: 'America/Chicago' },
    // Client Hints headers must match the UA — inconsistency is a detection signal
    extraHTTPHeaders: {
      'sec-ch-ua':          '\"Chromium\";v=\"128\", \"Google Chrome\";v=\"128\", \"Not-A.Brand\";v=\"99\"',
      'sec-ch-ua-mobile':   '?0',
      'sec-ch-ua-platform': '\"Windows\"',
      'Accept-Language':    'en-US,en;q=0.9',
    },
'''
new = '''    // Let the installed Chrome executable report its own UA and Client Hints.
    // Hard-coding another Chrome version/platform makes diagnostics describe a
    // browser we are not actually running.
    viewport:  { width: 1280, height: 900 },
    locale:    'en-US',
    env:       { TZ: 'America/Chicago' },
    extraHTTPHeaders: {
      'Accept-Language': 'en-US,en;q=0.9',
    },
'''
if old not in s:
    raise SystemExit('main browserOptions fingerprint block not found')
s = s.replace(old, new, 1)

start_marker = '''  // Mask automation signals that trigger bot detection on sites like Whatnot.\n'''
end_marker = '''  // ── Bootstrap session cookies (one-time first-run setup + manual re-auth) ────\n'''
start = s.find(start_marker)
end = s.find(end_marker, start)
if start == -1 or end == -1:
    raise SystemExit('legacy fingerprint shim block not found')
s = s[:start] + '''  // Use Chrome's native browser properties. Do not rewrite navigator, WebGL,\n  // platform, plugins, permissions, screen values, or Client Hints.\n\n''' + s[end:]

old = '''  const tempContext = await launchPersistentContextViaCdp(tempDir, {
    args: ['--no-sandbox', '--no-zygote', '--disable-dev-shm-usage', '--disable-gpu',
           '--disable-crash-reporter', '--crash-dumps-dir=/tmp'],
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport:  { width: 1280, height: 900 },
  });
'''
new = '''  const tempContext = await launchPersistentContextViaCdp(tempDir, {
    args: ['--no-sandbox', '--no-zygote', '--disable-dev-shm-usage', '--disable-gpu',
           '--disable-crash-reporter', '--crash-dumps-dir=/tmp'],
    viewport:  { width: 1280, height: 900 },
  });
'''
if old not in s:
    raise SystemExit('ws-explore Chrome 128 block not found')
s = s.replace(old, new, 1)

s = s.replace(
'''// Cloudflare's own cookies, none of which travel between machines.\n//\n// cf_clearance and __cf_bm are bound to the IP and User-Agent that earned them,\n// so one exported from a laptop cannot be honoured from a server — and offering\n// a clearance token that does not match the connection is what a replayed token\n// looks like. The cf_chl_* pair record a challenge already in progress\n// elsewhere, which is a state this browser is not in.\n''',
'''// Cloudflare edge/challenge cookies are connection-specific state rather than\n// Whatnot account-authentication state. Their presence does not prove that the\n// current browser context completed or will pass a challenge.\n''',
1,
)

s = s.replace(
''' * cf_clearance is what a browser receives for passing a challenge, and it is\n * bound to the IP and User-Agent that earned it — which is why one exported\n * from a laptop is worthless on a server. Its presence is the only direct\n * measure of whether the browser has ever actually got through: without it,\n * every run starts the fight from scratch. With it, the fight is already won\n * and stays won until it expires.\n''',
''' * cf_clearance is one piece of Cloudflare edge state. Its presence tells us\n * that a clearance cookie exists in this context, but not where it originated\n * or whether Cloudflare will accept it for the current request.\n''',
1,
)
s = s.replace(
"      info('cf_clearance: absent — this profile has never passed a Cloudflare challenge');\n",
"      info('cf_clearance: absent in the current browser context');\n",
1,
)
old = '''    // Cloudflare issues clearance for the order of an hour. A token good for
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
'''
new = '''    // An unusually long expiry is useful as a diagnostic hint, but expiry alone
    // cannot prove where a cookie originated.
    if (minutes !== null && minutes > 24 * 60) {
      info(
        'cf_clearance: unusually long expiry; treating it as saved edge state rather than',
        'proof that this current browser context completed a Cloudflare challenge.',
      );
    }
'''
if old not in s:
    raise SystemExit('reportClearance heuristic block not found')
s = s.replace(old, new, 1)
s = s.replace(
"      ? 'dropped an imported cf_clearance — its expiry says it was earned on another machine'\n",
"      ? 'dropped saved cf_clearance with an unusually long expiry; origin is not inferred'\n",
1,
)

anchor = 'function installNavigationLogging(page) {\n'
diagnostic = r'''async function reportNativeBrowserDiagnostics(page) {
  if (process.env.WHATNOT_BROWSER_DIAGNOSTICS !== '1') return;

  try {
    const runtime = await page.evaluate(() => ({
      userAgent: navigator.userAgent,
      platform: navigator.platform,
      webdriver: navigator.webdriver,
      language: navigator.language,
      languages: Array.from(navigator.languages || []),
      uaDataPlatform: navigator.userAgentData?.platform ?? null,
      uaDataMobile: navigator.userAgentData?.mobile ?? null,
      uaDataBrands: navigator.userAgentData?.brands ?? null,
      screen: {
        width: screen.width,
        height: screen.height,
        availWidth: screen.availWidth,
        availHeight: screen.availHeight,
        colorDepth: screen.colorDepth,
        pixelDepth: screen.pixelDepth,
      },
      viewport: {
        innerWidth: window.innerWidth,
        innerHeight: window.innerHeight,
        devicePixelRatio: window.devicePixelRatio,
      },
    }));

    info('[browser-diag] executable:', CHROMIUM_PATH);
    info('[browser-diag] runtime:', JSON.stringify(runtime));
  } catch (e) {
    info('[browser-diag] runtime inspection failed:', e.message);
  }

  page.on('request', async (request) => {
    try {
      if (!request.isNavigationRequest()) return;
      if (request.frame() !== page.mainFrame()) return;
      if (!request.url().includes('whatnot.com')) return;

      const headers = await request.allHeaders();
      info('[browser-diag] navigation headers:', JSON.stringify({
        url: navPath(request.url()),
        userAgent: headers['user-agent'] || null,
        secChUa: headers['sec-ch-ua'] || null,
        secChUaPlatform: headers['sec-ch-ua-platform'] || null,
        secChUaMobile: headers['sec-ch-ua-mobile'] || null,
        acceptLanguage: headers['accept-language'] || null,
      }));
    } catch {
      // Diagnostics must never affect scraper behavior.
    }
  });
}

'''
if anchor not in s:
    raise SystemExit('navigation logging anchor not found')
s = s.replace(anchor, diagnostic + anchor, 1)

old = '''  const page = await context.newPage();
  installChallengeHandling(page);
  installNavigationLogging(page);
  await reportClearance(context);
'''
new = '''  const page = await context.newPage();
  installChallengeHandling(page);
  installNavigationLogging(page);
  await reportNativeBrowserDiagnostics(page);
  await reportClearance(context);
'''
if old not in s:
    raise SystemExit('diagnostic call insertion point not found')
s = s.replace(old, new, 1)

p.write_text(s)
print('whatnot scraper cleanup applied')
