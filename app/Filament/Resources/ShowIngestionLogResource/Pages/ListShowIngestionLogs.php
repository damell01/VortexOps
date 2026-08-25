<?php

namespace App\Filament\Resources\ShowIngestionLogResource\Pages;

use App\Filament\Resources\ShowIngestionLogResource;
use App\Support\ScraperStatus;
use Filament\Resources\Pages\ListRecords;

class ListShowIngestionLogs extends ListRecords
{
    protected static string $resource = ShowIngestionLogResource::class;

    /** Per-request memo — the view reads each of these once, but renders repeat. */
    private ?array $statusMemo = null;

    public function getView(): string
    {
        return 'filament.resources.show-ingestion-log-resource.pages.list-show-ingestion-logs';
    }

    public function getTitle(): string
    {
        return 'Ingestion';
    }

    public function getSubheading(): ?string
    {
        return 'What the Whatnot importer has pulled in, per channel, and whether it is still working.';
    }

    /**
     * Point the table at one channel from the summary cards above it.
     *
     * Filters are deferred, so setting the state is only half of it — without
     * the apply the card would highlight a channel and the log underneath
     * would carry on showing everything.
     */
    public function focusChannel(int $channelId): void
    {
        // Clicking the channel you are already looking at goes back to all of
        // them, so the cards toggle rather than trapping you in one channel.
        $values = $this->focusedChannelId() === $channelId ? [] : [(string) $channelId];

        // The filter form binds to tableDeferredFilters and applyTableFilters()
        // copies that onto tableFilters — deferred is the side to write, and
        // writing the applied side directly would be undone by the next apply
        // and would leave the Filters dialog showing something else.
        $this->tableDeferredFilters ??= [];
        $this->tableDeferredFilters['whatnot_channel_id']['values'] = $values;

        $this->applyTableFilters();
    }

    public function focusedChannelId(): ?int
    {
        // The applied set, not the deferred one: this drives the card
        // highlight, which should follow what the table is actually showing.
        $values = $this->tableFilters['whatnot_channel_id']['values'] ?? [];

        return count($values) === 1 ? (int) $values[0] : null;
    }

    /**
     * Everything the panel above the table needs, gathered once.
     *
     * @return array{overall:string,jobs:array,channels:array,scheduler_at:?\Illuminate\Support\Carbon,
     *     scheduler_ok:bool,paused:bool,session:array}
     */
    public function getStatus(): array
    {
        return $this->statusMemo ??= [
            'overall'      => ScraperStatus::overall(),
            'jobs'         => ScraperStatus::jobs(),
            'channels'     => ScraperStatus::byChannel(),
            'scheduler_at' => ScraperStatus::schedulerLastRanAt(),
            'scheduler_ok' => ScraperStatus::schedulerIsRunning(),
            'paused'       => ScraperStatus::isPaused(),
            'session'      => ScraperStatus::session(),
        ];
    }
}
