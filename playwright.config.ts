import { defineConfig } from '@playwright/test';
import { existsSync } from 'node:fs';

const bundledPath = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const launchOptions = existsSync(bundledPath)
    ? { executablePath: bundledPath, args: ['--no-sandbox', '--disable-setuid-sandbox'] }
    : { args: ['--no-sandbox', '--disable-setuid-sandbox'] };

export default defineConfig({
    testDir: './tests/Browser',
    timeout: 900_000,
    use: {
        baseURL: 'http://127.0.0.1:8000',
        launchOptions,
        video: 'on',
        screenshot: 'on',
    },
    projects: [
        {
            name: 'desktop',
            use: { viewport: { width: 1440, height: 900 } },
        },
        {
            name: 'mobile',
            use: {
                viewport: { width: 390, height: 844 },
                userAgent:
                    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) ' +
                    'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                hasTouch: true,
                isMobile: true,
            },
        },
    ],
    outputDir: 'tests/Browser/output',
});
