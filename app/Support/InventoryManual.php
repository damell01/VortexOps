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
                        'title' => 'Add your vendors',
                        'where' => 'Inventory → Vendors',
                        'body'  => [
                            'A vendor is who you buy from. Pallets are booked against one, which is how cost history ends up attributable.',
                            'You can add a vendor while creating a pallet, but doing it here first keeps the names consistent.',
                        ],
                        'shot'  => 'vendors.png',
                        'note'  => null,
                    ],
                ],
            ],

            [
                'title' => 'The catalogue',
                'blurb' => 'Every physical thing you stock needs one record, and exactly one. Duplicate records '
                    . 'are the commonest cause of a count that looks wrong for no reason.',
                'steps' => [
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
                        'title' => 'Add an item properly',
                        'where' => 'Inventory → All Inventory → Add Item',
                        'body'  => [
                            'The full form: identity, costs, container settings, notes.',
                            '<b>List Unit Cost</b> is the fallback price used until real receipts exist. <b>Sale Price / Target</b> is what it should sell for.',
                            'The <b>Margin Potential</b> line answers as you type. It is not stored — it is recalculated from the weighted average cost every time you look, so it never goes stale after a receipt.',
                            'Mark it a <b>container</b> only if it holds other items you will break it into. A booster box is a box to a person, but a container here means something with recorded contents.',
                        ],
                        'shot'  => 'item-create.png',
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
                        'note'  => 'Use this for stock arriving outside a pallet. Anything that came on a delivery should be received against the pallet instead, so the cost and the paperwork stay together.',
                    ],
                    [
                        'title' => 'Adjust a count, with a reason',
                        'where' => 'row menu → Adjust',
                        'body'  => [
                            'For a single correction: the count says nine, the shelf says eight.',
                            'Say what the difference is and why. The adjustment is written to Movement History with your name on it.',
                        ],
                        'shot'  => 'adjust-modal.png',
                        'note'  => null,
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
                        'title' => 'Stage the pallet',
                        'where' => 'Inventory → Pallets → New Pallet',
                        'body'  => [
                            'Vendor and PO number, then a line per product with what the paperwork says you should be getting: expected cases, units per case, unit cost.',
                            'Put the unit cost on the line if you know it — that is the number that becomes the item\'s weighted average when the line is received.',
                            'Each line has to be mapped to an inventory item before it can be received. Map it here, or let a scan map it for you at the receiving station.',
                        ],
                        'shot'  => 'pallet-create.png',
                        'note'  => 'You do not need a manifest at all. Start a blank pallet and scan what turned up — lines are created as you go.',
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
                'blurb' => 'A USB or Bluetooth gun and a phone camera both work everywhere a scan is accepted. '
                    . 'What a scan <i>does</i> depends on which screen and which mode you are in.',
                'steps' => [
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
                        'shot'  => 'item-actions.png',
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
            ['Stock Transfer', 'Move stock between locations. Records both sides.'],
            ['Reconciliation', 'Record a physical count; the difference is written as an adjustment.'],
            ['Movement History', 'Every change to every quantity, with who and why.'],
            ['Pallets', 'Deliveries. Stage one, map its lines, receive it.'],
            ['Receiving Sessions', 'Each stint of work at the receiving station.'],
            ['Receiving History', 'What came off each pallet, per item, exportable.'],
            ['Pallet Receiving History', 'The same events grouped by pallet.'],
            ['Shipments', 'Inbound deliveries that have not become pallets yet.'],
            ['Product Identities', 'The extra codes an item answers to.'],
            ['Duplicate Detector', 'Finds records that are probably the same product.'],
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
