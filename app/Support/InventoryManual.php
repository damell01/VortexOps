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
                        'note'  => null,
                    ],
                    [
                        'title' => 'Edit an item',
                        'where' => 'Inventory → All Inventory → row menu → Edit',
                        'body'  => [
                            'Change anything about the record itself: name, codes, costs, sale target, reorder level, notes.',
                            'Editing does <b>not</b> change how many you have. Quantities only move through receiving, transfers, adjustments and reconciliation — each of which records what changed and why.',
                        ],
                        'shot'  => 'item-edit.png',
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
                        'title' => 'Import a manifest instead of typing one',
                        'where' => 'Pallet → Import Manifest',
                        'body'  => [
                            'If the vendor sent a spreadsheet, this reads it into lines rather than making you retype it.',
                            'You map the file\'s columns to item, quantity and cost once, then review what it produced before it is committed.',
                        ],
                        'shot'  => 'pallet-manifest.png',
                        'note'  => 'Check the unit cost column landed in the right place. A manifest imported with cost in the wrong column moves every weighted average on the pallet.',
                    ],
                    [
                        'title' => 'Review the pallet before you start',
                        'where' => 'Inventory → Pallets → open one',
                        'body'  => [
                            'Lines, mappings, expected quantities and what has been received so far.',
                            'Fix a wrong mapping here rather than at the receiving station, where you will be holding a box.',
                        ],
                        'shot'  => 'pallet-view.png',
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
                        'note'  => null,
                    ],
                    [
                        'title' => 'Receive it — all at once, or box by box',
                        'where' => 'Inventory → Pallets → Receive',
                        'body'  => [
                            'The receiving station shows expected, received and remaining, with a progress bar per pallet.',
                            '<b>Receive all</b> on a line books the whole expected quantity in one action. Use it when the count on the paperwork is right and you do not need to handle each box.',
                            '<b>Receive some</b> books a partial quantity — say how many actually turned up.',
                            '<b>Mark short</b> records that the rest did not arrive, so the difference is documented rather than argued about later.',
                            'Or scan each box: type or scan into the code field and press <b>Receive Scan</b>. Every scan books one unit against its line.',
                        ],
                        'shot'  => 'pallet-receive.png',
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
                        'note'  => null,
                    ],
                    [
                        'title' => 'The pallet\'s own history',
                        'where' => 'Inventory → Pallet Receiving History',
                        'body'  => [
                            'The same events grouped by pallet rather than by session — how long each took, what was short, what was still outstanding when it closed.',
                        ],
                        'shot'  => 'pallet-history.png',
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
                        'note'  => null,
                    ],
                    [
                        'title' => 'What is moving, and how fast',
                        'where' => 'Inventory → Velocity Analytics',
                        'body'  => [
                            'Turnover per item — what sells through quickly and what does not.',
                            'Read alongside Inventory Age: slow and valuable is a different problem from slow and cheap.',
                        ],
                        'shot'  => 'velocity.png',
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
            ['Velocity Analytics', 'Turnover — what sells through and what does not.'],
            ['Inventory Analytics', 'Levels, values and trends over time.'],
            ['Product Insights', 'One product\'s cost, movement and performance history.'],
            ['Receiving Analytics', 'Throughput, shortfalls and how long pallets take.'],
            ['Cost Adjustment History', 'Every change to an item\'s cost, and what caused it.'],
            ['Reports', 'Printable stock, valuation and movement summaries.'],
        ];
    }
}
