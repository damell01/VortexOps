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
                        'title' => 'Start from the streamer home page',
                        'where' => 'My Dashboard (a streamer\'s home page)',
                        'screen' => \App\Filament\Pages\DashboardImproved::class,
                        'body' => [
                            'Streamers land on My Dashboard — it is their one page for shows. The banner counts down to their next show; under it are their numbers for the month, the time clock, and every show report as a card.',
                            'To complete lists every show still waiting on a report, oldest included. Needs changes is what an admin sent back, Submitted is filed, Upcoming is what they are scheduled for, and All shows has everything, including previous shows from before reports started.',
                        ],
                        'shot' => 'streamer-home.png',
                        'fields' => [
                            ['Next show', 'Countdown to their next show; Upcoming shows lists the rest.'],
                            ['Shows / Gross sales / Recorded paid / Approved unpaid', 'Their numbers for the period picked on the right: this month, last month or this year.'],
                            ['Time clock', 'Add a show or reason, then Clock in; clock out when done.'],
                            ['To complete / Needs changes / Submitted / Upcoming / All shows', 'Their shows by what each report needs.'],
                            ['Log sold items / Continue report / Fix report / View report', 'Opens the show\'s report at the right step.'],
                            ['Items logged / Giveaways / Your pay', 'What the report holds so far, and their pay once it is on a pay run.'],
                            ['Need to change something?', 'On a filed report: asks an admin to reopen it, with a reason.'],
                        ],
                        'note' => 'Streamers see only their own shows. Admins can choose between My shows and All shows in the picker.',
                    ],
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
                        'title' => 'Work the Show Report Inbox',
                        'where' => 'Shows → Show Report Inbox',
                        'screen' => \App\Filament\Resources\StreamerLogResource::class,
                        'body' => [
                            'Every report is a card: the channel, where it stands, the show and streamer, and what it is worth. The banner under the counts says how reports get approved (set in Settings → Post-Show Workflow) and links straight to the next one waiting.',
                            'Each card shows the sales, the product cost the streamer entered and their pay for that show. Until the pay run picks it up the pay is an estimate, worked out exactly the way the pay run will; once it is on a run it shows the real figure. Hover over the pay to see how it was worked out.',
                            'A yellow Missing line lists anything pay or stock still depends on: Whatnot earnings not imported, hours worked, shipments, items without a cost, no items logged. Fix those before approving.',
                            'Each card carries the buttons for where that report is: Approve and Request changes once it is submitted, Reopen for edits or Decline request when a streamer asks for it back, Submit for streamer when they finished but never pressed Submit, Fulfillment checked for PWE/label pay. The last button opens the report. Select turns on checkboxes for approving or sending back several at once.',
                        ],
                        'shot' => 'report-inbox.png',
                        'fields' => [
                            ['Status cards', 'Counts for each stage; click one to show only those reports.'],
                            ['Approval banner', 'Whether reports need an admin or approve themselves, Review next, and a link to the setting.'],
                            ['All reports / Needs attention / Approved', 'The three everyday views. Needs attention is anything waiting on an admin or sent back to a streamer.'],
                            ['Sort / per page / Select', 'Order the cards, choose how many show, or switch on bulk selection.'],
                            ['Search, Streamer, Channel, Date range, Filters', 'Narrow the cards; Filters adds status and an exact show-date range.'],
                            ['Missing', 'What the report still needs before pay can be trusted.'],
                            ['Sales / Product cost / Pay', 'Est. pay before a pay run, then Pay (draft), On pay run or Paid.'],
                            ['Card buttons', 'Only the actions that fit the report right now; the right-most opens it.'],
                            ['Submit for streamer', 'Files a finished report the streamer forgot to submit, exactly as if they had pressed Submit.'],
                        ],
                        'note' => 'Shows before the date in Settings → Post-Show Workflow → Streamer reports start from are previous shows: streamers still see them on their Shows page and can log one if they want, but they never count as waiting on anyone and stay out of this inbox.',
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
