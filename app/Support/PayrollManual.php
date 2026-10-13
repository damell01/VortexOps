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
                        'The Payroll Command Center is organized by workflow state: Needs Attention, Payroll Ready, In Pay Run and Paid.',
                        'Each show card includes the show net, key financial inputs, its current blocker or next step, and the action that resolves it.',
                        'Use the readiness panel before finalizing a run. Use Payroll Simulator when you want to test formulas without touching live payroll or inventory.',
                    ],
                    'shot' => 'ops-payroll-command-center.png',
                    'fields' => [
                        ['Payroll Total', 'Calculated payroll total for the current period.'],
                        ['People', 'Team members represented in current payroll.'],
                        ['Ready / In Run / Paid', 'Counts of shows by payroll stage.'],
                        ['Blocked', 'Shows that cannot enter or advance in payroll yet.'],
                        ['Next step', 'The specific blocker or action for that show.'],
                        ['Run Readiness', 'Issues that must be resolved before finalization.'],
                        ['Current Run', 'The draft/finalized weekly pay run and its total.'],
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
                        'Estill Distribution pays a sales split: the business keeps a percentage of what the streamer\'s shows earned and the streamer gets the rest. Pick Sales split, enter the percentage the business keeps, choose Estimated net earnings (or Gross sales), and save.',
                        'Moving a person to another business changes how they are paid automatically, unless they have their own pay type set as an override.',
                    ],
                    'shot' => 'pay-business-rates.png',
                    'fields' => [
                        ['Profit share', 'Percentage of show profit, plus hourly for in-house streamers.'],
                        ['Sales split', 'The business keeps a percentage of the streamer\'s sales; the streamer gets the rest.'],
                        ['The business keeps', 'The percentage taken out of the streamer\'s sales.'],
                        ['Of the streamer\'s', 'What the split is taken from: Estimated net earnings (after Whatnot fees, the default) or Gross sales.'],
                        ['Use the shared rates instead', 'Drops this business\'s own rates so it follows the shared ones again.'],
                    ],
                    'note' => 'Burden rates, the pay schedule and Pay Run automation are the same for every business.',
                ],
                [
                    'title' => 'Pay someone differently from the rest of their business',
                    'where' => 'Payroll → Payment Structures → Individual Overrides',
                    'screen' => \App\Filament\Pages\PaymentStructures::class,
                    'body' => [
                        'Estill Distribution has one streamer who works hourly and is paid like Vortex Collects streamers. Give that person an override instead of changing the whole business.',
                        'Click their card, tick Paid a different way, choose Profit share, then set their pay percentage and hourly rate and save the override.',
                        'Reset to Role Default removes the override, so they go back to their business\'s rates.',
                    ],
                    'shot' => 'pay-business-override.png',
                    'fields' => [
                        ['Paid a different way', 'Gives this person their own pay type (profit share or sales split) instead of their business\'s.'],
                        ['Streamer Pay %', 'Their own profit share percentage.'],
                        ['Hourly rate', 'Paid for each show\'s hours on top of their share (in-house streamers only).'],
                        ['Save Override', 'Saves this person\'s own pay settings.'],
                        ['Reset to Role Default', 'Removes their overrides so they follow their business\'s rates.'],
                    ],
                    'note' => 'The person\'s Business and pay can also be set on their user profile (Admin → Users → Edit → Pay).',
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
            ['Payment Structures', 'Each business\'s pay rates, the sales split, individual overrides and the pay schedule.', \App\Filament\Pages\PaymentStructures::class],
        ];
    }
}
