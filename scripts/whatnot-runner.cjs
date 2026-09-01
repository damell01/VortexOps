'use strict';

const path = require('path');
const { spawnSync } = require('child_process');

const backend = String(process.env.WHATNOT_BROWSER_BACKEND || 'local').trim().toLowerCase();
const mode = String(process.env.WHATNOT_MODE || 'analytics').trim();
const scraplingModes = new Set(['analytics', 'orders-batch', 'shipments-batch', 'ledger']);

if (backend === 'scrapling' && scraplingModes.has(mode)) {
  const python = String(process.env.WHATNOT_PYTHON_BIN || 'python3').trim();
  const script = path.join(__dirname, 'whatnot-scrapling-stealth.py');
  const env = {
    ...process.env,
    WHATNOT_USER_DATA_DIR: process.env.WHATNOT_USER_DATA_DIR || path.resolve(__dirname, '..', 'storage', 'whatnot-browser-profile'),
    WHATNOT_SCRAPLING_DIAGNOSTICS_DIR:
      process.env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR || path.resolve(__dirname, '..', 'storage', 'logs', 'whatnot-scrapling'),
  };

  process.stderr.write(`[whatnot] browser backend: scrapling-stealth (mode=${mode})\n`);
  const result = spawnSync(python, [script], {
    env,
    cwd: path.resolve(__dirname, '..'),
    stdio: 'inherit',
  });

  if (result.error) {
    process.stderr.write(`[whatnot] Scrapling runner failed to start: ${result.error.message}\n`);
    process.exit(1);
  }
  process.exit(result.status == null ? 1 : result.status);
}

if (backend === 'scrapling') {
  process.stderr.write(`[whatnot] Scrapling does not replace mode=${mode}; using the existing Node scraper for this utility/auth mode.\n`);
  process.env.WHATNOT_BROWSER_BACKEND = 'local';
}

require('./whatnot-scraper.cjs');
