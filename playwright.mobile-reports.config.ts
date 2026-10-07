import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: 'mobile-reports.spec.ts',
    timeout: 240000,
    workers: 1,
    use: {
        baseURL: 'http://127.0.0.1:8000',
        actionTimeout: 10000,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium', use: { browserName: 'chromium' } },
        { name: 'webkit', use: { browserName: 'webkit' } },
    ],
    outputDir: 'tests/Browser/output',
});
