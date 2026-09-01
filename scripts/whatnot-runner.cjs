/**
 * Whatnot scraper backend router.
 *
 * Single entry point called by app/Services/WhatnotScraper.php. Dispatches to
 * whichever browser backend WHATNOT_BROWSER_BACKEND selects, then forwards
 * that backend's stdout/stderr/exit code untouched — Laravel only ever knows
 * about this file, never about which engine actually ran.
 *
 *   local     (default) → scripts/whatnot-scraper.cjs   (Node + Playwright)
 *   scrapling            → scripts/whatnot-scrapling.py  (Python), only for
 *                          modes it currently implements (see SCRAPLING_MODES
 *                          below) — everything else still falls back to local.
 *
 * Env vars read directly here:
 *   WHATNOT_BROWSER_BACKEND   local | scrapling (default: local)
 *   WHATNOT_PYTHON_BIN        python executable for the scrapling backend (default: python3)
 *   WHATNOT_MODE              same mode string WhatnotScraper.php always sets
 *
 * Everything else in the environment (WHATNOT_EMAIL, WHATNOT_LIMIT,
 * WHATNOT_CHANNEL_NAME, PLAYWRIGHT_*, ...) is passed through unchanged to
 * whichever child process is spawned.
 *
 * Exit codes are whatever the selected backend produced. See the header
 * comments in whatnot-scraper.cjs / whatnot-scrapling.py for the shared
 * contract (0 success, 1 misc failure, 2 selector/layout miss, 3 auth or
 * challenge required, 4 rate limited).
 */

'use strict';

const path = require('path');
const { spawnSync } = require('child_process');

const MODE    = process.env.WHATNOT_MODE || 'analytics';
const BACKEND = (process.env.WHATNOT_BROWSER_BACKEND || 'local').trim().toLowerCase();

// Modes whatnot-scrapling.py actually implements today. Anything else always
// runs on the local Node/Playwright scraper regardless of WHATNOT_BROWSER_BACKEND —
// there is no reason to route a mode to a backend that doesn't support it yet.
const SCRAPLING_MODES = new Set(['analytics', 'orders-batch', 'shipments-batch', 'ledger']);

function info(...args) {
  process.stderr.write('[whatnot-runner] ' + args.join(' ') + '\n');
}

function runLocal() {
  const scriptPath = path.join(__dirname, 'whatnot-scraper.cjs');
  const nodeBin = process.execPath;
  info(`routing mode="${MODE}" to local backend (${scriptPath})`);
  return spawnSync(nodeBin, [scriptPath], { stdio: 'inherit', env: process.env });
}

function runScrapling() {
  const scriptPath = path.join(__dirname, 'whatnot-scrapling.py');
  const pythonBin = process.env.WHATNOT_PYTHON_BIN || 'python3';
  info(`routing mode="${MODE}" to scrapling backend (${scriptPath})`);
  return spawnSync(pythonBin, [scriptPath], { stdio: 'inherit', env: process.env });
}

let result;

if (BACKEND === 'scrapling' && SCRAPLING_MODES.has(MODE)) {
  result = runScrapling();
} else {
  if (BACKEND === 'scrapling') {
    info(`mode="${MODE}" is not implemented by the scrapling backend yet — falling back to local`);
  }
  result = runLocal();
}

if (result.error) {
  // spawnSync itself failed (e.g. interpreter not found) — this is distinct
  // from the child process exiting non-zero, which is handled by propagating
  // result.status below as normal.
  process.stderr.write('[whatnot-runner] failed to launch backend: ' + result.error.message + '\n');
  process.exit(1);
}

process.exit(result.status === null ? 1 : result.status);
