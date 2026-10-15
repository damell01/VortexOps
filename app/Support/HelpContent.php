<?php

namespace App\Support;

use App\Filament\Pages;
use App\Filament\Resources;

/**
 * The words behind the "?" on every page (App\Support\HelpTopics): what the
 * page is for, how to use it, and the setup guides it points to.
 *
 * Kept short on purpose — a page's help answers "what is this and what do I
 * do here"; the Handbook is the long version.
 */
class HelpContent
{
    /** Start-to-finish setup guides, linked from the pages they involve. */
    public static function guides(): array
    {
        return [
            'guide.payroll' => [
                'title' => 'Setting up payroll',
                'intro' => 'Everything payroll needs, in the order to set it up. After this, each week is: settle hours, approve reports, run the pay run.',
                'sections' => [
                    ['heading' => '1. Businesses', 'items' => [
                        'Admin → Businesses lists Vortex Collects and Estill Distribution. Drag each person into the business they work for.',
                        'Tick "Pay tips monthly" on a business that pays tips once a month.',
                    ]],
                    ['heading' => '2. Team rates', 'items' => [
                        'Payroll → Rates & Adjustments. Pick the business at the top, then "Edit team rates".',
                        'Choose how its streamers are paid by default: the show formula (profit share) or a sales split (the business keeps a %).',
                        'Set the default %, hourly rate and burden. Fulfillment staff get their own defaults below.',
                    ]],
                    ['heading' => '3. People who are paid differently', 'items' => [
                        'In "People & pay methods", open a person and tick only what is different for them.',
                        'Estill\'s hourly streamers: "Hourly + shipments + profit share", with their hourly rate, envelope and package rates, share % and any burden.',
                        'You can also set pay from a user\'s profile, in its Pay section.',
                    ]],
                    ['heading' => '4. Pay run types', 'items' => [
                        'In "Pay run types", add the runs you pay: weekly wages + profit share, and a monthly Tips run if a business pays tips monthly.',
                        'Turn on a schedule to have dates suggested, and auto-create if you want runs made for you.',
                    ]],
                    ['heading' => '5. Each week', 'items' => [
                        'Timekeeping: settle flagged hours. Payroll pays approved Hours Worked only.',
                        'Show report inbox: approve or send back reports.',
                        'Payroll: open the period, clear blockers, review the pay run, finalize, export, mark paid.',
                        'On each run, the Business account transfer card says what to move to the business\'s separate account.',
                    ]],
                ],
                'tips' => ['Try a change in Payroll Simulator first: it never touches real pay.'],
                'related' => ['guide.estill', 'page.rates', 'page.payroll'],
            ],
            'guide.estill' => [
                'title' => 'Estill Distribution setup',
                'intro' => 'Estill has two kinds of streamer, pays tips monthly, keeps its own stock, and moves its share to a separate account.',
                'sections' => [
                    ['heading' => 'Independent streamers', 'items' => [
                        'Estill\'s team default is a sales split: the business keeps a %, the streamer gets the rest.',
                        'Change a person\'s % in their pay; change one show\'s % under that show → Financials → "Business keeps (this show only)".',
                    ]],
                    ['heading' => 'Hourly streamers', 'items' => [
                        'Pay type "Hourly + shipments + profit share": hours × rate, envelopes and packages at their rates, and a share of (net − product cost − burden − hourly − shipment pay).',
                        'Give each their own stock: Inventory → Locations → "Give a streamer their own location".',
                    ]],
                    ['heading' => 'Stock, tips and the transfer', 'items' => [
                        'Inventory → Locations → "Set up business storage" for Estill, then move Estill\'s stock in. Its streamers then sell from it, not the shared Main Storage.',
                        'Businesses → Estill → "Pay tips monthly", plus a monthly Tips pay run type.',
                        'Each pay run\'s Business account transfer = product cost + (Whatnot net − what the run pays).',
                    ]],
                ],
                'related' => ['guide.payroll', 'guide.inventory'],
            ],
            'guide.inventory' => [
                'title' => 'Setting up inventory',
                'intro' => 'Where stock lives, how it gets in, and how it leaves when a show sells it.',
                'sections' => [
                    ['heading' => '1. Locations', 'items' => [
                        'Inventory → Locations. Start with a Main Storage everyone shares.',
                        'A business that keeps its own stock gets its own storage ("Set up business storage"); a streamer who holds stock gets their own location.',
                        'Add Returned and Damaged locations for stock you won\'t sell.',
                    ]],
                    ['heading' => '2. Vendors and products', 'items' => [
                        'Add the vendors you buy from.',
                        'Add products one at a time, with Quick Add, or many at once with Import Inventory Sheet (it previews everything before writing).',
                    ]],
                    ['heading' => '3. Getting stock in', 'items' => [
                        'Deliveries: Receiving → create a pallet, add its lines, then receive by scanning or "Receive all". Each receipt sets the item\'s cost.',
                        'Small top-ups: Add stock on the item, or Quick Add Stock.',
                        'Moving stock: Stock Transfer between locations.',
                    ]],
                    ['heading' => '4. Stock leaving', 'items' => [
                        'Streamers log what they used in each show report; approving the report takes it out of stock.',
                        'Count a location from time to time with Inventory Count to correct drift.',
                    ]],
                ],
                'tips' => ['Average cost is a live FIFO average of what\'s on hand, set by receiving — you never type it in.'],
                'related' => ['page.locations', 'page.receiving', 'page.inventory'],
            ],
            'guide.streamer' => [
                'title' => 'Adding a streamer',
                'intro' => 'From a new account to their first paid show.',
                'steps' => [
                    'Users → Add User. Give them the Streamer role and choose In-House or Remote.',
                    'Add aliases: the Whatnot names and title wording that mean them, so shows match to them automatically.',
                    'Put them in their business, and set any pay that differs from the team under Pay.',
                    'If they hold their own stock, give them their own inventory location.',
                    'They sign in, pick a dashboard buddy, and log each show from their dashboard.',
                ],
                'related' => ['guide.payroll', 'guide.shows'],
            ],
            'guide.shows' => [
                'title' => 'How a show flows',
                'intro' => 'From Whatnot to payroll.',
                'steps' => [
                    'Shows sync from Whatnot with their sales, earnings and shipments, and match to streamers by alias.',
                    'After the show the streamer files a report: what they used (Sold, Giveaway, Promo, Other), hours and notes.',
                    'The report is approved automatically or waits in the Show report inbox, depending on your review policy.',
                    'Approving posts the stock and makes the show ready for payroll.',
                    'The next pay run picks it up and pays everyone on it.',
                ],
                'related' => ['page.inbox', 'page.payroll'],
            ],
        ];
    }

