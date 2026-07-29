<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FulfillmentNeedsAttentionWidget;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\OperationsOverviewWidget;
use App\Filament\Widgets\RecentShowsWidget;
use App\Filament\Widgets\ShowsKpiWidget;
use App\Filament\Widgets\StreamerOverviewWidget;
use App\Filament\Widgets\StreamerShowsToReviewWidget;
use App\Models\Setting;
use App\Support\ChannelContext;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Curated home overview. Each widget's own canView() still applies, so a
     * user only sees the ones relevant to their role/enabled modules.
     */
    public function getWidgets(): array
    {
        if ((bool) Setting::get('demo_mode', false)) {
            return [];
        }

        return [
            // Streamer-scoped overview + actionable to-do list (streamers only).
            StreamerOverviewWidget::class,
            StreamerShowsToReviewWidget::class,
            // Admin/owner overview (each gates itself via canView()).
            ShowsKpiWidget::class,
            NeedsAttentionWidget::class,
            // Fulfillment / fulfillment_admin's own "what needs me today" list.
            FulfillmentNeedsAttentionWidget::class,
            OperationsOverviewWidget::class,
            RecentShowsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getView(): string
    {
        if ((bool) Setting::get('demo_mode', false)) {
            return 'filament.pages.demo-dashboard';
        }

        return parent::getView();
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        if ((bool) Setting::get('demo_mode', false)) {
            return 'Welcome to VortexOps';
        }

        $hour = (int) now()->format('G');

        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };

        $name = auth()->user()?->name ?? 'there';

        return "{$greeting}, {$name}";
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        if ((bool) Setting::get('demo_mode', false)) {
            return 'Use the navigation on the left to explore the enabled modules below.';
        }

        $channel     = ChannelContext::current();
        $channelName = $channel?->display_title ?: $channel?->name;

        return 'Overview of ' . ($channelName ?: 'Vortex Breaks') . ' operations · ' . now()->format('l, F j, Y');
    }
}
