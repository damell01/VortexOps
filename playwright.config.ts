import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    timeout: 300_000,
    use: {
        baseURL: 'http://127.0.0.1:8000',
        launchOptions: {
            executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
            args: ['--no-sandbox', '--disable-setuid-sandbox'],
        },
        video: 'on',
        screenshot: 'on',
        viewport: { width: 1440, height: 900 },
    },
    projects: [
        { name: 'chromium' },
    ],
    outputDir: 'tests/Browser/output',
});
