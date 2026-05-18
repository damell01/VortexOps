/**
 * Interactive column mapper.
 *
 * Reads the headers from a CSV export and lets you assign each VortexOps
 * field to a CSV column. Saves the mapping to column-map.json so that
 * import-once.js uses it automatically.
 *
 * Usage: node map-columns.js --file=export.csv
 *
 * For each VortexOps field, type the exact CSV column header, or press
 * Enter to skip (that field will be left blank).
 */

const fs    = require('fs');
const path  = require('path');
const { parse } = require('csv-parse/sync');
const readline = require('readline');

const args   = Object.fromEntries(process.argv.slice(2).map(a => a.replace('--','').split('=')));
const csvPath = path.resolve(args.file || '');

if (!fs.existsSync(csvPath)) {
  console.error('File not found: ' + csvPath);
  process.exit(1);
}

const csv     = fs.readFileSync(csvPath, 'utf8');
const rows    = parse(csv, { columns: true, skip_empty_lines: true, to_line: 2 });
const headers = Object.keys(rows[0] || {});

const FIELDS = [
  { key: 'item_name',      label: 'Item Name (required)',           required: true },
  { key: 'sku',            label: 'SKU / Product Code' },
  { key: 'quantity',       label: 'Quantity Sold' },
  { key: 'sale_price',     label: 'Sale Price / Revenue' },
  { key: 'buyer_username', label: 'Buyer Username (Whatnot)' },
  { key: 'buyer_name',     label: 'Buyer Full Name' },
  { key: 'order_id',       label: 'Order / Transaction ID' },
  { key: 'sold_at',        label: 'Sale Timestamp' },
];

const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
const ask = q => new Promise(res => rl.question(q, res));

async function main() {
  console.log('\nAvailable CSV columns:');
  headers.forEach((h, i) => console.log(`  ${i + 1}. ${h}`));
  console.log('\nFor each VortexOps field, enter the matching CSV column header (or press Enter to skip).\n');

  const map = {};

  for (const field of FIELDS) {
    const answer = await ask(`  ${field.label}: `);
    const trimmed = answer.trim();
    if (trimmed && headers.includes(trimmed)) {
      map[field.key] = trimmed;
    } else if (trimmed && !headers.includes(trimmed)) {
      console.log(`    ⚠  "${trimmed}" not found in headers — skipped.`);
      map[field.key] = null;
    } else {
      map[field.key] = null;
    }
  }

  const outPath = path.join(__dirname, 'column-map.json');
  fs.writeFileSync(outPath, JSON.stringify(map, null, 2));
  console.log(`\nColumn map saved to ${outPath}`);
  console.log('Run: node import-once.js --file=' + csvPath + ' to import.');
  rl.close();
}

main().catch(err => { console.error(err); rl.close(); });
