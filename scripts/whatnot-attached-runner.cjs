'use strict';

/**
 * Run the existing Whatnot scraper against an already-open Chromium instance.
 *
 * This does not solve, suppress, or manipulate Cloudflare challenges. It only
 * changes browser ownership: VortexOps attaches to the local persistent Chrome
 * instance and reuses its authenticated state.
 */

const fs = require('fs');
const path = require('path');
const Module = require('module');

const target = path.join(__dirname, 'whatnot-scraper.cjs');
const endpoint = String(process.env.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222').trim();

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

function replaceRequired(marker, replacement, description) {
  if (!source.includes(marker)) {
    process.stderr.write(`[whatnot] attached-browser shim could not find ${description} in whatnot-scraper.cjs. The scraper changed; update scripts/whatnot-attached-runner.cjs before using attached mode.\n`);
    process.exit(2);
  }
  source = source.replace(marker, replacement);
}

const marker = `async function launchPersistentContextViaCdp(userDataDir, opts = {}) {\n  const { spawn } = require('child_process');`;
const injected = `async function launchPersistentContextViaCdp(userDataDir, opts = {}) {\n  if (process.env.WHATNOT_ATTACH_EXISTING_BROWSER === '1') {\n    const endpoint = String(process.env.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222').trim();\n    info('attaching to existing Chromium over local CDP:', endpoint);\n\n    let browser;\n    let firstAttachError = null;\n    try {\n      browser = await chromium.connectOverCDP(endpoint, { timeout: 30000 });\n    } catch (e) {\n      firstAttachError = e;\n      info('attached browser: first CDP attach attempt did not settle; retrying once after 2s');\n      await new Promise((resolve) => setTimeout(resolve, 2000));\n      try {\n        browser = await chromium.connectOverCDP(endpoint, { timeout: 30000 });\n      } catch (retryError) {\n        throw new Error('ATTACHED_BROWSER_UNAVAILABLE: could not connect to ' + endpoint + ' after two bounded attempts. First error: ' + firstAttachError.message + ' | Retry error: ' + retryError.message);\n      }\n    }\n\n    const contexts = browser.contexts();\n    if (contexts.length === 0) throw new Error('ATTACHED_BROWSER_UNAVAILABLE: Chromium exposed no browser context.');\n    const context = contexts[0];\n    info('attached browser connected; existing pages=' + context.pages().length);\n\n    try {\n      Object.defineProperty(context, 'close', {\n        configurable: true,\n        value: async () => { info('attached browser: leaving the human-owned Chromium session open'); },\n      });\n    } catch (e) {\n      throw new Error('ATTACHED_BROWSER_UNAVAILABLE: could not protect borrowed Chromium from context.close(): ' + e.message);\n    }\n    return context;\n  }\n\n  const { spawn } = require('child_process');`;
replaceRequired(marker, injected, 'the Chromium launch hook');

// Attached Chromium already owns the authoritative cookie jar. A successful
// whatnot:login --test writes whatnot-live-cookies.json immediately before a
// probe, making that file newer than its marker. Re-importing that snapshot into
// the same live context is unnecessary and sets _bootstrappedFromFile=true,
// which makes the main scraper navigate the already-good tab to `/` solely to
// restore localStorage. In attached mode only bootstrap when the live context
// genuinely has no Whatnot cookies.
const shouldLoadMarker = `    const _shouldLoad = _existingCookies.length === 0\n      || ((_isHumanImport || _isServerLiveSnapshot) && _fileMtimeMs > _lastLoadedMtimeMs);`;
const shouldLoadInjected = `    const _shouldLoad = process.env.WHATNOT_ATTACH_EXISTING_BROWSER === '1'\n      ? _existingCookies.length === 0\n      : (_existingCookies.length === 0\n        || ((_isHumanImport || _isServerLiveSnapshot) && _fileMtimeMs > _lastLoadedMtimeMs));`;
replaceRequired(shouldLoadMarker, shouldLoadInjected, 'the cookie bootstrap decision');

const pageMarker = `  const page = await context.newPage();`;
const pageInjected = `  const attachedPages = process.env.WHATNOT_ATTACH_EXISTING_BROWSER === '1'\n    ? context.pages().filter((candidate) => !candidate.isClosed())\n    : [];\n  const page = attachedPages.find((candidate) => candidate.url().includes('whatnot.com'))\n    || attachedPages[0]\n    || await context.newPage();\n  if (process.env.WHATNOT_ATTACH_EXISTING_BROWSER === '1') {\n    info('attached browser: reusing persistent page; open_pages=' + context.pages().length + ' url=' + page.url());\n  }`;
replaceRequired(pageMarker, pageInjected, 'the main page creation');

// The attached browser may already be sitting on a fully rendered Seller Hub.
// Inspect it before navigating. For a channel-scoped run, if that borrowed tab
// has already been replaced by a challenge, recover onto /seller instead of
// immediately requesting /dashboard/home again. /seller is only used as a
// normal authenticated shell from which the existing Switch Role flow can run;
// challenge/login detection remains fail-closed.
const hubPreflightMarker = `      await page.goto('https://www.whatnot.com/dashboard/home', { waitUntil: 'domcontentloaded', timeout: 20000 });\n      await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});\n\n      // Not just "did goto resolve" — see confirmSellerHub. The answer this\n      // gives is the one the rest of the run is built on, so it is worth the\n      // few seconds it costs to be sure of it.\n      const hub      = await confirmSellerHub(page);\n      const checkUrl = hub.url;`;
const hubPreflightInjected = `      let hub = null;\n      if (process.env.WHATNOT_ATTACH_EXISTING_BROWSER === '1' && page.url().includes('whatnot.com')) {\n        hub = await confirmSellerHub(page, { settleMs: 1000 });\n        if (hub.ok) {\n          info('cookie auth check: reusing already-settled Seller Hub from attached Chromium; no preflight navigation needed');\n        }\n      }\n\n      if ((!hub || !hub.ok) && process.env.WHATNOT_ATTACH_EXISTING_BROWSER === '1' && CHANNEL_NAME) {\n        info('cookie auth check: attached hub is not settled; trying /seller as the authenticated shell before forcing another dashboard navigation');\n        await page.goto(URLS.sellerMarketing, { waitUntil: 'domcontentloaded', timeout: 20000 });\n        await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});\n        const shellBody = await readBodyText(page);\n        if (LOGIN_URL_RE.test(page.url())) {\n          hub = { ok: false, reason: 'login', url: page.url(), body: shellBody };\n        } else if (shellBody !== null && isChallengePage(shellBody)) {\n          hub = { ok: false, reason: 'challenge', url: page.url(), body: shellBody };\n        } else {\n          hub = { ok: false, reason: 'unrecognised', url: page.url(), body: shellBody };\n          info('cookie auth check: /seller shell loaded without login/challenge; continuing to channel verification');\n        }\n      }\n\n      if (!hub || (!hub.ok && hub.reason !== 'unrecognised')) {\n        await page.goto('https://www.whatnot.com/dashboard/home', { waitUntil: 'domcontentloaded', timeout: 20000 });\n        await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});\n        hub = await confirmSellerHub(page);\n      }\n      const checkUrl = hub.url;`;
replaceRequired(hubPreflightMarker, hubPreflightInjected, 'the Seller Hub cookie-auth preflight');

// `whatnot:login --test` must answer the same question as a real scraper run.
// The underlying cookie-test mode historically treated landing on the Seller Hub
// URL as success even when the body had already become a Cloudflare interstitial.
// Add the same settled-page check used by the normal auth path so the command
// cannot report "Seller Hub accessible" for a challenged document.
const cookieTestMarker = `    if (MODE === 'cookie-test') {\n      info('cookie-test: navigating to seller hub');\n      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 20000 });\n      await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});`;
const cookieTestInjected = `    if (MODE === 'cookie-test') {\n      info('cookie-test: navigating to seller hub');\n      await page.goto(URLS.sellerHub, { waitUntil: 'domcontentloaded', timeout: 20000 });\n      await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});\n\n      const cookieHub = await confirmSellerHub(page);\n      if (!cookieHub.ok) {\n        if (cookieHub.reason === 'login') {\n          throw new Error('Cookie test redirected to login; saved Whatnot session is expired.');\n        }\n        if (cookieHub.reason === 'challenge') {\n          exitBlocked(\n            classifyBlockingPage(cookieHub.url, cookieHub.body) || {\n              code: EXIT.AUTH_REQUIRED,\n              error: 'BOT_CHALLENGE',\n              message: 'Cloudflare replaced the Seller Hub during cookie verification.',\n            },\n            cookieHub.url,\n          );\n        }\n        throw new Error('Cookie test could not verify a settled Seller Hub at ' + cookieHub.url);\n      }\n      info('cookie-test: Seller Hub rendered and settled; authentication verification is real');`;
replaceRequired(cookieTestMarker, cookieTestInjected, 'the cookie-test Seller Hub verification');

const profileMarker = `  const directProfileButton = page.locator('#team-invite-profile-menu-anchor').first();\n  if (await directProfileButton.isVisible({ timeout: 2500 }).catch(() => false)) {`;
const profileInjected = `  const directProfileButton = page.locator('button#team-invite-profile-menu-anchor:visible, button:has(img.z-avatar-image[alt]):visible').first();\n  await directProfileButton.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});\n  if (await directProfileButton.isVisible().catch(() => false)) {`;
replaceRequired(profileMarker, profileInjected, 'the direct profile-control path');

const switchRoleMarker = `    const directSwitchRole = page.locator('#team-invite-switch-role-anchor').first();\n    if (await directSwitchRole.isVisible({ timeout: 5000 }).catch(() => false)) {`;
const switchRoleInjected = `    const directSwitchRole = page.locator('button#team-invite-switch-role-anchor:visible').first();\n    await directSwitchRole.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});\n    if (await directSwitchRole.isVisible().catch(() => false)) {`;
replaceRequired(switchRoleMarker, switchRoleInjected, 'the direct Switch Role path');

const triggerSweepMarker = `    for (const extra of await page.$$('button[aria-haspopup], [role="button"][aria-haspopup]').catch(() => [])) {\n      triggerHandles.push({ sel: 'sweep:aria-haspopup', h: extra });\n    }`;
const triggerSweepInjected = `    const popupTriggers = page.locator('button[aria-haspopup], [role="button"][aria-haspopup]');\n    const popupCount = Math.min(await popupTriggers.count().catch(() => 0), 40);\n    for (let popupIndex = 0; popupIndex < popupCount; popupIndex++) {\n      const popupHandle = await popupTriggers.nth(popupIndex).elementHandle({ timeout: 1000 }).catch(() => null);\n      if (popupHandle) triggerHandles.push({ sel: 'sweep:aria-haspopup', h: popupHandle });\n    }\n\n    const compactButtons = page.locator('button');\n    const compactCount = Math.min(await compactButtons.count().catch(() => 0), 80);\n    for (let compactIndex = 0; compactIndex < compactCount; compactIndex++) {\n      const compact = compactButtons.nth(compactIndex);\n      const compactLabel = await compact.evaluate((el) =>\n        (el.getAttribute('aria-label') || el.innerText || el.textContent || '').trim().replace(/\\s+/g, ' ')\n      ).catch(() => '');\n      if (!/^[A-Za-z0-9]{1,3}$/.test(compactLabel)) continue;\n      const compactHandle = await compact.elementHandle({ timeout: 1000 }).catch(() => null);\n      if (compactHandle) triggerHandles.push({ sel: 'sweep:compact-avatar[' + compactLabel + ']', h: compactHandle });\n    }`;
replaceRequired(triggerSweepMarker, triggerSweepInjected, 'the profile trigger sweep');

const channelContextMarker = `      const verified = await getActiveChannelUsername(page);`;
replaceRequired(channelContextMarker, `      const verified = await waitForActiveChannel(page, CHANNEL_NAME, 15000);`, 'the final channel-context verification');

process.env.WHATNOT_ATTACH_EXISTING_BROWSER = '1';
process.env.WHATNOT_ATTACH_CDP_URL = endpoint;

const compiled = new Module(target, module.parent);
compiled.filename = target;
compiled.paths = Module._nodeModulePaths(path.dirname(target));
compiled._compile(source, target);