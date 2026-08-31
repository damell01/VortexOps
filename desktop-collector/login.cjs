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
const LOCK_FILE = path.join(APP_DIR, 'collector.lock');

function detectChrome() {
  const candidates = [
    process.env.PROGRAMFILES && path.join(process.env.PROGRAMFILES, 'Google', 'Chrome', 'Application', 'chrome.exe'),
    process.env['PROGRAMFILES(X86)'] && path.join(process.env['PROGRAMFILES(X86)'], 'Google', 'Chrome', 'Application', 'chrome.exe'),
    process.env.LOCALAPPDATA && path.join(process.env.LOCALAPPDATA, 'Google', 'Chrome', 'Application', 'chrome.exe'),
  ].filter(Boolean);
  return candidates.find((candidate) => fs.existsSync(candidate)) || '';
}

function processAlive(pid) {
  if (!Number.isInteger(pid) || pid <= 0) return false;
  try { process.kill(pid, 0); return true; }
  catch (e) { return e.code === 'EPERM'; }
}

function acquireLock() {
  fs.mkdirSync(APP_DIR, { recursive: true });
  if (fs.existsSync(LOCK_FILE)) {
    let existing = null;
    try { existing = JSON.parse(fs.readFileSync(LOCK_FILE, 'utf8')); } catch {}
    if (existing?.pid && processAlive(Number(existing.pid))) {
      console.error(`A Whatnot sync/login is already using the dedicated browser profile (PID ${existing.pid}).`);
      process.exit(1);
    }
    fs.rmSync(LOCK_FILE, { force: true });
  }
  fs.writeFileSync(LOCK_FILE, JSON.stringify({ pid: process.pid, mode: 'manual-login', started_at: new Date().toISOString() }), { flag: 'wx' });
}

function releaseLock() {
  try {
    const current = JSON.parse(fs.readFileSync(LOCK_FILE, 'utf8'));
    if (Number(current?.pid) === process.pid) fs.rmSync(LOCK_FILE, { force: true });
  } catch {}
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
acquireLock();

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
  releaseLock();
  console.error('Could not start Chrome:', e.message);
  process.exitCode = 1;
});

child.on('close', () => {
  releaseLock();
  console.log('Dedicated Chrome closed. The Whatnot session is saved in the collector profile.');
});

process.on('SIGINT', () => { releaseLock(); process.exit(130); });
process.on('SIGTERM', () => { releaseLock(); process.exit(143); });
process.on('exit', releaseLock);
