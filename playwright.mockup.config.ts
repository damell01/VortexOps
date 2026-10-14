import { defineConfig } from '@playwright/test';
export default defineConfig({
 testDir:'./tests/Browser',testMatch:'mockup-workspaces.spec.ts',workers:1,timeout:60000,
 use:{baseURL:'http://127.0.0.1:8000',browserName:'chromium',screenshot:'only-on-failure',trace:'retain-on-failure'},
 outputDir:'tests/Browser/output',
});
