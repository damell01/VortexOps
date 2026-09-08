<?php

namespace App\Support;

class FulfillmentManual
{
    public static function title(): string { return 'Fulfillment Handbook'; }
    public static function subtitle(): string { return 'From an approved streamer report to assigned, packed and complete.'; }

    public static function sections(): array
    {
        return [[
            'title' => 'Fulfillment workflow',
            'icon' => 'heroicon-o-truck',
            'blurb' => 'The streamer report becomes the packing list, then the assigned fulfillment member owns the physical work.',
            'steps' => [
                [
                    'title' => 'Receive assigned fulfillment work',
                    'where' => 'Fulfillment',
                    'screen' => \App\Filament\Resources\FulfillmentResource::class,
                    'body' => [
                        'A show enters Fulfillment only after the streamer report is approved and contains logged items. Imported order or shipment rows by themselves never create fulfillment work.',
                        'Admins, owners and fulfillment admins can see all active fulfillment. A regular fulfillment member sees the approved shows assigned to them.',
                        'The queue is based on streamer-log lines and units: item lines, total units, units remaining, packing progress and issues. Buyer names and Whatnot shipment grouping are intentionally excluded from the fulfillment workspace.',
                    ],
                    'shot' => 'ops-fulfillment-center.png',
                    'fields' => [
                        ['Needs Assignment', 'Approved streamer report that still needs a fulfillment team member.'],
                        ['Ready to Pack', 'Assigned show with streamer-logged units ready to be packed.'],
                        ['Packing', 'Assigned show where some logged units have already been accounted for.'],
                        ['Issues', 'Streamer-logged items with a packing exception that must be resolved.'],
                        ['Ready to Complete', 'All logged units are accounted for and no deliberately tracked box is open.'],
                        ['Completed', 'Fulfillment signoff is complete and the show can move toward payroll.'],
                    ],
                    'note' => 'Historical scraper/backfill shows are kept for reporting but do not create fulfillment work unless an admin restores them to the operational workflow.',
                ],
                [
                    'title' => 'Pack the streamer log',
                    'where' => 'Fulfillment → Open Show',
                    'screen' => \App\Filament\Resources\FulfillmentResource::class,
                    'body' => [
                        'The Streamer Packing List is the physical source of truth. Pack the item and quantity the streamer logged using the scanner, +1, or Pack Remaining.',
                        'No buyer name, shipment count, or Whatnot grouping is needed to do the job.',
                        'A VortexOps box is optional. You can pack directly from the streamer log without creating a box. If the team chooses to track a physical box, future scans can be linked to that box and the box must be sealed before the show can be completed.',
                        'Complete Fulfillment becomes available when all logged units are packed, no item issues remain, and any deliberately tracked boxes are sealed.',
                    ],
                    'shot' => 'ops-packing-workstation.png',
                    'fields' => [
                        ['Units Left', 'Streamer-logged units still requiring packing.'],
                        ['Lines Done', 'Streamer-log lines whose full quantity is accounted for.'],
                        ['Issues', 'Open packing exceptions that block completion.'],
                        ['Tracked Boxes', 'Optional VortexOps box records; zero is valid.'],
                        ['+1 / Pack remaining', 'Incremental or remainder packing controls for a streamer-log line.'],
                        ['Flag Issue', 'Records an exception instead of forcing an incorrect pack count.'],
                        ['Optional Physical Box Tracking', 'Links future scans to a physical box only when that tracking is useful.'],
                        ['Complete Fulfillment', 'Final signoff after every logged unit is accounted for and any tracked boxes are closed.'],
                    ],
                    'note' => 'Whatnot buyer and shipment data can remain in the database for analytics or scraper work, but it is not shown as fulfillment work.',
                ],
            ],
        ]];
    }

    public static function troubleshooting(): array
    {
        return [
            ['A show is missing from my queue', 'Confirm its streamer report is approved, it has logged items, and you are assigned to the show. Admins can see the full active fulfillment queue.'],
            ['Complete Fulfillment is unavailable', 'Check units remaining, open item issues, and any optional VortexOps boxes that were created but not sealed. A box is not required when none was created.'],
            ['An old scraper show is asking for work', 'Admins can mark the show Historical. Historical shows stay available for reporting without creating streamer, fulfillment or payroll tasks.'],
            ['A packed line is wrong', 'Reset the line or flag an issue rather than continuing with an incorrect count.'],
        ];
    }

    public static function screenIndex(): array
    {
        return [
            ['Fulfillment Center', 'Assigned and active shows organized around streamer-log lines, units remaining and issues.', \App\Filament\Resources\FulfillmentResource::class],
            ['Packing Workstation', 'Streamer packing list, scanner, optional physical box tracking and final fulfillment signoff.', \App\Filament\Resources\FulfillmentResource::class],
        ];
    }
}
