<?php

namespace App\Support;

class ReportsManual
{
    public static function title(): string { return 'Reports & Analytics Handbook'; }
    public static function subtitle(): string { return 'Where the money went: Whatnot earnings, business profit, losses, streamers, inventory and the alerts that watch them.'; }

    public static function sections(): array
    {
        return [
            [
                'title' => 'Start here',
                'icon' => 'heroicon-o-chart-bar',
                'blurb' => 'Every report sits under the Reports menu. Reports Overview is the front page: the headline numbers and what needs a look.',
                'steps' => [
                    [
                        'title' => 'Find a report',
                        'where' => 'Reports (top menu)',
                        'screen' => \App\Filament\Pages\ReportsOverview::class,
                        'body' => [
                            'Hover or click Reports in the top menu. Reports are grouped by what they answer: Financial for money (Whatnot performance, losses, business profit and the ledger) and Operations for people and stock.',
                            'Two kinds of number appear across the reports, and they are not the same thing. Whatnot earnings are Whatnot\'s own estimate after its fees, available as soon as a show syncs. Business profit also takes off the cost of the products used (COGS) and what was paid to people, so it is only final once those are recorded.',
                        ],
                        'shot' => 'rep-menu.png',
                        'fields' => [
                            ['Reports Overview', 'The front page: headline numbers, the earnings trend and what needs attention.'],
                            ['Whatnot Performance', 'Sales, units, Whatnot\'s estimated earnings, channels and top streamers.'],
                            ['Whatnot Losses', 'Shows where Whatnot\'s estimated earnings came in below $0.'],
                            ['Business Profitability', 'Profit after COGS and pay, period by period (Profit & Loss).'],
                            ['Ledger', 'Every credit and debit Whatnot recorded, by channel.'],
                            ['Streamer Analytics', 'Shows, revenue and payouts per streamer.'],
                            ['Inventory Reports', 'Stock value, movement, low stock and idle inventory.'],
                        ],
                        'note' => 'A report you cannot open is not shown in the menu; access is set on Admin → Roles & Permissions.',
                    ],
                    [
                        'title' => 'Read the Reports Overview',
                        'where' => 'Reports → Reports Overview',
                        'screen' => \App\Filament\Pages\ReportsOverview::class,
                        'body' => [
                            'Pick the period and business at the top right. The four cards give the headline numbers for that period; the chart shows Whatnot\'s estimated earnings day by day, dipping below the line on days that lost money.',
                            'Needs Attention lists the three things worth chasing — shows Whatnot paid out negative on, finalized shows that lost money, and shows still waiting on costs or pay. Click one to open the report already filtered to it.',
                        ],
                        'shot' => 'rep-overview.png',
                        'fields' => [
                            ['Period', 'This Week, Last Week, This Month, Last Month, Last 30 Days or This Year.'],
                            ['Business', 'All businesses, or just one.'],
                            ['Gross Sales', 'What buyers paid across the period\'s shows.'],
                            ['Whatnot Est. Earnings', 'Whatnot\'s estimate after its fees, before our own costs.'],
                            ['Finalized Business Profit', 'Profit from shows whose earnings, COGS and pay are all in. A dash means none are final yet.'],
                            ['Pending Financials', 'Shows still waiting on costs or a pay run.'],
                            ['Whatnot Earnings Trend', 'Estimated earnings by day for the period.'],
                            ['Needs Attention', 'Negative Whatnot earnings, finalized business losses, and pending costs & payroll — each opens its report.'],
                            ['Report Library', 'A card for every report with a one-line description.'],
                        ],
                        'note' => 'Business profit counts finalized shows only, so it fills in as COGS are approved and pay runs are completed.',
                    ],
                ],
            ],
            [
                'title' => 'Money: Whatnot and the business',
                'icon' => 'heroicon-o-currency-dollar',
                'blurb' => 'Whatnot Performance and the Ledger show what Whatnot paid; Business Profitability shows what the business kept; Whatnot Losses catches the shows that lost money.',
                'steps' => [
                    [
                        'title' => 'Check Whatnot performance',
                        'where' => 'Reports → Whatnot Performance',
                        'screen' => \App\Filament\Pages\Reports::class,
                        'body' => [
                            'Pick a preset (Last 7 days, Last 30 days, Last 90 days, This year) or your own dates and Apply; narrow to one channel if you like. Each card compares with the period before.',
                            'Recorded COGS reads Not recorded until show reports carry costs — a missing cost is never treated as a verified $0. Further down: revenue by week, top streamers, every channel, and Compare two periods.',
                        ],
                        'shot' => 'rep-performance.png',
                        'fields' => [
                            ['Last 7 / 30 / 90 days, This year', 'Quick periods; or type dates and Apply.'],
                            ['All channels', 'Narrow the report to one Whatnot channel.'],
                            ['Shows / Units Sold / Gross Revenue', 'Volume and sales, with the change from the period before.'],
                            ['Whatnot Est. Earnings', 'Whatnot\'s estimate after its fees.'],
                            ['Recorded COGS / Gross minus recorded COGS', 'Product cost from approved reports, and sales less that cost. Provisional while costs are missing.'],
                            ['Tips', 'Tips received in the period.'],
                            ['Revenue by Week', 'A weekly line; hover a point for that week\'s shows, revenue and units.'],
                            ['Top Streamers', 'Each primary streamer\'s shows, gross and estimated earnings.'],
                            ['Channel Performance', 'Shows, units, gross and estimated earnings per channel.'],
                            ['Compare two periods', 'This period against the previous period or one you pick.'],
                            ['Export CSV', 'Downloads the figures on screen.'],
                        ],
                        'note' => 'Cancelled shows are left out of every figure.',
                    ],
                    [
                        'title' => 'Find shows that lost money on Whatnot',
                        'where' => 'Reports → Whatnot Losses',
                        'screen' => \App\Filament\Pages\WhatnotLosses::class,
                        'body' => [
                            'Whatnot Losses lists every show whose estimated Whatnot earnings came in below $0 for the period, worst first. Narrow by business or streamer, and click a column heading to sort.',
                            'Send Summary sends the list now to the people on the Negative Whatnot earnings notification (or a person you pick), by email, in-app or both. Preview Summary shows exactly what they will get. The automatic daily or weekly summary is set up in Notifications.',
                        ],
                        'shot' => 'rep-losses.png',
                        'more' => ['rep-losses-send.png'],
                        'fields' => [
                            ['Period', 'This week, Last week, Last 7 / 30 days, This month, Last month or a custom range.'],
                            ['Businesses / Streamers', 'Narrow the list to one of each.'],
                            ['Negative Shows', 'How many shows lost money.'],
                            ['Estimated Whatnot Earnings', 'Their combined estimated earnings.'],
                            ['Businesses Affected', 'How many businesses those shows belong to.'],
                            ['Last Synced', 'When the show\'s Whatnot figures were last refreshed.'],
                            ['Export CSV', 'Downloads the list.'],
                            ['Send Summary', 'Opens the send window: who to send to, email and/or in-app, Preview Summary and Send Summary.'],
                            ['Manage summaries in Notification Settings', 'Where the scheduled daily or weekly summary is set.'],
                        ],
                        'note' => 'These are Whatnot\'s estimates and can change as analytics refresh. Product cost and pay are not included — see Business Profitability for those.',
                    ],
                    [
                        'title' => 'See what the business made',
                        'where' => 'Reports → Business Profitability',
                        'screen' => \App\Filament\Pages\ProfitLoss::class,
                        'body' => [
                            'Choose Daily, Weekly, Monthly or Yearly and step through periods with the arrows (Today jumps back). Narrow by channel, business or streamer. Each card compares with the period before.',
                            'A show\'s business profit is final only when its Whatnot earnings, COGS and pay are all in. The yellow banner says how many shows are not there yet, and the Finalization Checklist says what is missing and links to where to fix it.',
                            'Click any show to open its breakdown: what is still missing, the pay runs it is in, and gross sales → Whatnot earnings → tips → COGS → worker payouts → business profit.',
                        ],
                        'shot' => 'rep-profit.png',
                        'more' => ['rep-profit-show.png'],
                        'fields' => [
                            ['Daily / Weekly / Monthly / Yearly', 'The length of the period; the arrows move one period back or forward.'],
                            ['Channels / businesses / streamers', 'Narrow the report to one of each.'],
                            ['Shows / Sales / Whatnot Earnings', 'Volume, gross sales and Whatnot\'s estimate, against the period before.'],
                            ['Recorded COGS', 'Product cost from approved reports, and how many shows are missing it.'],
                            ['Worker Payouts', 'Pay for those shows, and how many are not final.'],
                            ['Finalized Profit', 'Profit from finalized shows, with the figure including incomplete shows below it.'],
                            ['All Shows / Finalized / Incomplete / Business Losses / Whatnot Losses', 'Tabs that narrow the show list.'],
                            ['Finalization Checklist', 'Whatnot earnings in, inventory costs logged, hours approved and pay runs paid — each with a link to finish it.'],
                            ['Show breakdown', 'Click a show: still missing, pay runs, and the financial breakdown. View Show opens it.'],
                            ['How streamers are doing / Breakdown', 'Each streamer\'s results, and profit by streamer, channel, business, weekday or time slot.'],
                            ['Export / Send Summary', 'Download the period, or send the losses summary now.'],
                        ],
                        'note' => 'Whatnot earnings already include Whatnot\'s fees, so fees are never taken off twice.',
                    ],
                    [
                        'title' => 'Reconcile the Whatnot ledger',
                        'where' => 'Reports → Ledger',
                        'screen' => \App\Filament\Resources\WhatnotLedgerResource::class,
                        'body' => [
                            'The ledger is every credit and debit Whatnot recorded — order earnings, fees, shipping labels, tips, refunds and payouts to the bank. It is imported from Whatnot nightly.',
                            'Pick a channel along the top, then narrow by date, type, status or search for an order number. The cards total what is on screen.',
                        ],
                        'shot' => 'rep-ledger.png',
                        'fields' => [
                            ['Channel chips', 'All Channels or one channel.'],
                            ['Date / transaction type / status', 'Narrow the list; dates go from All time to a custom range.'],
                            ['Search order or details', 'Find an order number or text in the details.'],
                            ['Credits / Debits / Net Activity', 'Money in, money out, and the difference, for what is shown.'],
                            ['Processing', 'Transactions Whatnot has not completed yet.'],
                            ['Amount', 'Green for money in, red for money out.'],
                            ['Status', 'Completed, processing or pending, as Whatnot reports it.'],
                            ['Export CSV', 'Downloads the filtered list.'],
                        ],
                        'note' => 'An empty ledger usually means the nightly import has not run for that channel yet.',
                    ],
                ],
            ],
            [
                'title' => 'People and stock',
                'icon' => 'heroicon-o-user-group',
                'blurb' => 'How each streamer is doing, and the money tied up in inventory.',
                'steps' => [
                    [
                        'title' => 'Compare streamers',
                        'where' => 'Reports → Streamer Analytics',
                        'screen' => \App\Filament\Pages\StreamerAnalytics::class,
                        'body' => [
                            'Pick the dates and, if you like, a few streamers to compare instead of the full team. Streamer Overview gives totals and a card per streamer; Weekly Breakdown gives the same week by week.',
                            'Show hours are how long a stream ran on Whatnot, not hours worked for payroll — those come from Timekeeping.',
                        ],
                        'shot' => 'rep-streamers.png',
                        'fields' => [
                            ['Dates', 'The period the report covers.'],
                            ['Compare streamers', 'Full team, or the streamers you pick.'],
                            ['Streamer Overview / Weekly Breakdown', 'Totals per streamer, or week by week.'],
                            ['Total Shows / Gross Revenue', 'Shows and sales in the period, with Whatnot\'s estimated earnings below.'],
                            ['Revenue per Show Hour', 'Gross revenue divided by broadcast hours.'],
                            ['Recorded Payouts', 'Pay recorded for the period.'],
                            ['Streamer cards', 'Each streamer\'s shows, revenue, estimated earnings, show hours and revenue per hour. View Details opens their numbers.'],
                            ['Sort by / Search', 'Order or find streamers.'],
                            ['Streamer Statements / Export CSV', 'Per-person statements, or the figures as a spreadsheet.'],
                        ],
                        'note' => 'A streamer with no shows in the period still appears, with dashes, so nobody is missed.',
                    ],
                    [
                        'title' => 'Check inventory value and health',
                        'where' => 'Reports → Inventory Reports',
                        'screen' => \App\Filament\Pages\InventoryReport::class,
                        'body' => [
                            'The cards are today\'s stock: what it is worth at cost, how many items and units are on hand, and how many are low, out or uncategorized. The activity period and filters apply to movement, not to today\'s value.',
                            'Idle Inventory shows money sitting still for 30, 60 and 90+ days. The tabs below list stock by value, location, low stock, recent activity, top sellers and idle items.',
                        ],
                        'shot' => 'rep-inventory.png',
                        'fields' => [
                            ['Activity period / locations / categories / More filters', 'Narrow the movement and lists.'],
                            ['Inventory Value', 'On-hand units at their current average cost.'],
                            ['Total Items / Units On Hand / Avg Cost per Unit', 'What is in stock and its average cost.'],
                            ['Low Stock / Out of Stock / Uncategorized Items', 'Items to reorder or tidy up.'],
                            ['Inventory Value Trend', 'A nightly snapshot of total value; it fills in night by night.'],
                            ['Idle Inventory', 'Value and units with no movement for 30–59, 60–89 and 90+ days.'],
                            ['Tabs', 'Current Inventory, Valuation, Locations, Low Stock, Out of Stock, Recent Activity, Top Value, Top Sold and Idle.'],
                            ['Item list / Export', 'Open the item list, or download the report.'],
                        ],
                        'note' => 'Average cost is a live FIFO average of what is on hand, so it moves as stock is received and sold.',
                    ],
                ],
            ],
            [
                'title' => 'Alerts and summaries',
                'icon' => 'heroicon-o-bell-alert',
                'blurb' => 'Choose who hears about what, how, and when — straight away, once a day, or once a week.',
                'steps' => [
                    [
                        'title' => 'Set up a notification',
                        'where' => 'Admin → Notifications',
                        'screen' => \App\Filament\Pages\NotificationSettings::class,
                        'body' => [
                            'Every notification is a card: what it is, who gets it, how and when. The switch turns it on or off; Configure opens its settings in a pop-up.',
                            'In the pop-up, choose recipients by role (Owner, Admins, the show\'s streamer) or by person, add an email address for someone without a login, and tick In-app and/or Email.',
                            'Schedule sets when it is sent: Immediately as things happen, or Daily or Weekly as one summary at the day and time you pick. Skip empty summaries stops a "nothing to report" message. Preview and Send Test show what people will get before you save.',
                        ],
                        'shot' => 'rep-notifications.png',
                        'more' => ['rep-notify-configure.png'],
                        'fields' => [
                            ['Tabs', 'All, Shows, Reports, Inventory, Fulfillment, Payroll, Summaries and System.'],
                            ['Search / Enabled only', 'Find a notification, or hide the ones that are off.'],
                            ['Switch', 'Turns the notification on or off.'],
                            ['Configure', 'Opens its settings.'],
                            ['Recipients', 'Roles or people, plus an email address with no login needed.'],
                            ['Delivery', 'In-app, Email or both.'],
                            ['Schedule', 'Immediately, Daily or Weekly — with the day, time and timezone.'],
                            ['Include businesses', 'All businesses, or only some.'],
                            ['Skip empty summaries', 'Does not send a summary when there is nothing in it.'],
                            ['Preview / Send Test', 'See the message, or send it to yourself.'],
                            ['Delivery Log / Test Email', 'What was sent and to whom, and a check that email works.'],
                        ],
                        'note' => 'Negative Whatnot earnings uses Whatnot\'s estimate and can go out daily or weekly. Finalized business loss waits until a show\'s earnings, COGS and pay are complete. If email is switched off on the server, a yellow banner says so and only in-app is sent.',
                    ],
                ],
            ],
        ];
    }

