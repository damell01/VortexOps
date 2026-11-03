<?php

namespace App\Filament\Pages;

use App\Models\ShowChangeLog;
use App\Models\WhatnotApiSyncAudit;
use App\Models\WhatnotChannel;
use App\Services\WhatnotApiShowImporter;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

/**
 * Review what the Whatnot Seller API changed: every show field it set
 * (show change log, source whatnot_api) and what the last backfill and
 * syncs skipped for review. Owner only, opened from Owner tools.
 */
class WhatnotApiChanges extends Page
{
    use WithPagination;

    protected static ?string $title = 'Whatnot API Changes';
    protected static ?string $slug = 'whatnot-api-changes';
    protected static bool $shouldRegisterNavigation = false;

    public string $channel = '';
    public string $field = '';
    public string $search = '';

    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Owner Tools'; }
    public static function canAccess(): bool { return (bool) auth()->user()?->isOwner(); }
    public function getView(): string { return 'filament.pages.whatnot-api-changes'; }
    public function getSubheading(): ?string { return 'Every show value the Whatnot API filled in or corrected, and what it left for review.'; }

    public function updated(string $property): void
    {
        if (in_array($property, ['channel', 'field', 'search'], true)) $this->resetPage();
    }

    #[Computed]
    public function changes()
    {
        return ShowChangeLog::query()
            ->where('source', WhatnotApiShowImporter::SOURCE)
            ->join('shows', 'shows.id', '=', 'show_change_logs.show_id')
            ->leftJoin('whatnot_channels as wc', 'wc.id', '=', 'shows.whatnot_channel_id')
            ->when($this->channel !== '', fn ($q) => $q->where('shows.whatnot_channel_id', (int) $this->channel))
            ->when($this->field !== '', fn ($q) => $q->where('show_change_logs.field_name', $this->field))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('shows.title', 'like', '%'.trim($this->search).'%'))
            ->orderByDesc('show_change_logs.id')
            ->select('show_change_logs.*', 'shows.title as show_title', 'shows.show_date', 'wc.name as channel_name')
            ->paginate(50);
    }

    #[Computed]
    public function fields(): array
    {
        return ShowChangeLog::query()->where('source', WhatnotApiShowImporter::SOURCE)->distinct()->orderBy('field_name')->pluck('field_name')->all();
    }

    #[Computed]
    public function channels(): array
    {
        return WhatnotChannel::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** The latest backfill and sync per channel, with what they skipped for review. */
    #[Computed]
    public function runs(): array
    {
        return WhatnotApiSyncAudit::query()->whereIn('resource', ['backfill', 'sync', 'verify'])
            ->orderByDesc('id')->limit(30)->get()
            ->unique(fn ($a) => $a->whatnot_channel_id.'|'.$a->resource)
            ->map(function (WhatnotApiSyncAudit $a) {
                $s = $a->summary ?? [];
                $flags = [];
                foreach (($s['steps'] ?? []) + array_intersect_key($s, array_flip(['orders', 'ledger', 'shows'])) as $step => $counts) {
                    if (! is_array($counts)) continue;
                    foreach (['id_format_mismatch' => 'orders skipped: order IDs differ from the scraper\'s', 'show_not_found' => 'orders whose show is not in VortexOps',
                        'other_show' => 'orders already filed under a different show', 'covered_day_unmatched' => 'ledger rows not added: the scraper has that day but no exact match',
                        'ambiguous' => 'ledger rows with more than one possible match', 'other_channel' => 'shows already on another channel'] as $key => $label) {
                        if (($counts[$key] ?? 0) > 0) $flags[] = $counts[$key].' '.$label." ({$step})";
                    }
                }
                return ['channel' => WhatnotChannel::find($a->whatnot_channel_id)?->name ?? '#'.$a->whatnot_channel_id,
                    'kind' => $a->resource, 'status' => $a->status, 'at' => $a->finished_at?->diffForHumans(),
                    'flags' => $flags, 'errors' => $s['errors'] ?? $s['problems'] ?? []];
            })->values()->all();
    }
}
