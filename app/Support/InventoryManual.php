<?php

namespace App\Support;

/**
 * The printed inventory manual, as content rather than markup.
 *
 * Kept out of the Blade view so the words can be corrected without touching
 * the page furniture, and so the screenshot each step refers to is declared
 * next to the step it belongs to — a manual whose pictures drift out of step
 * with its instructions is worse than one with no pictures.
 */
class InventoryManual
{
    public const IMAGE_DIR = 'guide/manual';

    public static function title(): string
    {
        return 'Inventory Handbook';
    }

    public static function subtitle(): string
    {
        return 'Adding stock, moving it, receiving pallets and scanning — the whole job, in order.';
    }

    /**
     * @return array<int, array{
     *   title: string, blurb: string,
     *   steps: array<int, array{title: string, where: ?string, body: array<int,string>, shot: ?string, note: ?string}>
     * }>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => 'Before you start',
                'icon'  => 'heroicon-o-map',
                'blurb' => 'Two lists that everything else refers to. Get them right once and the rest of '
                    . 'the handbook works; get them wrong and every count afterwards is arguing with you.',
                'steps' => [
                    [
                        'title' => 'Create your locations',
                        'where' => 'Inventory → Locations',
                        'body'  => [
                            'A location is a physical place stock can be: a shelf, a streamer\'s setup, the returns bin, the damaged pile.',
                            'Every screen that moves stock asks which location. There is no "unassigned" — stock is always somewhere.',
                            'You need at least one of type <b>Main Storage</b>. If you have been looking for "the warehouse", that is its name here.',
                        ],
                        'shot'  => 'locations.png',
                        'fields' => [
                            ['Name', 'The place, with the streamer it belongs to underneath when it is a streamer location.'],
                            ['Type', 'Colour-coded badge. Main Storage, Streamer Inventory, Returned, Damaged, Fulfillment or Other — and it governs which screens can pick this location.'],
                            ['Item Count', 'How many distinct items are held here. Not units — records.'],
                            ['Status', 'Active or Inactive. Inactive locations vanish from every picker and keep their history.'],
                            ['Streamer / Channel', 'Hidden by default; turn them on with the columns button when you want to see whose stock is where.'],
                            ['Export CSV', 'The whole location list as a file, for a stocktake sheet or an audit.'],
                        ],
                        'note'  => 'Admin only. Do this before anything else — a receipt booked to the wrong location is a count that will not reconcile weeks later.',
                    ],
                    [
                        'title' => 'What a location record holds',
                        'where' => 'Inventory → Locations → New location',
                        'body'  => [
                            'Six fields, and the <b>type</b> is the one that matters: it decides which screens offer the location at all.',
                            'Mark Damaged only lists locations of type <b>Damaged</b>. Move to Returns only lists <b>Returned</b>. A streamer\'s own stock only appears under a <b>Streamer Inventory</b> location tied to them.',
                            'So a location whose type is wrong looks like a missing feature: the dropdown you need is simply empty.',
                        ],
                        'shot'  => 'location-create.png',
                        'fields' => [
                            ['Name', 'What people call the place out loud — "Shelf A", "Back Room". This is what every location dropdown shows.'],
                            ['Type', 'Main Storage, Streamer Inventory, Returned, Damaged, Fulfillment or Other. Decides which screens can pick it, so it is not cosmetic.'],
                            ['Assigned Streamer', 'Only appears for Streamer Inventory. Ties the location to one person, which is what makes their own stock screen show it.'],
                            ['Status', 'Active or Inactive. Inactive keeps the record and its history but takes it out of every picker — the way to retire a shelf without losing what happened on it.'],
                            ['Channel', 'Optional. Groups this location\'s stock under a Whatnot channel for reporting.'],
                            ['Notes', 'Internal. Where it physically is, who has the key, anything the name cannot carry.'],
                        ],
                        'note'  => 'You cannot delete a location that has ever held stock, because that would orphan its movement history. Set it Inactive instead.',
                    ],
                    [
                        'title' => 'Add your vendors',
                        'where' => 'Inventory → Vendors',
                        'body'  => [
                            'A vendor is who you buy from. Pallets are booked against one, which is how cost history ends up attributable.',
                            'You can add a vendor while creating a pallet, but doing it here first keeps the names consistent.',
                        ],
                        'shot'  => 'vendors.png',
                        'fields' => [
                            ['Name', 'The supplier. Searchable, and the name that appears on every pallet.'],
                            ['Contact / Email / Phone', 'Hidden by default — turn them on when you are chasing a delivery. Email copies with one click.'],
                            ['Status', 'Active or Inactive. Inactive keeps the history and takes them out of the pallet vendor picker.'],
                            ['Pallets', 'How many deliveries you have booked against them. Sortable, so the list doubles as "who do we actually buy from".'],
                            ['View / Edit / Delete', 'Delete is refused while any pallet references them — the button says so rather than failing afterwards.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'What a vendor record holds',
                        'where' => 'Inventory → Vendors → New vendor',
                        'body'  => [
                            'Name is the only thing required. Everything else is there so the person chasing a delivery does not have to go looking for an email address in someone\'s inbox.',
                            '<b>Lead Time</b> is the one field that does work elsewhere: Product Insights uses it to say when to reorder, not just how much.',
                        ],
                        'shot'  => 'vendor-create.png',
                        'fields' => [
                            ['Name', 'Required. Keep it the same across pallets — two spellings of one supplier splits its cost history in two.'],
                            ['Contact Name', 'Who you actually deal with there.'],
                            ['Email / Phone / Website', 'For chasing a delivery. Email is copyable straight from the list.'],
                            ['Account #', 'Your account number with them, for referencing on an order.'],
                            ['Lead Time (days)', 'Typical days from order to delivery. Feeds the reorder suggestions on Product Insights — the difference between "you are low" and "order it today".'],
                            ['Status', 'Active or Inactive. Inactive keeps the history and removes them from pickers.'],
                            ['Notes', 'Terms, minimums, anything you would otherwise have to remember.'],
                        ],
                        'note'  => null,
                    ],
                ],
            ],

            [
                'title' => 'The catalogue',
                'icon'  => 'heroicon-o-cube',
                'blurb' => 'Every physical thing you stock needs one record, and exactly one. Duplicate records '
                    . 'are the commonest cause of a count that looks wrong for no reason.',
                'steps' => [
                    [
                        'title' => 'Every button on the inventory screen',
                        'where' => 'Inventory → All Inventory',
                        'body'  => [
                            'This is the screen most days start on, so it is worth knowing what each control is for before you need it in a hurry.',
                            'Across the top: <b>Quick Scan</b> for something already in your hand, <b>Receive Shipment</b> when a pallet has landed, <b>Quick Add</b> for a new item in twenty seconds, <b>Add Item</b> for the full form, and <b>More</b> for the exports.',
                            'The four tiles underneath do the same jobs in words rather than buttons, plus <b>Locations</b>. The counters below them — Total, In Stock, Low Stock, Out of Stock — are clickable shortcuts into the list, already filtered.',
                        ],
                        'shot'  => 'inventory-hub.png',
                        'fields' => [
                            ['Quick Scan', 'Opens the scanner. Look something up, add stock to it, or receive it against a pallet, depending on the mode you pick there.'],
                            ['Receive Shipment', 'Jumps to the pallets list — the start of the receiving flow, not a form of its own.'],
                            ['Quick Add', 'The short create form: name, cost, code. For when the box is in your hand and the details can follow.'],
                            ['Add Item', 'The full create form, with costs, sale target, container contents and opening stock.'],
                            ['More', 'View report, Download PDF, Export to Excel — the three ways to get this list out of the app.'],
                            ['Total / In Stock / Low Stock / Out of Stock', 'Live counts of the whole catalogue. Low Stock means at or under an item\'s reorder level; items with no reorder level set never appear there.'],
                        ],
                        'note'  => 'Which of these you see depends on your role. A missing button is a permission, not a fault — Roles & Permissions decides it.',
                    ],
                    [
                        'title' => 'Find out whether it already exists',
                        'where' => 'Inventory → All Inventory',
                        'body'  => [
                            'Search the name, SKU or barcode before creating anything. The search covers all three.',
                            'This is also the list you work from day to day: stock on hand, status, cost and sale target per item.',
                        ],
                        'shot'  => 'items-list.png',
                        'fields' => [
                            ['Item', 'Name, with the description underneath. Searchable along with SKU and barcode.'],
                            ['SKU', 'Your own code, with the barcode underneath it when one is on file.'],
                            ['Type', 'Item or Container. A container is something you can Break Case into its contents.'],
                            ['Status', 'In Stock, Low Stock or Out of Stock — worked out from quantity against the reorder level, not typed.'],
                            ['Qty on Hand', 'Units across every location. Open the item to see the split.'],
                            ['Reorder Level', 'The number that decides Low Stock. Blank means this item never raises one.'],
                            ['Avg Cost', 'Weighted average from receiving history — what you have actually been paying.'],
                            ['Sale Target', 'What it should sell for, if you have set one.'],
                            ['Margin Potential', 'Sale target minus real cost, with the percentage underneath. Blank when either half is missing.'],
                            ['Inventory Value', 'Quantity × average cost. What that row is worth on the shelf.'],
                            ['Active / Barcode / Added / Updated', 'Off by default. Turn them on from the columns button when you need them.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Narrow the list when it gets long',
                        'where' => 'Inventory → All Inventory → Filters',
                        'body'  => [
                            'Filters open in a dialog so you can see all of them at once. Set what you need and press <b>Apply filters</b>.',
                            'The useful ones: <b>Low Stock Only</b>, <b>Missing a sale target</b>, <b>Margin under 25%</b>, and <b>Location</b> when you want one shelf.',
                            'Advanced Filters at the bottom builds compound rules if the standard ones are not enough.',
                        ],
                        'shot'  => 'items-filters.png',
                        'fields' => [
                            ['Type', 'Item or container.'],
                            ['Barcode used by both a case and a single', 'Finds the codes that will scan into the wrong record. Worth running after a big receiving week.'],
                            ['Location', 'Only items with stock at one place. This is the filter for working a single shelf.'],
                            ['Low Stock Only', 'At or under the reorder level. Items with no reorder level set are never included.'],
                            ['My inventory only', 'For a streamer: the stock in your own location rather than the whole catalogue.'],
                            ['Missing a sale target', 'Everything that cannot show a margin because nobody has said what it should sell for.'],
                            ['Margin under 25%', 'The thin end of the catalogue, priced against real cost.'],
                            ['Active Only', 'Hides retired items. On by default on most screens.'],
                            ['Advanced Filters', 'Compound rules — item name, SKU, barcode, avg cost, sale target, reorder level, active — combined with AND or OR when the standard filters are not enough.'],
                        ],
                        'note'  => 'Nothing changes until you press Apply. Closing the dialog without applying leaves the list as it was.',
                    ],
                    [
                        'title' => 'Choose which columns you see',
                        'where' => 'Inventory → All Inventory → the columns button',
                        'body'  => [
                            'The button beside Filters opens the column list. Everything switched off there still exists — it is just not on screen.',
                            'Worth turning on when you are doing money work: <b>Avg Cost</b>, <b>Sale Target</b>, <b>Margin Potential</b> and <b>Inventory Value</b>. Worth turning off when you are stocktaking: everything except name, location and quantity.',
                            'Your choice sticks per person, so the screen you leave is the screen you come back to.',
                        ],
                        'shot'  => 'items-columns.png',
                        'fields' => [
                            ['Item / SKU / Type / Status', 'The four that are on by default. Turning any of them off is for a stocktake sheet, not for everyday use.'],
                            ['Qty on Hand', 'Units across all locations.'],
                            ['Added / Updated', 'When the record was created and last touched. Useful for finding what someone entered yesterday.'],
                            ['Category', 'On when you are working one product line at a time.'],
                            ['Reorder Level', 'Turn it on beside Qty on Hand and the list explains its own Low Stock badges.'],
                            ['Avg Cost / Sale Target / Margin Potential / Inventory Value', 'The money columns. Turn all four on together — each is meaningless without the others.'],
                            ['Active', 'Shows retired items as retired rather than just missing.'],
                            ['Barcode', 'Worth turning on when you are attaching codes, so you can see which rows still have none.'],
                            ['Reset', 'Puts the columns back to the default set.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Do something to many items at once',
                        'where' => 'Inventory → All Inventory → tick the rows',
                        'body'  => [
                            'Ticking rows puts a bulk actions button at the bottom of the list. Two things live there: <b>Export</b> and <b>Delete</b>.',
                            'Export writes what you have selected, with the columns you currently have on, so filter and choose columns first and the export comes out right.',
                        ],
                        'shot'  => 'items-bulk.png',
                        'fields' => [
                            ['Row checkbox', 'Selects one row. The bar appears as soon as anything is ticked.'],
                            ['Select all 172 / Deselect all', 'Select all reaches past the page you are looking at to the whole filtered list — which is the point, and the risk.'],
                            ['Export', 'Writes the selected rows out with the columns you currently have switched on.'],
                            ['Delete selected', 'Deletes every ticked record after one confirmation. There is no per-item prompt.'],
                        ],
                        'note'  => 'Bulk delete is the one genuinely destructive control in the module, and it does not ask twice per item. Anything with stock or history should be set Inactive instead.',
                    ],
                    [
                        'title' => 'Get the list out of the app',
                        'where' => 'Inventory → All Inventory → More',
                        'body'  => [
                            '<b>View report</b> opens the printable stock report on screen. <b>Download PDF</b> is that same report as a file. <b>Export to Excel</b> gives you the rows to work with in a spreadsheet.',
                            'All three follow the filters you have applied, so a filtered list exports as a filtered list.',
                        ],
                        'shot'  => 'inventory-more.png',
                        'fields' => [
                            ['View report', 'The printable stock report on screen.'],
                            ['Download PDF', 'The same report as a file.'],
                            ['Export to Excel', 'The rows as a spreadsheet, for anything the app does not do.'],
                            ['Filters apply to all three', 'A filtered list exports as a filtered list — so filter first, then export.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Add an item properly',
                        'where' => 'Inventory → All Inventory → Add Item',
                        'body'  => [
                            'The full form: identity, costs, container settings, notes.',
                            '<b>List Unit Cost</b> is the fallback price used until real receipts exist. <b>Sale Price / Target</b> is what it should sell for.',
                            'The <b>Margin Potential</b> line answers as you type. It is not stored — it is recalculated from the weighted average cost every time you look, so it never goes stale after a receipt.',
                            'Mark it a <b>container</b> only if it holds other items you will break it into. A booster box is a box to a person, but a container here means something with recorded contents.',
                        ],
                        'shot'  => 'item-create.png',
                        'more'  => ['item-create-2.png', 'item-create-3.png'],
                        'fields' => [
                            ['Photo', 'Optional. Shows as the item\'s thumbnail everywhere it is listed; without one the brand mark stands in.'],
                            ['Item Name', 'Required. What you would call it out loud. This is what everyone searches by, so match how the box is labelled rather than how the vendor invoices it.'],
                            ['Active', 'On by default. Turning it off keeps the record and its history but hides it from pickers and the default list — for something you no longer stock.'],
                            ['SKU', 'Generated automatically if you leave it. Your own code for the item; unique across the catalogue.'],
                            ['Barcode / UPC', 'The code on the box. Unique — if another item already has it you are told which. This is what every scan resolves against.'],
                            ['This is a container / single item', 'A container is something with recorded contents that you will break into its parts. A booster box is a box to a person, but leave this on "single item" unless you are going to record what is inside and use Break Case.'],
                            ['Contents (containers only)', 'One row per thing inside: which item, and how many per container. This is what Break Case converts one unit into.'],
                            ['Category', 'Free grouping used by filters and reports. Pick an existing one where you can — a category used once is a category nobody filters by.'],
                            ['Preferred Vendor', 'Who you usually buy it from. Does not restrict anything; it is a default and a reporting dimension.'],
                            ['List Unit Cost', 'Required, defaults to 0. The fallback cost used until real receipts exist. Margin is measured against this until a pallet is received.'],
                            ['Avg Cost', 'Maintained by the system from receiving history — the weighted average of what you actually paid. Editable, but you are overriding an arithmetic result, so only do it to correct a known error.'],
                            ['Sale Price / Target', 'What it should sell for. Margin Potential underneath answers as you type: target minus whichever cost is real.'],
                            ['Reorder Level', 'Units. When stock on hand drops to or below this, the item shows as Low Stock and appears in the low-stock filter and alerts. Leave blank for no alert.'],
                            ['Description', 'Free text shown under the name in lists.'],
                            ['Notes', 'Internal. Not shown to streamers.'],
                            ['Initial stock (location, quantity, cost)', 'Optional shortcut on create only: books opening stock in one step instead of creating the item and then adding stock. The cost you give here becomes the starting weighted average.'],
                        ],
                        'note'  => 'Leave the sale target blank if you do not know it. The item drops out of margin figures until it has one, and the "Missing a sale target" filter finds it again.',
                    ],
                    [
                        'title' => 'Or add one in twenty seconds',
                        'where' => 'Inventory → Quick Add',
                        'body'  => [
                            'For when you have a box in one hand: name, cost, code, done.',
                            'Everything else can be filled in later by editing the item.',
                        ],
                        'shot'  => 'item-quick-add.png',
                        'fields' => [
                            ['Item name', 'The only field you must fill in. Everything else can follow later.'],
                            ['Barcode / UPC', 'Type it or press Scan to use the camera. Attaching it now is what makes the box scannable everywhere afterwards.'],
                            ['SKU', 'Optional. Generated for you if you leave it blank.'],
                            ['Category', 'Optional, with your existing categories offered as you type.'],
                            ['Preferred vendor', 'Optional. Who you usually buy it from.'],
                            ['Starting location', 'Optional. Choose one and the item is created with stock already in it; leave it on "No starting stock" to create the record only.'],
                            ['Quantity', 'How many units go into that location now.'],
                            ['Unit cost', 'What each one cost. This becomes the item\'s opening weighted average.'],
                            ['Cost for this starting stock', 'Only when the opening stock came in at a different price from the normal unit cost.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Or add two hundred at once, from a spreadsheet',
                        'where' => 'Inventory → Import Sheet',
                        'body'  => [
                            'For a price list from a vendor, or the sheet the catalogue lived in before this app. Three steps: choose the file, read what it would do, then decide.',
                            'It matches every row against the catalogue first — by SKU where the sheet has one, by name otherwise — so a second import of the same sheet updates what the first one created instead of duplicating it.',
                            'Nothing is written until the button at the bottom. The table you approve is what gets written, because the preview and the import are the same code.',
                        ],
                        'shot'  => 'import-sheet.png',
                        'more'  => ['import-sheet-2.png', 'import-sheet-3.png'],
                        'fields' => [
                            ['Spreadsheet', '.xlsx, .xls or .csv, up to 20 MB. The only column it insists on is <b>PRODUCT NAME</b>.'],
                            ['Worksheet', 'Which sheet in the workbook to read. It picks the usual one where it finds it, and lists the rest.'],
                            ['Replace costs and targets that already have a value', 'Off by default, and worth leaving off. On, the sheet wins over every number already in the catalogue — including ones the warehouse corrected after a real receipt.'],
                            ['New items', 'Rows that match nothing and would create a record.'],
                            ['Updated', 'Rows that match something and would change it. The match is shown per row — "matched on sku" or "matched on name" — so you can see why.'],
                            ['Already matched', 'Rows that match something and would change nothing. Normal on a re-import.'],
                            ['Need a look', 'Rows with something wrong: a formula in a price cell, a cost that is not a number, or the same product twice in one sheet. Read these before importing.'],
                            ['Changes column', 'Field by field, with the old value where there is one — "Unit cost 118.00 → 129.00". This is the whole promise of the screen.'],
                            ['Import N rows', 'Writes it. Afterwards the table is re-read against the catalogue as it now is, which is why most rows then say "No change".'],
                        ],
                        'note'  => 'Cost columns holding a formula are skipped rather than read as zero, and the row says so. A sheet imported without that check puts plausible, wrong costs into the catalogue.',
                    ],
                    [
                        'title' => 'Edit an item',
                        'where' => 'Inventory → All Inventory → row menu → Edit',
                        'body'  => [
                            'Change anything about the record itself: name, codes, costs, sale target, reorder level, notes.',
                            'Editing does <b>not</b> change how many you have. Quantities only move through receiving, transfers, adjustments and reconciliation — each of which records what changed and why.',
                        ],
                        'shot'  => 'item-edit.png',
                        'fields' => [
                            ['Item Identification', 'Photo, name, Active switch, SKU with its regenerate button, and barcode with its scan button. The same fields as Add Item.'],
                            ['Container / Case Settings', 'Whether this is a container, and one row per thing inside it. Editing the contents changes what Break Case will produce next time — not what it produced last time.'],
                            ['Classification & Sourcing', 'Category and preferred vendor.'],
                            ['Pricing & Inventory Levels', 'List unit cost, average cost, sale target and reorder level. Margin Potential recalculates under the sale target as you type.'],
                            ['Notes & Description', 'Description shows in lists; notes stay internal.'],
                            ['View Item', 'Opens the read-only view with stock by location and history.'],
                            ['Move / Correct Stock', 'The one button here that changes a quantity. Editing this form never does.'],
                        ],
                        'more'  => ['item-edit-2.png', 'item-edit-3.png', 'item-edit-4.png'],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Search across everything at once',
                        'where' => 'Inventory → Inventory Search',
                        'body'  => [
                            'One box that looks across items, locations and stock together, rather than filtering one list at a time.',
                            'Quickest way to answer "do we have any of these, anywhere?" without knowing which screen to be on.',
                        ],
                        'shot'  => 'inventory-search.png',
                        'fields' => [
                            ['Search box', 'One box across items, locations and stock at once. Name, SKU or barcode.'],
                            ['Item results', 'What matched in the catalogue, with stock on hand and cost.'],
                            ['Location results', 'Which places hold it and how many are at each.'],
                            ['Open', 'Jumps to the item, so a search is the start of the job rather than the end of it.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Give an item its extra codes',
                        'where' => 'Inventory → Product Identities',
                        'body'  => [
                            'An item can carry more than one code: the manufacturer\'s UPC, a vendor\'s own SKU, a case code and a singles code.',
                            'Every code listed here resolves to the same item when scanned, so a box labelled by the vendor and the same box labelled by the manufacturer both find it.',
                            'Add one here when a delivery arrives with a code the system does not recognise but the item already exists.',
                        ],
                        'shot'  => 'product-identities.png',
                        'fields' => [
                            ['Item', 'Which inventory record this code resolves to.'],
                            ['Type', 'What kind of code it is — manufacturer UPC, vendor SKU, case code, alias or search token.'],
                            ['Value', 'The code itself. This is what a scan is matched against.'],
                            ['Times confirmed', 'How often a scan of it landed on this item without being corrected. A zero here on an old code is worth a look.'],
                            ['Reassign to product (bulk)', 'Points selected codes at a different item — the fix when a code was learned onto the wrong record.'],
                            ['Delete', 'Removes a code. The item and its stock are untouched; only the code stops resolving.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Find items that got entered twice',
                        'where' => 'Inventory → Duplicate Detector',
                        'body'  => [
                            'Compares names, SKUs and codes to surface records that are probably the same physical thing.',
                            'Two records for one product splits its stock and its cost in two, and nothing warns you it happened — this is how you catch it.',
                            'Run it after a busy receiving week, or whenever a count looks wrong for no reason.',
                        ],
                        'shot'  => 'duplicate-detector.png',
                        'fields' => [
                            ['Scan', 'Runs the comparison across the catalogue. It is not automatic, so nothing changes until you ask.'],
                            ['Similarity score', 'How alike two records are, as a percentage. Red is near-certain, orange is likely, grey is worth a look.'],
                            ['Reasons', 'Why the pair was flagged — same barcode, near-identical name, same SKU stem. Read this before merging anything.'],
                            ['Merge into', 'Two buttons, one per direction. The record you merge into keeps its history; the other one\'s stock, codes and movements are moved onto it.'],
                            ['Ignore', 'Dismisses a pair that is genuinely two different products, so it stops coming back on every scan.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Print labels for things that arrive without one',
                        'where' => 'Inventory → Barcode Printer',
                        'body'  => [
                            'Generates printable barcode labels for any item.',
                            'For products that turn up with no scannable code, or a code so damaged the camera will not read it. Label it once and it scans forever after.',
                        ],
                        'shot'  => 'barcode-printer.png',
                        'fields' => [
                            ['Select Items', 'Search and pick as many active items as you want on one sheet.'],
                            ['Label Size', '4×6, 3×5 or 2×3 inches. Match it to the stock in your printer, not to the box.'],
                            ['Items Per Sheet', '4, 6, 8 or 12. Fewer per sheet means a bigger, more forgiving barcode.'],
                            ['Generate', 'Produces the printable sheet. Print it, stick it on, and that box scans forever after.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Look at one item in full',
                        'where' => 'Inventory → All Inventory → row menu → View',
                        'body'  => [
                            'Stock by location, receiving history, cost history and every movement against this item.',
                            'This is the screen to open when a number looks wrong — the history usually says why.',
                        ],
                        'shot'  => 'item-view.png',
                        'fields' => [
                            ['Header', 'Name, SKU, barcode and status, with Edit and Move / Correct Stock beside them.'],
                            ['Stock by Location', 'One row per place holding it, with quantities. The direct answer to "where is it?".'],
                            ['Costs', 'List unit cost, weighted average, sale target and margin.'],
                            ['Movement history', 'Every change to this item, newest first, with who and why.'],
                            ['Receiving history', 'Which pallets it came in on and at what cost — where the average came from.'],
                        ],
                        'note'  => null,
                    ],
                ],
            ],

            [
                'title' => 'Moving and correcting stock',
                'icon'  => 'heroicon-o-arrows-right-left',
                'blurb' => 'Four ways a quantity legitimately changes. None of them is typing over a number.',
                'steps' => [
                    [
                        'title' => 'The row menu — everything you can do to one item',
                        'where' => 'Inventory → All Inventory → ⋮',
                        'body'  => [
                            '<b>Add Stock</b> puts units into a location. <b>Transfer</b> moves them between locations. <b>Adjust</b> corrects a count with a reason.',
                            '<b>Scan Barcode</b> opens the camera and writes the code straight onto this item — see the scanning section.',
                            '<b>Contents</b> and <b>Break Case</b> appear on containers only.',
                        ],
                        'shot'  => 'item-actions.png',
                        'fields' => [
                            ['Add Stock', 'Books units into a location, optionally at a cost that moves the average.'],
                            ['Move or correct stock', 'Opens the item\'s own screen for a correction or a transfer, with a reason.'],
                            ['Mark Damaged', 'Moves units to a damaged location, out of sellable stock but still on the books.'],
                            ['Move to Returns', 'Moves units to a returns location for sending back.'],
                            ['Scan / Replace Barcode', 'Writes a scanned code straight onto this item. Says "Replace" when one is already on file.'],
                            ['Delete', 'Removes the record. Almost always the wrong choice — see the step below.'],
                            ['Contents / Break Case', 'Two more entries that only appear on containers.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Add stock to a location',
                        'where' => 'row menu → Add Stock',
                        'body'  => [
                            'Location and quantity are required; vendor and unit cost are optional but worth filling in.',
                            'The unit cost you enter blends into this item\'s weighted average. Leave it blank to add units without moving the average — which is what you want when correcting a count rather than recording a purchase.',
                            'The reason field is free text and it is what Movement History will show later. "Restock from vendor" is worth four seconds now and saves an argument in a month.',
                        ],
                        'shot'  => 'add-stock-modal.png',
                        'fields' => [
                            ['Location', 'Required. Where the units are physically going. There is no default — picking the wrong one here is the commonest cause of a count that will not reconcile.'],
                            ['Vendor', 'Optional. Who it came from, for cost attribution and reporting.'],
                            ['Quantity to Add', 'Required. Units, not cases — unless the item itself is the case.'],
                            ['Unit Cost', 'Optional, and the field worth understanding. What you enter blends into the weighted average. Leave it blank to add units without moving the average, which is what you want when correcting a count rather than recording a purchase.'],
                            ['Reason', 'Free text, and it is what Movement History shows later. "Restock from vendor" takes four seconds now and saves an argument in a month.'],
                        ],
                        'note'  => 'Use this for stock arriving outside a pallet. Anything that came on a delivery should be received against the pallet instead, so the cost and the paperwork stay together.',
                    ],
                    [
                        'title' => 'Correct or move stock — the screen behind "Move or correct stock"',
                        'where' => 'row menu → Move or correct stock',
                        'body'  => [
                            'Two jobs on one screen, and the tabs at the top decide which you are doing. <b>Correct / remove</b> when the count is wrong — damaged, lost, used, given away. <b>Move stock</b> when the count is right and the item changed location.',
                            'The panel on the left lists every location holding this item with its quantity. Pick the one you are physically changing; the form on the right then talks about that location only.',
                            'The important field is <b>Actual quantity now</b>: you enter what should be there when you are finished, not the difference. Ten recorded, two given away, enter eight.',
                        ],
                        'shot'  => 'item-stock.png',
                        'fields' => [
                            ['Correct / remove (tab)', 'The count is wrong and you are making it right. Writes an adjustment.'],
                            ['Move stock (tab)', 'The count is right, the location is not. Writes a transfer — both sides, one movement.'],
                            ['Stock on hand now', 'Every location holding this item. Click one to choose what you are correcting; nothing on the right applies until you have.'],
                            ['Location being corrected', 'The location you picked, with what is recorded there right now underneath it.'],
                            ['Actual quantity now', 'What should remain after the change — not the amount removed. This is the field people get backwards.'],
                            ['Why did the count change?', 'Pick the real reason: Inventory Correction, damaged, used, given away. Movement History is only useful if this is honest.'],
                            ['Related Show', 'Optional. Ties the change to a show, so stock used on air can be traced to it later.'],
                            ['Note', 'Free text, and required if you chose Other. "2 boxes used as viewer giveaway" is the standard.'],
                        ],
                        'note'  => 'Every change here is written to this item\'s history with your name on it. That is the point of using this screen rather than editing a number.',
                    ],
                    [
                        'title' => 'Mark stock damaged',
                        'where' => 'row menu → Mark Damaged',
                        'body'  => [
                            'Damaged stock does not disappear — it moves to a damaged location, so it is out of sellable count but still on the books.',
                            'The second dropdown only offers locations of type <b>Damaged</b>. If it is empty, no such location exists yet; create one first.',
                        ],
                        'shot'  => 'act-damaged.png',
                        'fields' => [
                            ['From Location', 'Where the damaged units are now.'],
                            ['Damaged Inventory Location', 'Where they are going. Only locations of type Damaged appear here.'],
                            ['Quantity', 'How many units are damaged.'],
                            ['Reason', 'What happened. Worth writing — a vendor claim later is easier with a sentence than without one.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Send stock back to the vendor',
                        'where' => 'row menu → Move to Returns',
                        'body'  => [
                            'The same shape as Mark Damaged, pointed at a <b>Returned</b> location instead.',
                            'Use it when something is going back rather than being written off. The stock stays visible in the returns location until it is resolved.',
                        ],
                        'shot'  => 'act-returns.png',
                        'fields' => [
                            ['From Location', 'Where the units are now.'],
                            ['Returns Location', 'Where they are going. Only locations of type Returned appear here.'],
                            ['Quantity', 'How many units are going back.'],
                            ['Reason', 'Why. This is what you will quote to the vendor.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Deleting an item, and when not to',
                        'where' => 'row menu → Delete',
                        'body'  => [
                            'Delete asks to confirm, and then the record is gone.',
                            'Almost always the wrong tool. An item with stock or movement history should be made <b>Inactive</b> on its edit form instead: that hides it from pickers and the default list while keeping every number that ever referred to it.',
                            'Delete is for a record created by mistake five minutes ago that nothing has touched since.',
                        ],
                        'shot'  => 'act-delete.png',
                        'fields' => [
                            ['Confirmation dialog', 'One prompt, then the record is gone. There is no undo and no recycle bin.'],
                            ['What survives', 'Movements already written stay in history; the item they pointed at does not.'],
                            ['The alternative', 'Active off, on the edit form. Same effect for everyday use, none of the loss.'],
                        ],
                        'note'  => 'If you are deleting because the same product exists twice, use Duplicate Detector instead — it merges the two and keeps the stock, rather than throwing one side away.',
                    ],
                    [
                        'title' => 'See what is inside a case',
                        'where' => 'row menu → Contents (containers only)',
                        'body'  => [
                            'For an item marked as a container, this lists what one of them holds and how much of each is in stock.',
                            'Read-only. It is the answer to "what do I actually get if I open this?".',
                        ],
                        'shot'  => 'act-contents.png',
                        'fields' => [
                            ['Row per content', 'Which item is inside, and how many per container.'],
                            ['In stock', 'How many of that inner item you already hold, so you can see what breaking one would add to.'],
                            ['Close', 'Read-only. Nothing on this dialog changes a quantity — Break Case does that.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Break a case into its contents',
                        'where' => 'row menu → Break Case (containers only)',
                        'body'  => [
                            'Turns recorded contents into real stock: one case out, twelve boxes in, at the same location, in one movement.',
                            'The location dropdown only lists places actually holding the case, so you cannot break one where there is none.',
                            'The line underneath tells you exactly what the break will produce before you commit to it.',
                        ],
                        'shot'  => 'act-break-case.png',
                        'fields' => [
                            ['Location', 'Where the case is being opened. Only locations holding this container are listed.'],
                            ['How many to break', 'Number of cases to open. The contents land in the same location.'],
                            ['Each one produces', 'Not a field — the system telling you what one case turns into, from the item\'s recorded contents.'],
                        ],
                        'note'  => 'If Break Case is not on the menu, the item is not marked as a container, or it has no contents recorded. Both are fixed on its edit form.',
                    ],
                    [
                        'title' => 'Add stock in a hurry',
                        'where' => 'Inventory → Quick Add Stock',
                        'body'  => [
                            'The same job as Add Stock, on its own screen and built for a phone — one item, one location, a number.',
                            'For putting a handful of units somewhere without navigating the catalogue first.',
                        ],
                        'shot'  => 'quick-add-stock.png',
                        'fields' => [
                            ['Barcode', 'Scan or type. Resolves to the item before anything else on the screen means anything.'],
                            ['Quantity', 'Units to add, with big touch targets because this screen is used one-handed.'],
                            ['Unit Cost', 'Optional. Fill it in when this is a purchase; leave it out when you are correcting a count.'],
                            ['Storage Location', 'Where the units are going.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Check what is where',
                        'where' => 'Inventory → Stock Levels',
                        'body'  => [
                            'One row per item per location. The direct answer to "how many, and where?".',
                            'Look here straight after receiving. If a number is not where you expect, it is nearly always a location picked in haste.',
                        ],
                        'shot'  => 'stock-levels.png',
                        'fields' => [
                            ['Item / SKU / Category', 'What it is.'],
                            ['Location / Location Type', 'Where this row\'s stock is, and what kind of place that is.'],
                            ['Quantity', 'Units at that location. One row per item per location, which is why an item can appear several times.'],
                            ['Unit Cost / Stock Value', 'What each unit is worth and what the row is worth.'],
                            ['Low Stock Only', 'Filter for rows at or under the item\'s reorder level.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Transfer between locations',
                        'where' => 'Inventory → Stock Transfer',
                        'body'  => [
                            'Pick the item, where it is coming from, where it is going and how many.',
                            'A transfer writes both sides — out of one location and into the other — so the totals stay right and the history says who moved it.',
                            'Use this whenever stock physically moves, including handing boxes to a streamer.',
                        ],
                        'shot'  => 'transfer.png',
                        'fields' => [
                            ['Item', 'What is moving.'],
                            ['From Location', 'Where it is now. Only locations actually holding this item are offered, so you cannot transfer out of somewhere that has none.'],
                            ['To Location', 'Where it is going.'],
                            ['Quantity', 'Units to move. Cannot exceed what is at the source.'],
                            ['Reason', 'Optional but worth it — "handed to Jordan for tonight" reads better in six weeks than a bare movement.'],
                        ],
                        'note'  => 'Never fix a location mistake by adjusting one side down and the other up. Two adjustments look like two errors; one transfer looks like what happened.',
                    ],
                    [
                        'title' => 'Correct a count after a physical check',
                        'where' => 'Inventory → Reconciliation',
                        'body'  => [
                            'Record what you actually counted. The difference is written as an adjustment with the reason attached.',
                            'For a one-off correction, <b>Adjust</b> on the item is quicker; for a shelf or a full count, use this.',
                        ],
                        'shot'  => 'reconciliation.png',
                        'fields' => [
                            ['Location', 'Which place you counted. Everything on the screen is scoped to it.'],
                            ['Start Location Count', 'Begins a full count of that location, item by item.'],
                            ['Item', 'For a single correction rather than a whole shelf.'],
                            ['Physical Count', 'What you actually counted. The system works out the difference — you do not enter it.'],
                            ['Reason', 'Optional but the whole point: "inventory audit" is what Movement History will show next month.'],
                        ],
                        'note'  => 'A recurring discrepancy is only traceable if the corrections were recorded. Editing numbers to match tells you nothing next month.',
                    ],
                    [
                        'title' => 'See every change that has been made',
                        'where' => 'Inventory → Movement History',
                        'body'  => [
                            'What moved, when, who did it, from where to where, and why.',
                            'Nothing in the system changes a quantity without writing a row here. If a count changed and you do not know why, the answer is on this screen.',
                        ],
                        'shot'  => 'movements.png',
                        'fields' => [
                            ['Date & Time', 'When it happened. Newest first.'],
                            ['Movement Type', 'Receipt, transfer, adjustment, damage, return, break. What kind of change this was.'],
                            ['Item / SKU', 'What moved.'],
                            ['Quantity', 'How many, signed — in or out.'],
                            ['From / To', 'Locations. A transfer shows both; a receipt shows only a destination.'],
                            ['Reason', 'What whoever did it wrote at the time. This is why the reason fields matter.'],
                            ['Created By', 'Who did it. Not editable, which is the point.'],
                            ['Unit Cost / Reference', 'What it was valued at and what it was against — a pallet, a show, a session.'],
                        ],
                        'note'  => null,
                    ],
                ],
            ],

            [
                'title' => 'Receiving a delivery',
                'icon'  => 'heroicon-o-truck',
                'blurb' => 'A pallet is one delivery from one vendor. Receiving against it is where real cost '
                    . 'enters the system — each receipt recalculates the item\'s weighted average, so everything '
                    . 'downstream follows what you actually paid.',
                'steps' => [
                    [
                        'title' => 'Open the pallet list',
                        'where' => 'Inventory → Pallets',
                        'body'  => [
                            'Everything in progress and everything closed. Status tells you what still needs work.',
                            'You can also reach this from All Inventory → <b>Receive Shipment</b>.',
                        ],
                        'shot'  => 'pallets-list.png',
                        'fields' => [
                            ['New Shipment / Pallet', 'Starts a delivery. Vendor and reference are all you need to begin.'],
                            ['Vendor / Pallet / Reference', 'Who it is from and what it is called, yours and theirs.'],
                            ['Received', 'The date it physically landed.'],
                            ['Status', 'Staged, receiving, received, processed — where this delivery is in its life.'],
                            ['Search / Filters', 'By vendor and status, for when several are open at once.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'What the pallet list is telling you',
                        'where' => 'Inventory → Pallets',
                        'body'  => [
                            'The column worth reading is <b>Next Action</b>. It is not a status — it is the button for whatever this pallet needs next, so the list doubles as a to-do list.',
                            'The row menu carries the rest: <b>View</b> to open it, <b>Scanning Station</b> while it is being worked, <b>Edit</b> for the vendor and cost fields, and <b>Delete</b> for one raised by mistake.',
                        ],
                        'shot'  => 'pallet-actions.png',
                        'fields' => [
                            ['Receiving Progress', 'Received against expected, as a bar. A pallet at 100% that is still open usually means a line was never mapped.'],
                            ['Missing Items', 'Lines short of what the manifest promised. This is the number a vendor claim starts from.'],
                            ['Next Action', 'The one thing this pallet needs now — start receiving, finish it, mark it processed. Clickable.'],
                            ['Tracking / Expected', 'Carrier reference and expected delivery date for anything not yet here.'],
                            ['Lines / Total Cost', 'How many manifest lines, and what the delivery is worth.'],
                        ],
                        'note'  => 'Delete on a pallet removes the delivery record, not the stock already received from it. Those movements stay in history where they belong.',
                    ],
                    [
                        'title' => 'Stage the pallet',
                        'where' => 'Inventory → Pallets → New Pallet',
                        'body'  => [
                            'Vendor and PO number, then a line per product with what the paperwork says you should be getting: expected cases, units per case, unit cost.',
                            'Put the unit cost on the line if you know it — that is the number that becomes the item\'s weighted average when the line is received.',
                            'Each line has to be mapped to an inventory item before it can be received. Map it here, or let a scan map it for you at the receiving station.',
                        ],
                        'shot'  => 'pallet-create.png',
                        'more'  => ['pallet-create-2.png'],
                        'fields' => [
                            ['Pallet Name', 'Optional. What you would call it out loud — "Topps Chrome, August". Helps when several are open at once.'],
                            ['Vendor', 'Required. Who the delivery is from.'],
                            ['PO / Reference #', 'The vendor\'s number, so this matches their paperwork when something is queried.'],
                            ['Received Date', 'When it physically arrived.'],
                            ['Total Invoice Cost', 'What the whole delivery cost. Used for reconciling against the sum of the lines.'],
                            ['Shipping Cost', 'Spread across the items by quantity when the pallet is received, so freight lands in the unit cost rather than disappearing.'],
                            ['Payment Fees', 'Same treatment as shipping.'],
                            ['Status', 'Required. Where this pallet is in its life — staged, receiving, received, processed.'],
                            ['Line: Item', 'Which inventory item this line is. A line cannot be received until it has one; a scan at the receiving station can set it for you.'],
                            ['Line: Location', 'Where this line\'s stock will land when received.'],
                            ['Line: Expected cases / units per case', 'What the paperwork says. The receiving screen counts against this and flags the difference.'],
                            ['Line: Unit Cost', 'What you paid per unit for this line. This is the number that becomes the item\'s weighted average on receipt, so it matters more than any other field on the pallet.'],
                        ],
                        'note'  => 'You do not need a manifest at all. Start a blank pallet and scan what turned up — lines are created as you go.',
                    ],
                    [
                        'title' => 'Every button on a pallet\'s own screen',
                        'where' => 'Inventory → Pallets → open one',
                        'body'  => [
                            'The row of buttons across the top is the whole receiving workflow in the order you would use it.',
                            'The status strip underneath — Manifest Staged → Actively Receiving → All Received → Complete — is where this pallet is right now, and it is worth a glance before you touch anything.',
                        ],
                        'shot'  => 'pallet-stage.png',
                        'fields' => [
                            ['Start receiving', 'Opens the receiving station and moves the pallet into Actively Receiving. This is the one that starts the work.'],
                            ['Add Lines', 'Type the manifest — one row per product. A name is enough; linking to inventory can wait.'],
                            ['Scan Item', 'Scan a box straight onto this pallet without typing a line first.'],
                            ['Review Manifest', 'The staged lines, line by line, with what each is mapped to.'],
                            ['Review & Receive', 'The check-and-commit screen for a pallet you are confident about.'],
                            ['Add Photos / Documents', 'Attach the invoice, the packing slip, a picture of a damaged box. This is what a vendor claim is made of.'],
                        ],
                        'note'  => 'A pallet with no lines is not a problem. Start it blank and scan what turned up — lines are created as you go.',
                    ],
                    [
                        'title' => 'Type the manifest',
                        'where' => 'Pallet → Add Lines',
                        'body'  => [
                            'A grid built for typing rather than clicking: <b>Tab</b> moves across, <b>Enter</b> starts a new row, and empty rows are ignored.',
                            'The <b>Already stock this?</b> column is where a line gets linked to an item you already have. Leave it on "Something new" and the item is created when the line is received.',
                            '<b>Receive everything into</b> at the top sets one destination for the whole pallet, so you are not picking a location per line.',
                        ],
                        'shot'  => 'pallet-add-lines.png',
                        'fields' => [
                            ['Receive everything into', 'One location for the whole pallet. "Decide when it lands" leaves it to the receiving station.'],
                            ['Item', 'Required. What the paperwork calls it. This is the only field you cannot skip.'],
                            ['Already stock this?', 'Links the line to an existing inventory item. "Something new" creates one on receipt — which is right for a first-time product and wrong for one you already carry under a different name.'],
                            ['Case or single', 'Whether the line is cases or loose units. "Not sure" is allowed and can be settled at the receiving station.'],
                            ['Cases / Qty', 'How many the paperwork says are coming.'],
                            ['Units / Case', 'How many units one case holds. Cases × units is what the receiving screen counts against.'],
                            ['Unit Cost', 'What you paid per unit. This becomes part of the item\'s weighted average when the line is received, so it matters more than anything else on the row.'],
                            ['Line Total', 'Not a field — quantity × unit cost, so a typo in either shows up as a total that does not look like the invoice.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Photograph the packing slip instead of typing it',
                        'where' => 'Pallet → Review Manifest → Import Packing Slip',
                        'body'  => [
                            'Four steps across the top — <b>Upload</b>, <b>Reading</b>, <b>Verify</b>, <b>Done</b> — and you only do two of them.',
                            'Take a photo of the slip that came with the pallet, or upload the PDF. It is read for you and every line comes back as a draft manifest line.',
                            'Nothing is committed until the <b>Verify</b> step, which is where you check the quantities and costs against the paper in your hand.',
                        ],
                        'shot'  => 'pallet-manifest.png',
                        'fields' => [
                            ['Choose Photo or PDF', 'JPG, PNG, WEBP or PDF, up to 20 MB. A phone photo of the slip on the pallet is enough.'],
                            ['Read with AI', 'Starts the extraction. Greyed out until a file is attached.'],
                            ['Verify', 'Every extracted line, editable, before anything is written. Read this step — it is the whole safety net.'],
                            ['Edit the pallet directly', 'The way out if the slip is unreadable: enter the lines by hand instead.'],
                        ],
                        'note'  => 'Check the costs at the Verify step. A slip read with a cost in the wrong column moves the weighted average of every item on the pallet.',
                    ],
                    [
                        'title' => 'Review the pallet before you start',
                        'where' => 'Inventory → Pallets → open one',
                        'body'  => [
                            'Lines, mappings, expected quantities and what has been received so far.',
                            'Fix a wrong mapping here rather than at the receiving station, where you will be holding a box.',
                        ],
                        'shot'  => 'pallet-view.png',
                        'fields' => [
                            ['Pallet Status strip', 'Manifest Staged → Actively Receiving → All Received → Complete. Where this delivery is, at a glance.'],
                            ['Vendor / Pallet / Status / Total Cost', 'The summary line — who, what, where in the process, and what it is worth.'],
                            ['Received date / Shipping cost / Carrier / Tracking', 'The delivery\'s own facts, for chasing or reconciling.'],
                            ['What should be on this pallet', 'The staged lines. Empty means nothing has been entered yet — Add Lines or a scan fills it.'],
                            ['Button row', 'Start receiving, Add Lines, Scan Item, Review Manifest, Review & Receive, Add Photos / Documents.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'The manifest, line by line',
                        'where' => 'Pallet → Review Manifest',
                        'body'  => [
                            'Each staged line with what it is mapped to, what is expected and what has come in.',
                            'This is where an unmapped line is obvious. A line with no inventory item cannot be received, and this screen is where you fix that before anyone is standing at the pallet.',
                        ],
                        'shot'  => 'pallet-items.png',
                        'fields' => [
                            ['Lines / Units Received / Goods Cost', 'The three totals for this delivery, after receiving.'],
                            ['Item', 'What came in, with its SKU and barcode underneath — and what the manifest called it, when the two differ.'],
                            ['Received', 'Received against expected, per line.'],
                            ['In Stock', 'What you now hold of that item in total, so a receipt can be sanity-checked immediately.'],
                            ['Unit Cost', 'What this delivery paid per unit. This is the number that moved the weighted average.'],
                            ['Location', 'Where the line landed.'],
                            ['Line Total', 'Units × unit cost.'],
                            ['Fix', 'Corrects cost, name, count or location on that line without leaving the pallet — the fastest place to catch a typo.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Receive it — all at once, or box by box',
                        'where' => 'Inventory → Pallets → Receive',
                        'body'  => [
                            'The receiving station shows expected, received and remaining, with a progress bar per pallet.',
                            '<b>Receive All</b> on a line books the whole expected quantity in one action. Use it when you have counted the line and it matches the paperwork.',
                            'For a partial delivery, scan what actually arrived and then use <b>Mark Short</b> — under "Something did not arrive?" — for the rest. That records the difference instead of leaving it to be argued about later.',
                            'Or scan each box: type or scan into the code field and press <b>Receive Scan</b>. Every scan books one unit against its line.',
                        ],
                        'shot'  => 'pallet-receive.png',
                        'fields' => [
                            ['Expected / Received / Remaining', 'The pallet\'s state in three numbers, with a progress bar under them.'],
                            ['Scan or type UPC / barcode', 'Where a gun types. It submits itself; typing needs Receive Scan.'],
                            ['Camera / Clear', 'Use the phone camera instead, or empty the box and start again.'],
                            ['Scan (per line)', 'Scan a box against that line. On a new line, the code becomes the line\'s mapping in the same step.'],
                            ['Receive All (per line)', 'Books the line\'s whole expected quantity. Only after physically counting it.'],
                            ['Mark Short', 'Under "Something did not arrive?" — records what is missing so the difference is documented, not argued.'],
                            ['Received By', 'Who worked it. Goes on the session record.'],
                            ['Complete Receiving', 'Closes the pallet when the counts match. Until then it stays open, and Pause is safe.'],
                        ],
                        'note'  => 'Bulk receive and scanning are not exclusive. Bulk-receive the lines you have counted, scan the ones you want confirmed individually.',
                    ],
                    [
                        'title' => 'Every button at the receiving station',
                        'where' => 'Pallet → Start receiving',
                        'body'  => [
                            'This is the screen someone stands at with a box in their hands, so nothing on it is more than one tap from where your eyes already are.',
                            'The three counters at the top — Expected, Received, Remaining — are the whole state of the pallet. The progress bar underneath is the same thing for someone walking past.',
                            'The code box takes a gun scan, a camera scan or typing, and all three end the same way: one unit booked against its line.',
                        ],
                        'shot'  => 'pallet-receive-live.png',
                        'fields' => [
                            ['Pause — keep it open', 'Stops for now and keeps everything received so far. The pallet stays open and the next person picks it up.'],
                            ['Add Photos / Documents', 'Attach the invoice or a picture of the damage, from this screen, without losing your place.'],
                            ['Map Line to Item', 'Links a manifest line to an inventory item. Needed once per line before it can be received.'],
                            ['Scan or type UPC / barcode', 'Where a gun scanner types. It submits itself, so a gun needs no button at all.'],
                            ['Camera', 'Uses the phone camera instead of a gun. Fill the frame with the barcode and hold still — it confirms a code before it accepts it.'],
                            ['Receive Scan', 'Books what is in the code box. Only needed when you typed the code by hand.'],
                            ['Scan (per line)', 'Scan a box against this specific line. For a new line, the barcode you scan becomes the line\'s mapping and the box is received in the same step.'],
                            ['Receive All (per line)', 'Books the whole expected quantity for that line at once. Only after physically counting it — this is the button that makes a count wrong if you trust the paperwork over the pallet.'],
                        ],
                        'note'  => 'A scan that finds nothing means that code is on no item. Map the line first, or attach the code to the item, then scan again.',
                    ],
                    [
                        'title' => 'Pause and hand over mid-pallet',
                        'where' => 'Receive Pallet → Pause — keep it open',
                        'body'  => [
                            'A pallet does not have to be finished in one go. Pausing keeps everything received so far and leaves the rest outstanding.',
                            'Each stint is recorded as a receiving session, so a pallet worked by two people over two days shows who did which part.',
                        ],
                        'shot'  => 'receiving-sessions.png',
                        'fields' => [
                            ['Date', 'When the stint of work happened.'],
                            ['Pallet / PO', 'Which delivery was being worked.'],
                            ['Vendor', 'Who it came from.'],
                            ['Operator', 'Who was at the station. This is what makes a two-person pallet traceable.'],
                            ['Mode', 'How it was worked — scanned box by box, or received in bulk.'],
                            ['Items Scanned / Duration', 'How much was done and how long it took.'],
                            ['Status', 'Whether that session was completed or is still open.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Look back at what was received',
                        'where' => 'Inventory → Receiving History',
                        'body'  => [
                            'Every session with a per-item breakdown, exportable as PDF.',
                            'This is the screen for "what actually came off that pallet?" weeks later, and for settling a disagreement with a vendor about a short delivery.',
                        ],
                        'shot'  => 'receiving-history.png',
                        'fields' => [
                            ['Search', 'Across pallets, vendors and operators.'],
                            ['Per-session breakdown', 'Open a row for what came off that pallet, item by item.'],
                            ['PDF export', 'The breakdown as a document — what you send a vendor when a delivery was short.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'The pallet\'s own history',
                        'where' => 'Inventory → Pallet Receiving History',
                        'body'  => [
                            'The same events grouped by pallet rather than by session — how long each took, what was short, what was still outstanding when it closed.',
                        ],
                        'shot'  => 'pallet-history.png',
                        'fields' => [
                            ['Received Date', 'When the pallet was worked.'],
                            ['PO / Reference', 'The delivery, clickable through to it.'],
                            ['Vendor', 'Who it was from.'],
                            ['Items', 'How many lines came off it.'],
                            ['Total Cost / Avg Unit Cost', 'What the delivery cost and what the average unit came to — the quickest check that a pallet was priced sanely.'],
                            ['Stage', 'How far through its life the pallet is.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Where every pallet stands right now',
                        'where' => 'Inventory → Pallet Status',
                        'body'  => [
                            'One board for every pallet in flight: what is staged, what is being worked, what is finished but not closed.',
                            'The screen to open on a Monday. It answers "what is outstanding?" without opening pallets one at a time.',
                        ],
                        'shot'  => 'pallet-status.png',
                        'fields' => [
                            ['Pending / In Transit / Receiving / Received', 'Counts by stage, across every open delivery.'],
                            ['Total Value', 'What is in flight, in money.'],
                            ['Pending Orders', 'Ordered, not shipped: pallet, vendor, expected delivery, days remaining, tracking.'],
                            ['In Transit Pallets', 'On their way: carrier and tracking, expected arrival, how long they have been travelling.'],
                            ['Currently Receiving', 'Being worked right now, with each one\'s percentage complete.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Track a delivery that has not landed yet',
                        'where' => 'Inventory → Shipments',
                        'body'  => [
                            'Inbound shipments and their status, before they become a pallet you can receive.',
                            'Use it to see what is on its way and what is overdue.',
                        ],
                        'shot'  => 'shipments.png',
                        'fields' => [
                            ['Show / Recipient', 'What the shipment is for and who it is going to.'],
                            ['Carrier / Tracking', 'Who is carrying it and the number, copyable in one click.'],
                            ['Order Date', 'When it was raised.'],
                            ['Items / Weight / Dimensions', 'What is in it and how big it is.'],
                            ['Shipping / Insured / Signature', 'What it cost to send, and whether it is covered and signed for.'],
                        ],
                        'note'  => null,
                    ],
                ],
            ],

            [
                'title' => 'Scanning',
                'icon'  => 'heroicon-o-qr-code',
                'blurb' => 'A USB or Bluetooth gun and a phone camera both work everywhere a scan is accepted. '
                    . 'What a scan <i>does</i> depends on which screen and which mode you are in.',
                'steps' => [
                    [
                        'title' => 'The scan screen, and picking a mode',
                        'where' => 'Inventory → Quick Scan',
                        'body'  => [
                            'One screen, three modes, and the mode is the whole difference between reading and writing. Pick it before you scan anything.',
                            'The code box accepts a gun scan, a typed code or a camera capture. A gun submits by itself; typing needs the button; the camera needs the barcode to fill the frame.',
                        ],
                        'shot'  => 'quick-scan.png',
                        'fields' => [
                            ['Look Up', 'Read only. Tells you what the code is, what it costs and where it is. Nothing changes.'],
                            ['Add Stock', 'Books units into a location. Asks for location and quantity before it commits.'],
                            ['Receive', 'Works a delivery against a pallet you pick first. Each scan books one unit against its line.'],
                            ['Camera', 'Uses the phone camera. It votes on a code across several frames before accepting it, so a blurred read is refused rather than guessed.'],
                        ],
                        'note'  => 'If a scan does nothing at all, check the mode first. Look Up is doing exactly what it promises.',
                    ],
                    [
                        'title' => 'Look up — read only',
                        'where' => 'Inventory → Quick Scan → Look Up',
                        'body'  => [
                            'Scan anything to see what it is, what it costs and where it is. Nothing changes.',
                            'The safe mode. Use it when you just want to know what is in your hand.',
                            'A gun scanner types into the code field and submits itself. For the camera, tap <b>Camera</b> and centre the barcode.',
                        ],
                        'shot'  => 'scanner-lookup.png',
                        'fields' => [
                            ['Barcode, UPC, or SKU', 'One box, three kinds of code. All of them resolve to the same item.'],
                            ['On hand / Avg cost / Value', 'What the scan found: how many, at what cost, worth what.'],
                            ['Stock by location', 'Where those units are.'],
                            ['View Item', 'Opens the full record if you need more than the summary.'],
                            ['Move / Correct Stock', 'Straight from a lookup into a correction, without going back to the catalogue.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Add stock — puts units on a shelf',
                        'where' => 'Inventory → Quick Scan → Add Stock',
                        'body'  => [
                            'Scan, choose the location and quantity, confirm. Units go in.',
                            'For stock arriving outside a pallet — a small buy, something found in a cupboard.',
                        ],
                        'shot'  => 'scanner-addstock.png',
                        'fields' => [
                            ['Quantity', 'Units to add.'],
                            ['Destination location', 'Where they go.'],
                            ['Unit cost', 'Optional, and it moves the weighted average when filled in.'],
                            ['Cases / Units per case', 'For adding by the case rather than the unit — the maths is done for you.'],
                            ['Confirm', 'Nothing is booked until you press it. A half-typed code cannot add stock by accident.'],
                        ],
                        'note'  => 'This mode is deliberately not automatic. A half-typed code should not silently book stock in.',
                    ],
                    [
                        'title' => 'Receive — works a delivery off a pallet',
                        'where' => 'Inventory → Quick Scan → Receive',
                        'body'  => [
                            'Pick the pallet, then scan boxes. Each scan books a unit against the matching line.',
                            'The same station as the pallet\'s own Receive screen, reachable without going through the pallet first.',
                        ],
                        'shot'  => 'scanner-receive.png',
                        'fields' => [
                            ['Which delivery?', 'Pick the pallet first. Every scan afterwards is booked against it.'],
                            ['Default receiving location', 'Where boxes land unless a line says otherwise.'],
                            ['Scan', 'Each scan books one unit against the matching manifest line.'],
                            ['All arrived / Some / Not here', 'How a line is settled when the count does not match the paperwork.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Getting a UPC onto an item after the fact',
                        'where' => 'Inventory → All Inventory → row menu → Scan Barcode',
                        'body'  => [
                            'This is the one for a pallet you have already received, where the items have no barcode on file yet.',
                            'Find the item, open its row menu, choose <b>Scan Barcode</b>, and scan the box. The code is written straight onto the item — no editing, no navigating to the field.',
                            'If that code is already on another item you are told which one, and nothing is changed.',
                            'From then on that box scans everywhere: lookup, add stock, receiving, and the streamer\'s end-of-stream report.',
                        ],
                        'shot'  => 'act-barcode.png',
                        'fields' => [
                            ['Scan Barcode / Replace Barcode', 'The label changes depending on whether a code is already on file.'],
                            ['Camera', 'Opens the phone camera. It confirms a code across several frames before accepting it.'],
                            ['Typing', 'Always works, and is the fallback when a label is damaged or the light is bad.'],
                            ['Already in use', 'If another item has that code you are told which one, and nothing is changed.'],
                        ],
                        'note'  => 'At the receiving station you can do this and receive in one step: tap a line\'s Scan button, scan the box, and the barcode becomes that line\'s mapping as the box is received.',
                    ],
                    [
                        'title' => 'Cases that contain other items',
                        'where' => 'Inventory → Quick Add (container scan)',
                        'body'  => [
                            'Scan the case, then scan what is inside it, to record the contents once.',
                            'After that, <b>Break Case</b> on the item converts one case into its contents in a single action — one case out, twelve boxes in, at the same location.',
                        ],
                        'shot'  => 'container-scan.png',
                        'fields' => [
                            ['Scan Container / Box SKU', 'The case first. This is the record everything else attaches to.'],
                            ['Scan or Add Items', 'Then each thing inside, with quantity and unit cost.'],
                            ['Total Items / Total Quantity / Contents Value', 'What you have built so far, so a miscount is visible before saving.'],
                            ['Review & Save', 'Creates the container with its contents in one step — which is what makes Break Case work later.'],
                        ],
                        'note'  => null,
                    ],
                ],
            ],

            [
                'title' => 'Where the numbers end up',
                'icon'  => 'heroicon-o-chart-bar',
                'blurb' => 'What the whole cycle exists to keep honest.',
                'steps' => [
                    [
                        'title' => 'What the shelf is worth',
                        'where' => 'Inventory → Inventory Value',
                        'body'  => [
                            'Everything on hand at weighted average cost, and the margin sitting in it at your sale targets.',
                            'Only as good as your receiving and your reports: cost enters at receiving, and leaves when a streamer\'s end-of-stream report is approved.',
                        ],
                        'shot'  => 'value-dashboard.png',
                        'fields' => [
                            ['Total Inventory Value', 'Every unit on hand at its weighted average cost. What the shelf is worth, not what it would sell for.'],
                            ['Total SKUs', 'Distinct items, with total units underneath.'],
                            ['Low Stock', 'Items at or below their reorder level.'],
                            ['Issues', 'Items with no cost on file or a suspicious cost variance — the ones whose value figure cannot be trusted.'],
                            ['Inventory Value by Location', 'The same money split by place, with each location\'s share of the total. This is how you find value sitting somewhere it should not be.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'What is not moving',
                        'where' => 'Inventory → Inventory Age',
                        'body'  => [
                            'How long each item has been sitting, and what that capital is worth.',
                            'Dead stock is money on a shelf. This is the screen that tells you which shelf.',
                        ],
                        'shot'  => 'inventory-age.png',
                        'fields' => [
                            ['Location filter', 'All locations, or one. Age is measured per location, because stock moved to a streamer is not the same age problem as stock on a shelf.'],
                            ['0 – 30 Days (Healthy)', 'Recently received. Value, share of total, units and item count.'],
                            ['31 – 60 Days (Monitor)', 'Not yet a problem, worth watching.'],
                            ['61 – 90 Days (At Risk)', 'Slow. Usually the last chance to move it at full price.'],
                            ['90+ Days (High Risk)', 'Capital that is not working. Expand any band to see which items are in it.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'The overview',
                        'where' => 'Inventory → Inventory Analytics',
                        'body'  => [
                            'Stock levels, values and movement trends over time in one place.',
                            'Where to start when the question is general rather than about one item.',
                        ],
                        'shot'  => 'inventory-analytics.png',
                        'fields' => [
                            ['Total Inventory Value', 'With the change against 30 days ago, so the number has a direction and not just a size.'],
                            ['Total Units in Stock', 'Units, not records.'],
                            ['Active Items', 'Items not retired.'],
                            ['Low Stock Items', 'At or below reorder level, and needing an order.'],
                            ['Inventory Value Over Time', 'The last 30 days as a line. A step in it is a pallet; a slope is trading.'],
                            ['Inventory by Category', 'Share of stock value by category — what you are actually holding, by money rather than by count.'],
                            ['Low Stock Items table', 'Item, SKU, on hand, reorder at, status — the reorder list, ready to work.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'How individual products perform',
                        'where' => 'Inventory → Product Insights',
                        'body'  => [
                            'Per-product history: what it cost over time, how it moved, what it made.',
                            'The screen to open before deciding whether to reorder something.',
                        ],
                        'shot'  => 'product-insights.png',
                        'fields' => [
                            ['Inventory Value', 'Capital in on-hand stock.'],
                            ['Dead Stock', 'Money tied up with no sale in 90 days.'],
                            ['Active SKUs', 'The catalogue, with how many have actually sold underneath.'],
                            ['Best Margin / Reorder Soon / All Products / Dead Stock / Never Sold', 'Five views of the same table. Reorder Soon is the one that uses the vendor\'s lead time.'],
                            ['On Hand / Sold / Revenue', 'What you hold, what went out, what it brought in.'],
                            ['Margin', 'Money and percentage per unit, against real cost.'],
                            ['Sell-Through', 'Proportion of what you held that has sold.'],
                            ['Suggested Reorder', 'How many to buy, from sales rate and the vendor\'s lead time. A dash means not enough history yet.'],
                            ['Capital / Last Sold', 'What this product is holding, and how long since it last moved.'],
                            ['Export CSV', 'The current view as a file.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'How receiving itself is going',
                        'where' => 'Inventory → Receiving Analytics',
                        'body'  => [
                            'Throughput, shortfalls and how long pallets take to work.',
                            'Useful for spotting a vendor who is consistently short, or a week where receiving fell behind.',
                        ],
                        'shot'  => 'receiving-analytics.png',
                        'fields' => [
                            ['Sessions Completed', 'How many stints of receiving work have been finished.'],
                            ['Lines Processed', 'Manifest lines received across those sessions.'],
                            ['Auto-Match Rate', 'How often a scan mapped itself to the right item with no human help. This is the number that says whether your codes are in good order.'],
                            ['Aliases Learned', 'Codes the system picked up during receiving, and how many have since been confirmed by a second scan.'],
                            ['Total Receiving Cost', 'What has come in, across all received lots.'],
                            ['Match Stage Breakdown', 'How lines are being matched — straight UPC hit, learned alias, or a person deciding.'],
                            ['Top Vendors', 'By number of receiving sessions, exportable as CSV.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'When a cost changed and why',
                        'where' => 'Inventory → Cost Adjustment History',
                        'body'  => [
                            'Every change to an item\'s cost, with what caused it.',
                            'The answer to "why did the average cost move?" — usually a receipt at a different price, and this says which one.',
                        ],
                        'shot'  => 'cost-adjustments.png',
                        'fields' => [
                            ['Date', 'When the cost changed. Newest first.'],
                            ['Item', 'Which product\'s cost moved.'],
                            ['PO / Reference', 'The pallet or receipt that caused it. This is the column that answers "why".'],
                            ['Previous Cost / New Cost / Change', 'Before, after, and the difference.'],
                            ['Changed By', 'The person, or the system when a receipt did it.'],
                            ['Notes', 'Anything written at the time.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Things reported missing',
                        'where' => 'Inventory → Missing Item Reports',
                        'body'  => [
                            'What people have flagged as unaccounted for, and whether it was resolved.',
                            'A pattern here is worth more than any single report — the same item going missing repeatedly is a process problem, not bad luck.',
                        ],
                        'shot'  => 'missing-items.png',
                        'fields' => [
                            ['Pallet', 'Which delivery the shortage is against.'],
                            ['Item', 'What did not turn up.'],
                            ['Expected Quantity / Unit Cost', 'How many and at what price — the size of the claim.'],
                            ['Reason', 'Damaged in shipping, quantity shortage, billing mismatch.'],
                            ['Status', 'Whether it is still open. A pattern of the same item across vendors is worth more than any single report.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Filing a missing item report',
                        'where' => 'Inventory → Missing Item Reports → Create',
                        'body'  => [
                            'This is about a delivery, not a shelf: it records what a pallet was supposed to contain and did not.',
                            'Filing one changes no count. It is the paperwork that sits behind a credit request, so the number and the explanation stay two separate things.',
                        ],
                        'shot'  => 'missing-create.png',
                        'fields' => [
                            ['Pallet', 'Required. Which delivery came up short. This is what ties the report to a vendor and an invoice.'],
                            ['Missing Item', 'Required. The inventory item that did not turn up.'],
                            ['Expected Quantity', 'Required. How many the paperwork said were coming that are not here.'],
                            ['Unit Cost', 'What each one was billed at. Quantity × cost is the number you are asking the vendor to credit.'],
                            ['Reason for Missing Items', 'Damaged in shipping, quantity shortage, billing mismatch. Write what you saw — this is the sentence that gets quoted back.'],
                        ],
                        'note'  => 'File it while the pallet is in front of you. A shortage remembered a week later is a shortage you will lose the argument about.',
                    ],
                    [
                        'title' => 'How healthy the catalogue is',
                        'where' => 'Inventory → Product Health',
                        'body'  => [
                            'Not about stock — about the records themselves: how many items have no barcode, no sale target, no known cost, no alias.',
                            'Each gap here becomes a scan that finds nothing or a margin figure that is not there. This is the screen that tells you which is worth an afternoon.',
                        ],
                        'shot'  => 'product-health.png',
                        'fields' => [
                            ['Total Products / Total Aliases', 'The catalogue, and how many extra codes it answers to.'],
                            ['Avg Match Confidence', 'How certain the matching was, across completed sessions.'],
                            ['Auto-Match Rate', 'How much of receiving happened without a person deciding.'],
                            ['Missing UPC', 'Items with no barcode on file, each with an Edit link. Every one of these is a box that will not scan.'],
                            ['Never Received', 'In the catalogue but never received against a pallet — legacy records or hand-added ones.'],
                            ['UPC Coverage', 'The percentage with a code. This is the single number worth driving up.'],
                            ['Most Aliases / Most Received', 'Which products are best known to the scanner, and which come in most.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'The short version, inside the app',
                        'where' => 'Inventory → How It Works',
                        'body'  => [
                            'A one-page tour of the same module, arranged by job rather than by screen, with the setup state of your own install at the top.',
                            'Where this handbook is the reference, that page is the reminder — and it tells you when you have no locations, which is the one problem that makes every other screen look broken.',
                        ],
                        'shot'  => 'inventory-guide.png',
                        'fields' => [
                            ['Setup state', 'At the top: whether you have locations at all, and whether one is general storage. The one problem that makes every other screen look broken.'],
                            ['Full Cycle tab', 'The whole flow from a box arriving to the money, in order.'],
                            ['Other tabs', 'The same module grouped by job rather than by screen.'],
                        ],
                        'note'  => null,
                    ],
                    [
                        'title' => 'Printable reports',
                        'where' => 'Inventory → Reports',
                        'body'  => [
                            'Stock on hand, valuation and movement summaries, exportable as PDF or CSV.',
                            'Use these for a stock take or when someone outside the app needs the numbers.',
                        ],
                        'shot'  => 'inventory-report.png',
                        'fields' => [
                            ['Report type', 'Stock on hand, valuation or movement summary.'],
                            ['Filters', 'Location, category and date range where the report supports them.'],
                            ['PDF / CSV', 'PDF for someone outside the app, CSV for anything you want to work on.'],
                        ],
                        'note'  => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * The things people get wrong, on one page at the back.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function troubleshooting(): array
    {
        return [
            ['A location dropdown is empty', 'No active locations exist, or none of the type that screen needs. Inventory → Locations.'],
            ['A pallet line will not receive', 'It is not mapped to an inventory item yet. Map it on the pallet, or scan the box at the receiving station to map and receive in one step.'],
            ['A scan finds nothing', 'That code is on no item. Find the item by name and use Scan Barcode on its row menu to attach the code, then scan again.'],
            ['A scan is slow or will not settle', 'Glare and curved boxes are the usual cause. Tilt the box out of the light and fill the frame with the barcode. Typing the code by hand always works.'],
            ['The count is wrong', 'Do not edit the number. Use Adjust with a reason, or Reconciliation for a counted shelf, so the correction is recorded.'],
            ['Stock is in the wrong place', 'Use Stock Transfer, not two adjustments. A transfer records both sides as one movement.'],
            ['Margin shows nothing for an item', 'It has no sale target. Set one on the item, or find them all with the "Missing a sale target" filter.'],
            ['Cost looks wrong after receiving', 'The weighted average moved because a receipt came in at a different unit cost. Cost Adjustment History says which receipt did it.'],
            ['The same item appears twice', 'Two records were created for one product, which splits its stock and its cost. Inventory → Duplicate Detector finds them.'],
            ['A vendor code will not scan', 'The item exists but that code is not on it. Add it under Product Identities — an item can carry a manufacturer UPC, a vendor SKU and a case code, and all of them resolve to it.'],
            ['A box has no barcode at all', 'Inventory → Barcode Printer produces a label for it. Print it once and it scans forever after.'],
            ['Half a pallet is received and the person went home', 'Nothing is lost. Pause keeps it open, and the next person picks it up — Receiving History shows who did which part.'],
            ['Stock is on the shelf but the system says zero', 'Nothing was ever received for it, or it was received against a different item. Check Movement History for the item, then Duplicate Detector.'],
            ['Break Case is not on the menu', 'The item is not marked as a container, or it has no contents recorded. Both are set on its edit form.'],
            ['A quantity went the wrong way when I corrected it', 'Actual quantity now is what should remain, not the amount removed. Ten recorded, two gone, enter eight.'],
            ['A pallet line says "Something new" but we already stock it', 'The line was never linked to the item. Link it on the manifest, or scan the box at the station — either way the stock lands on the record you already have instead of a second one.'],
            ['A screen in this handbook is not in your sidebar', 'That is a role or a switched-off module, not a fault. Ask an admin — the Roles & Permissions screen decides it.'],
        ];
    }

    /**
     * Every inventory screen on one page, for someone who knows what they want
     * and only needs to be told where it lives.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function screenIndex(): array
    {
        return [
            ['All Inventory', 'The catalogue. Every item, its stock, cost and sale target. Most jobs start here.'],
            ['Quick Add', 'Create an item fast: name, cost, code.'],
            ['Import Sheet', 'Read a spreadsheet of products into the catalogue, after showing you every row it would touch.'],
            ['Quick Add Stock', 'Put units into a location without going through the catalogue.'],
            ['Quick Scan', 'Scan to look up, add stock, or receive against a pallet.'],
            ['Inventory Search', 'Search items, locations and stock together.'],
            ['Locations', 'The physical places stock can be. Admin only.'],
            ['Vendors', 'Who you buy from. Pallets are booked against one.'],
            ['Stock Levels', 'One row per item per location — how many, and where.'],
            ['Move or correct stock', 'One item\'s own screen for correcting a count or moving it between locations, with its reason.'],
            ['Stock Transfer', 'Move stock between locations. Records both sides.'],
            ['Reconciliation', 'Record a physical count; the difference is written as an adjustment.'],
            ['Movement History', 'Every change to every quantity, with who and why.'],
            ['Pallets', 'Deliveries. Stage one, map its lines, receive it.'],
            ['Manifest Lines', 'The typing grid for what a pallet should contain — one row per product.'],
            ['Import Manifest', 'Reads a vendor spreadsheet into pallet lines instead of retyping it.'],
            ['Pallet Status', 'Every pallet in flight on one board: staged, being worked, finished.'],
            ['Receiving Sessions', 'Each stint of work at the receiving station.'],
            ['Receiving History', 'What came off each pallet, per item, exportable.'],
            ['Pallet Receiving History', 'The same events grouped by pallet.'],
            ['Shipments', 'Inbound deliveries that have not become pallets yet.'],
            ['Product Identities', 'The extra codes an item answers to.'],
            ['Duplicate Detector', 'Finds records that are probably the same product.'],
            ['Product Health', 'How complete the catalogue is: what has no barcode, no cost, no sale target.'],
            ['Handbook', 'This handbook on screen, section by section, with a search across it.'],
            ['How It Works', 'The one-page version, arranged by job, with your install\'s setup state at the top.'],
            ['Barcode Printer', 'Labels for items that arrive without a scannable code.'],
            ['Missing Item Reports', 'What has been flagged as unaccounted for.'],
            ['Inventory Value', 'What is on the shelf at weighted average cost.'],
            ['Inventory Age', 'How long things have been sitting, and what that is worth.'],
            ['Inventory Analytics', 'Levels, values and trends over time.'],
            ['Product Insights', 'One product\'s cost, movement and performance history.'],
            ['Receiving Analytics', 'Throughput, shortfalls and how long pallets take.'],
            ['Cost Adjustment History', 'Every change to an item\'s cost, and what caused it.'],
            ['Reports', 'Printable stock, valuation and movement summaries.'],
        ];
    }
}
