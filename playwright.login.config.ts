import { defineConfig } from '@playwright/test';
export default defineConfig({testDir:'tests/Browser',testMatch:'vortex-login.spec.ts',workers:1,timeout:45000,use:{baseURL:'http://127.0.0.1:8000',headless:true},outputDir:'tests/Browser/output/login',reporter:'list'});
