import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: './tests/Browser',
    testMatch: ['inventory-audit.spec.ts', 'inventory-scanner-runtime.spec.ts', 'assistant-dashboard.spec.ts'],
    workers: 1,
    timeout: 90000,
    use: { baseURL: 'http://127.0.0.1:8000', actionTimeout: 15000, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
    projects: [
        { name: 'desktop', use: { browserName: 'chromium', viewport: {width: 1440, height: 1000} } },
        { name: 'mobile', use: { browserName: 'chromium', viewport: {width: 390, height: 844}, isMobile: true, hasTouch: true } },
        { name: 'safari-mobile', use: { browserName: 'webkit', viewport: {width: 390, height: 844}, isMobile: true, hasTouch: true } },
    ],
    outputDir: 'tests/Browser/output/inventory-audit',
});
