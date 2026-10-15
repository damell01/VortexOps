<?php

namespace App\Support;

class PayrollManual
{
    public static function title(): string { return 'Payroll Handbook'; }
    public static function subtitle(): string { return 'From payroll-ready shows to finalized, submitted and paid weekly runs.'; }

    public static function sections(): array
    {
        return [[
            'title' => 'Payroll command center',
            'icon' => 'heroicon-o-banknotes',
            'blurb' => 'Review blockers, test formulas safely, and move the current week through one controlled pay run.',
            'steps' => [
                [
                    'title' => 'Work the weekly payroll flow',
                    'where' => 'Payroll',
                    'screen' => \App\Filament\Pages\PayrollOverview::class,
                    'body' => [
                        'Pick the pay period at the top (arrows or a date). The cards show the selected run\'s total and how many shows are ready, need attention or wait on approval.',
                        'Payout trend shows the last eight weeks of paid runs; Pay period status counts the period\'s shows by stage. Quick actions create a pay run or open history, rates and overrides.',
                        'Below, the show list is filtered by stage (Needs report, Blocked, Ready, In pay run, Paid…) with the action that moves each show on. Use Payroll Simulator to test formulas without touching live payroll.',
                    ],
                    'shot' => 'ops-payroll-command-center.png',
                    'fields' => [
                        ['Pay period', 'The period you are looking at; move with the arrows or pick a date.'],
                        ['Selected run total', 'Total of the pay run for that period, if one exists.'],
                        ['People in run', 'Streamers and fulfillment people paid in it.'],
                        ['Ready for payroll / Needs attention / Pending approval', 'Shows by payroll stage.'],
                        ['Payout trend', 'Paid runs over the last eight weeks.'],
                        ['Pay period status', 'The period\'s shows: completed, ready, in a run, pending, blocked.'],
                        ['Quick actions', 'Create a pay run, view history, rates & adjustments, individual overrides.'],
                        ['Show list', 'Each show\'s stage and an Open Show button, filtered by the stage tabs.'],
                        ['Recent pay runs', 'The latest runs with total and status.'],
                        ['History / + Create Pay Run', 'Past runs, or start a new one.'],
                        ['Payroll Simulator', 'Opens a separate read-only sandbox for one show, a week, or a four-week month.'],
                    ],
                    'note' => 'Do not force a blocked show into payroll. Fix the source show, fulfillment or report input, then recalculate the draft run.',
                ],
                [
                    'title' => 'Test payroll in the sandbox',
                    'where' => 'Payroll → Payroll Simulator',
                    'screen' => \App\Filament\Pages\PayrollSimulator::class,
                    'body' => [
                        'Choose 1 Show, 1 Week, or 4-Week Month. The month preset creates mock shows across four weekly pay periods.',
                        'The simulator reads real catalog products/cost basis and the current streamer payment structures, but the show revenue, dates, hours, shipments, tips and quantities are mock inputs you can change.',
                        'Swap the streamer/payment structure, switch catalog products, add/remove shows, and inspect the projected weekly pay runs and show-by-show calculation audit.',
                        'Nothing on this page creates a Show, Streamer Log, Payout, Pay Run, Inventory Movement, or stock deduction.',
                    ],
                    'shot' => null,
                    'fields' => [
                        ['1 Show', 'Smallest formula test for one mock show.'],
                        ['1 Week', 'Multiple mock shows grouped into one weekly payroll result.'],
                        ['4-Week Month', 'Mock month grouped into separate weekly pay-run totals.'],
                        ['Catalog item', 'Real product used only to read its current cost basis.'],
                        ['Payment structure', 'Real active streamer/payment configuration used for the mock payout.'],
                        ['Projected Weekly Pay Runs', 'Weekly gross, COGS, payroll and business-after-payroll totals.'],
                        ['Calculation Audit', 'Show-level payout result and formula explanation.'],
                    ],
                    'note' => 'This is intentionally read-only: it is for validating numbers and comparing compensation setups before real data exists.',
                ],
                [
                    'title' => 'Review the Pay Run Workspace',
                    'where' => 'Payroll → Open Current Pay Run',
                    'screen' => \App\Filament\Resources\WeeklyPayoutBatchResource::class,
                    'body' => [
                        'The individual Pay Run Workspace shows the lifecycle Review → Finalized → Submitted → Paid.',
                        'The Next Action panel explains what moves the run forward. People are summarized first; expand a person only when you need to inspect the payout lines that built their total.',
                        'Readiness blockers must be zero before Finalize Pay Run is available. Finalizing locks payout amounts.',
                    ],
                    'shot' => 'ops-pay-run-workspace.png',
                    'fields' => [
                        ['Total Payroll', 'Total amount in the weekly batch.'],
                        ['Streamer Pay', 'Streamer portion of the batch.'],
                        ['Fulfillment Pay', 'Fulfillment portion of the batch.'],
                        ['Blockers', 'Readiness problems preventing finalization.'],
                        ['Person payout', 'Total calculated payout for one team member.'],
                        ['Payout lines', 'Show/work records used to build that person total.'],
                        ['Finalize Pay Run', 'Locks the reviewed payout amounts.'],
                        ['Export ADP CSV', 'Creates the payroll file after finalization.'],
                        ['Mark Submitted to ADP', 'Records that the finalized run was sent to ADP.'],
                        ['Mark Paid', 'Completes the run and updates team balances.'],
                    ],
                    'note' => 'The normal lifecycle is Draft → Finalized → Submitted to ADP → Paid. Treat those stages as audit history, not cosmetic statuses.',
                ],
            ],
        ], [
            'title' => 'Businesses: Vortex Collects and Estill Distribution',
            'icon' => 'heroicon-o-building-office-2',
            'blurb' => 'Both businesses stream on the same channels, but every person works for one of them. Each business has its own pay rates and its own weekly Pay Runs.',
            'steps' => [
                [
                    'title' => 'Put each person in their business',
                    'where' => 'Payroll → Businesses (fulfillment admins: Admin → Businesses)',
                    'screen' => \App\Filament\Pages\Businesses::class,
                    'body' => [
                        'The board has a column for each business and one for people who are not assigned yet.',
                        'Drag a card onto a column to move that person. To move several at once, tick their boxes and use the move buttons in the bar above the board, or drag one of the ticked cards and they all move together.',
                        'A show belongs to the business of the person who streamed it, so assigning people is all it takes to split shows and pay between the businesses.',
                        'Use Add business to set up another business, and Edit on a column to rename it or change its colour.',
                    ],
                    'shot' => 'pay-businesses-board.png',
                    'fields' => [
                        ['Not assigned', 'People who are not in a business yet. Their pay is left out of the business Pay Runs until they are moved.'],
                        ['Business column', 'Everyone who works for that business, with how they are paid.'],
                        ['Card', 'One team member: their type (in-house, remote, fulfillment) and pay summary.'],
                        ['Move to', 'Moves every ticked person to the business you click.'],
                        ['Select all', 'Ticks everyone in that column.'],
                        ['Add business', 'Creates a new business with its own name and colour.'],
                        ['Edit', 'Renames or recolours a business. A business can only be deleted when it has nobody in it and no Pay Runs.'],
                    ],
                    'note' => 'Until at least one person is in a business, payroll runs exactly as before: one Pay Run per week for everyone.',
                ],
                [
                    'title' => 'Choose which business you are running payroll for',
                    'where' => 'Business picker at the top of any payroll page',
                    'screen' => \App\Filament\Pages\PayrollOverview::class,
                    'body' => [
                        'On payroll pages (Payroll Overview, Pay Run History, Payouts, Payment Structures, Businesses) a Business picker sits next to the Channel picker.',
                        'Choose a business and those pages show only its people, shows and Pay Runs. A coloured banner names the business so it is always clear which payroll you are working on. Choose All Businesses to see both together.',
                        'The picker only affects payroll. The dashboard, shows, reports and inventory always show every business.',
                        'Each business gets its own weekly Pay Run, so one business can be finalized, submitted and marked paid without touching the other\'s people. Prepare this week\'s pay run creates the run for the chosen business, or one per business when All Businesses is chosen.',
                    ],
                    'shot' => 'pay-business-picker.png',
                    'fields' => [
                        ['Business picker', 'All Businesses, or one business. Only shown on payroll pages.'],
                        ['Banner', 'Names the business the payroll pages are showing.'],
                        ['Who\'s in it', 'Opens the Businesses board.'],
                        ['Prepare this week\'s pay run', 'Creates or refreshes the chosen business\'s Pay Run for the current pay week.'],
                        ['Split by business', 'On a draft Pay Run made for everyone before businesses were set up: turns it into one run per business.'],
                    ],
                    'note' => 'A run made for all businesses and a run for one business cannot cover the same week, so nobody is ever paid twice. Split the old draft run first.',
                ],
                [
                    'title' => 'Set each business\'s pay rates',
                    'where' => 'Payroll → Payment Structures, with a business picked',
                    'screen' => \App\Filament\Pages\PaymentStructures::class,
                    'body' => [
                        'With a business chosen in the picker, Payment Structures edits that business\'s rates. With All Businesses chosen it edits the shared rates, which any business uses until it saves its own.',
                        'Vortex Collects pays profit share: a percentage of each show\'s profit after product cost and burden, plus hourly for in-house streamers.',
                        'Estill Distribution pays a sales split: the business keeps a percentage of what the streamer\'s shows earned and the streamer gets the rest. Click Edit team rates, pick Sales split, enter the percentage the business keeps, choose Estimated net earnings (or Gross sales), and Save team rates.',
                        'Moving a person to another business changes how they are paid automatically, unless they have their own pay type set as an override.',
                    ],
                    'shot' => 'pay-business-rates.png',
                    'fields' => [
                        ['Edit team rates', 'Opens this business\'s rates in a pop-up.'],
                        ['Regular payouts', 'Profit share: percentage of show profit, plus hourly for in-house streamers.'],
                        ['Sales split', 'The business keeps a percentage of the streamer\'s sales; the streamer gets the rest.'],
                        ['The business keeps', 'The percentage taken out of the streamer\'s sales.'],
                        ['Of the streamer\'s', 'What the split is taken from: Estimated net earnings (after Whatnot fees, the default) or Gross sales.'],
                        ['Use the shared rates instead', 'Drops this business\'s own rates so it follows the shared ones again.'],
                        ['Save team rates / Cancel', 'Saves the business\'s rates, or closes without saving.'],
                    ],
                    'note' => 'Burden rates, the pay schedule and Pay Run automation are the same for every business.',
                ],
                [
                    'title' => 'Pay someone differently from the rest of their business',
                    'where' => 'Payroll → Payment Structures → Individual Overrides',
                    'screen' => \App\Filament\Pages\PaymentStructures::class,
                    'body' => [
                        'Estill Distribution has one streamer who works hourly and is paid like Vortex Collects streamers. Give that person an override instead of changing the whole business.',
                        'Click Edit on their row. In the pop-up, tick Paid a different way and choose how they are paid, then tick and fill in only the settings that differ, and Save Override.',
                        'Reset to Role Default removes the override, so they go back to their business\'s rates.',
                    ],
                    'shot' => 'pay-business-override.png',
                    'fields' => [
                        ['Paid a different way', 'Gives this person their own pay method instead of their business\'s.'],
                        ['Use a different Streamer Pay %', 'Their own profit share percentage.'],
                        ['Use a different hourly rate', 'Paid for each show\'s Hours Worked on top of their show-based pay.'],
                        ['Use a different calculation', 'A formula just for this person.'],
                        ['Override tip handling', 'Whether tips are included in their pay.'],
                        ['Remove from payroll', 'Takes them off payroll; a profile with history is archived and can be put back.'],
                        ['Save Override', 'Saves this person\'s own pay settings.'],
                        ['Reset to Role Default', 'Removes their overrides so they follow their business\'s rates.'],
                    ],
                    'note' => 'The person\'s Business and pay can also be set on their user profile (Admin → Users → Edit → Pay).',
                ],
            ],
        ], [
            'title' => 'Time clock and Hours Worked',
            'icon' => 'heroicon-o-clock',
            'blurb' => 'People clock in and out against a show or a reason. Payroll pays approved Hours Worked, never the Whatnot stream length.',
            'steps' => [
                [
                    'title' => 'Clock in, clock out and fix your own time',
                    'where' => 'Timekeeping',
                    'screen' => \App\Filament\Pages\Timekeeping::class,
                    'body' => [
                        'Timekeeping is one page: your time clock at the top, the team at a glance in the middle, and every entry below. Link the show you are working on, or pick a reason such as Packing, then Clock in. Both are optional and can be added later.',
                        'While you are clocked in, a reminder bar follows you on every page so a shift is not left running. Clock out from the bar or the clock.',
                        'The four tiles show what needs review, who is clocked in now, the team\'s hours this week and this week\'s entries; Needs review opens Time & Attendance. Forgot to clock? Use Add missed time. Admins can edit or delete any entry.',
                    ],
                    'shot' => 'tk-clock.png',
                    'fields' => [
                        ['Today / This week / This month / All time', 'Your own clocked hours.'],
                        ['Link one of the shows', 'The show this time was worked on. Show time is paid as that show\'s Hours Worked.'],
                        ['Reason', 'Preparation, Cleanup, Packing, Meeting / training or Other, for time that is not on a show.'],
                        ['Add a note', 'Optional detail kept with the entry.'],
                        ['Clock in / Clock out', 'Starts and stops the timer.'],
                        ['Needs review', 'Entries to settle before payroll; opens Time & Attendance.'],
                        ['Clocked in now', 'Who is on the clock right now.'],
                        ['Team hours this week / Entries this week', 'The team\'s total and how many entries it came from.'],
                        ['All entries / Team hours', 'Every entry, or hours per person for the chosen period (admins).'],
                        ['Add missed time', 'Records a shift you forgot to clock.'],
                        ['Status', 'OK, or Needs review with the reason (e.g. added by hand, edited).'],
                        ['Edit / Delete', 'Corrects or removes an entry.'],
                        ['Export CSV', 'Downloads the entries or team hours shown.'],
                    ],
                    'note' => 'If the page warns about your timezone, click Use this device\'s timezone so clock times match your watch.',
                ],
                [
                    'title' => 'Settle hours before payroll',
                    'where' => 'Timekeeping → Needs review',
                    'screen' => \App\Filament\Pages\TimeAttendance::class,
                    'body' => [
                        'Time & Attendance lists everything that needs a decision before payroll: shifts left open, time added by hand or edited, and shows where the report\'s hours and the clocked hours disagree.',
                        'Click Review on a row to open the entry in a pop-up. Choose a decision, add a note if you like, and Save decision. For a show, it also puts the report\'s Hours Worked, the clocked time and the Whatnot length side by side. Back to Timekeeping returns to the dashboard.',
                        'Only approved Hours Worked reach a pay run. By person shows each person\'s week; Clocked in now shows who is on the clock.',
                    ],
                    'shot' => 'ta-review.png',
                    'fields' => [
                        ['Needs review / By person / Clocked in now / All entries', 'Items waiting for a decision, each person\'s hours, who is on the clock, and everything.'],
                        ['Issue', 'Why the entry was flagged, e.g. added by hand or edited by the person.'],
                        ['Approve clock time', 'Pays the time as clocked.'],
                        ['Approve a corrected time', 'Pays a time you type instead.'],
                        ['Don\'t pay this time', 'Keeps the entry but leaves it out of payroll.'],
                        ['Note', 'Why you decided, kept with the entry.'],
                        ['Save decision', 'Records the decision so payroll can use it.'],
                        ['Delete entry', 'Removes an entry made by mistake.'],
                        ['History', 'Every change to the entry, who made it and why.'],
                    ],
                    'note' => 'Payroll pays report hours when they are set, otherwise clock time linked to the show. Clock time not on any show is paid on its own line.',
                ],
            ],
        ], [
            'title' => 'Pay runs and pay rates',
            'icon' => 'heroicon-o-calendar-days',
            'blurb' => 'Create a pay run whenever you need one. Pay run types hold your usual settings; team rates and individual overrides are edited in pop-ups.',
            'steps' => [
                [
                    'title' => 'Set up pay run types',
                    'where' => 'Payroll → Payment Structures → Pay run types',
                    'screen' => \App\Filament\Pages\PaymentStructures::class,
                    'body' => [
                        'A pay run type is a saved recipe for a run: what it pays (hourly & wages, profit share, or both) and, if you want, a schedule that suggests its dates. Open Edit type settings on a card to change it, or Add pay run type for a new one.',
                        'Wages and profit share always stay separate pay lines, even in a combined run.',
                        'A schedule is optional. Tick Automatically create draft runs only if you want drafts made for you; nothing is ever finalized or paid automatically.',
                    ],
                    'shot' => 'pay-run-types.png',
                    'fields' => [
                        ['Type name', 'What you call it, e.g. Weekly wages or Monthly profit share.'],
                        ['What it pays', 'Hourly & wages (hours, shipments, sales splits), Profit share (shares only, usually monthly), Tips, or All pay at once.'],
                        ['Available for new runs', 'Shows the type when creating a run.'],
                        ['Use a schedule to suggest dates', 'Fills in each run\'s dates from a frequency and start date.'],
                        ['Automatically create draft runs', 'Makes draft runs on the schedule. Leave off to create runs yourself.'],
                        ['+ Create run', 'Starts a new run of this type.'],
                        ['Delete', 'Removes the type; runs already made with it are kept.'],
                    ],
                    'note' => 'Deleting a type keeps every pay run already made with it.',
                ],
                [
                    'title' => 'Create a pay run when you need one',
                    'where' => 'Payroll → Pay Runs → New',
                    'screen' => \App\Filament\Resources\WeeklyPayoutBatchResource::class,
                    'body' => [
                        'Choose the business and a pay run type, or One-off custom run and say what it pays. The + button saves a new reusable type without leaving the page.',
                        'Pick any start and end dates. A scheduled type suggests its dates; Custom dates lets you choose your own.',
                        'Create makes a draft for review. Nothing is paid until you finalize, submit and mark it paid.',
                    ],
                    'shot' => 'pay-run-new.png',
                    'fields' => [
                        ['Business', 'Whose people this run pays, or all businesses in one run.'],
                        ['Pay run type', 'A saved type, or One-off custom run. + adds a reusable type.'],
                        ['What this run pays', 'Wages, profit share, or both. They are always separate lines.'],
                        ['Dates', 'Custom dates, or the type\'s scheduled period.'],
                        ['Start date / End date', 'The period the run covers.'],
                        ['Add a note', 'Anything to remember about this run.'],
                    ],
                    'note' => 'Two runs that pay the same kind of pay cannot cover the same dates, so nobody is paid twice.',
                ],
                [
                    'title' => 'Edit team rates and individual overrides',
                    'where' => 'Payroll → Payment Structures',
                    'screen' => \App\Filament\Pages\PaymentStructures::class,
                    'body' => [
                        'The team rates card shows the pay %, burden rates, in-house hourly and tips at a glance. Click Edit team rates to change them in a pop-up; Cancel leaves everything as it was.',
                        'Click a person under Individual Overrides to give just them different pay, in the same kind of pop-up.',
                    ],
                    'shot' => 'pay-team-rates.png',
                    'fields' => [
                        ['Edit team rates', 'Opens the team\'s pay settings.'],
                        ['Regular payouts / Sales split', 'Profit share formula, or the business keeps a % of what shows earned.'],
                        ['Default Streamer Pay %', 'Share of a show\'s profit after product cost and burden.'],
                        ['Burden Per Shipment / Burden Per Hour', 'Costs taken off a show before profit is shared.'],
                        ['In-house hourly rate', 'Paid to in-house streamers for each show\'s hours, on top of their share.'],
                        ['How payroll is calculated', 'The formula with your numbers filled in.'],
                        ['Save team rates', 'Saves the changes for everyone without an override.'],
                        ['Cancel', 'Closes the pop-up without saving.'],
                    ],
                    'note' => 'Saved rates apply to pay calculated from then on; recalculate a draft run to pick them up.',
                ],
            ],
        ], [
            'title' => 'Profit & Loss',
            'icon' => 'heroicon-o-chart-bar',
            'blurb' => 'What each day, week, month and year made after product cost and pay, and which shows are not final yet. The Reports & Analytics handbook covers every report.',
            'steps' => [
                [
                    'title' => 'Read the Profit & Loss report',
                    'where' => 'Reports → Business Profitability',
                    'screen' => \App\Filament\Pages\ProfitLoss::class,
                    'body' => [
                        'Profit & Loss is the Business Profitability page. Pick Daily, Weekly, Monthly or Yearly and move back and forward through periods; narrow by channel, business or streamer. The totals compare with the period before.',
                        'Profit is final only once a show\'s Whatnot earnings, product cost and pay are all in. The Finalization Checklist says what is missing for the period — including hours waiting in Time & Attendance and pay runs not yet paid — with a link to each.',
                        'Click a show to open its breakdown in a pop-up: what is still missing, its pay runs, and the money from gross sales down to business profit.',
                    ],
                    'shot' => 'rep-profit.png',
                    'fields' => [
                        ['Daily / Weekly / Monthly / Yearly', 'The length of the period shown.'],
                        ['Channels / businesses / streamers', 'Narrow the report to one of each.'],
                        ['Sales / Whatnot Earnings', 'Gross sales, and Whatnot\'s estimate after its fees.'],
                        ['Recorded COGS', 'Cost of the products used, from approved reports.'],
                        ['Worker Payouts', 'Pay for those shows, and how many are not final.'],
                        ['Finalized Profit', 'Earnings plus tips minus COGS minus pay, for finalized shows.'],
                        ['All Shows / Finalized / Incomplete / Business Losses / Whatnot Losses', 'Tabs that narrow the show list.'],
                        ['Finalization Checklist', 'What is still needed before the period\'s profit is final.'],
                        ['How streamers are doing', 'Each streamer\'s shows and profit compared with the period before.'],
                        ['Show breakdown', 'Click a show for what is missing, its pay runs and its numbers.'],
                    ],
                    'note' => 'The Finalized business loss and Negative Whatnot earnings alerts can be sent in-app or by email, straight away or as a daily or weekly summary. Choose who gets them, and when, in Admin → Notifications.',
                ],
            ],
        ], [
            'title' => 'See what a user sees',
            'icon' => 'heroicon-o-eye',
            'blurb' => 'The owner can log in as any user to check their screens, then return in one click.',
            'steps' => [
                [
                    'title' => 'Log in as a user',
                    'where' => 'Admin → Users → Log in as',
                    'screen' => \App\Filament\Resources\UserResource::class,
                    'body' => [
                        'On the Users list, click the Log in as button on a person\'s row (or on their profile) and confirm.',
                        'You now see the app exactly as they do. An orange banner on every page says who you are logged in as.',
                        'Click Return to your name on the banner to go back to your own account.',
                    ],
                    'shot' => 'user-login-as.png',
                    'fields' => [
                        ['Log in as', 'The arrow-into-door button on a row. Switches to this user; only the owner sees it.'],
                        ['You\'re logged in as', 'The banner naming the user you are viewing as.'],
                        ['Return to', 'Goes back to your own account.'],
                    ],
                    'note' => 'Anything you do while logged in as someone is done as them. Both switches are written to the activity log.',
                ],
            ],
        ]];
    }

