<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Concerns\HasModuleAccess;
use App\Jobs\RunWhatnotSyncJob;
use App\Models\WhatnotChannel;
use App\Models\WhatnotSync;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatnotSyncPage extends Page
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'streams';
    protected static string $featureSlug = 'whatnot_sync';

    protected static ?string $title           = 'Whatnot Sync';
    protected static ?string $navigationLabel = 'Sync Dashboard';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 36;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-path';
    }

    public function getView(): string
    {
        return 'filament.pages.whatnot-sync';
    }

    // ── Computed properties for the view ──────────────────────────────────────

    public function getChannelsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return WhatnotChannel::where('include_in_import', true)
            ->where('status', 'active')
            ->with('latestSync')
            ->orderBy('name')
            ->get();
    }

    public function getRecentSyncsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return WhatnotSync::with('channel')
            ->latest('started_at')
            ->limit(20)
            ->get();
    }

    public function getLastSyncProperty(): ?WhatnotSync
    {
        return WhatnotSync::where('status', 'completed')->latest('started_at')->first();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function syncIncremental(?int $channelId = null): void
    {
        RunWhatnotSyncJob::dispatch($channelId, 'incremental');
        Notification::make()->title('Incremental sync queued')->success()->send();
    }

    public function syncLast30Days(?int $channelId = null): void
    {
        RunWhatnotSyncJob::dispatch($channelId, 'last_30_days');
        Notification::make()->title('Last 30 days sync queued')->success()->send();
    }

    public function syncFull(?int $channelId = null): void
    {
        RunWhatnotSyncJob::dispatch($channelId, 'full');
        Notification::make()->title('Full resync queued')->warning()->send();
    }
}
