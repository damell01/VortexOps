<?php

namespace App\Filament\Resources\WhatnotLedgerResource\Pages;

use App\Filament\Resources\WhatnotLedgerResource;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
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

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $sort = 'created_date';

    #[Url]
    public string $dir = 'desc';

    public int $pageNo = 1;

    public int $perPage = 25;

    public ?int $selectedId = null;

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
        if (in_array($property, ['channel', 'preset', 'from', 'until', 'type', 'status', 'search', 'perPage'], true)) {
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
    }

    public function clearFilters(): void
    {
        $this->preset = 'all';
        $this->from = $this->until = null;
        $this->type = $this->status = $this->search = '';
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
