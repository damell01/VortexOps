<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Jobs\RunWhatnotSyncJob;
use App\Jobs\RunWhatnotReportingJob;
use App\Jobs\SyncWhatnotShipmentsJob;
use App\Models\Setting;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Models\WhatnotSync;
use App\Support\AdminModules;
use App\Support\WhatnotBrowserLock;
use App\Support\WhatnotPipelineLock;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatnotSyncPage extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'streams';

    protected static ?string $title           = 'Whatnot Operations';
    protected static ?string $navigationLabel = 'Scraper Status';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Super Admin';
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

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getReportingJobProperty(): array
    {
        return json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
    }

    public function runReporting(string $mode): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        abort_unless(in_array($mode, ['test', 'freshness', 'analytics', 'full'], true), 422);
        $active = $this->reportingJob;
        if (in_array($active['status'] ?? null, ['queued', 'running'], true)) {
            Notification::make()->title('A Whatnot job is already active')->warning()->send();
            return;
        }
        Setting::set('whatnot_ui_job', json_encode([
            'mode' => $mode, 'status' => 'queued', 'launched_by' => auth()->id(),
            'queued_at' => now()->toIso8601String(), 'phase' => 'Waiting for queue worker',
        ]));
        RunWhatnotReportingJob::dispatch($mode, (int) auth()->id());
        Notification::make()->title(ucfirst($mode).' Whatnot job queued')->success()->send();
    }

    public function getPipelineStatusProperty(): array
    {
        WhatnotPipelineLock::recoverIfStale();
        WhatnotBrowserLock::recoverIfStale();

        return [
            'pipeline' => WhatnotPipelineLock::holder(),
            'browser' => WhatnotBrowserLock::holder(),
        ];
    }

    public function recoverStaleLocks(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $pipelineRecovered = WhatnotPipelineLock::recoverIfStale();
        $browser = WhatnotBrowserLock::recoverIfStale();

        if ($pipelineRecovered || ($browser['recovered'] ?? false)) {
            Notification::make()->title('Stale Whatnot lock recovered')->success()->send();
            return;
        }

        Notification::make()
            ->title('No stale locks found')
            ->body('Healthy active processes were left alone.')
            ->info()
            ->send();
    }

    public function getRecentActivityProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return ShowIngestionLog::query()
            ->with(['show', 'channel'])
            ->latest('created_at')
            ->limit(12)
            ->get();
    }

    public function getActivityUrlProperty(): string
    {
        return \App\Filament\Resources\ShowIngestionLogResource::getUrl('index');
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

    /**
     * Shipment refresh (weight/dims/carrier/status) runs outside the whatnot_syncs
     * table — it's tracked via Setting heartbeats instead, same pattern as the
     * whatnot_last_import_success_at used for show imports.
     */
    public function getLastShipmentSyncProperty(): ?array
    {
        $at = Setting::get('whatnot_last_shipment_sync_at');
        if (! $at) {
            return null;
        }

        $summary = json_decode(Setting::get('whatnot_last_shipment_sync_summary', '{}'), true) ?: [];

        return [
            'at'            => \Illuminate\Support\Carbon::parse($at),
            'updated'       => $summary['updated'] ?? 0,
            'shows_checked' => $summary['shows_checked'] ?? 0,
            'errors'        => $summary['errors'] ?? [],
        ];
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

    /**
     * The confirmation this button needs, as a real modal.
     *
     * It used to be `onclick="return confirm(...)"` sitting next to a
     * wire:click. Livewire binds its own listener, so returning false from an
     * inline onclick does not stop it — pressing Cancel queued the full
     * resync anyway, which is the one action on this page worth being sure
     * about.
     */
    public function fullResyncAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('fullResync')
            ->label('Full Resync')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Run a full resync?')
            ->modalDescription('This re-pulls every show, order and shipment from the beginning. It can take several minutes and will keep the scraper busy until it finishes.')
            ->modalSubmitActionLabel('Start full resync')
            ->action(fn () => $this->syncFull());
    }

    public function syncShipments(?int $channelId = null): void
    {
        SyncWhatnotShipmentsJob::dispatch($channelId);
        Notification::make()->title('Shipment refresh queued')->success()->send();
    }
}
