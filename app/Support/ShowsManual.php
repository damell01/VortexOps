<?php

namespace App\Support;

class ShowsManual
{
    public static function title(): string { return 'Shows & Streams Handbook'; }
    public static function subtitle(): string { return 'From a scheduled show through streamer report, admin review, fulfillment and payroll.'; }

    public static function sections(): array
    {
        return [
            [
                'title' => 'Shows command center',
                'icon' => 'heroicon-o-video-camera',
                'blurb' => 'Start with the work that needs attention instead of hunting through tables.',
                'steps' => [
                    [
                        'title' => 'Find the next show to work',
                        'where' => 'Shows',
                        'screen' => \App\Filament\Resources\ShowResource::class,
                        'body' => [
                            'The Shows page is the command center. Priority work is shown before the advanced table.',
                            'Read the workflow stage first, then the blocker/current state, then use the primary action on the card. The page is intentionally ordered around what needs to happen next.',
                        ],
                        'shot' => 'ops-shows-command-center.png',
                        'fields' => [
                            ['Workflow stage', 'Where the show currently sits from Show through Paid.'],
                            ['Blocker / current state', 'Why it cannot move forward, or what is currently happening.'],
                            ['Progress', 'Operational completion for the current stage.'],
                            ['Primary action', 'The safest next screen for this show.'],
                            ['Advanced table', 'Full Filament table and filters, collapsed below the operational cards.'],
                        ],
                        'note' => 'Use the card action unless you specifically need a table-only admin task.',
                    ],
                    [
                        'title' => 'Use the Show Workspace',
                        'where' => 'Shows → Open Show',
                        'screen' => \App\Filament\Resources\ShowResource::class,
                        'body' => [
                            'The Show Workspace is the single record-level home for a show.',
                            'At the top, the workflow strip shows Show → Streamer Report → Admin Review → Fulfillment → Payroll Review → Payroll Ready → Pay Run → Paid.',
                            'The Next Action panel is the first thing to follow. The four handoffs below it keep Streamer Report, Fulfillment, Inventory/COGS and Payroll together.',
                        ],
                        'shot' => 'ops-show-workspace.png',
                        'fields' => [
                            ['Next action', 'The action that moves this show forward from its current workflow state.'],
                            ['Streamer Report', 'Draft, submitted, changes requested or approved.'],
                            ['Fulfillment', 'Assigned team, shipment count and entry point to Pack Show.'],
                            ['Inventory / COGS', 'Approved product cost and deduction status.'],
                            ['Payroll', 'Payout lines and the linked Pay Run when one exists.'],
                            ['Show Financials', 'Gross sales, Whatnot net, tips, COGS, payroll and show net.'],
                        ],
                        'note' => 'Secondary show/sync metadata is intentionally collapsed so it does not compete with the workflow.',
                    ],
                ],
            ],
            [
                'title' => 'Streamer report and admin review',
                'icon' => 'heroicon-o-clipboard-document-check',
                'blurb' => 'One report moves through Items, Details and Review before fulfillment can begin.',
                'steps' => [
                    [
                        'title' => 'Complete the streamer report',
                        'where' => 'Show Workspace → Streamer Report',
                        'screen' => \App\Filament\Resources\StreamerLogResource::class,
                        'body' => [
                            'The report is a three-step workspace: Items → Details → Review & Submit.',
                            'Add the products sold first, complete the stream details second, and submit only after the review summary matches what happened on the show.',
                        ],
                        'shot' => 'ops-admin-review.png',
                        'fields' => [
                            ['Items', 'Inventory items and quantities sold in the show.'],
                            ['Details', 'Required stream and financial inputs.'],
                            ['Review & Submit', 'Summary before the report is sent to admin review.'],
                            ['Status', 'Draft, Awaiting Review, Changes Requested or Approved.'],
                        ],
                        'note' => 'If an admin requests changes, return to the same report, correct it, then resubmit.',
                    ],
                    [
                        'title' => 'Review as an admin',
                        'where' => 'Streamer Report → Admin Review Workspace',
                        'screen' => \App\Filament\Resources\StreamerLogResource::class,
                        'body' => [
                            'Admins use the same workspace, but the final step becomes Review & Approve.',
                            'Verify the item list, product cost, hours and stream details. Approve when correct or Reject & Return with a clear reason when the streamer needs to fix something.',
                            'Reopen for Editing grants an edit window without falsely rejecting a report or un-posting stock.',
                        ],
                        'shot' => 'ops-admin-review.png',
                        'fields' => [
                            ['Approve Report', 'Approves the report and lets the show advance.'],
                            ['Reject & Return', 'Sends the report back with required correction notes.'],
                            ['Reopen for Editing', 'Restores streamer edit access without treating the report as incorrect.'],
                            ['Decline Edit Request', 'Answers and clears an edit request without reopening the report.'],
                        ],
                        'note' => 'Approval is a workflow handoff; do not approve until the products and show inputs are actually correct.',
                    ],
                ],
            ],
        ];
    }

    public static function troubleshooting(): array
    {
        return [
            ['The show looks stuck', 'Open the Show Workspace and read the Next Action / blocker. It is generated from the current workflow state.'],
            ['Streamer cannot edit the report', 'The edit window may be closed. An admin can use Reopen for Editing; do not reject a correct report just to unlock it.'],
            ['Payroll does not see the show', 'The show must clear streamer report, admin review, fulfillment and payroll readiness checks first.'],
        ];
    }

    public static function screenIndex(): array
    {
        return [
            ['Shows', 'Command center for priority work, upcoming/recent shows and the advanced table.', \App\Filament\Resources\ShowResource::class],
            ['Show Workspace', 'Record-level workflow, handoffs and show financials.', \App\Filament\Resources\ShowResource::class],
            ['Streamer Report / Admin Review', 'Items, details, submission, corrections and approval.', \App\Filament\Resources\StreamerLogResource::class],
        ];
    }
}