    public static function troubleshooting(): array
    {
        return [
            ['Business profit shows a dash', 'No show in the period is finalized yet. Open Business Profitability; the Finalization Checklist says what is missing.', \App\Filament\Pages\ProfitLoss::class],
            ['Whatnot earnings and business profit disagree', 'They measure different things: Whatnot earnings are after Whatnot\'s fees only; business profit also takes off COGS and pay.', \App\Filament\Pages\ProfitLoss::class],
            ['A loss summary did not arrive', 'Check the notification is on, who it goes to and its schedule in Notifications, then look in the Delivery Log. Skip empty summaries sends nothing when there were no losses.', \App\Filament\Pages\NotificationSettings::class],
            ['The ledger is empty', 'The nightly Whatnot ledger import has not run for that channel yet, or the date filter is too narrow.', \App\Filament\Resources\WhatnotLedgerResource::class],
        ];
    }

    public static function screenIndex(): array
    {
        return [
            ['Reports Overview', 'Headline numbers, earnings trend and what needs attention.', \App\Filament\Pages\ReportsOverview::class],
            ['Whatnot Performance', 'Sales, Whatnot earnings, channels and top streamers.', \App\Filament\Pages\Reports::class],
            ['Whatnot Losses', 'Shows with negative estimated Whatnot earnings; send a summary.', \App\Filament\Pages\WhatnotLosses::class],
            ['Business Profitability', 'Profit after COGS and pay, with the finalization checklist.', \App\Filament\Pages\ProfitLoss::class],
            ['Ledger', 'Whatnot credits and debits by channel.', \App\Filament\Resources\WhatnotLedgerResource::class],
            ['Streamer Analytics', 'Shows, revenue and payouts per streamer.', \App\Filament\Pages\StreamerAnalytics::class],
            ['Inventory Reports', 'Stock value, health, movement and idle inventory.', \App\Filament\Pages\InventoryReport::class],
            ['Notifications', 'Who gets which alert, how and when.', \App\Filament\Pages\NotificationSettings::class],
        ];
    }
}
