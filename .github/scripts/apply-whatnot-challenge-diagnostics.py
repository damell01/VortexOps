from pathlib import Path

p = Path('scripts/whatnot-scraper.cjs')
s = p.read_text()

anchor = """function installNavigationLogging(page) {\n"""

addition = r'''function installChallengeDiagnostics(page) {
  if (process.env.WHATNOT_BROWSER_DIAGNOSTICS !== '1') return;

  const context = page.context();
  const interesting = (url = '') => {
    const u = String(url || '');
    return u.includes('whatnot.com') || u.includes('challenges.cloudflare.com') || u.includes('/cdn-cgi/challenge-platform/');
  };

  page.on('console', (msg) => {
    try {
      const type = msg.type();
      if (!['error', 'warning'].includes(type)) return;
      const text = msg.text();
      const loc = msg.location?.() || {};
      const url = loc.url || '';
      if (!interesting(url) && !/cloudflare|turnstile|challenge|worker|importscripts|content security|csp/i.test(text)) return;
      info('[challenge-diag] console:', JSON.stringify({ type, text: text.substring(0, 800), url: navPath(url) }));
    } catch {}
  });

  page.on('pageerror', (error) => {
    try {
      info('[challenge-diag] pageerror:', JSON.stringify({
        name: error?.name || null,
        message: String(error?.message || error || '').substring(0, 1200),
      }));
    } catch {}
  });

  page.on('requestfailed', (request) => {
    try {
      if (!interesting(request.url())) return;
      const failure = request.failure?.();
      info('[challenge-diag] requestfailed:', JSON.stringify({
        method: request.method(),
        resourceType: request.resourceType(),
        url: navPath(request.url()),
        errorText: failure?.errorText || null,
      }));
    } catch {}
  });

  page.on('worker', (worker) => {
    try {
      info('[challenge-diag] worker-created:', navPath(worker.url()));
      worker.on('close', () => {
        info('[challenge-diag] worker-closed:', navPath(worker.url()));
      });
    } catch {}
  });

  try {
    context.on('serviceworker', (worker) => {
      try {
        info('[challenge-diag] serviceworker-created:', navPath(worker.url()));
      } catch {}
    });
  } catch {}

  page.on('response', async (response) => {
    try {
      const url = response.url();
      const status = response.status();
      if (!interesting(url)) return;
      if (status < 400 && !url.includes('/cdn-cgi/challenge-platform/') && !url.includes('challenges.cloudflare.com')) return;

      const headers = await response.allHeaders().catch(() => ({}));
      info('[challenge-diag] response:', JSON.stringify({
        status,
        resourceType: response.request().resourceType(),
        url: navPath(url),
        server: headers.server || null,
        cfRay: headers['cf-ray'] || null,
        cfMitigated: headers['cf-mitigated'] || null,
        contentType: headers['content-type'] || null,
        contentSecurityPolicy: (headers['content-security-policy'] || '').substring(0, 700) || null,
      }));
    } catch {}
  });
}

'''

if 'function installChallengeDiagnostics(page)' in s:
    raise SystemExit('challenge diagnostics already installed')
if anchor not in s:
    raise SystemExit('installNavigationLogging anchor not found')
s = s.replace(anchor, addition + anchor, 1)

old = """  installChallengeHandling(page);\n  installNavigationLogging(page);\n  await reportNativeBrowserDiagnostics(page);\n"""
new = """  installChallengeHandling(page);\n  installNavigationLogging(page);\n  installChallengeDiagnostics(page);\n  await reportNativeBrowserDiagnostics(page);\n"""
if old not in s:
    raise SystemExit('page setup anchor not found')
s = s.replace(old, new, 1)

p.write_text(s)
