import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

// Helper to take screenshots
async function screenshot(page, name) {
  const dir = `tests/Browser/screenshots`;
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.screenshot({ path: `${dir}/desktop/${name}.png`, fullPage: false });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: `${dir}/mobile/${name}.png`, fullPage: false });
}

// Helper to login
async function login(page, email, password) {
  await page.goto(`${BASE_URL}/admin/login`);
  await page.waitForLoadState('networkidle');

  const emailInput = await page.$('input[type="email"]');
  const passwordInput = await page.$('input[type="password"]');

  if (emailInput && passwordInput) {
    await emailInput.fill(email);
    await passwordInput.fill(password);

    const submitBtn = await page.$('button[type="submit"]');
    if (submitBtn) {
      await submitBtn.click();
      await page.waitForURL(`**/admin**`, { timeout: 10000 }).catch(() => {});
      await page.waitForLoadState('networkidle');
    }
  }
}

test.describe('VortexOps Screenshot Tour', () => {
  test('Login page', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    await page.waitForLoadState('networkidle');
    await screenshot(page, '01-login-page');
  });

  test('Login with credentials', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    await page.waitForLoadState('networkidle');

    const emailInput = await page.$('input[type="email"]');
    const passwordInput = await page.$('input[type="password"]');

    if (emailInput && passwordInput) {
      await emailInput.fill('dbellcreations@gmail.com');
      await passwordInput.fill('password');
      await screenshot(page, '02-login-filled');
    }
  });

  test('Admin dashboard', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    await screenshot(page, '03-dashboard');
  });

  test('Shows resource list', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    await screenshot(page, '10-shows-list');
  });

  test('Streamers resource list', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/streamers`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    await screenshot(page, '20-streamers-list');
  });

  test('Payouts resource list', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/payouts`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    await screenshot(page, '30-payouts-list');
  });

  test('Inventory items', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-items`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    await screenshot(page, '40-inventory-items');
  });

  test('Vendors list', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/vendors`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    await screenshot(page, '60-vendors');
  });

  test('Pallets list', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/pallets`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    await screenshot(page, '50-pallets');
  });

  test('Dark mode dashboard', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');

    // Toggle dark mode
    await page.evaluate(() => {
      document.documentElement.style.colorScheme = 'dark';
      document.documentElement.classList.add('dark');
    });

    await page.waitForTimeout(500);
    await screenshot(page, '04-dashboard-dark');
  });

  test('Mobile responsive', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: 'tests/Browser/screenshots/mobile/05-responsive-mobile.png' });
  });
});
