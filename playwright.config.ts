import { defineConfig } from '@playwright/test';

const CHROMIUM = {
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
};

export default defineConfig({
    testDir: './tests/Browser',
    timeout: 300_000,
    use: {
        baseURL: 'http://127.0.0.1:8000',
        launchOptions: CHROMIUM,
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
