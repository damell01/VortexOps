'use strict';

const path = require('path');
const { spawnSync } = require('child_process');

function normalizeChannel(value) {
  return String(value || '')
    .trim()
    .replace(/^@+/, '')
    .toLowerCase();
}

function channelsFromArgs() {
  const args = process.argv.slice(2);
  const channels = [];
  const passthrough = [];

  for (let i = 0; i < args.length; i += 1) {
    const arg = args[i];
    if (arg.startsWith('--channel=')) {
      channels.push(arg.slice('--channel='.length));
      continue;
    }
    if (arg === '--channel' && args[i + 1]) {
      channels.push(args[i + 1]);
      i += 1;
      continue;
    }
    passthrough.push(arg);
  }

  const envChannels = String(process.env.WHATNOT_CHANNELS || '')
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);

  return {
    channels: [...channels, ...envChannels],
    passthrough,
  };
}

const { channels: rawChannels, passthrough } = channelsFromArgs();
const channels = [...new Set(rawChannels.map(normalizeChannel).filter(Boolean))];

if (channels.length === 0) {
  console.error('[whatnot:multi] No channels configured. Set WHATNOT_CHANNELS or pass --channel.');
  process.exit(2);
}

const invalid = channels.filter((channel) => !/^[a-z0-9._-]+$/.test(channel));
if (invalid.length) {
  console.error(`[whatnot:multi] Invalid channel name(s): ${invalid.join(', ')}`);
  process.exit(2);
}

const runner = path.join(__dirname, 'whatnot-runner.cjs');
const results = [];

// All roles belong to the same authenticated Whatnot account/team context, so
// process them serially in one shared persistent Chromium session. The scraper
// verifies the requested active role before each scrape and fails closed if the
// role cannot be proven. Per-channel profile isolation would prevent that role
// switcher state from being shared and was the source of stale channels.
for (const channel of channels) {
  console.error(`[whatnot:multi] START requested=@${channel}`);

  const result = spawnSync(process.execPath, [runner, ...passthrough], {
    cwd: path.resolve(__dirname, '..'),
    stdio: 'inherit',
    env: {
      ...process.env,
      WHATNOT_CHANNEL_NAME: channel,
      WHATNOT_CHANNEL_ISOLATE: '0',
      WHATNOT_AUTO_BROWSER: process.env.WHATNOT_AUTO_BROWSER || '1',
    },
  });

  const status = result.error ? 1 : (result.status == null ? 1 : result.status);
  results.push({ channel, status, error: result.error?.message || null });

  if (result.error) {
    console.error(`[whatnot:multi] FAIL requested=@${channel} process_error=${result.error.message}`);
  } else if (status === 0) {
    console.error(`[whatnot:multi] OK requested=@${channel}`);
  } else {
    console.error(`[whatnot:multi] FAIL requested=@${channel} exit=${status}`);
  }
}

const failed = results.filter((result) => result.status !== 0);
console.error(
  `[whatnot:multi] SUMMARY total=${results.length} ok=${results.length - failed.length} failed=${failed.length}`,
);

for (const result of failed) {
  console.error(`[whatnot:multi] FAILED_CHANNEL requested=@${result.channel} exit=${result.status}`);
}

// Every channel is attempted even if a prior one fails. A non-zero aggregate
// status still lets the scheduler/monitoring layer know that attention is needed.
process.exit(failed.length ? 1 : 0);
