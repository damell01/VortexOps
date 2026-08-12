import { test } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

// Helper to take screenshots in both desktop and mobile
async function captureScreenshots(page, name) {
  // Desktop
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.screenshot({ path: `tests/Browser/screenshots/desktop/${name}.png`, fullPage: false });

  // Mobile
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: `tests/Browser/screenshots/mobile/${name}.png`, fullPage: false });
}

// Helper to login
async function login(page, email, password) {
  await page.goto(`${BASE_URL}/admin/login`);
  await page.waitForLoadState('networkidle');

  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(`**/admin**`, { timeout: 10000 }).catch(() => {});
  await page.waitForLoadState('networkidle');
}

test.describe('VortexOps Complete Feature Tour', () => {

  // ===== AUTHENTICATION =====
  test('01. Authentication - Login page', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '01-login-page');
  });

  test('02. Authentication - Login form filled', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('input[type="email"]', 'admin@example.com');
    await page.fill('input[type="password"]', 'password');
    await captureScreenshots(page, '02-login-filled');
  });

  // ===== DASHBOARD =====
  test('03. Dashboard - Admin overview', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    await captureScreenshots(page, '03-dashboard-overview');
  });

  test('04. Dashboard - With stats cards', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');
    // Hover over a stat card if visible
    const statCard = await page.$('[data-stat]');
    if (statCard) await statCard.hover();
    await captureScreenshots(page, '04-dashboard-stats');
  });

  test('05. Dashboard - Dark mode', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.evaluate(() => {
      document.documentElement.style.colorScheme = 'dark';
      document.documentElement.classList.add('dark');
    });
    await page.waitForTimeout(500);
    await captureScreenshots(page, '05-dashboard-dark');
  });

  // ===== SHOWS MODULE =====
  test('06. Shows - List view', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '06-shows-list');
  });

  test('07. Shows - Search functionality', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');
    const searchBox = await page.$('input[placeholder*="search"], input[placeholder*="Search"]');
    if (searchBox) {
      await searchBox.fill('test');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '07-shows-search');
  });

  test('08. Shows - Detail page (first show)', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');
    const firstRow = await page.$('[role="row"] >> nth=1');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '08-show-detail');
  });

  test('09. Shows - Filter panel', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');
    // Try to open filter
    const filterBtn = await page.$('button:has-text("Filter"), [data-filter]');
    if (filterBtn) {
      await filterBtn.click();
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '09-shows-filter');
  });

  // ===== STREAMERS MODULE =====
  test('10. Streamers - List view', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/streamers`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '10-streamers-list');
  });

  test('11. Streamers - Detail page (first streamer)', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/streamers`);
    await page.waitForLoadState('networkidle');
    const firstRow = await page.$('[role="row"] >> nth=1');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '11-streamer-detail');
  });

  test('12. Streamers - Payout configuration', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/streamers`);
    await page.waitForLoadState('networkidle');
    const firstRow = await page.$('[role="row"] >> nth=1');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      // Scroll down to see payout config
      await page.evaluate(() => window.scrollBy(0, 400));
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '12-streamer-payout-config');
  });

  // ===== PAYOUTS MODULE =====
  test('13. Payouts - List view', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/payouts`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '13-payouts-list');
  });

  test('14. Payouts - Grouped by Streamer', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/payouts`);
    await page.waitForLoadState('networkidle');
    const groupBtn = await page.$('select, [data-group]');
    if (groupBtn) {
      await groupBtn.click();
      await page.waitForTimeout(300);
    }
    await captureScreenshots(page, '14-payouts-grouped');
  });

  test('15. Payouts - Detail view', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/payouts`);
    await page.waitForLoadState('networkidle');
    const firstRow = await page.$('[role="row"] >> nth=1');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '15-payout-detail');
  });

  test('16. Payouts - Weekly batches', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/weekly-payout-batches`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await captureScreenshots(page, '16-weekly-batches');
  });

  // ===== INVENTORY MODULE =====
  test('17. Inventory Items - List', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-items`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '17-inventory-items-list');
  });

  test('18. Inventory Items - Search by SKU', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-items`);
    await page.waitForLoadState('networkidle');
    const searchBox = await page.$('input[placeholder*="search"], input[placeholder*="Search"]');
    if (searchBox) {
      await searchBox.fill('card');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '18-inventory-search');
  });

  test('19. Inventory Items - Detail view', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-items`);
    await page.waitForLoadState('networkidle');
    const firstRow = await page.$('[role="row"] >> nth=1');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '19-inventory-item-detail');
  });

  test('20. Inventory Locations - List', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-locations`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '20-inventory-locations');
  });

  test('21. Inventory Stock - List', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-stock`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '21-inventory-stock');
  });

  test('22. Inventory Movements - Audit log', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-movements`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '22-inventory-movements');
  });

  test('23. Inventory Scanner', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-scanner`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await captureScreenshots(page, '23-inventory-scanner');
  });

  // ===== RECEIVING MODULE =====
  test('24. Pallets - List view', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/pallets`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '24-pallets-list');
  });

  test('25. Pallets - Detail view', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/pallets`);
    await page.waitForLoadState('networkidle');
    const firstRow = await page.$('[role="row"] >> nth=1');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '25-pallet-detail');
  });

  test('26. Pallets - Manifest upload', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/pallets`);
    await page.waitForLoadState('networkidle');
    const createBtn = await page.$('a:has-text("Create"), button:has-text("Create")');
    if (createBtn) {
      await createBtn.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '26-pallet-create');
  });

  // ===== DEDUCTIONS MODULE =====
  test('27. Deduction Requests - List', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/deduction-requests`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '27-deduction-requests-list');
  });

  test('28. Deduction Requests - Detail (approval view)', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/deduction-requests`);
    await page.waitForLoadState('networkidle');
    const firstRow = await page.$('[role="row"] >> nth=1');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '28-deduction-request-detail');
  });

  // ===== VENDORS & CHANNELS =====
  test('29. Vendors - List', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/vendors`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '29-vendors-list');
  });

  test('30. Whatnot Channels - List', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/whatnot-channels`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await captureScreenshots(page, '30-whatnot-channels');
  });

  // ===== ADMIN FEATURES =====
  test('31. Users - List', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/users`);
    await page.waitForLoadState('networkidle');
    await captureScreenshots(page, '31-users-list');
  });

  test('32. Activity Log - Audit trail', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/activity-log`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await captureScreenshots(page, '32-activity-log');
  });

  test('33. Settings - Configuration', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/settings`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await captureScreenshots(page, '33-settings');
  });

  test('34. Log Viewer', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/log-viewer`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await captureScreenshots(page, '34-log-viewer');
  });

  // ===== FEEDBACK TICKETS =====
  test('35. Feedback Tickets - List', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/feedback-tickets`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await captureScreenshots(page, '35-feedback-tickets');
  });

  // ===== FORMS & MODALS =====
  test('36. Create Show Modal', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');
    const createBtn = await page.$('a:has-text("Create"), button:has-text("Create")');
    if (createBtn) {
      await createBtn.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }
    await captureScreenshots(page, '36-create-show-modal');
  });

  test('37. Edit Streamer Modal', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/streamers`);
    await page.waitForLoadState('networkidle');
    const firstRow = await page.$('[role="row"] >> nth=1');
    if (firstRow) {
      await firstRow.click();
      await page.waitForLoadState('networkidle');
      const editBtn = await page.$('button:has-text("Edit"), a:has-text("Edit")');
      if (editBtn) {
        await editBtn.click();
        await page.waitForTimeout(500);
      }
    }
    await captureScreenshots(page, '37-edit-streamer-modal');
  });

  // ===== NOTIFICATIONS =====
  test('38. Toast Notifications - Success', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.evaluate(() => {
      if (window.showToast) window.showToast('Success! Changes saved.', 'success');
    });
    await page.waitForTimeout(500);
    await captureScreenshots(page, '38-toast-success');
  });

  test('39. Toast Notifications - Error', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.evaluate(() => {
      if (window.showToast) window.showToast('Error: Something went wrong', 'error');
    });
    await page.waitForTimeout(500);
    await captureScreenshots(page, '39-toast-error');
  });

  test('40. Toast Notifications - Warning', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin`);
    await page.evaluate(() => {
      if (window.showToast) window.showToast('Warning: This action affects 5 payouts', 'warning');
    });
    await page.waitForTimeout(500);
    await captureScreenshots(page, '40-toast-warning');
  });

  // ===== RESPONSIVE DESIGN =====
  test('41. Mobile - Sidebar navigation', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');
    const sidebarToggle = await page.$('[aria-label*="menu"], button:has-text("Menu")');
    if (sidebarToggle) {
      await sidebarToggle.click();
      await page.waitForTimeout(500);
    }
    await page.screenshot({ path: 'tests/Browser/screenshots/mobile/41-sidebar-menu.png' });
  });

  test('42. Tablet view - Dashboard', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'tests/Browser/screenshots/desktop/42-tablet-dashboard.png' });
  });

  test('43. Mobile - Shows list', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'tests/Browser/screenshots/mobile/43-shows-mobile.png' });
  });

  test('44. Mobile - Inventory items', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE_URL}/admin/inventory-items`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'tests/Browser/screenshots/mobile/44-inventory-mobile.png' });
  });

  test('45. Mobile - Payouts', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE_URL}/admin/payouts`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'tests/Browser/screenshots/mobile/45-payouts-mobile.png' });
  });

  // ===== DARK MODE ON MULTIPLE PAGES =====
  test('46. Dark mode - Shows', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/shows`);
    await page.evaluate(() => {
      document.documentElement.style.colorScheme = 'dark';
      document.documentElement.classList.add('dark');
    });
    await page.waitForTimeout(500);
    await captureScreenshots(page, '46-shows-dark');
  });

  test('47. Dark mode - Inventory', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/inventory-items`);
    await page.evaluate(() => {
      document.documentElement.style.colorScheme = 'dark';
      document.documentElement.classList.add('dark');
    });
    await page.waitForTimeout(500);
    await captureScreenshots(page, '47-inventory-dark');
  });

  test('48. Dark mode - Payouts', async ({ page }) => {
    await login(page, 'dbellcreations@gmail.com', 'password');
    await page.goto(`${BASE_URL}/admin/payouts`);
    await page.evaluate(() => {
      document.documentElement.style.colorScheme = 'dark';
      document.documentElement.classList.add('dark');
    });
    await page.waitForTimeout(500);
    await captureScreenshots(page, '48-payouts-dark');
  });

  // ===== STREAMER VIEW =====
  test('49. Streamer Dashboard - Limited view', async ({ page }) => {
    // Would need to create a streamer account first
    // For now, just show the attempt
    try {
      await page.goto(`${BASE_URL}/admin/login`);
      await page.fill('input[type="email"]', 'streamer@example.com');
      await page.fill('input[type="password"]', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL(`**/admin**`, { timeout: 5000 }).catch(() => {});
      await page.goto(`${BASE_URL}/admin`);
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '49-streamer-dashboard');
    } catch {
      // Streamer account may not exist
    }
  });

  test('50. Streamer Shows - Scoped view', async ({ page }) => {
    try {
      await page.goto(`${BASE_URL}/admin/login`);
      await page.fill('input[type="email"]', 'streamer@example.com');
      await page.fill('input[type="password"]', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL(`**/admin**`, { timeout: 5000 }).catch(() => {});
      await page.goto(`${BASE_URL}/admin/shows`);
      await page.waitForLoadState('networkidle');
      await captureScreenshots(page, '50-streamer-shows');
    } catch {
      // Streamer account may not exist
    }
  });
});
