'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');

const ROOT = __dirname;
const CONFIG_PATH = path.join(ROOT, 'config.json');
const APP_DIR = process.env.LOCALAPPDATA
  ? path.join(process.env.LOCALAPPDATA, 'VortexOps', 'WhatnotCollector')
  : path.join(os.homedir(), '.vortexops', 'whatnot-collector');
const DEFAULT_PROFILE = path.join(APP_DIR, 'ChromeProfile');

function detectChrome() {
  const candidates = [
    process.env.PROGRAMFILES && path.join(process.env.PROGRAMFILES, 'Google', 'Chrome', 'Application', 'chrome.exe'),
    process.env['PROGRAMFILES(X86)'] && path.join(process.env['PROGRAMFILES(X86)'], 'Google', 'Chrome', 'Application', 'chrome.exe'),
    process.env.LOCALAPPDATA && path.join(process.env.LOCALAPPDATA, 'Google', 'Chrome', 'Application', 'chrome.exe'),
  ].filter(Boolean);
  return candidates.find((candidate) => fs.existsSync(candidate)) || '';
}

let config = {};
if (fs.existsSync(CONFIG_PATH)) {
  try { config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8')); } catch {}
}

const chrome = config.chrome_path ? path.resolve(config.chrome_path) : detectChrome();
const profile = config.profile_dir ? path.resolve(config.profile_dir) : DEFAULT_PROFILE;

if (!chrome || !fs.existsSync(chrome)) {
  console.error('Google Chrome was not found. Set chrome_path in desktop-collector/config.json.');
  process.exit(1);
}

fs.mkdirSync(profile, { recursive: true });

console.log('============================================================');
console.log(' VortexOps Whatnot Login');
console.log('============================================================');
console.log('');
console.log('A dedicated Chrome window is opening.');
console.log('1. Log into Whatnot normally.');
console.log('2. Open Seller Hub and make sure you can see your seller account.');
console.log('3. Close the ENTIRE dedicated Chrome window when finished.');
console.log('4. Then run "Sync Whatnot.bat".');
console.log('');
console.log('This does not type, save, or upload your Whatnot password.');
console.log(`Profile: ${profile}`);
console.log('');

const child = spawn(chrome, [
  `--user-data-dir=${profile}`,
  '--no-first-run',
  '--no-default-browser-check',
  'https://www.whatnot.com/dashboard/home',
], {
  stdio: 'ignore',
  detached: false,
});

child.on('error', (e) => {
  console.error('Could not start Chrome:', e.message);
  process.exitCode = 1;
});

child.on('close', () => {
  console.log('Dedicated Chrome closed. The Whatnot session is saved in the collector profile.');
});
