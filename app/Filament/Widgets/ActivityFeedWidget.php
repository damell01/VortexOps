<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Spatie\Activitylog\Models\Activity;

class ActivityFeedWidget extends Widget
{
    protected static ?int $sort = 9;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Recent Activity';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin();
    }

    public function getView(): string
    {
        return 'filament.widgets.activity-feed';
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