    /** What each page is for and how to use it. */
    public static function pages(): array
    {
        return [
            'page.admin-dashboard' => [
                'title' => 'Dashboard',
                'intro' => 'What needs you today: reports to review, unmatched items, open shipments and payroll waiting.',
                'steps' => ['Start with the action cards — each opens the list behind it.', 'Use the channel picker in the top bar to look at one channel.'],
                'related' => ['guide.shows', 'guide.payroll', 'guide.inventory'],
            ],
            'page.my-dashboard' => [
                'title' => 'Your dashboard',
                'intro' => 'Your next show, your numbers, your time clock, and every show you need to report.',
                'steps' => [
                    'To complete: shows waiting for your report. "Log sold items" starts one.',
                    'Needs changes: an admin sent it back. Fix it and submit again.',
                    'Submitted and Upcoming show what\'s in review and what\'s next.',
                    'Clock in and out from the time clock strip.',
                ],
                'tips' => ['Filed a report and need to fix it? "Need to change something?" asks an admin to reopen it.', 'Click your buddy to say hi; change it under Profile.'],
            ],
            'page.shows' => [
                'title' => 'Shows',
                'intro' => 'Every Whatnot show: when it airs, who streamed it, and where it is in its flow.',
                'steps' => ['Find a show by date, channel or streamer.', 'Open it to see its sales, report, shipments and pay in one place.', 'Assign streamers or fix the channel if Whatnot matched it wrongly.'],
                'related' => ['guide.shows'],
            ],
            'page.show' => [
                'title' => 'A show',
                'intro' => 'One show in full: details, financials, Whatnot analytics and the streamer report.',
                'steps' => ['Overview: title, date, channel and streamers.', 'Financials: gross, net, tips, and the business\'s % for this show if it differs.', 'Analytics: Whatnot\'s numbers for the show.'],
                'related' => ['guide.shows', 'show.house_cut'],
            ],
            'page.inbox' => [
                'title' => 'Show report inbox',
                'intro' => 'Every streamer\'s show report as a card, with their pay and anything missing.',
                'steps' => [
                    'Needs attention: waiting on you, sent back, or asking to be reopened.',
                    'Review a card, then approve it or request changes with a reason.',
                    '"Submit for streamer" files a report for them; "Reset" clears it back to not started.',
                ],
                'tips' => ['Amber on a card means something pay or stock depends on is missing.', 'Shows before the report start date are history: listed, never counted as waiting.'],
                'related' => ['guide.shows'],
            ],
            'page.my-reports' => [
                'title' => 'Your show reports',
                'intro' => 'Every report you\'ve filed or need to file.',
                'steps' => ['Open a show to log what you used.', 'Submitted reports are locked; ask an admin to reopen one if you need to change it.'],
            ],
            'page.report' => [
                'title' => 'Reviewing a report',
                'intro' => 'One show\'s report: every item used, hours, notes and what it costs.',
                'steps' => ['Check each line\'s usage (Sold, Giveaway, Promo, Other) and quantity.', 'Match any unlisted item to the real product.', 'Approve, or request changes with a clear reason.'],
                'related' => ['guide.shows'],
            ],
            'page.end-of-stream' => [
                'title' => 'Show report',
                'intro' => 'Log what you used in this show, in three steps.',
                'steps' => [
                    'Items: add every item used and mark it Sold, Giveaway, Promo or Other.',
                    'Time & notes: check your hours and explain anything unusual.',
                    'Review everything, then submit.',
                ],
                'tips' => ['Not in the catalogue? Use Unlisted Item rather than something close.', 'After submitting it locks; ask to reopen it if you need a change.'],
            ],
            'page.payroll' => [
                'title' => 'Payroll',
                'intro' => 'The pay period at a glance: what is ready, what is blocked, and the pay run.',
                'steps' => [
                    'Pick the pay period at the top.',
                    'Work the show list by stage: Needs report, Needs approval, Blocked, then Ready.',
                    'Create or open the pay run, clear its blockers, finalize, export, and mark paid.',
                ],
                'related' => ['guide.payroll', 'guide.estill'],
            ],
            'page.pay-runs' => [
                'title' => 'Pay runs',
                'intro' => 'Every pay run, newest first.',
                'steps' => ['Open one to review it.', 'Create a run when you need one; its type decides what it pays.'],
                'related' => ['pay.run_types', 'guide.payroll'],
            ],
            'page.pay-run' => [
                'title' => 'A pay run',
                'intro' => 'Review → Finalized → Sent to ADP → Paid.',
                'steps' => [
                    'Check Readiness: blockers must be zero before you can finalize.',
                    'Open a person to see the lines behind their total.',
                    'Finalize to lock the amounts, export for ADP, then mark it paid.',
                ],
                'tips' => ['The Business account transfer card says what to move to the business\'s separate account.'],
                'related' => ['payrun.transfer', 'guide.payroll'],
            ],
            'page.new-pay-run' => [
                'title' => 'New pay run',
                'intro' => 'Pick the type and the dates; the run gathers every ready pay line in them.',
                'steps' => ['Choose a pay run type (or what it pays).', 'Confirm the dates and business.', 'Create, then review it.'],
                'related' => ['pay.run_types'],
            ],
            'page.rates' => [
                'title' => 'Rates & Adjustments',
                'intro' => 'How each business\'s team is paid, who is paid differently, and the types of pay run.',
                'steps' => [
                    'Pick the business at the top.',
                    '"Edit team rates" sets the default for everyone in it.',
                    'In People & pay methods, open a person to override only what is different for them.',
                    'Pay run types: what each run pays and how often.',
                ],
                'related' => ['guide.payroll', 'pay.shift_share', 'pay.sales_split', 'pay.run_types'],
            ],
            'page.simulator' => [
                'title' => 'Payroll Simulator',
                'intro' => 'Try pay on made-up shows. Nothing here touches real pay, stock or reports.',
                'steps' => ['Pick 1 show, 1 week or a 4-week month.', 'Change revenue, hours, shipments and products.', 'See each person\'s pay and the working behind it.'],
            ],
            'page.businesses' => [
                'title' => 'Businesses',
                'intro' => 'Each person works for one business, and each business runs its own payroll.',
                'steps' => ['Drag people between columns, or tick several and move them together.', 'Edit a business to rename it or turn on "Pay tips monthly".'],
                'related' => ['business.tips_monthly', 'guide.estill'],
            ],
            'page.time' => [
                'title' => 'Time & Attendance',
                'intro' => 'Payroll pays approved Hours Worked only. Settle what\'s flagged here before the pay run.',
                'steps' => ['Work the flagged entries: missing clock-outs, long shifts, unlinked shows.', 'Approve or correct each one.'],
                'related' => ['guide.payroll'],
            ],
            'page.timekeeping' => [
                'title' => 'Timekeeping',
                'intro' => 'Clock in and out, link your time to a show, and fix your own entries.',
                'steps' => ['Clock in when you start; pick the show if it\'s for one.', 'Clock out when you finish.', 'Fix a mistake in your history; an admin approves the change.'],
            ],
            'page.profit-loss' => [
                'title' => 'Business Profitability',
                'intro' => 'Profit and loss by business and period: sales, product cost, pay and what\'s left.',
                'steps' => ['Pick the period and business.', 'Work the checklist so the numbers are complete.', 'Open a line for the shows behind it.'],
            ],
            'page.reports' => [
                'title' => 'Reports',
                'intro' => 'Every report, grouped by what it answers.',
                'steps' => ['Pick a report from its group.', 'Most can be filtered by channel and date, and downloaded.'],
            ],
            'page.streamers' => [
                'title' => 'Streamers',
                'intro' => 'Everyone who streams or fulfills, with their business and pay.',
                'steps' => ['Open a person for their shows, pay and stock.', 'Set up new people from Users.'],
                'related' => ['guide.streamer'],
            ],
            'page.users' => [
                'title' => 'Users',
                'intro' => 'Accounts, roles, aliases and pay.',
                'steps' => ['Add User, then choose their role.', 'For a streamer: In-House or Remote, aliases, business and pay.', 'Leave Password blank when editing to keep it.'],
                'related' => ['guide.streamer', 'guide.payroll'],
            ],
            'page.inventory' => [
                'title' => 'All inventory',
                'intro' => 'Every product, its stock and what it\'s worth.',
                'steps' => [
                    'Search by name, SKU, UPC, barcode or brand.',
                    'Click a stock tile (In stock, Low, Out) to show only those.',
                    'Each card: History, Add stock, View, Edit, Stock (move or correct), Scan (set its barcode), Delete.',
                ],
                'tips' => ['Switch to Table for a sortable list.'],
                'related' => ['guide.inventory'],
            ],
            'page.item' => [
                'title' => 'A product',
                'intro' => 'One product in full: stock by location, cost, history and codes.',
                'steps' => ['See where its stock is.', 'Add, move or correct stock from the actions.', 'History shows every change and who made it.'],
                'related' => ['guide.inventory'],
            ],
            'page.locations' => [
                'title' => 'Inventory locations',
                'intro' => 'Where stock lives, and who sells from it.',
                'steps' => [
                    '"New location": name it, say what it\'s for, and who uses it.',
                    '"Give a streamer their own location" or "Set up business storage" do it in one step.',
                    'Receive or transfer stock into it.',
                ],
                'tips' => ['A business with storage of its own stops selling from the shared Main Storage — move its stock in right away.'],
                'related' => ['locations.audience', 'guide.inventory'],
            ],
            'page.receiving' => [
                'title' => 'Receiving',
                'intro' => 'Deliveries (pallets) and getting their stock in.',
                'steps' => [
                    'Create a pallet with its vendor and PO number.',
                    'Add its lines: item, location, cases and unit cost — or import the manifest.',
                    'Receive by scanning each case, or "Receive all" on a line.',
                ],
                'tips' => ['A partly received pallet keeps its place; carry on later.', 'Receiving sets each item\'s cost.'],
                'related' => ['guide.inventory'],
            ],
            'page.transfer' => [
                'title' => 'Stock transfer',
                'intro' => 'Move stock between locations.',
                'steps' => ['Pick where from and where to.', 'Add the items and quantities.', 'Transfer. Moving stock never changes its cost.'],
                'related' => ['guide.inventory'],
            ],
            'page.count' => [
                'title' => 'Inventory count',
                'intro' => 'Count a location and correct the system to match.',
                'steps' => ['Pick the location.', 'Scan or find each item and enter what you counted.', 'Save: the differences become adjustments.'],
            ],
            'page.import-sheet' => [
                'title' => 'Import inventory sheet',
                'intro' => 'Read a product spreadsheet into the catalogue, after you\'ve seen what it will do.',
                'steps' => ['Upload the sheet.', 'Read the preview: new, updated and skipped rows.', 'Apply.'],
                'tips' => ['A price cell with a formula is skipped and reported, never turned into $0.'],
            ],
            'page.scanner' => [
                'title' => 'Inventory scanner',
                'intro' => 'Scan to look items up, add stock, or receive a delivery.',
                'steps' => ['Choose what you\'re doing.', 'Scan, or type the code.'],
            ],
            'page.vendors' => [
                'title' => 'Vendors',
                'intro' => 'Who you buy from. Pallets and costs link back to them.',
                'steps' => ['Add a vendor before receiving from them.'],
            ],
            'page.fulfillment' => [
                'title' => 'Fulfillment center',
                'intro' => 'Pick, pack and close out each show\'s shipments.',
                'steps' => ['Open a show with open shipments.', 'Work its packing lines and keep tracking current.', 'Close it out.'],
            ],
            'page.whatnot-losses' => [
                'title' => 'Whatnot losses',
                'intro' => 'Shows where Whatnot paid out less than nothing, and why.',
                'steps' => ['Review each loss and its cause.'],
            ],
            'page.notifications' => [
                'title' => 'Notifications',
                'intro' => 'Who is told about what, and when.',
                'steps' => ['Open a notification to choose who gets it and how.'],
            ],
            'page.settings' => [
                'title' => 'Settings',
                'intro' => 'How the app behaves: review policy, report start date, modules and more.',
                'steps' => ['Change a setting and save. Most take effect straight away.'],
            ],
        ];
    }

