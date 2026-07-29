<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use Filament\Pages\Page;

/**
 * A plain-English tour of how VortexOps fits together — the show lifecycle,
 * the streamer log, inventory, and payouts — so a new team member can orient
 * themselves without a manual.
 */
class HowItWorks extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'How It Works';
    protected static ?string $navigationLabel = 'How It Works';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

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
        return 'A quick tour of how the pieces fit together.';
    }

    /** @return array{label: string, items: array<int, array{title: string, body: string}>} */
    public function getMyRoleGuideProperty(): array
    {
        $user = auth()->user();

        if ($user?->isOwner()) {
            return [
                'label' => 'Owner',
                'items' => [
                    ['title' => 'Everything', 'body' => 'You see and can do everything in the app, including toggling modules on/off and managing roles under <strong>Settings → Roles & Permissions</strong> — the only account that can.'],
                    ['title' => 'Streamer balances', 'body' => 'Your dashboard includes the streamer balance widget other admins don\'t see, for tracking loans/advances against upcoming pay runs.'],
                ],
            ];
        }

        if ($user?->isAdmin()) {
            return [
                'label' => 'Admin',
                'items' => [
                    ['title' => 'Review & approve shows', 'body' => 'Work the <strong>Status Board</strong> and <strong>Streamer Log</strong> to approve streamer-reviewed entries, then run <strong>Payouts</strong> weekly.'],
                    ['title' => 'Inventory & receiving', 'body' => 'Receive pallets, manage vendors, and watch <strong>Product Insights</strong> for dead stock and reorder suggestions.'],
                    ['title' => 'Manage the team', 'body' => 'Add teammates and set their role under <strong>Settings → Users</strong>. Fine-tune what each role can see under <strong>Settings → Roles & Permissions</strong>.'],
                ],
            ];
        }

        if ($user?->isFulfillmentAdmin()) {
            return [
                'label' => 'Fulfillment Admin',
                'items' => [
                    ['title' => 'Every channel\'s fulfillment work', 'body' => 'Unlike regular fulfillment teammates, you see the <strong>Fulfillment Center</strong> across every channel, not just shows assigned to you.'],
                    ['title' => 'PWE + Labels review', 'body' => 'You\'re the one who actions the "Fulfillment Reviewed" step in <strong>Streamer Log</strong> for streamers on that payout type — their pay depends on your count.'],
                    ['title' => 'Clock in/out', 'body' => 'Use <strong>Timekeeping</strong> to log your hours — you only ever see your own entries there.'],
                ],
            ];
        }

        if ($user?->isFulfillment()) {
            return [
                'label' => 'Fulfillment',
                'items' => [
                    ['title' => 'Your assigned shows', 'body' => 'The <strong>Fulfillment Center</strong> shows only the shows assigned to you — pack and ship what\'s there.'],
                    ['title' => 'Clock in/out', 'body' => 'Use <strong>Timekeeping</strong> to log your hours — you only ever see your own entries there.'],
                ],
            ];
        }

        if ($user?->isStreamer()) {
            return [
                'label' => 'Streamer',
                'items' => [
                    ['title' => 'Add items during/after your show', 'body' => 'Open your show and click <strong>Add Items</strong> to log what sold — no cost needed, ops fills that in later.'],
                    ['title' => 'Review your show', 'body' => 'From <strong>Streamer Log → To Review</strong>, map sold items to inventory, confirm cost, add hours, and mark it <strong>Streamer Reviewed</strong>. You only ever see shows you were on.'],
                    ['title' => 'Check your pay', 'body' => 'Use <strong>Payouts</strong> for a running list of what you\'re owed/paid, or <strong>Streamer Statement</strong> for a printable breakdown by date range — both are locked to your own numbers.'],
                    ['title' => 'Clock in/out', 'body' => 'Use <strong>Timekeeping</strong> to log your hours — you only ever see your own entries there.'],
                ],
            ];
        }

        return [
            'label' => 'Your account',
            'items' => [
                ['title' => 'No role assigned yet', 'body' => 'Ask an admin to assign you a role under <strong>Settings → Users</strong> — until then you won\'t see much in the sidebar.'],
            ],
        ];
    }
}
