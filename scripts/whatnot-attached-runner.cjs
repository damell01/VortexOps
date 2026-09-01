'use strict';

/**
 * Run the existing Whatnot scraper against an already-open Chromium instance.
 *
 * This does not solve, suppress, or manipulate Cloudflare challenges. It only
 * changes browser ownership: a human starts Chromium, signs in normally, and
 * VortexOps attaches over the local Chrome DevTools Protocol endpoint. The
 * scraper's existing challenge detection and channel fail-closed checks remain
 * active.
 *
 * The source shim is intentionally tiny so the main scraper stays the single
 * implementation of Whatnot navigation/extraction logic.
 */

const fs = require('fs');
const path = require('path');
const Module = require('module');

const target = path.join(__dirname, 'whatnot-scraper.cjs');
const endpoint = String(process.env.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222').trim();

// Keep the debugging endpoint local. Remote Chrome debugging is effectively
// browser control and should never be exposed as an unauthenticated network
// service by this helper.
let parsed;
try {
  parsed = new URL(endpoint);
} catch {
  process.stderr.write(`[whatnot] invalid WHATNOT_ATTACH_CDP_URL: ${endpoint}\n`);
  process.exit(2);
}

const host = (parsed.hostname || '').toLowerCase();
if (!['127.0.0.1', 'localhost', '::1'].includes(host)) {
  process.stderr.write(
    '[whatnot] attached-browser mode only permits a loopback CDP endpoint. ' +
    'Start Chromium with --remote-debugging-address=127.0.0.1 and connect locally.\n',
  );
  process.exit(2);
}

let source = fs.readFileSync(target, 'utf8');

const marker = `async function launchPersistentContextViaCdp(userDataDir, opts = {}) {\n  const { spawn } = require('child_process');`;

if (!source.includes(marker)) {
  process.stderr.write(
    '[whatnot] attached-browser shim could not find the Chromium launch hook in whatnot-scraper.cjs. ' +
    'The scraper changed; update scripts/whatnot-attached-runner.cjs before using attached mode.\n',
  );
  process.exit(2);
}

const injected = `async function launchPersistentContextViaCdp(userDataDir, opts = {}) {\n  if (process.env.WHATNOT_ATTACH_EXISTING_BROWSER === '1') {\n    const endpoint = String(process.env.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222').trim();\n    info('attaching to existing Chromium over local CDP:', endpoint);\n\n    let browser;\n    try {\n      browser = await chromium.connectOverCDP(endpoint, { timeout: 15000 });\n    } catch (e) {\n      throw new Error(\n        'ATTACHED_BROWSER_UNAVAILABLE: could not connect to ' + endpoint + '. ' +\n        'Start the manual Chromium session with a local remote-debugging port first. ' +\n        'Original error: ' + e.message\n      );\n    }\n\n    const contexts = browser.contexts();\n    if (contexts.length === 0) {\n      await browser.close().catch(() => {});\n      throw new Error('ATTACHED_BROWSER_UNAVAILABLE: Chromium exposed no browser context.');\n    }\n\n    const context = contexts[0];\n    const pages = context.pages();\n    info('attached browser connected; existing pages=' + pages.length);\n\n    // The human-owned browser must remain alive after an Artisan command ends.\n    // The normal scraper closes a context it created itself in finally/blocked\n    // paths, so make close() a no-op for this borrowed default context. Refuse\n    // attached mode if Playwright ever makes that method non-overridable rather\n    // than risking closing the human browser unexpectedly.\n    try {\n      Object.defineProperty(context, 'close', {\n        configurable: true,\n        value: async () => {\n          info('attached browser: leaving the human-owned Chromium session open');\n        },\n      });\n    } catch (e) {\n      throw new Error('ATTACHED_BROWSER_UNAVAILABLE: could not protect borrowed Chromium from context.close(): ' + e.message);\n    }\n\n    return context;\n  }\n\n  const { spawn } = require('child_process');`;

source = source.replace(marker, injected);

// Make attach mode explicit to the transformed scraper. The endpoint itself is
// already validated as loopback above.
process.env.WHATNOT_ATTACH_EXISTING_BROWSER = '1';
process.env.WHATNOT_ATTACH_CDP_URL = endpoint;

const compiled = new Module(target, module.parent);
compiled.filename = target;
compiled.paths = Module._nodeModulePaths(path.dirname(target));
compiled._compile(source, target);
