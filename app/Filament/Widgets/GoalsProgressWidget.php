<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Goals;
use App\Services\GoalProgress;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/** This month's goals as progress bars on the dashboard. */
class GoalsProgressWidget extends Widget
{
    protected static ?int $sort = 0;
    protected static bool $isLazy = true;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.goals-progress';

    public static function canView(): bool
    {
        return Goals::canAccess();
    }

    protected function getViewData(): array
    {
        $progress = Cache::remember('widget:goals:' . now()->format('Y-m-d-H') . ':' . \App\Models\Goal::query()->count() . ':' . \App\Models\Goal::query()->max('updated_at'), 300,
            fn () => app(GoalProgress::class)->forMonth(now()));

        return ['progress' => $progress, 'url' => Goals::getUrl(panel: 'admin'), 'monthLabel' => now()->format('F')];
    }
}
