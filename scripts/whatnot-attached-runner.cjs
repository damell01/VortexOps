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

const injected = `async function launchPersistentContextViaCdp(userDataDir, opts = {}) {\n  if (process.env.WHATNOT_ATTACH_EXISTING_BROWSER === '1') {\n    const endpoint = String(process.env.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222').trim();\n    info('attaching to existing Chromium over local CDP:', endpoint);\n\n    let browser;\n    try {\n      browser = await chromium.connectOverCDP(endpoint, { timeout: 15000 });\n    } catch (e) {\n      throw new Error(\n        'ATTACHED_BROWSER_UNAVAILABLE: could not connect to ' + endpoint + '. ' +\n        'Start the manual Chromium session with a local remote-debugging port first. ' +\n        'Original error: ' + e.message\n      );\n    }\n\n    const contexts = browser.contexts();\n    if (contexts.length === 0) {\n      throw new Error('ATTACHED_BROWSER_UNAVAILABLE: Chromium exposed no browser context.');\n    }\n\n    const context = contexts[0];\n    const pages = context.pages();\n    info('attached browser connected; existing pages=' + pages.length);\n\n    // The human-owned browser must remain alive after an Artisan command ends.\n    // The normal scraper closes a context it created itself in finally/blocked\n    // paths, so make close() a no-op for this borrowed default context. Refuse\n    // attached mode if Playwright ever makes that method non-overridable rather\n    // than risking closing the human browser unexpectedly.\n    try {\n      Object.defineProperty(context, 'close', {\n        configurable: true,\n        value: async () => {\n          info('attached browser: leaving the human-owned Chromium session open');\n        },\n      });\n    } catch (e) {\n      throw new Error('ATTACHED_BROWSER_UNAVAILABLE: could not protect borrowed Chromium from context.close(): ' + e.message);\n    }\n\n    return context;\n  }\n\n  const { spawn } = require('child_process');`;

source = source.replace(marker, injected);

// The current Seller Hub exposes stable ids for the two controls involved in a
// role change. The profile button may arrive a little after the dashboard shell,
// so in attached mode wait for the exact visible control instead of immediately
// falling into the generic hamburger/menu scan.
const profileMarker = `  const directProfileButton = page.locator('#team-invite-profile-menu-anchor').first();\n  if (await directProfileButton.isVisible({ timeout: 2500 }).catch(() => false)) {`;

if (!source.includes(profileMarker)) {
  process.stderr.write(
    '[whatnot] attached-browser shim could not find the direct profile-control path in whatnot-scraper.cjs. ' +
    'The scraper changed; update the attached-mode channel-switch shim before using it.\n',
  );
  process.exit(2);
}

const profileInjected = `  const directProfileButton = page.locator('button#team-invite-profile-menu-anchor:visible, button:has(img.z-avatar-image[alt]):visible').first();\n  await directProfileButton.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});\n  if (await directProfileButton.isVisible().catch(() => false)) {`;

source = source.replace(profileMarker, profileInjected);

const switchRoleMarker = `    const directSwitchRole = page.locator('#team-invite-switch-role-anchor').first();\n    if (await directSwitchRole.isVisible({ timeout: 5000 }).catch(() => false)) {`;

if (!source.includes(switchRoleMarker)) {
  process.stderr.write(
    '[whatnot] attached-browser shim could not find the direct Switch Role path in whatnot-scraper.cjs. ' +
    'The scraper changed; update the attached-mode channel-switch shim before using it.\n',
  );
  process.exit(2);
}

const switchRoleInjected = `    const directSwitchRole = page.locator('button#team-invite-switch-role-anchor:visible').first();\n    await directSwitchRole.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});\n    if (await directSwitchRole.isVisible().catch(() => false)) {`;

source = source.replace(switchRoleMarker, switchRoleInjected);

// Keep the generic fallback safe for attached CDP too. Locator count/nth avoids
// relying on ElementHandle arrays being directly iterable in this environment.
const triggerSweepMarker = `    for (const extra of await page.$$('button[aria-haspopup], [role="button"][aria-haspopup]').catch(() => [])) {\n      triggerHandles.push({ sel: 'sweep:aria-haspopup', h: extra });\n    }`;

if (!source.includes(triggerSweepMarker)) {
  process.stderr.write(
    '[whatnot] attached-browser shim could not find the profile trigger sweep in whatnot-scraper.cjs. ' +
    'The scraper changed; update the attached-mode channel-switch shim before using it.\n',
  );
  process.exit(2);
}

const triggerSweepInjected = `    const popupTriggers = page.locator('button[aria-haspopup], [role="button"][aria-haspopup]');\n    const popupCount = Math.min(await popupTriggers.count().catch(() => 0), 40);\n    for (let popupIndex = 0; popupIndex < popupCount; popupIndex++) {\n      const popupHandle = await popupTriggers.nth(popupIndex).elementHandle({ timeout: 1000 }).catch(() => null);\n      if (popupHandle) triggerHandles.push({ sel: 'sweep:aria-haspopup', h: popupHandle });\n    }\n\n    // Current Seller Hub profile/avatar control can also be represented by the\n    // nested user-initial button in diagnostics. Keep it as a last-resort fallback\n    // after the exact stable profile id path above.\n    const compactButtons = page.locator('button');\n    const compactCount = Math.min(await compactButtons.count().catch(() => 0), 80);\n    for (let compactIndex = 0; compactIndex < compactCount; compactIndex++) {\n      const compact = compactButtons.nth(compactIndex);\n      const compactLabel = await compact.evaluate((el) =>\n        (el.getAttribute('aria-label') || el.innerText || el.textContent || '').trim().replace(/\\s+/g, ' ')\n      ).catch(() => '');\n      if (!/^[A-Za-z0-9]{1,3}$/.test(compactLabel)) continue;\n      const compactHandle = await compact.elementHandle({ timeout: 1000 }).catch(() => null);\n      if (!compactHandle) continue;\n      triggerHandles.push({ sel: 'sweep:compact-avatar[' + compactLabel + ']', h: compactHandle });\n    }`;

source = source.replace(triggerSweepMarker, triggerSweepInjected);

// Make attach mode explicit to the transformed scraper. The endpoint itself is
// already validated as loopback above.
process.env.WHATNOT_ATTACH_EXISTING_BROWSER = '1';
process.env.WHATNOT_ATTACH_CDP_URL = endpoint;

const compiled = new Module(target, module.parent);
compiled.filename = target;
compiled.paths = Module._nodeModulePaths(path.dirname(target));
compiled._compile(source, target);
