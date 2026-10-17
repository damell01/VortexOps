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
use App\Services\WhatnotAnalyticsCsvImporter;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatnotSyncPage extends Page
{
    use HasModuleAccess, WithFileUploads;

    public array $analyticsCsv = [];
    public ?int $analyticsCsvChannelId = null;
    public array $analyticsCsvResult = [];

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

    /**
     * Start a run right now: stop whatever the scraper is doing, clear its
     * locks and launch this one in the background (App\Support\WhatnotRunControl),
     * instead of queueing behind it.
     */
    public function runReporting(string $mode): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        abort_unless(array_key_exists($mode, self::RUN_MODES), 422);

        $stopped = \App\Support\WhatnotRunControl::stopEverything();
        $runId = (string) \Illuminate\Support\Str::uuid();
        Setting::set('whatnot_ui_job', json_encode([
            'run_id' => $runId, 'mode' => $mode, 'status' => 'queued', 'launched_by' => auth()->id(),
            'queued_at' => now()->toIso8601String(), 'started_at' => now()->toIso8601String(), 'phase' => 'Starting now…',
        ]));
        \App\Support\WhatnotLiveLog::clear();

        $direct = \App\Support\WhatnotRunControl::launch($mode, (int) auth()->id(), $runId);
        Notification::make()
            ->title(self::RUN_MODES[$mode] . ($direct ? ' started' : ' queued'))
            ->body($stopped . ($direct ? ' Watch it below.' : ' It could not be started directly, so it will start when the queue worker picks it up.'))
            ->{$direct ? 'success' : 'warning'}()
            ->send();
    }

    public const RUN_MODES = [
        'catchup' => 'Catch up everything',
        'freshness' => 'Freshness sync',
        'analytics' => 'Analytics only',
        'full' => 'Reconciliation',
        'test' => 'Smoke test',
    ];

    public function getJobActiveProperty(): bool
    {
        return in_array($this->reportingJob['status'] ?? null, ['queued', 'running', 'waiting'], true);
    }

    /** What the scraper has printed so far this run (App\Support\WhatnotLiveLog). */
    public function getLiveLogProperty(): array
    {
        return \App\Support\WhatnotLiveLog::lines();
    }

    /**
     * Shows this run has written to — analytics filled or orders re-pulled —
     * newest first, so you can watch the data land instead of reading logs.
     */
    public function getPulledThisRunProperty(): \Illuminate\Support\Collection
    {
        $since = $this->runStartedAt();
        if (! $since) {
            return collect();
        }

        return \App\Models\Show::query()
            ->with('channel:id,name')
            ->where(fn ($q) => $q->where('last_analytics_synced_at', '>=', $since)->orWhere('last_synced_at', '>=', $since))
            ->get(['id', 'title', 'show_date', 'whatnot_channel_id', 'gross_revenue', 'whatnot_net', 'units_sold', 'buyers_count', 'analytics_sync_status', 'analytics_sync_note', 'last_analytics_synced_at', 'last_synced_at'])
            ->sortByDesc(fn ($show) => max($show->last_analytics_synced_at?->getTimestamp() ?? 0, $show->last_synced_at?->getTimestamp() ?? 0))
            ->take(30)
            ->values();
    }

    /** Running totals for this run, and how much is still left to catch up. */
    public function getRunStatsProperty(): array
    {
        $since = $this->runStartedAt();
        $logs = $since ? ShowIngestionLog::where('created_at', '>=', $since) : null;
        $reportingStart = \Illuminate\Support\Carbon::parse('2026-07-01');

        return [
            'analytics' => $since ? \App\Models\Show::where('last_analytics_synced_at', '>=', $since)->count() : 0,
            'orders' => $since ? (clone $logs)->where('source', 'whatnot_orders')->count() : 0,
            'still_due' => \App\Models\Show::query()->whereDate('show_date', '>=', $reportingStart)->readyForAnalytics()->missingAnalytics()->count(),
            'elapsed' => $since ? \Carbon\CarbonInterval::seconds((int) $since->diffInSeconds(
                ! $this->jobActive && ! empty($this->reportingJob['finished_at']) ? \Illuminate\Support\Carbon::parse($this->reportingJob['finished_at']) : now()
            ))->cascade()->forHumans(['short' => true, 'parts' => 2]) : null,
        ];
    }

    public function getRunSinceProperty(): ?\Illuminate\Support\Carbon
    {
        return $this->runStartedAt();
    }

    private function runStartedAt(): ?\Illuminate\Support\Carbon
    {
        $at = $this->reportingJob['started_at'] ?? $this->reportingJob['queued_at'] ?? null;

        return $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone(config('app.timezone')) : null;
    }

    public function importAnalyticsCsv(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $this->validate([
            'analyticsCsvChannelId' => ['required', 'integer', 'exists:whatnot_channels,id'],
            'analyticsCsv' => ['required', 'array', 'min:1', 'max:25'],
            'analyticsCsv.*' => ['file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $totals = [
                'files' => count($this->analyticsCsv),
                'rows' => 0, 'created' => 0, 'updated' => 0, 'already_complete' => 0,
                'ignored_current_future' => 0, 'blank_metrics' => 0,
                'unmatched' => 0, 'ambiguous' => 0, 'no_shows' => 0,
                'exact_matched' => 0, 'date_tolerant_matched' => 0, 'fuzzy_matched' => 0,
                'unmatched_examples' => [],
            ];

            foreach ($this->analyticsCsv as $file) {
                $result = app(WhatnotAnalyticsCsvImporter::class)->import(
                    $file->getRealPath(),
                    (int) $this->analyticsCsvChannelId,
                );

                foreach (['rows','created','updated','already_complete','ignored_current_future','blank_metrics','unmatched','ambiguous','no_shows','exact_matched','date_tolerant_matched','fuzzy_matched'] as $key) {
                    $totals[$key] += (int) ($result[$key] ?? 0);
                }

                $totals['unmatched_examples'] = array_slice(array_values(array_unique(array_merge(
                    $totals['unmatched_examples'],
                    $result['unmatched_examples'] ?? [],
                ))), 0, 25);
            }

            $this->analyticsCsvResult = $totals;
            $this->analyticsCsv = [];

            Notification::make()
                ->title('Whatnot analytics CSVs imported')
                ->body(sprintf(
                    '%d files · %d rows · %d created · %d updated · %d unmatched · %d current/future ignored · %d no-shows',
                    $totals['files'], $totals['rows'], $totals['created'], $totals['updated'], $totals['unmatched'],
                    $totals['ignored_current_future'], $totals['no_shows'],
                ))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('CSV import failed')->body($e->getMessage())->danger()->persistent()->send();
        }
    }

    public function getPipelineStatusProperty(): array
    {
        WhatnotPipelineLock::recoverIfStale();
        WhatnotBrowserLock::recoverIfStale();

        $pipeline = WhatnotPipelineLock::holder();
        $browser = WhatnotBrowserLock::holder();

        return [
            'pipeline' => $pipeline,
            'browser' => $browser,
            'pipeline_process' => WhatnotBrowserLock::processDetails($pipeline['pid'] ?? null),
            'browser_process' => WhatnotBrowserLock::processDetails($browser['pid'] ?? null),
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

    public function forceClearLocks(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        try {
            $stopped = \App\Support\WhatnotRunControl::stopEverything();
            Setting::set('whatnot_ui_job', json_encode([
                'status' => 'idle',
                'phase' => 'Stopped by ' . (auth()->user()?->name ?? 'an admin'),
                'finished_at' => now()->toIso8601String(),
                'launched_by' => auth()->id(),
            ]));

            Notification::make()->title('Whatnot scraper stopped')->body($stopped)->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Could not stop the Whatnot scraper')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
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
