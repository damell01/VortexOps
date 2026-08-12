import { test, expect } from '@playwright/test';

// Screenshot tour: captures all major functionality across roles and screen sizes

const BASE_URL = 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = 'tests/Browser/screenshots';

// Test users by role
const USERS = {
  owner: { email: 'dbellcreations@gmail.com', password: 'password', name: 'Owner' },
  admin: { email: 'admin@example.com', password: 'password', name: 'Admin' },
  streamer: { email: 'streamer@example.com', password: 'password', name: 'Streamer' },
};

// Helper to take screenshots in both desktop and mobile
async function captureScreenshots(
  page,
  name,
  { desktop = true, mobile = true, waitFor = null, scroll = false }
) {
  if (desktop) {
    await page.setViewportSize({ width: 1440, height: 900 });
    if (waitFor) await page.waitForSelector(waitFor);
    if (scroll) {
      const fullHeight = await page.evaluate(() => document.body.scrollHeight);
      await page.setViewportSize({ width: 1440, height: fullHeight });
    }
    await page.screenshot({ path: `${SCREENSHOT_DIR}/desktop/${name}.png` });
  }

  if (mobile) {
    await page.setViewportSize({ width: 390, height: 844 });
    if (waitFor) await page.waitForSelector(waitFor);
    if (scroll) {
      const fullHeight = await page.evaluate(() => document.body.scrollHeight);
      await page.setViewportSize({ width: 390, height: fullHeight });
    }
    await page.screenshot({ path: `${SCREENSHOT_DIR}/mobile/${name}.png` });
  }
}

