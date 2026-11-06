import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser', testMatch: 'inventory-changes.spec.ts', workers: 1, timeout: 60000,
    use: { baseURL: 'http://127.0.0.1:8000', browserName: 'chromium', channel: process.env.PLAYWRIGHT_CHROMIUM_CHANNEL, trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    outputDir: 'tests/Browser/output/inventory-changes',
});
