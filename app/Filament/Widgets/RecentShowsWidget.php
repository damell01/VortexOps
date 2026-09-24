<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Support\AdminModules;
use Filament\Widgets\Widget;

class RecentShowsWidget extends Widget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 3;
    protected static string $view = 'filament.widgets.recent-shows-cards';
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AdminModules::isEnabled('streams');
    }

    protected function getViewData(): array
    {
        $query = Show::query()
            ->with('channel')
            ->inChannelContext()
            ->where('is_operational', true)
            ->whereNotIn('status', ['cancelled'])
            // Do not show today's streams here. Analytics can still be settling.
            ->whereDate('show_date', '<=', today()->subDay())
            ->whereDate('show_date', '>=', today()->subDays(8));

        $user = auth()->user();
        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            $streamerId = $user->streamer?->id ?? 0;
            $query->whereHas('streamers', fn ($s) => $s->where('streamers.id', $streamerId));
        }

        $shows = $query->orderByDesc('show_date')->orderByDesc('start_time')->limit(5)->get();

        return [
            'shows' => $shows,
            'allShowsUrl' => ShowResource::getUrl('index'),
        ];
    }
}