    /**
     * Page or resource class => topic, or ['admin' => topic, 'streamer' => topic].
     *
     * @return array<class-string, string|array{admin?:string, streamer?:string}>
     */
    public static function pageMap(): array
    {
        return [
            Pages\DashboardImproved::class => ['admin' => 'page.admin-dashboard', 'streamer' => 'page.my-dashboard'],
            Pages\Shows::class => 'page.shows',
            Resources\ShowResource\Pages\ListShows::class => 'page.shows',
            Resources\ShowResource\Pages\EditShow::class => 'page.show',
            Resources\ShowResource\Pages\ViewShow::class => 'page.show',
            Resources\StreamerLogResource\Pages\ListStreamerLogEntries::class => ['admin' => 'page.inbox', 'streamer' => 'page.my-reports'],
            Resources\StreamerLogResource\Pages\EditStreamerLogEntry::class => 'page.report',
            Resources\StreamerLogResource\Pages\ViewStreamerLogEntry::class => 'page.report',
            Pages\EndOfStreamForm::class => 'page.end-of-stream',
            Pages\PayrollOverview::class => 'page.payroll',
            Resources\WeeklyPayoutBatchResource\Pages\ListWeeklyPayoutBatches::class => 'page.pay-runs',
            Resources\WeeklyPayoutBatchResource\Pages\ViewWeeklyPayoutBatch::class => 'page.pay-run',
            Resources\WeeklyPayoutBatchResource\Pages\CreateWeeklyPayoutBatch::class => 'page.new-pay-run',
            Pages\PaymentStructures::class => 'page.rates',
            Pages\PayrollSimulator::class => 'page.simulator',
            Pages\Businesses::class => 'page.businesses',
            Pages\TimeAttendance::class => 'page.time',
            Pages\Timekeeping::class => 'page.timekeeping',
            Pages\ProfitLoss::class => 'page.profit-loss',
            Pages\ReportsOverview::class => 'page.reports',
            Resources\StreamerResource::class => 'page.streamers',
            Resources\UserResource::class => 'page.users',
            Resources\InventoryItemResource\Pages\ListInventoryItems::class => 'page.inventory',
            Resources\InventoryItemResource\Pages\ViewInventoryItem::class => 'page.item',
            Resources\InventoryItemResource\Pages\EditInventoryItem::class => 'page.item',
            Resources\InventoryItemResource\Pages\ManageStock::class => 'page.item',
            Resources\InventoryLocationResource::class => 'page.locations',
            Resources\PalletResource::class => 'page.receiving',
            Pages\StockTransfer::class => 'page.transfer',
            Pages\InventoryCount::class => 'page.count',
            Pages\ImportInventorySheet::class => 'page.import-sheet',
            Pages\InventoryScanner::class => 'page.scanner',
            Resources\VendorResource::class => 'page.vendors',
            Pages\FulfillmentCenter::class => 'page.fulfillment',
            Pages\WhatnotLosses::class => 'page.whatnot-losses',
            Pages\NotificationSettings::class => 'page.notifications',
            Pages\AppSettings::class => 'page.settings',
        ];
    }
}
