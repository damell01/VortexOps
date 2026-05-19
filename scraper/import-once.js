/**
 * One-shot CSV import from Whatnot spreadsheet export.
 *
 * Usage:
 *   node import-once.js --file=/path/to/whatnot-export.csv --show-date=2026-05-18 --streamer-ids=1,2
 *
 * Column mapping is read from column-map.json if it exists, otherwise it uses defaults.
 * Run `node map-columns.js --file=export.csv` first to generate a mapping interactively.
 */

require('dotenv').config();
const fs    = require('fs');
const path  = require('path');
const axios = require('axios');
const { parse } = require('csv-parse/sync');

const args = Object.fromEntries(
  process.argv.slice(2).map(a => a.replace('--', '').split('=')),
);

if (!args.file) {
  console.error('Usage: node import-once.js --file=export.csv [--show-date=YYYY-MM-DD] [--streamer-ids=1,2]');
  process.exit(1);
}

const csvPath    = path.resolve(args.file);
const showDate   = args['show-date'] || new Date().toISOString().slice(0, 10);
const streamerIds = (args['streamer-ids'] || '').split(',').map(Number).filter(Boolean);
const mapFile    = path.join(__dirname, 'column-map.json');

// Default column mapping — keys are VortexOps fields, values are CSV column headers
const DEFAULT_MAP = {
  item_name:      'Item Name',
  sku:            'SKU',
  quantity:       'Quantity',
  sale_price:     'Sale Price',
  buyer_username: 'Buyer Username',
  buyer_name:     'Buyer Name',
  order_id:       'Order ID',
  sale_type:      null,       // no equivalent, defaults to break_slot
  sold_at:        'Created At',
};

const columnMap = fs.existsSync(mapFile)
  ? JSON.parse(fs.readFileSync(mapFile, 'utf8'))
  : DEFAULT_MAP;

function mapRow(row) {
  const get = field => {
    const col = columnMap[field];
    return col ? (row[col] || null) : null;
  };

  return {
    item_name:      get('item_name') || 'Unknown',
    sku:            get('sku'),
    quantity:       parseFloat(get('quantity') || '1'),
    sale_price:     parseFloat((get('sale_price') || '0').replace(/[^0-9.]/g, '')),
    buyer_username: get('buyer_username'),
    buyer_name:     get('buyer_name'),
    order_id:       get('order_id'),
    sale_type:      get('sale_type') || 'break_slot',
    sold_at:        get('sold_at'),
  };
}

async function main() {
  const csv   = fs.readFileSync(csvPath, 'utf8');
  const rows  = parse(csv, { columns: true, skip_empty_lines: true, trim: true });
  const sales = rows.map(mapRow);

  console.log(`Parsed ${sales.length} row(s) from ${csvPath}`);
  console.log('Sample:', JSON.stringify(sales[0], null, 2));

  const payload = {
    show_date:    showDate,
    source:       'csv_import',
    streamer_ids: streamerIds,I wil probably 
    sales,
  };

  const { data } = await axios.post(`${process.env.VORTEXOPS_URL}/api/shows/import`, payload, {
    headers: { Authorization: `Bearer ${process.env.VORTEXOPS_API_TOKEN}` },
  });

  console.log('Import result:', data);
}

main().catch(err => {
  console.error('Import failed:', err.response?.data || err.message);
  process.exit(1);
});
