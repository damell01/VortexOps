/**
 * VortexOps — Whatnot Show Watcher
 *
 * Polls the Whatnot seller dashboard on a schedule, detects newly completed
 * shows, scrapes the available data, and posts it to the VortexOps API.
 *
 * Run: node watch.js
 *
 * NOTE: Whatnot's seller dashboard layout may change. When it does, update
 * the selectors in ScraperClient. The data model in VortexOps does not need
 * to change — just the selectors here.
 */

require('dotenv').config();
const { chromium } = require('playwright');
const axios = require('axios');
const winston = require('winston');

const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.printf(({ timestamp, level, message }) => `[${timestamp}] ${level.toUpperCase()} ${message}`)
  ),
  transports: [
    new winston.transports.Console(),
    new winston.transports.File({ filename: 'scraper.log' }),
  ],
});

const config = {
  vortexopsUrl: process.env.VORTEXOPS_URL || 'http://localhost:8000',
  apiToken: process.env.VORTEXOPS_API_TOKEN,
  whatnotEmail: process.env.WHATNOT_EMAIL,
  whatnotPassword: process.env.WHATNOT_PASSWORD,
  sellerUsername: process.env.WHATNOT_SELLER_USERNAME,
  pollIntervalMs: parseInt(process.env.POLL_INTERVAL_MS || '900000', 10),
  headless: process.env.HEADLESS !== 'false',
  defaultStreamerIds: (process.env.DEFAULT_STREAMER_IDS || '')
    .split(',')
    .map(s => parseInt(s.trim(), 10))
    .filter(Boolean),
};

// Track which show IDs we've already imported this session
const importedShows = new Set();

class WhatnotScraper {
  constructor(page) {
    this.page = page;
  }

  async login() {
    logger.info('Logging into Whatnot...');
    await this.page.goto('https://www.whatnot.com/login');
    await this.page.waitForLoadState('networkidle');

    // Fill email + password — update selectors if Whatnot changes their login form
    await this.page.getByLabel(/email/i).fill(config.whatnotEmail);
    await this.page.getByLabel(/password/i).fill(config.whatnotPassword);
    await this.page.getByRole('button', { name: /sign in|log in/i }).click();
    await this.page.waitForURL(/whatnot\.com\/(home|feed|@)/, { timeout: 15000 });
    logger.info('Login successful.');
  }

  async fetchCompletedShows() {
    // Navigate to the seller's show history
    const historyUrl = `https://www.whatnot.com/user/${config.sellerUsername}/shows`;
    logger.info(`Checking show history at ${historyUrl}`);
    await this.page.goto(historyUrl);
    await this.page.waitForLoadState('networkidle');

    // -----------------------------------------------------------------------
    // SELECTOR NOTE: These selectors are best guesses based on Whatnot's
    // public profile structure. Update them after inspecting the actual page.
    // -----------------------------------------------------------------------
    const shows = await this.page.evaluate(() => {
      const items = [];
      document.querySelectorAll('[data-testid="show-card"], .show-card, article').forEach(card => {
        const titleEl  = card.querySelector('h2, h3, [class*="title"]');
        const dateEl   = card.querySelector('time, [class*="date"], [class*="time"]');
        const statusEl = card.querySelector('[class*="status"], [class*="ended"], [class*="live"]');
        const linkEl   = card.querySelector('a[href*="/show/"], a[href*="/live/"]');

        if (!titleEl) return;

        const status = (statusEl?.textContent || '').toLowerCase();
        // Only pick up ended/completed shows
        if (!status.includes('ended') && !status.includes('completed') && !status.includes('past')) return;

        const href   = linkEl?.getAttribute('href') || '';
        const showId = href.match(/\/(show|live)\/([^/?]+)/)?.[2] || null;

        items.push({
          whatnot_show_id: showId,
          title: titleEl.textContent.trim(),
          show_date: dateEl?.getAttribute('datetime') || dateEl?.textContent.trim() || null,
          href,
        });
      });
      return items;
    });

    return shows.filter(s => s.whatnot_show_id && !importedShows.has(s.whatnot_show_id));
  }

