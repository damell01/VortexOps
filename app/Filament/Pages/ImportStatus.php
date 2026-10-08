<?php

namespace App\Filament\Pages;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Models\WhatnotSync;
use App\Support\ChannelContext;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class ImportStatus extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Import Status';
    protected static ?string $slug = 'import-status';
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Admin'; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-arrow-path'; }
    public static function canAccess(): bool { return (bool) auth()->user()?->isOwner(); }
    public function getView(): string { return 'filament.pages.import-status'; }

    #[Computed]
    public function channels(): array
    {
        $channels = WhatnotChannel::query()
            ->when(ChannelContext::isScoped(), fn ($q) => $q->whereKey(ChannelContext::currentId()))
            ->orderBy('name')->get();
        $ids = $channels->modelKeys();
        $successIds = ShowIngestionLog::whereIn('whatnot_channel_id', $ids)->where('status', 'success')
            ->selectRaw('MAX(id) as id')->groupBy('whatnot_channel_id')->pluck('id');
        $successes = ShowIngestionLog::whereIn('id', $successIds)->get()->keyBy('whatnot_channel_id');
        $runIds = WhatnotSync::whereIn('whatnot_channel_id', $ids)->where('status', 'completed')->where('error_count', 0)
            ->selectRaw('MAX(id) as id')->groupBy('whatnot_channel_id')->pluck('id');
        $runs = WhatnotSync::whereIn('id', $runIds)->get()->keyBy('whatnot_channel_id');
        $counts = ShowIngestionLog::whereIn('whatnot_channel_id', $ids)->where('created_at', '>=', now()->subDay())
            ->selectRaw("whatnot_channel_id, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as updates, SUM(CASE WHEN status IN ('failed','partial') THEN 1 ELSE 0 END) as failures")
            ->groupBy('whatnot_channel_id')->get()->keyBy('whatnot_channel_id');

        return $channels->map(function ($channel) use ($successes, $runs, $counts) {
            $recent = ShowIngestionLog::with('show:id,title')->where('whatnot_channel_id', $channel->id)
                ->where('created_at', '>=', now()->subDay())->latest('id')->limit(8)->get();
            $missing = Show::where('whatnot_channel_id', $channel->id)->whereDate('show_date', '<', today())
                ->whereNotIn('status', ['cancelled'])->missingAnalytics();
            return [
                'channel' => $channel,
                'success' => $successes->get($channel->id),
                'run' => $runs->get($channel->id),
                'updates' => (int) ($counts->get($channel->id)?->updates ?? 0),
                'failures' => (int) ($counts->get($channel->id)?->failures ?? 0),
                'missing_count' => (clone $missing)->count(),
                'missing' => (clone $missing)->orderByDesc('show_date')->limit(5)->get(['id','title','show_date','analytics_sync_status','analytics_sync_note']),
                'events' => $recent->take(8),
            ];
        })->all();
    }
}
