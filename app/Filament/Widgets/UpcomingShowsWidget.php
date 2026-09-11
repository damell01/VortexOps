<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Support\AdminModules;
use Filament\Widgets\Widget;

class UpcomingShowsWidget extends Widget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];
    protected static bool $isLazy = true;
    protected string $view = 'filament.widgets.upcoming-shows';

    public static function canView(): bool
    {
        return ((auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false)
            && AdminModules::isEnabled('streams');
    }

    protected function getViewData(): array
    {
        $nowTime = now()->format('H:i:s');

        $shows = Show::query()
            ->with('channel')
            ->inChannelContext()
            ->where('is_operational', true)
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->where(function ($query) use ($nowTime) {
                $query->whereDate('show_date', '>', today())
                    ->orWhere(function ($today) use ($nowTime) {
                        $today->whereDate('show_date', today())
                            ->where(function ($time) use ($nowTime) {
                                $time->whereNull('start_time')
                                    ->orWhereTime('start_time', '>', $nowTime);
                            });
                    });
            })
            ->orderBy('show_date')
            ->orderBy('start_time')
            ->limit(6)
            ->get();

        return [
            'shows' => $shows,
            'allShowsUrl' => ShowResource::getUrl('index'),
        ];
    }
}
