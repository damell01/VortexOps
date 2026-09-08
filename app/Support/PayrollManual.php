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
            'blurb' => 'Review blockers and move the current week through one controlled pay run.',
            'steps' => [
                [
                    'title' => 'Work the weekly payroll flow',
                    'where' => 'Payroll',
                    'screen' => \App\Filament\Pages\PayrollOverview::class,
                    'body' => [
                        'The Payroll Command Center is organized by workflow state: Needs Attention, Payroll Ready, In Pay Run and Paid.',
                        'Each show card includes the show net, key financial inputs, its current blocker or next step, and the action that resolves it.',
                        'Use the readiness panel before finalizing a run. The simulator is a secondary tool and does not write payroll data.',
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
                    ],
                    'note' => 'Do not force a blocked show into payroll. Fix the source show, fulfillment or report input, then recalculate the draft run.',
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
        ]];
    }

    public static function troubleshooting(): array
    {
        return [
            ['Finalize Pay Run is unavailable', 'Open Readiness. A draft cannot finalize until all readiness problems are cleared.'],
            ['A show disappeared after recalculation', 'Recalculation removes newly blocked payouts from the draft until the source issue is fixed.'],
            ['A person total looks wrong', 'Expand that person in the Pay Run Workspace and inspect the individual show/work payout lines and payout structure.'],
            ['Need to test a formula', 'Use Mock Pay Run in the collapsed simulation section. It uses real catalog costs but writes nothing.'],
        ];
    }

    public static function screenIndex(): array
    {
        return [
            ['Payroll Command Center', 'Weekly blockers, ready shows, current run and show-level financial review.', \App\Filament\Pages\PayrollOverview::class],
            ['Pay Run Workspace', 'Readiness, person totals, payout lines and Draft → Paid lifecycle.', \App\Filament\Resources\WeeklyPayoutBatchResource::class],
        ];
    }
}
