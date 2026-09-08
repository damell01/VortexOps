<?php

namespace App\Support;

class FulfillmentManual
{
    public static function title(): string { return 'Fulfillment Handbook'; }
    public static function subtitle(): string { return 'From a reviewed show to packed, sealed, labeled and complete.'; }

    public static function sections(): array
    {
        return [[
            'title' => 'Fulfillment workflow',
            'icon' => 'heroicon-o-truck',
            'blurb' => 'Work one show from ready-to-pack through completed fulfillment.',
            'steps' => [
                [
                    'title' => 'Pick the next show',
                    'where' => 'Fulfillment',
                    'screen' => \App\Filament\Resources\FulfillmentResource::class,
                    'body' => [
                        'The Fulfillment Workspace is card-first. Shows are grouped by what needs attention, what is ready, what is actively being packed and what is complete.',
                        'Use the stage and primary action on the card rather than opening the advanced table first.',
                    ],
                    'shot' => 'ops-fulfillment-center.png',
                    'fields' => [
                        ['Needs Attention', 'Shows with a fulfillment blocker or issue.'],
                        ['Ready to Pack', 'Approved shows that can begin packing.'],
                        ['Packing', 'Shows with active packing work.'],
                        ['Seal Boxes', 'Packing is done but one or more boxes still need verification/sealing.'],
                        ['Completed', 'Fulfillment signoff is complete.'],
                    ],
                    'note' => 'The advanced table is still available below the operational cards for admin filtering and bulk work.',
                ],
                [
                    'title' => 'Pack the show',
                    'where' => 'Fulfillment → Open Show',
                    'screen' => \App\Filament\Resources\FulfillmentResource::class,
                    'body' => [
                        'The Packing Workstation keeps the active box and scanner at the top while the packing list stays directly underneath.',
                        'Scan or pack the correct line, build boxes from Whatnot shipment data, verify the box, print the internal 4×6 label, then seal it.',
                        'The show cannot be completed while units remain, issues are open, physical items have no package, or a package is unsealed.',
                    ],
                    'shot' => 'ops-packing-workstation.png',
                    'fields' => [
                        ['Units Left', 'Units still requiring packing.'],
                        ['Lines Done', 'Packing lines fully satisfied.'],
                        ['Issues', 'Open fulfillment exceptions that block completion.'],
                        ['Boxes', 'Packages created for the show.'],
                        ['+1 / Pack remaining', 'Incremental or remainder packing controls for a line.'],
                        ['Flag Issue', 'Records an exception instead of forcing a bad pack.'],
                        ['Use Box', 'Sets the package you are currently packing into.'],
                        ['Verify + Seal', 'Confirms box contents and closes the package.'],
                        ['Print Label', 'Prints the internal 4×6 box label and verification QR.'],
                        ['Show Complete', 'Final fulfillment signoff after all completion guards pass.'],
                    ],
                    'note' => 'Do not use Show Complete as a shortcut. The completion guard is there to keep incomplete boxes out of payroll-ready shows.',
                ],
            ],
        ]];
    }

    public static function troubleshooting(): array
    {
        return [
            ['Show Complete is unavailable', 'Check pending units, open issues, missing packages and unsealed boxes. Every physical item must be packed into a sealed package.'],
            ['Wrong box is active', 'Use the package/box selector before scanning more items. The sticky workstation shows the current active box.'],
            ['A packed line is wrong', 'Reset the line or flag an issue rather than continuing with a bad count.'],
        ];
    }

    public static function screenIndex(): array
    {
        return [
            ['Fulfillment Workspace', 'Queue of shows organized by operational stage.', \App\Filament\Resources\FulfillmentResource::class],
            ['Packing Workstation', 'Scanner, packing list, box building, verification, labels and completion.', \App\Filament\Resources\FulfillmentResource::class],
        ];
    }
}
