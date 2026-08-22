<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use Filament\Pages\Page;

class HowItWorks extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'How It Works';
    protected static ?string $navigationLabel = 'How It Works';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = -3;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getView(): string
    {
        return 'filament.pages.how-it-works';
    }

    public function getSubheading(): ?string
    {
        return 'Your role, the show flow, and what to do next.';
    }

    /** @return array{label: string, items: array<int, array{title: string, body: string}>} */
    public function getMyRoleGuideProperty(): array
    {
        $user = auth()->user();

        if ($user?->isOwner()) {
            return [
                'label' => 'Owner',
                'items' => [
                    ['title' => 'Run the exceptions first', 'body' => 'Start on the <strong>Admin Operations Center</strong>. Clear reports waiting for review, unmatched inventory, unassigned fulfillment, and other exceptions before working routine reporting.'],
                    ['title' => 'Choose how automated the show flow should be', 'body' => 'The dashboard <strong>Show Workflow</strong> controls decide when inventory posts and whether every report, only exception reports, or no reports require manual approval.'],
                    ['title' => 'Use each Show as the command center', 'body' => 'A show contains Whatnot analytics, shipments, streamer report, inventory reconciliation, activity history, and payout actions. Avoid bouncing between separate logs unless you need a specialized report.'],
                    ['title' => 'Manage access and modules', 'body' => 'Use <strong>Settings → Roles & Permissions</strong> and module controls to decide what each role can see.'],
                ],
            ];
        }

        if ($user?->isAdmin()) {
            return [
                'label' => 'Admin',
                'items' => [
                    ['title' => 'Start with the Admin Operations Center', 'body' => 'The first numbers are action queues, not vanity metrics: reports to review, unmatched items, open shipments, unassigned fulfillment, and draft payouts.'],
                    ['title' => 'Review the streamer report inside the Show', 'body' => 'Open a completed show. The <strong>Streamer Show Report</strong> shows Sold, Giveaway, Promo, Other, costs, posting status, and inventory exceptions in one place.'],
                    ['title' => 'Match unlisted items inline', 'body' => 'If a streamer reports something not in the catalog, use <strong>Match to Inventory</strong> directly on that report line. Do not create a second reconciliation workflow.'],
                    ['title' => 'Approve or request changes', 'body' => 'Approve a correct report or send it back with a clear reason. The activity timeline records report, inventory, and Whatnot changes for the show.'],
                    ['title' => 'Finish fulfillment and payouts', 'body' => 'Make sure fulfillment ownership and shipment work are settled, then calculate/process payouts using the show’s final data.'],
                ],
            ];
        }

        if ($user?->isFulfillmentAdmin()) {
            return [
                'label' => 'Fulfillment Admin',
                'items' => [
                    ['title' => 'Use the Fulfillment Center as your queue', 'body' => 'It is show-first. On phones you see only the essential columns: show, open work, and next action. Open a row for the complete shipment and packing context.'],
                    ['title' => 'Assign work before it gets lost', 'body' => 'Watch unassigned shows and make sure fulfillment users are attached to the work they own. Regular fulfillment users only see their assigned shows.'],
                    ['title' => 'Keep shipment state current', 'body' => 'Work open shipments, verify packing lines, and update tracking/status as the package moves. Whatnot shipment data remains the reference source.'],
                    ['title' => 'Complete fulfillment-dependent review', 'body' => 'For PWE + Labels streamers, complete the fulfillment review/count before final payout calculation.'],
                ],
            ];
        }

        if ($user?->isFulfillment()) {
            return [
                'label' => 'Fulfillment',
                'items' => [
                    ['title' => 'Open the Fulfillment Center', 'body' => 'Your queue contains only shows assigned to you. Start with shows that have open shipments.'],
                    ['title' => 'Work one show at a time', 'body' => 'Open a show, process its shipment and packing lines, and keep the shipping status/tracking current. Avoid working from one giant cross-show shipment list.'],
                    ['title' => 'Use the dashboard for workload', 'body' => 'Shows to Work, Open Shipments, and Delivered Today tell you what needs attention without showing financial information you do not need.'],
                    ['title' => 'Clock in/out separately', 'body' => 'Use <strong>Timekeeping</strong> for your hours. Shipping workflow and timekeeping stay separate.'],
                ],
            ];
        }

        if ($user?->isStreamer()) {
            return [
                'label' => 'Streamer',
                'items' => [
                    ['title' => 'Keep your inventory current', 'body' => 'Move inventory into your streamer inventory whenever you take possession of it. That stock is yours to hold/use and is <strong>not assigned to a specific show ahead of time</strong>.'],
                    ['title' => 'Run the show normally', 'body' => 'Whatnot sales, buyers, earnings, giveaways, and shipment reference data sync automatically. You do not need to re-enter those totals.'],
                    ['title' => 'After the show: End of Stream', 'body' => 'Open <strong>End of Stream</strong> and record which inventory was actually used for that show. Classify every line as <strong>Sold</strong>, <strong>Giveaway</strong>, <strong>Promo / Bonus</strong>, or <strong>Other</strong>.'],
                    ['title' => 'If an item is not in the catalog', 'body' => 'Use <strong>Unlisted Item</strong>. Do not pick a product that is “close enough.” The report will flag it so admin can match the correct inventory later.'],
                    ['title' => 'Review and submit', 'body' => 'Check quantities, classifications, cost, and inventory exceptions on the Review step. The app will either post/approve automatically or route the report to admin based on the configured workflow.'],
                    ['title' => 'If admin requests changes', 'body' => 'The report reopens in <strong>Changes Requested</strong>. Fix the specific issue and submit again.'],
                ],
            ];
        }

        return [
            'label' => 'Your account',
            'items' => [
                ['title' => 'No role assigned yet', 'body' => 'Ask an admin to assign you a role under <strong>Settings → Users</strong>. Your dashboard and navigation are role-specific.'],
            ],
        ];
    }
}
