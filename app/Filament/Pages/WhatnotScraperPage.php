<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Models\Setting;
use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatnotScraperPage extends Page
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'streams';
    protected static string $featureSlug = 'show_ingestion_logs';

    protected static ?string $title = 'Whatnot Scraper';
    protected static ?string $navigationLabel = 'Scraper & Import';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-down-tray';
    }

    public function getView(): string
    {
        return 'filament.pages.whatnot-scraper';
    }

    // ── State ─────────────────────────────────────────────────────────────────

    public string $testResult   = '';
    public string $testStatus   = '';
    public string $importResult = '';
    public string $importStatus = '';
    public string $urlResult    = '';
    public string $urlStatus    = '';

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getChannelsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return WhatnotChannel::orderBy('name')->get();
    }

    public function getLastImportProperty(): string
    {
        return Setting::get('whatnot_last_import_at', '');
    }

    public function getRecentShowsProperty(): array
    {
        return Show::with('channel')
            ->orderByDesc('show_date')
            ->limit(15)
            ->get(['id', 'title', 'show_date', 'gross_revenue', 'units_sold', 'status', 'whatnot_channel_id'])
            ->map(fn ($s) => [
                'id'      => $s->id,
                'title'   => $s->title,
                'date'    => $s->show_date?->format('M j, Y'),
                'gross'   => number_format((float) $s->gross_revenue, 2),
                'units'   => $s->units_sold ?? 0,
                'status'  => $s->status,
                'channel' => $s->channel?->name ?? '—',
            ])
            ->toArray();
    }

    public function getConfiguredProperty(): bool
    {
        return ! empty(config('vortex.whatnot.email')) && ! empty(config('vortex.whatnot.password'));
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function testConnection(): void
    {
        $this->testResult = '';
        $this->testStatus = '';

        try {
            $result = app(WhatnotScraper::class)->testConnection();
            $this->testResult = "Connected as {$result['email']}. Seller dashboard accessible.";
            $this->testStatus = 'success';

            Notification::make()->title('Whatnot connection OK')->body($this->testResult)->success()->send();
        } catch (\Throwable $e) {
            $this->testResult = $e->getMessage();
            $this->testStatus = 'error';

            Notification::make()->title('Connection failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function runImport(): void
    {
        $this->importResult = '';
        $this->importStatus = '';

        try {
            $result = app(WhatnotScraper::class)->importAllEnabledChannels(
                limit: (int) config('vortex.whatnot.limit', 50),
            );

            $this->importResult = "Import complete — {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";
            $this->importStatus = 'success';

            Setting::set('whatnot_last_import_at', now()->toISOString());

            Notification::make()->title('Import complete')->body($this->importResult)->success()->send();
        } catch (\Throwable $e) {
            $this->importResult = $e->getMessage();
            $this->importStatus = 'error';

            Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Scrape /seller/shows to backfill detail_url on existing shows so order
     * import becomes available on each show's page.
     */
    public function fetchShowUrls(): void
    {
        $this->urlResult = '';
        $this->urlStatus = '';

        try {
            $result = app(WhatnotScraper::class)->importDetailUrls();

            $this->urlResult = "Done — {$result['updated']} show URL(s) saved, {$result['unmatched']} could not be matched to an existing show.";
            $this->urlStatus = 'success';

            Notification::make()->title('Show URLs updated')->body($this->urlResult)->success()->send();
        } catch (\Throwable $e) {
            $this->urlResult = $e->getMessage();
            $this->urlStatus = 'error';

            Notification::make()->title('URL fetch failed')->body($e->getMessage())->danger()->send();
        }
    }
}