    public static function troubleshooting(): array
    {
        return [
            ['Finalize Pay Run is unavailable', 'Open Readiness. A draft cannot finalize until all readiness problems are cleared.'],
            ['A show disappeared after recalculation', 'Recalculation removes newly blocked payouts from the draft until the source issue is fixed.'],
            ['A person total looks wrong', 'Expand that person in the Pay Run Workspace and inspect the individual show/work payout lines and payout structure.'],
            ['Need to test a formula or a month of payroll', 'Open Payroll Simulator. Use real catalog costs and current payment structures against mock shows; the sandbox writes nothing.'],
            ['Someone\'s pay is missing from the business Pay Runs', 'They are not in a business yet. Open Payroll → Businesses, move them into their business, then recalculate the Pay Run.'],
            ['Payroll only shows one business', 'The Business picker at the top is set to one business. Choose All Businesses to see both.'],
            ['Cannot create a business Pay Run because it overlaps another', 'A draft run made for all businesses covers that week. Open it and use Split by business.'],
            ['A show\'s hours are not in the pay run', 'The hours are still waiting in Timekeeping → Time & Attendance. Save a decision for them, then recalculate the run.'],
            ['Someone left the clock running', 'Open Time & Attendance, fix the clock-out time on the open shift, and approve it.'],
            ['Cannot create a pay run for those dates', 'Another run already pays the same kind of pay for some of those dates. Open Pay Run History and adjust or delete the draft.'],
            ['An Estill streamer is still paid profit share', 'Check they are in Estill Distribution on the Businesses board, that Estill\'s rates are saved as Sales split, and that they have no pay-type override.'],
        ];
    }

