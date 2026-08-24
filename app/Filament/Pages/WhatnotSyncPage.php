<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Jobs\RunWhatnotSyncJob;
use App\Jobs\SyncWhatnotShipmentsJob;
use App\Models\Setting;
use App\Models\WhatnotChannel;
use App\Models\WhatnotSync;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatnotSyncPage extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'streams';

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
