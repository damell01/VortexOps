<?php

namespace App\Filament\Resources\WhatnotLedgerResource\Pages;

use App\Filament\Resources\WhatnotLedgerResource;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\Business;
use App\Services\LedgerAttributionService;
use Illuminate\Support\Facades\DB;
use App\Support\ChannelContext;
use App\Support\MobileTablePagination;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Whatnot Ledger: every credit and debit the ledger import copied from
 * Whatnot, filterable by channel, date, type, status and free text, with the
 * totals for whatever the filters select and a details panel per row.
 *
 * A custom view rather than a Filament table so the channel chips, totals and
 * details panel sit together; the filtering, search, sort, CSV export, channel
 * scoping and owner-only delete the old table had are all kept here.
 */
class ListWhatnotLedgerEntries extends Page
{
    protected static string $resource = WhatnotLedgerResource::class;

    protected static ?string $title = 'Whatnot Ledger';

    public const CSV_ROW_LIMIT = 20000;

    public const PRESETS = [
        'all' => 'All time',
        '7' => 'Last 7 days',
        '30' => 'Last 30 days',
        '90' => 'Last 90 days',
        'ytd' => 'This year',
        'custom' => 'Custom range',
    ];

    /** 'all', 'unassigned' or a channel id. */
    #[Url(as: 'channel')]
    public string $channel = 'all';