// Helper to login
async function login(page, user) {
  await page.goto(`${BASE_URL}/admin/login`);
  await page.fill('input[type="email"]', user.email);
  await page.fill('input[type="password"]', user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**');
}

test.describe('VortexOps UI Screenshot Tour', () => {
  test.beforeAll(async () => {
    // Ensure screenshot directories exist
    const fs = require('fs');
    [
      `${SCREENSHOT_DIR}/desktop`,
      `${SCREENSHOT_DIR}/mobile`,
      `${SCREENSHOT_DIR}/flows`,
    ].forEach((dir) => {
      if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
      }
    });
  });

  test('01: Authentication Pages', async ({ page }) => {
    // Login page - empty
    await page.goto(`${BASE_URL}/admin/login`);
    await captureScreenshots(page, '01-login-empty');

    // Login page - filled
    await page.fill('input[type="email"]', 'test@example.com');
    await page.fill('input[type="password"]', 'password');
    await captureScreenshots(page, '02-login-filled');
  });

  test('02: Owner Dashboard', async ({ page }) => {
    await login(page, USERS.owner);

    // Dashboard - overview
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '03-dashboard-owner', { scroll: true });

    // Stats visible
    await page.hover('[data-stat-card]');
    await captureScreenshots(page, '04-dashboard-stats');

    // Dark mode toggle
    await page.click('[data-dark-mode-toggle]');
    await page.waitForTimeout(500);
    await captureScreenshots(page, '05-dashboard-dark');
  });

  test('03: Shows Resource', async ({ page }) => {
    await login(page, USERS.owner);

    // Shows list
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '10-shows-list', { scroll: true });

    // Search/filter
    await page.fill('input[placeholder*="Search"]', 'test');
    await page.waitForTimeout(500);
    await captureScreenshots(page, '11-shows-search');

    // Show detail
    const firstRow = await page.$('[role="row"]');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '12-show-detail', { scroll: true });
    }
  });

  test('04: Streamers Resource', async ({ page }) => {
    await login(page, USERS.owner);

    // Streamers list
    await page.goto(`${BASE_URL}/admin/streamers`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '20-streamers-list', { scroll: true });

    // Streamer detail
    const firstRow = await page.$('[role="row"]');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '21-streamer-detail', { scroll: true });
    }
  });

  test('05: Payouts Resource', async ({ page }) => {
    await login(page, USERS.owner);

    // Payouts list
    await page.goto(`${BASE_URL}/admin/payouts`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '30-payouts-list', { scroll: true });

    // Payout filters
    await page.click('[data-filter-button]');
    await page.waitForTimeout(300);
    await captureScreenshots(page, '31-payouts-filters');

    // Payout detail
    const firstRow = await page.$('[role="row"]');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '32-payout-detail', { scroll: true });
    }
  });

  test('06: Inventory Resources', async ({ page }) => {
    await login(page, USERS.owner);

    // Inventory items
    await page.goto(`${BASE_URL}/admin/inventory-items`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '40-inventory-items', { scroll: true });

    // Inventory locations
    await page.goto(`${BASE_URL}/admin/inventory-locations`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '41-inventory-locations', { scroll: true });

    // Inventory stock
    await page.goto(`${BASE_URL}/admin/inventory-stock`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '42-inventory-stock', { scroll: true });

    // Inventory movements
    await page.goto(`${BASE_URL}/admin/inventory-movements`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '43-inventory-movements', { scroll: true });
  });

  test('07: Pallets & Receiving', async ({ page }) => {
    await login(page, USERS.owner);

    // Pallets list
    await page.goto(`${BASE_URL}/admin/pallets`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '50-pallets-list', { scroll: true });

    // Pallet detail
    const firstRow = await page.$('[role="row"]');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '51-pallet-detail', { scroll: true });
    }
  });

  test('08: Vendors & Settings', async ({ page }) => {
    await login(page, USERS.owner);

    // Vendors
    await page.goto(`${BASE_URL}/admin/vendors`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '60-vendors-list', { scroll: true });

    // Settings (if accessible)
    try {
      await page.goto(`${BASE_URL}/admin/settings`);
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '61-settings', { scroll: true });
    } catch {
      // Settings might not be available
    }
  });

  test('09: Admin User Experience', async ({ page }) => {
    // Test with admin user if exists
    try {
      await login(page, USERS.admin);
      await page.goto(`${BASE_URL}/admin`);
      await captureScreenshots(page, '70-dashboard-admin', { scroll: true });
    } catch {
      // Admin account may not exist
    }
  });

  test('10: Streamer User Experience', async ({ page }) => {
    // Test with streamer user if exists
    try {
      await login(page, USERS.streamer);
      await page.goto(`${BASE_URL}/admin`);
      await captureScreenshots(page, '80-dashboard-streamer', { scroll: true });

      // Streamer shows
      await page.goto(`${BASE_URL}/admin/shows`);
      await captureScreenshots(page, '81-shows-streamer-view', { scroll: true });
    } catch {
      // Streamer account may not exist
    }
  });

  test('11: Modals & Actions', async ({ page }) => {
    await login(page, USERS.owner);

    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');

    // Create action
    const createBtn = await page.$('[data-action="create"]');
    if (createBtn) {
      await createBtn.click();
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '90-create-modal');
    }

    // Edit action
    const editBtn = await page.$('[data-action="edit"]');
    if (editBtn) {
      await editBtn.click();
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '91-edit-modal');
    }

    // Close modal
    await page.press('Escape');
  });

  test('12: Responsive Design Tests', async ({ page }) => {
    await login(page, USERS.owner);

    // Tablet view
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto(`${BASE_URL}/admin`);
    await captureScreenshots(page, '95-tablet-dashboard', { desktop: false, mobile: false });

    // Small mobile
    await page.setViewportSize({ width: 320, height: 667 });
    await captureScreenshots(page, '96-mobile-small-dashboard', { desktop: false, mobile: false });
  });

  test('13: Toast Notifications', async ({ page }) => {
    await login(page, USERS.owner);

    await page.goto(`${BASE_URL}/admin`);

    // Trigger a toast (via console)
    await page.evaluate(() => {
      if (typeof window !== 'undefined' && (window as any).showToast) {
        (window as any).showToast('Success! Changes saved.', 'success');
      }
    });

    await page.waitForTimeout(500);
    await captureScreenshots(page, '97-toast-notification');

    // Error toast
    await page.evaluate(() => {
      if (typeof window !== 'undefined' && (window as any).showToast) {
        (window as any).showToast('Error: Something went wrong', 'error');
      }
    });

    await page.waitForTimeout(500);
    await captureScreenshots(page, '98-toast-error');
  });

  test('14: Dark Mode Across Pages', async ({ page }) => {
    await login(page, USERS.owner);

    // Enable dark mode
    await page.evaluate(() => {
      document.documentElement.style.colorScheme = 'dark';
    });

    // Dashboard dark
    await page.goto(`${BASE_URL}/admin`);
    await captureScreenshots(page, '99-dark-mode-dashboard', { scroll: true });

    // Shows dark
    await page.goto(`${BASE_URL}/admin/shows`);
    await captureScreenshots(page, '99b-dark-mode-shows');
  });
});
