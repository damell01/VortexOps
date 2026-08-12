<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Spatie\Activitylog\Models\Activity;

class ActivityFeedWidget extends Widget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 9;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Recent Activity';
    protected string $view = 'filament.widgets.activity-feed';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        $activities = Activity::with('causer')
            ->latest()
            ->limit(20)
            ->get();

        return compact('activities');
    }
}