    #[Url(as: 'period')]
    public string $preset = 'all';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    #[Url(as: 'type')]
    public string $type = '';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'category')]
    public string $categoryFilter = '';

    #[Url(as: 'destination')]
    public string $destinationFilter = '';

    #[Url(as: 'show')]
    public string $showFilter = '';

    #[Url(as: 'streamer')]
    public string $streamerFilter = '';

    #[Url(as: 'review')]
    public string $review = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $sort = 'created_date';

    #[Url]
    public string $dir = 'desc';

    public int $pageNo = 1;

    public int $perPage = 25;

    public ?int $selectedId = null;
    public bool $editingAttribution = false;
    public string $attributionCategory = 'other';
    public string $attributionTarget = 'unassigned';
    public string $attributionShowId = '';
    public string $attributionStreamerId = '';
    public string $attributionBusinessId = '';
    public string $attributionReason = '';

    /** @var array<int, string> ids ticked for the owner's bulk delete */
    public array $checked = [];

    private const SORTABLE = ['created_date', 'amount', 'transaction_type', 'status', 'whatnot_order_id', 'channel', 'completed_date'];

    public function getView(): string
    {
        return 'filament.resources.whatnot-ledger-resource.pages.list-whatnot-ledger-entries';
    }

    public function getHeading(): string
    {
        return ''; // the view draws its own header
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        // Links from elsewhere (the Channels table) still use the old
        // Filament filter parameter; honour it.
        $legacy = request()->input('tableFilters.whatnot_channel_id.value');
        if (filled($legacy)) {
            $this->channel = (string) (int) $legacy;
        }

        $this->perPage = (int) MobileTablePagination::defaultPageSize(25);
        $this->normalise();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['channel', 'preset', 'from', 'until', 'type', 'status', 'categoryFilter', 'destinationFilter', 'showFilter', 'streamerFilter', 'review', 'search', 'perPage'], true)) {
            if ($property === 'from' || $property === 'until') {
                $this->preset = 'custom';
            }
            $this->pageNo = 1;
            $this->checked = [];
            $this->normalise();
        }
    }

    private function normalise(): void
    {
        if (! array_key_exists($this->preset, self::PRESETS)) {
            $this->preset = 'all';
        }
        if (! in_array($this->sort, self::SORTABLE, true)) {
            $this->sort = 'created_date';
        }
        $this->dir = $this->dir === 'asc' ? 'asc' : 'desc';
        if (! in_array($this->perPage, [5, 10, 25, 50], true)) {
            $this->perPage = 25;
        }
        if (! in_array($this->review, ['all', 'needs_attribution', 'pending_approval', 'rejected_attribution', 'unverified_tips', 'tips_needs_business', 'tips_missing_order', 'tips_unmatched_order', 'tips_ambiguous_show', 'tips_missing_streamer'], true)) {
            $this->review = 'all';
        }
        if ($this->categoryFilter !== '' && ! in_array($this->categoryFilter, LedgerAttributionService::CATEGORIES, true)) $this->categoryFilter = '';
        if ($this->destinationFilter !== '' && ! in_array($this->destinationFilter, ['show', 'streamer', 'house', 'unassigned'], true)) $this->destinationFilter = '';
        foreach (['showFilter', 'streamerFilter'] as $field) {
            if ($this->{$field} !== '' && (! ctype_digit($this->{$field}) || (int) $this->{$field} < 1)) $this->{$field} = '';
        }
        if ($this->channel !== 'all' && $this->channel !== 'unassigned' && ! ctype_digit($this->channel)) {
            $this->channel = 'all';
        }
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
        $this->updated('channel');
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }
        if ($this->sort === $column) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->dir = in_array($column, ['created_date', 'completed_date', 'amount'], true) ? 'desc' : 'asc';
        }
        $this->pageNo = 1;
    }

    public function goToPage(int $page): void
    {
        $this->pageNo = max(1, min($page, $this->lastPage()));
    }

    public function select(?int $id): void
    {
        $this->selectedId = $this->selectedId === $id ? null : $id;
        $this->editingAttribution = false;
        if ($this->selectedId) {
            $existing = $this->selectedAttribution();
            $entry = $this->selected;
            $suggestedShow = $entry ? app(LedgerAttributionService::class)->suggestedShow($entry) : null;
            $this->attributionCategory = $existing->category ?? ($entry ? app(LedgerAttributionService::class)->suggestedCategory($entry) : 'other');
            $this->attributionTarget = $existing->target ?? ($suggestedShow ? 'show' : 'unassigned');
            $this->attributionShowId = (string) ($existing->show_id ?? $suggestedShow?->id ?? '');
            $this->attributionStreamerId = (string) ($existing->streamer_id ?? '');
            $this->attributionBusinessId = (string) ($existing->business_id ?? '');
            $this->attributionReason = '';
        }
    }

    public function toggleAttributionEditor(): void
    {
        abort_unless($this->canReconcile(), 403);
        $this->editingAttribution = ! $this->editingAttribution;
    }

    public function selectedShowDetails(): ?array
    {
        $entry = $this->selected;
        if (! $entry) return null;

        $attribution = $this->selectedAttribution();
        if ($attribution && $attribution->status === 'approved' && $attribution->show_id) {
            $show = Show::query()->whereKey($attribution->show_id)
                ->where('whatnot_channel_id', $entry->whatnot_channel_id)->first();
            if ($show) return ['id' => $show->id, 'title' => $show->title, 'date' => $show->show_date?->format('M j, Y'), 'source' => 'Approved allocation'];
        }

        $show = app(LedgerAttributionService::class)->suggestedShow($entry);
        return $show ? ['id' => $show->id, 'title' => $show->title, 'date' => $show->show_date?->format('M j, Y'), 'source' => 'Verified match · awaiting approval'] : null;
    }

    /**
     * Explain the selected transaction's evidence without treating a channel or
     * an unverified order association as proof of business ownership.
     */
    public function selectedReviewEvidence(): array
    {
        $entry = $this->selected;
        if (! $entry) return [];

        $attribution = $this->selectedAttribution();
        $show = $this->selectedShowDetails();
        $approved = $attribution?->status === 'approved' && $attribution?->business_id !== null;
        $order = trim((string) $entry->whatnot_order_id);
        $reason = match (true) {
            $approved => 'Approved business attribution exists.',
            $attribution?->status === 'pending' => 'Assignment submitted; a different administrator must review it.',
            $attribution?->status === 'rejected' => 'Previous assignment was rejected; inspect the evidence before resubmitting.',
            $entry->whatnot_channel_id === null => 'Source channel is missing; business cannot be inferred.',
            $order === '' || $order === '-' => 'Source order ID is missing. Check the imported livestream evidence.',
            $show === null => 'No uniquely verified show match. An imported order link alone is not sufficient evidence.',
            default => 'A uniquely verified show is suggested, but the business assignment still requires approval.',
        };

        return [
            'status' => $approved ? 'Verified' : ($attribution?->status === 'pending' ? 'Pending approval' : 'Needs review'),
            'reason' => $reason,
            'order' => $order !== '' && $order !== '-' ? $order : null,
            'show' => $show,
            'business_id' => $attribution?->business_id,
            'business_approved' => $approved,
        ];
    }

    public function canReconcile(): bool
    {
        return WhatnotLedgerResource::canAccess() && (bool) (auth()->user()?->isOwner() || auth()->user()?->isAdmin());
    }

    public function selectedAttribution(): ?object
    {
        return $this->selectedId ? DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $this->selectedId)->first() : null;
    }

    public function attributionShows(): array
    {
        if (! $this->selected || ! $this->canReconcile()) return [];
        return Show::query()->when($this->selected->whatnot_channel_id, fn ($q) => $q->where('whatnot_channel_id', $this->selected->whatnot_channel_id))
            ->orderByDesc('show_date')->limit(250)->get(['id', 'title', 'show_date'])->mapWithKeys(fn ($s) => [$s->id => $s->title.' ('.$s->show_date?->format('M j, Y').')'])->all();
    }

    public function attributionStreamers(): array
    {
        if (! $this->canReconcile()) return [];
        return Streamer::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function attributionBusinesses(): array
    {
        if (! $this->canReconcile()) return [];
        return Business::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function submitAttribution(LedgerAttributionService $service): void
    {
        abort_unless($this->canReconcile(), 403);
        $entry = $this->selected ?? abort(404);
        $service->save($entry, [
            'category' => $this->attributionCategory, 'target' => $this->attributionTarget,
            'show_id' => $this->attributionShowId, 'streamer_id' => $this->attributionStreamerId,
            'business_id' => $this->attributionBusinessId, 'reason' => $this->attributionReason,
        ], auth()->id());
        $this->attributionReason = '';
        Notification::make()->title('Ledger correction submitted for approval')->success()->send();
    }

    public function reviewAttribution(bool $approve, LedgerAttributionService $service): void
    {
        abort_unless($this->canReconcile(), 403);
        $service->review($this->selected ?? abort(404), auth()->id(), $approve);
        Notification::make()->title($approve ? 'Correction approved' : 'Correction rejected')->success()->send();
    }

    public function clearFilters(): void
    {
        $this->preset = 'all';
        $this->from = $this->until = null;
        $this->type = $this->status = $this->search = $this->showFilter = $this->streamerFilter = $this->categoryFilter = $this->destinationFilter = '';
        $this->review = 'all';
        $this->pageNo = 1;
    }

    /** [from, until] dates the period selects, or nulls for "all time". */
    public function range(): array
    {
        $today = Carbon::today();

        return match ($this->preset) {
            '7' => [$today->copy()->subDays(6), $today],
            '30' => [$today->copy()->subDays(29), $today],
            '90' => [$today->copy()->subDays(89), $today],
            'ytd' => [$today->copy()->startOfYear(), $today],
            'custom' => [
                $this->from ? rescue(fn () => Carbon::parse($this->from), null, false) : null,
                $this->until ? rescue(fn () => Carbon::parse($this->until), null, false) : null,
            ],
            default => [null, null],
        };
    }

    /** Resource query (channel context scoping) plus every filter on the page. */
    public function filteredQuery(): Builder
    {
        [$from, $until] = $this->range();

        return WhatnotLedgerResource::getEloquentQuery()
            ->when($this->channel === 'unassigned', fn (Builder $q) => $q->whereNull('whatnot_channel_id'))
            ->when(ctype_digit($this->channel), fn (Builder $q) => $q->where('whatnot_channel_id', (int) $this->channel))
            ->when($from, fn (Builder $q) => $q->whereDate('created_date', '>=', $from->toDateString()))
            ->when($until, fn (Builder $q) => $q->whereDate('created_date', '<=', $until->toDateString()))
            ->when($this->review === 'needs_attribution', fn (Builder $q) => $q->whereNotExists(function ($sub) {
                $sub->selectRaw('1')->from('ledger_attributions as la')
                    ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                    ->where('la.status', 'approved');
            }))
            ->when($this->review === 'pending_approval', fn (Builder $q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('ledger_attributions as la')
                    ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                    ->where('la.status', 'pending');
            }))
            ->when($this->review === 'rejected_attribution', fn (Builder $q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('ledger_attributions as la')
                    ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                    ->where('la.status', 'rejected');
            }))
            ->when($this->review === 'unverified_tips', fn (Builder $q) => $q
                ->whereRaw('LOWER(TRIM(whatnot_ledger_entries.transaction_type)) IN (?, ?)', ['tip', 'tips'])
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')->from('ledger_attributions as la')
                        ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                        ->where('la.status', 'approved')
                        ->where('la.target', 'show')
                        ->whereNotNull('la.show_id')
                        ->whereNotNull('la.streamer_id');
                }))
            ->when($this->review === 'tips_needs_business', fn (Builder $q) => $q
                ->whereRaw('LOWER(TRIM(whatnot_ledger_entries.transaction_type)) IN (?, ?)', ['tip', 'tips'])
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')->from('ledger_attributions as la')
                        ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                        ->where('la.status', 'approved')
                        ->whereNotNull('la.business_id');
                }))
            ->when($this->review === 'tips_missing_order', fn (Builder $q) => $q
                ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
                ->where(fn (Builder $missing) => $missing->whereNull('whatnot_order_id')->orWhereIn('whatnot_order_id', ['', '-'])))
            ->when($this->review === 'tips_unmatched_order', fn (Builder $q) => $q
                ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
                ->whereNotNull('whatnot_order_id')
                ->whereNotIn('whatnot_order_id', ['', '-'])
                ->whereNotExists(function ($subquery) {
                    $subquery->selectRaw('1')
                        ->from('whatnot_show_orders')
                        ->join('shows', 'shows.id', '=', 'whatnot_show_orders.show_id')
                        ->whereColumn('whatnot_show_orders.whatnot_order_id', 'whatnot_ledger_entries.whatnot_order_id')
                        ->whereColumn('shows.whatnot_channel_id', 'whatnot_ledger_entries.whatnot_channel_id');
                }))
            ->when($this->review === 'tips_ambiguous_show', fn (Builder $q) => $q
                ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
                ->whereNotNull('whatnot_order_id')
                ->whereNotIn('whatnot_order_id', ['', '-'])
                ->whereExists(function ($subquery) {
                    $subquery->selectRaw('1')
                        ->from('whatnot_show_orders')
                        ->join('shows', 'shows.id', '=', 'whatnot_show_orders.show_id')
                        ->whereColumn('whatnot_show_orders.whatnot_order_id', 'whatnot_ledger_entries.whatnot_order_id')
                        ->whereColumn('shows.whatnot_channel_id', 'whatnot_ledger_entries.whatnot_channel_id')
                        ->groupBy('whatnot_show_orders.whatnot_order_id')
                        ->havingRaw('COUNT(DISTINCT shows.id) > 1');
                }))
            ->when($this->review === 'tips_missing_streamer', fn (Builder $q) => $q
                ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
                ->whereNotNull('whatnot_order_id')
                ->whereNotIn('whatnot_order_id', ['', '-'])
                ->whereExists(function ($subquery) {
                    $subquery->selectRaw('1')
                        ->from('whatnot_show_orders')
                        ->join('shows', 'shows.id', '=', 'whatnot_show_orders.show_id')
                        ->whereColumn('whatnot_show_orders.whatnot_order_id', 'whatnot_ledger_entries.whatnot_order_id')
                        ->whereColumn('shows.whatnot_channel_id', 'whatnot_ledger_entries.whatnot_channel_id')
                        ->groupBy('whatnot_show_orders.whatnot_order_id')
                        ->havingRaw('COUNT(DISTINCT shows.id) = 1');
                })
                ->whereNotExists(function ($subquery) {
                    $subquery->selectRaw('1')
                        ->from('whatnot_show_orders')
                        ->join('shows', 'shows.id', '=', 'whatnot_show_orders.show_id')
                        ->join('show_streamer', 'show_streamer.show_id', '=', 'shows.id')
                        ->whereColumn('whatnot_show_orders.whatnot_order_id', 'whatnot_ledger_entries.whatnot_order_id')
                        ->whereColumn('shows.whatnot_channel_id', 'whatnot_ledger_entries.whatnot_channel_id');
                }))
            ->when($this->categoryFilter !== '', fn (Builder $q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('ledger_attributions as la')
                    ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                    ->where('la.status', 'approved')->where('la.category', $this->categoryFilter);
            }))
            ->when($this->destinationFilter !== '', fn (Builder $q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('ledger_attributions as la')
                    ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                    ->where('la.status', 'approved')->where('la.target', $this->destinationFilter);
            }))
            ->when($this->showFilter !== '', fn (Builder $q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('ledger_attributions as la')
                    ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                    ->where('la.status', 'approved')->where('la.show_id', (int) $this->showFilter);
            }))
            ->when($this->streamerFilter !== '', fn (Builder $q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('ledger_attributions as la')
                    ->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                    ->where('la.status', 'approved')->where('la.streamer_id', (int) $this->streamerFilter);
            }))
            ->when($this->type !== '', fn (Builder $q) => $q->where('transaction_type', $this->type))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn (Builder $w) => $w->where('whatnot_order_id', 'like', $term)->orWhere('message', 'like', $term));
            });
    }

    private function sortedQuery(): Builder
    {
        $query = $this->filteredQuery();

        if ($this->sort === 'channel') {
            $query->orderBy(
                WhatnotChannel::query()->select('name')->whereColumn('whatnot_channels.id', 'whatnot_ledger_entries.whatnot_channel_id'),
                $this->dir,
            );
        } else {
            $query->orderBy($this->sort, $this->dir);
        }

        return $query->orderBy('id', $this->dir);
    }

    public function totalRows(): int
    {
        return $this->filteredQuery()->count();
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->totalRows() / max(1, $this->perPage)));
    }

    /** @return array{credits: float, debits: float, net: float, processing: int, count: int} */
    public function getStatsProperty(): array
    {
        $row = $this->filteredQuery()->toBase()
            ->reorder()
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS credits')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) AS debits')
            ->selectRaw("SUM(CASE WHEN LOWER(status) IN ('processing', 'pending') THEN 1 ELSE 0 END) AS processing")
            ->selectRaw('COUNT(*) AS cnt')
            ->first();

        $credits = round((float) ($row->credits ?? 0), 2);
        $debits = round(abs((float) ($row->debits ?? 0)), 2);

        return [
            'credits' => $credits,
            'debits' => $debits,
            'net' => round($credits - $debits, 2),
            'processing' => (int) ($row->processing ?? 0),
            'count' => (int) ($row->cnt ?? 0),
        ];
    }

    /**
     * Monthly ledger movement for the current filters. Aggregate in SQL rather
     * than loading individual transactions into Livewire.
     */
    public function getLedgerTrendProperty(): array
    {
        $rows = $this->filteredQuery()->toBase()->reorder()
            ->selectRaw("DATE_FORMAT(whatnot_ledger_entries.created_date, '%Y-%m') as bucket")
            ->selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as credits')
            ->selectRaw('ABS(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END)) as debits')
            ->selectRaw('SUM(amount) as net')
            ->groupBy('bucket')->orderByDesc('bucket')->limit(12)->get()->reverse()->values();

        return $rows->map(fn ($row) => [
            'label' => (string) $row->bucket,
            'credits' => round((float) $row->credits, 2),
            'debits' => round((float) $row->debits, 2),
            'net' => round((float) $row->net, 2),
        ])->all();
    }

    /** Attribution status for the currently filtered ledger rows, without bypassing channel scope. */
    public function getAttributionSummaryProperty(): array
    {
        $ids = $this->filteredQuery()->select('whatnot_ledger_entries.id');
        $row = DB::table('whatnot_ledger_entries as le')
            ->joinSub($ids->toBase(), 'filtered', 'filtered.id', '=', 'le.id')
            ->leftJoin('ledger_attributions as a', 'a.whatnot_ledger_entry_id', '=', 'le.id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN a.id IS NULL THEN 1 ELSE 0 END) as unmatched")
            ->selectRaw("SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->selectRaw("SUM(CASE WHEN a.status = 'approved' AND a.target = 'show' THEN 1 ELSE 0 END) as shows")
            ->selectRaw("SUM(CASE WHEN a.status = 'approved' AND a.target = 'streamer' THEN 1 ELSE 0 END) as streamers")
            ->selectRaw("SUM(CASE WHEN a.status = 'approved' AND a.target = 'house' THEN 1 ELSE 0 END) as house")
            ->first();

        return collect((array) $row)->map(fn ($value) => (int) $value)->all();
    }

    /** Signed value awaiting attribution, using the same ledger filters as the table. */
    public function getUnallocatedAmountsProperty(): array
    {
        $ids = $this->filteredQuery()->select('whatnot_ledger_entries.id');
        $row = DB::table('whatnot_ledger_entries as le')
            ->joinSub($ids->toBase(), 'filtered', 'filtered.id', '=', 'le.id')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')->from('ledger_attributions as a')
                    ->whereColumn('a.whatnot_ledger_entry_id', 'le.id')
                    ->where('a.status', 'approved');
            })
            ->selectRaw('COUNT(*) as entries, COALESCE(SUM(le.amount), 0) as net')
            ->first();

        return ['entries' => (int) ($row->entries ?? 0), 'net' => (float) ($row->net ?? 0)];
    }

    /** Approved financial allocations grouped by category and destination, in the current filter scope. */
    public function getCategoryBreakdownProperty(): array
    {
        $ids = $this->filteredQuery()->select('whatnot_ledger_entries.id');
        return DB::table('whatnot_ledger_entries as le')
            ->joinSub($ids->toBase(), 'filtered', 'filtered.id', '=', 'le.id')
            ->join('ledger_attributions as a', 'a.whatnot_ledger_entry_id', '=', 'le.id')
            ->where('a.status', 'approved')
            ->groupBy('a.category', 'a.target')
            ->selectRaw('a.category, a.target, COUNT(*) as entries, SUM(le.amount) as net')
            ->orderBy('a.category')->orderBy('a.target')->get()->all();
    }

    /**
     * Money per streamer for the entries matching the current filters, from
     * approved matches (to a show's primary streamer or to a streamer
     * directly), split by category. Business/house entries are not included.
     *
     * @return list<array{streamer:string, tips:float, sales:float, refunds:float, shipping:float, other:float, net:float, entries:int}>
     */
    public function getStreamerTotalsProperty(): array
    {
        $ids = $this->filteredQuery()->select('whatnot_ledger_entries.id');
        $rows = DB::table('whatnot_ledger_entries as le')
            ->joinSub($ids->toBase(), 'filtered', 'filtered.id', '=', 'le.id')
            ->join('ledger_attributions as a', 'a.whatnot_ledger_entry_id', '=', 'le.id')
            ->join('streamers as s', 's.id', '=', 'a.streamer_id')
            ->where('a.status', 'approved')
            ->groupBy('a.streamer_id', 's.name', 'a.category')
            ->selectRaw('a.streamer_id, s.name, a.category, SUM(le.amount) as total, COUNT(*) as entries')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->streamer_id] ??= ['streamer' => $row->name, 'tips' => 0.0, 'sales' => 0.0, 'refunds' => 0.0, 'shipping' => 0.0, 'other' => 0.0, 'net' => 0.0, 'entries' => 0];
            $bucket = in_array($row->category, ['tips', 'sales', 'refunds', 'shipping'], true) ? $row->category : 'other';
            $out[$row->streamer_id][$bucket] += (float) $row->total;
            $out[$row->streamer_id]['net'] += (float) $row->total;
            $out[$row->streamer_id]['entries'] += (int) $row->entries;
        }
        usort($out, fn ($a, $b) => $b['net'] <=> $a['net']);

        return array_values($out);
    }

    /** @return Collection<int, WhatnotLedgerEntry> */
    public function getEntriesProperty(): Collection
    {
        $this->pageNo = max(1, min($this->pageNo, $this->lastPage()));

        return $this->sortedQuery()
            ->forPage($this->pageNo, $this->perPage)
            ->get();
    }

    public function getSelectedProperty(): ?WhatnotLedgerEntry
    {
        return $this->selectedId
            ? WhatnotLedgerResource::getEloquentQuery()->whereKey($this->selectedId)->first()
            : null;
    }

    /** Chips: All Channels, each active channel, and Unassigned when any rows lack a channel. */
    public function getChannelChipsProperty(): array
    {
        $chips = [['key' => 'all', 'label' => 'All Channels', 'count' => null]];

        $channels = WhatnotChannel::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        if (ChannelContext::isScoped()) {
            $channels = $channels->where('id', ChannelContext::currentId());
        }
        foreach ($channels as $channel) {
            $chips[] = ['key' => (string) $channel->id, 'label' => $channel->name, 'count' => null];
        }

        if (! ChannelContext::isScoped()) {
            $unassigned = WhatnotLedgerEntry::query()->whereNull('whatnot_channel_id')->count();
            if ($unassigned > 0) {
                $chips[] = ['key' => 'unassigned', 'label' => 'Unassigned', 'count' => $unassigned];
            }
        }

        // A channel picked by link that is not in the active list still gets a chip.
        if (ctype_digit($this->channel) && ! collect($chips)->contains('key', $this->channel)) {
            $name = WhatnotChannel::query()->whereKey((int) $this->channel)->value('name');
            if ($name) {
                $chips[] = ['key' => $this->channel, 'label' => $name, 'count' => null];
            }
        }

        return $chips;
    }

    /** Show and streamer filters only use approved financial attributions. */
    public function getShowFilterOptionsProperty(): array
    {
        return Show::query()
            ->when(ChannelContext::isScoped(), fn ($q) => $q->where('whatnot_channel_id', ChannelContext::currentId()))
            ->when(ctype_digit($this->channel), fn ($q) => $q->where('whatnot_channel_id', (int) $this->channel))
            ->orderByDesc('show_date')->limit(300)->get(['id', 'title', 'show_date'])
            ->mapWithKeys(fn ($show) => [$show->id => $show->title.' · '.$show->show_date?->format('M j, Y')])->all();
    }

    public function getStreamerFilterOptionsProperty(): array
    {
        return Streamer::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function getTypeOptionsProperty(): array
    {
        return $this->distinct('transaction_type');
    }

    /** @return array<string, string> */
    public function getStatusOptionsProperty(): array
    {
        return $this->distinct('status');
    }

    private function distinct(string $column): array
    {
        return WhatnotLedgerEntry::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->toArray();
    }

    public static function orderUrl(WhatnotLedgerEntry $entry): ?string
    {
        return filled($entry->order_hash)
            ? 'https://www.whatnot.com/dashboard/orders/'.rawurlencode($entry->order_hash)
            : null;
    }

    /** vxr pill tone for a status word, matching StatusColor's buckets. */
    public static function statusTone(?string $status): string
    {
        return match (\App\Support\StatusColor::for($status)) {
            'success' => 'ok',
            'warning' => 'warn',
            'danger' => 'bad',
            'info' => 'info',
            default => '',
        };
    }

    public function canDelete(): bool
    {
        return (bool) auth()->user()?->isOwner();
    }

    public function deleteChecked(): void
    {
        if (! $this->canDelete() || $this->checked === []) {
            return;
        }

        $ids = array_map('intval', $this->checked);
        $deleted = WhatnotLedgerResource::getEloquentQuery()->whereKey($ids)->get()->each->delete()->count();

        if (in_array($this->selectedId, $ids, true)) {
            $this->selectedId = null;
        }
        $this->checked = [];

        Notification::make()->title($deleted.' ledger '.str('entry')->plural($deleted).' deleted')->success()->send();
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->sortedQuery()->limit(self::CSV_ROW_LIMIT)->get();

        $fmt = fn ($d) => $d ? $d->format('M j, Y g:i A') : '';

        return response()->streamDownload(function () use ($rows, $fmt) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Created', 'Amount', 'Type', 'Status', 'Order ID', 'Details', 'Channel', 'Completed', 'Listing ID'], ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $fmt($r->created_date),
                    $r->amount === null ? '' : number_format((float) $r->amount, 2, '.', ''),
                    $r->transaction_type,
                    $r->status,
                    $r->whatnot_order_id,
                    $r->message,
                    $r->channel?->name,
                    $fmt($r->completed_date),
                    $r->listing_id,
                ], ',', '"', '');
            }
            fclose($out);
        }, 'whatnot-ledger-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