  async scrapeShowDetail(showInfo) {
    logger.info(`Scraping show detail: ${showInfo.title} (${showInfo.whatnot_show_id})`);

    const showUrl = showInfo.href.startsWith('http')
      ? showInfo.href
      : `https://www.whatnot.com${showInfo.href}`;

    await this.page.goto(showUrl);
    await this.page.waitForLoadState('networkidle');

    // -----------------------------------------------------------------------
    // SELECTOR NOTE: Whatnot's post-show summary page structure is unknown
    // until we inspect it. The code below captures whatever the page renders.
    // Update selectors once you've seen a real ended-show page.
    // -----------------------------------------------------------------------
    const detail = await this.page.evaluate(() => {
      const sales = [];
      // Try to find a sales/items table
      document.querySelectorAll('tr, [class*="sale-row"], [class*="item-row"]').forEach(row => {
        const cells   = Array.from(row.querySelectorAll('td, [class*="cell"]'));
        if (cells.length < 2) return;
        sales.push({
          item_name:      cells[0]?.textContent.trim() || 'Unknown',
          sale_price:     parseFloat((cells[1]?.textContent || '0').replace(/[^0-9.]/g, '')) || 0,
          buyer_username: cells[2]?.textContent.trim() || null,
          quantity:       parseFloat((cells[3]?.textContent || '1').replace(/[^0-9.]/g, '')) || 1,
          sale_type:      'break_slot',
        });
      });

      // Try to capture financial summary totals
      const getText = selector => document.querySelector(selector)?.textContent.trim() || null;
      const parseAmt = str => str ? parseFloat(str.replace(/[^0-9.]/g, '')) : 0;

      const grossText    = getText('[class*="gross"], [class*="total-sales"]');
      const feeText      = getText('[class*="fee"], [class*="commission"]');
      const shippingText = getText('[class*="shipping"]');
      const tipsText     = getText('[class*="tip"]');

      return {
        sales,
        financials: {
          gross_sales:        parseAmt(grossText),
          platform_fee_amount: parseAmt(feeText),
          shipping_collected: parseAmt(shippingText),
          tips_collected:     parseAmt(tipsText),
        },
        raw_page_text: document.body.innerText.slice(0, 5000),
      };
    });

    return detail;
  }
}

class VortexOpsClient {
  constructor() {
    this.http = axios.create({
      baseURL: `${config.vortexopsUrl}/api`,
      headers: { Authorization: `Bearer ${config.apiToken}` },
      timeout: 30000,
    });
  }

  async getChannels() {
    const { data } = await this.http.get('/channels');
    return data;
  }

  async getStreamers() {
    const { data } = await this.http.get('/streamers');
    return data;
  }

  async importShow(payload) {
    const { data } = await this.http.post('/shows/import', payload);
    return data;
  }
}

async function runOnce(browser, vortexops) {
  const page    = await browser.newPage();
  const scraper = new WhatnotScraper(page);

  try {
    await scraper.login();
    const shows = await scraper.fetchCompletedShows();
    logger.info(`Found ${shows.length} new completed show(s) to import.`);

    for (const showInfo of shows) {
      try {
        const detail = await scraper.scrapeShowDetail(showInfo);

        const payload = {
          title:       showInfo.title,
          show_date:   showInfo.show_date,
          source:      'scraper',
          streamer_ids: config.defaultStreamerIds,
          sales:       detail.sales,
          financials:  detail.financials,
          raw_data: {
            whatnot_show_id: showInfo.whatnot_show_id,
            page_excerpt:    detail.raw_page_text,
          },
        };

        const result = await vortexops.importShow(payload);
        logger.info(`Imported: ${showInfo.title} → VortexOps Show #${result.show_id}`);
        importedShows.add(showInfo.whatnot_show_id);
      } catch (err) {
        logger.error(`Failed to import show "${showInfo.title}": ${err.message}`);
      }
    }
  } finally {
    await page.close();
  }
}

async function main() {
  if (!config.apiToken) {
    logger.error('VORTEXOPS_API_TOKEN is not set. Aborting.');
    process.exit(1);
  }
  if (!config.whatnotEmail || !config.whatnotPassword) {
    logger.error('WHATNOT_EMAIL / WHATNOT_PASSWORD not set. Aborting.');
    process.exit(1);
  }

  const vortexops = new VortexOpsClient();
  const browser   = await chromium.launch({ headless: config.headless });

  logger.info(`VortexOps Whatnot watcher started. Poll interval: ${config.pollIntervalMs / 1000}s`);

  const poll = async () => {
    try {
      await runOnce(browser, vortexops);
    } catch (err) {
      logger.error('Poll cycle error: ' + err.message);
    }
    setTimeout(poll, config.pollIntervalMs);
  };

  await poll();
}

main().catch(err => {
  logger.error('Fatal: ' + err.message);
  process.exit(1);
});
