<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\OperationsOverviewWidget;
use App\Filament\Widgets\RecentShowsWidget;
use App\Filament\Widgets\SetupChecklistWidget;
use App\Filament\Widgets\ShowsKpiWidget;
use App\Filament\Widgets\StreamerOverviewWidget;
use App\Models\Setting;
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
            // First-run onboarding (auto-hides once complete/dismissed).
            SetupChecklistWidget::class,
            // Streamer-scoped overview (visible to streamer accounts only).
            StreamerOverviewWidget::class,
            // Admin/owner overview (each gates itself via canView()).
            ShowsKpiWidget::class,
            NeedsAttentionWidget::class,
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

        return 'Overview of Vortex Breaks operations · ' . now()->format('l, F j, Y');
    }
}