    public static function screenIndex(): array
    {
        return [
            ['Payroll Command Center', 'Weekly blockers, ready shows, current run and show-level financial review.', \App\Filament\Pages\PayrollOverview::class],
            ['Payroll Simulator', 'Read-only one-show/week/month payroll sandbox using real catalog costs and current compensation settings.', \App\Filament\Pages\PayrollSimulator::class],
            ['Pay Run Workspace', 'Readiness, person totals, payout lines and Draft → Paid lifecycle.', \App\Filament\Resources\WeeklyPayoutBatchResource::class],
            ['Businesses', 'Board for putting each team member in Vortex Collects or Estill Distribution.', \App\Filament\Pages\Businesses::class],
            ['Payment Structures', 'Each business\'s pay rates, the sales split, individual overrides, pay run types and the pay schedule.', \App\Filament\Pages\PaymentStructures::class],
            ['Timekeeping', 'Time clock, your entries, and team hours.', \App\Filament\Pages\Timekeeping::class],
            ['Time & Attendance', 'Settle open shifts and show hours before payroll.', \App\Filament\Pages\TimeAttendance::class],
            ['Profit & Loss', 'Daily to yearly profit after product cost and pay, by streamer and show.', \App\Filament\Pages\ProfitLoss::class],
        ];
    }
}
