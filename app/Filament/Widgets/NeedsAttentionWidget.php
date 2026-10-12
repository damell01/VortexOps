<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ProductInsights;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Filament\Resources\StreamerResource;
use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Support\AdminModules;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One place that answers "what needs me today?" — a compact list of actionable
 * items with counts and deep links. Each row only appears when its count > 0,
 * so a healthy install shows a friendly all-clear.
 */
class NeedsAttentionWidget extends Widget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static bool $isLazy = false;
    protected static ?string $heading = 'Needs Attention';
    protected string $view = 'filament.widgets.needs-attention';

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    }

    protected function getViewData(): array
    {
        return ['items' => $this->items(), 'detailData' => $this->detail ? $this->detailFor($this->detail) : null];
    }

    /**
     * The same rows the widget shows, for the weekly owner email.
     *
     * @return array<int,array<string,mixed>>
     */
    public function summaryItems(): array
    {
        return $this->items();
    }

    /** @return array<int,array<string,mixed>> */
    private function items(): array
    {
        $items = [];

        // $detail names the list the row opens in a modal; null keeps it a plain link.
        $add = function (bool $when, int $count, string $label, string $icon, string $color, string $url, ?string $detail = null) use (&$items) {
            if ($when && $count > 0) {
                $items[] = compact('count', 'label', 'icon', 'color', 'url', 'detail');
            }
        };

        try {
            $add(
                AdminModules::isEnabled('streamer_log') && Schema::hasTable('streamer_log_entries'),
                StreamerLogEntry::where('status', 'pending')->inChannelContext()->count(),
                'streamer logs awaiting review',
                'heroicon-o-clipboard-document-list',
                'warning',
                StreamerLogResource::getUrl(),
            );

            $add(
                AdminModules::isEnabled('streams') && Schema::hasColumn('shows', 'channel_attribution_suspect'),
                $this->channelReviewQuery()->count(),
                'shows need a channel confirmed',
                'heroicon-o-check-badge',
                'warning',
                ShowResource::getUrl('channel-review'),
                'channel_review',
            );

            $add(
                AdminModules::isEnabled('streams'),
                app(\App\Services\DuplicateShowFinder::class)->count(),
                'possible duplicate shows — the same stream recorded twice',
                'heroicon-o-document-duplicate',
                'danger',
                ShowResource::getUrl('index'),
                'duplicates',
            );

            $add(
                AdminModules::isEnabled('streams') && Schema::hasColumn('shows', 'financials_revised_after_lock'),
                $this->financialsRevisedQuery()->count(),
                'shows had financials change after being locked in — review before payroll',
                'heroicon-o-exclamation-triangle',
                'danger',
                ShowResource::getUrl('index', ['tableFilters[financials_revised_after_lock][value]' => '1']),
                'financials_revised',
            );

            $add(
                Schema::hasTable('deduction_requests'),
                DeductionRequest::where('status', 'pending')->inChannelContext()->count(),
                'deduction requests pending approval',
                'heroicon-o-clipboard-document-check',
                'info',
                DeductionRequestResource::getUrl(),
            );

            $add(
                AdminModules::isEnabled('inventory') && Schema::hasTable('inventory_stock'),
                $this->lowStockCount(),
                'items at or below reorder level',
                'heroicon-o-cube',
                'danger',
                InventoryItemResource::getUrl(),
                'low_stock',
            );

            $add(
                AdminModules::isEnabled('inventory') && Schema::hasTable('inventory_stock'),
                $this->deadStockCount(),
                'products dead on the shelf (capital stuck)',
                'heroicon-o-archive-box-x-mark',
                'warning',
                ProductInsights::getUrl(),
                'dead_stock',
            );

            $add(
                AdminModules::isEnabled('inventory') && Schema::hasTable('whatnot_show_orders'),
                $this->zeroStockMappedSalesCount(),
                'sold items mapped to a product with no stock — likely mis-mapped',
                'heroicon-o-magnifying-glass',
                'danger',
                InventoryItemResource::getUrl(),
                'zero_stock_sales',
            );

            // Scraper age is operational telemetry, not an action item. The
            // pipeline can be healthy even when there simply has not been a
            // new show to import. Real failed jobs still surface below and the
            // detailed scraper health remains available on System Health.
            $add(
                (bool) auth()->user()?->isOwner() && Schema::hasTable('failed_jobs'),
                auth()->user()?->isOwner() ? (int) DB::table('failed_jobs')->count() : 0,
                'failed background jobs',
                'heroicon-o-exclamation-triangle',
                'danger',
                SystemHealth::getUrl(),
                'failed_jobs',
            );

            $add(
                AdminModules::isEnabled('streams'),
                $this->trendingDownStreamerCount(),
                'streamers trending down vs. their recent average — worth a check-in',
                'heroicon-o-arrow-trending-down',
                'warning',
                StreamerResource::getUrl(),
                'trending_down',
            );
        } catch (\Throwable) {
            // On a partially-migrated install just show what we could gather.
        }

        return $items;
    }

    /** Which row's list is open in the modal. */
    public ?string $detail = null;

    /** Rows shown in a modal before pointing at the full page. */
    private const DETAIL_LIMIT = 50;

    public function openDetail(string $key): void
    {
        abort_unless(static::canView(), 403);
        abort_unless(in_array($key, ['duplicates', 'channel_review', 'financials_revised', 'low_stock', 'dead_stock', 'zero_stock_sales', 'failed_jobs', 'trending_down'], true), 404);
        abort_if($key === 'failed_jobs' && ! auth()->user()?->isOwner(), 403);
        $this->detail = $key;
        $this->dispatch('open-modal', id: $this->detailModalId());
    }

    public function detailModalId(): string
    {
        return 'needs-attention-'.$this->getId();
    }

    /**
     * The list behind one row: the same query as its count, so they agree.
     *
     * @return array{title: string, description: string, columns: list<string>, rows: list<array{cells: list<string>, url: ?string}>, total: int, more_url: string, more_label: string}|null
     */
    private function detailFor(?string $key): ?array
    {
        try {
            return match ($key) {
                'dead_stock' => $this->deadStockDetail(),
                'duplicates' => $this->duplicatesDetail(),
                'low_stock' => $this->lowStockDetail(),
                'zero_stock_sales' => $this->zeroStockDetail(),
                'trending_down' => $this->trendingDownDetail(),
                'channel_review' => $this->showsDetail($this->channelReviewQuery(), 'Shows that need a channel confirmed', 'This month’s shows imported under more than one channel.', ShowResource::getUrl('channel-review'), 'Open Channel Review'),
                'financials_revised' => $this->showsDetail($this->financialsRevisedQuery(), 'Financials changed after lock', 'Whatnot revised these shows after they were locked in. Check them before payroll.', ShowResource::getUrl('index', ['tableFilters[financials_revised_after_lock][value]' => '1']), 'Open in Shows'),
                'failed_jobs' => $this->failedJobsDetail(),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** "Not a duplicate": remember the pair so it is not raised again. */
    public function dismissDuplicate(string $key): void
    {
        abort_unless(static::canView(), 403);
        app(\App\Services\DuplicateShowFinder::class)->dismiss($key);
        \Filament\Notifications\Notification::make()->title('Marked as two separate shows')->success()->send();
    }

    private function duplicatesDetail(): array
    {
        $pairs = app(\App\Services\DuplicateShowFinder::class)->pairs();
        $label = fn (Show $s) => trim((string) $s->title).' · '.$this->money((float) $s->gross_revenue).($s->whatnot_show_id ? '' : ' · no Whatnot ID');

        return [
            'title' => 'Possible duplicate shows',
            'description' => 'Same channel, same date, nearly the same title — usually one stream imported twice, which doubles it in every report. Open the extra one and cancel it, or mark the pair as two separate shows.',
            'columns' => ['Date · channel', 'Show', 'Possible duplicate', 'Match', ''],
            'rows' => collect($pairs)->take(self::DETAIL_LIMIT)->map(fn (array $p) => [
                'cells' => [
                    $p['a']->show_date?->format('M j, Y').' · '.($p['a']->channel?->name ?? 'No channel'),
                    $label($p['a']), $label($p['b']), $p['similarity'].'%',
                ],
                'url' => null,
                'links' => [1 => ShowResource::getUrl('view', ['record' => $p['a']]), 2 => ShowResource::getUrl('view', ['record' => $p['b']])],
                'action' => ['label' => 'Not a duplicate', 'method' => 'dismissDuplicate', 'arg' => $p['key']],
            ])->all(),
            'total' => count($pairs),
            'more_url' => ShowResource::getUrl('index'),
            'more_label' => 'Open Shows',
        ];
    }

    private function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }

    private function productUrl(int $id): string
    {
        return InventoryItemResource::getUrl('view', ['record' => $id]);
    }

    private function scopedStock($query)
    {
        return $query->when(\App\Support\ChannelContext::isScoped(), fn ($q) => $q
            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock.inventory_location_id')
            ->where('inventory_locations.whatnot_channel_id', \App\Support\ChannelContext::currentId()));
    }

    private function scopedOrders($query)
    {
        return $query->when(\App\Support\ChannelContext::isScoped(), fn ($q) => $q
            ->join('inventory_locations', 'inventory_locations.id', '=', 'whatnot_show_orders.inventory_location_id')
            ->where('inventory_locations.whatnot_channel_id', \App\Support\ChannelContext::currentId()));
    }

    /** Adds on_hand and last_sold_at to a products query. */
    private function withStockFigures($query)
    {
        return $query->select('products.id', 'products.name', 'products.sku', 'products.average_cost', 'products.reorder_level')
            ->selectSub($this->scopedStock(DB::table('inventory_stock')->selectRaw('COALESCE(SUM(inventory_stock.quantity), 0)')
                ->whereColumn('inventory_stock.inventory_item_id', 'products.id')), 'on_hand')
            ->selectSub($this->scopedOrders(DB::table('whatnot_show_orders')->selectRaw('MAX(whatnot_show_orders.show_date)')
                ->whereColumn('whatnot_show_orders.inventory_item_id', 'products.id')), 'last_sold_at');
    }

    private function lastSold($value): string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y') : 'Never sold';
    }

    private function deadStockDetail(): array
    {
        $rows = $this->withStockFigures($this->deadStockQuery())->get()
            ->map(fn ($p) => $p->setAttribute('tied_up', (float) $p->on_hand * (float) $p->average_cost))
            ->sortByDesc('tied_up');

        return [
            'title' => 'Dead stock',
            'description' => 'In stock with no sale in '.ProductInsights::DEAD_DAYS.' days, most money tied up first. '.$this->money($rows->sum('tied_up')).' in total.',
            'columns' => ['Product', 'On hand', 'Tied up', 'Last sold'],
            'rows' => $rows->take(self::DETAIL_LIMIT)->map(fn ($p) => ['cells' => [
                $p->name.($p->sku ? " · {$p->sku}" : ''), number_format((float) $p->on_hand), $this->money($p->tied_up), $this->lastSold($p->last_sold_at),
            ], 'url' => $this->productUrl($p->id)])->values()->all(),
            'total' => $rows->count(),
            'more_url' => ProductInsights::getUrl(),
            'more_label' => 'Open Product Insights',
        ];
    }

    private function lowStockDetail(): array
    {
        $rows = $this->withStockFigures($this->lowStockQuery())->get()->sortBy(fn ($p) => (float) $p->on_hand - (float) $p->reorder_level);

        return [
            'title' => 'At or below reorder level',
            'description' => 'Furthest below their reorder level first.',
            'columns' => ['Product', 'On hand', 'Reorder at', 'Last sold'],
            'rows' => $rows->take(self::DETAIL_LIMIT)->map(fn ($p) => ['cells' => [
                $p->name.($p->sku ? " · {$p->sku}" : ''), number_format((float) $p->on_hand), number_format((float) $p->reorder_level), $this->lastSold($p->last_sold_at),
            ], 'url' => $this->productUrl($p->id)])->values()->all(),
            'total' => $rows->count(),
            'more_url' => InventoryItemResource::getUrl(),
            'more_label' => 'Open Inventory',
        ];
    }

    private function zeroStockDetail(): array
    {
        $rows = $this->withStockFigures($this->zeroStockMappedSalesQuery())->get()->sortByDesc('last_sold_at');

        return [
            'title' => 'Sold from a product with no stock',
            'description' => 'Recent Whatnot sales were mapped to these products, but none are on hand and none were restocked lately. The sale was probably mapped to the wrong product.',
            'columns' => ['Product', 'On hand', 'Last sold'],
            'rows' => $rows->take(self::DETAIL_LIMIT)->map(fn ($p) => ['cells' => [
                $p->name.($p->sku ? " · {$p->sku}" : ''), number_format((float) $p->on_hand), $this->lastSold($p->last_sold_at),
            ], 'url' => $this->productUrl($p->id)])->values()->all(),
            'total' => $rows->count(),
            'more_url' => InventoryItemResource::getUrl(),
            'more_label' => 'Open Inventory',
        ];
    }

    private function trendingDownDetail(): array
    {
        $rows = $this->trendingDownStreamers()->sortBy(fn (array $r) => $r['trend']['change_pct']);

        return [
            'title' => 'Streamers trending down',
            'description' => 'Last week’s gross against the average of the three weeks before it (down 30% or more).',
            'columns' => ['Streamer', 'Last week', '3-week average', 'Change'],
            'rows' => $rows->take(self::DETAIL_LIMIT)->map(fn (array $r) => ['cells' => [
                $r['streamer']->name, $this->money($r['trend']['last_week']), $this->money($r['trend']['baseline']), number_format($r['trend']['change_pct'], 0).'%',
            ], 'url' => StreamerResource::getUrl('view', ['record' => $r['streamer']])])->values()->all(),
            'total' => $rows->count(),
            'more_url' => StreamerResource::getUrl(),
            'more_label' => 'Open Streamers',
        ];
    }

    private function showsDetail($query, string $title, string $description, string $moreUrl, string $moreLabel): array
    {
        $total = (clone $query)->count();
        $shows = $query->with('channel:id,name')->orderByDesc('show_date')->limit(self::DETAIL_LIMIT)->get();

        return [
            'title' => $title,
            'description' => $description,
            'columns' => ['Date', 'Show', 'Channel'],
            'rows' => $shows->map(fn (Show $show) => ['cells' => [
                $show->show_date?->format('M j, Y') ?? '—', (string) $show->title, $show->channel?->name ?? 'No channel',
            ], 'url' => ShowResource::getUrl('view', ['record' => $show])])->all(),
            'total' => $total,
            'more_url' => $moreUrl,
            'more_label' => $moreLabel,
        ];
    }

    private function failedJobsDetail(): array
    {
        $jobs = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(self::DETAIL_LIMIT)->get(['payload', 'exception', 'failed_at']);

        return [
            'title' => 'Failed background jobs',
            'description' => 'Newest first. The error is the first line of what the job threw.',
            'columns' => ['Failed', 'Job', 'Error'],
            'rows' => $jobs->map(fn ($job) => ['cells' => [
                \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans(),
                class_basename((string) (json_decode((string) $job->payload, true)['displayName'] ?? 'Job')),
                \Illuminate\Support\Str::limit(strtok((string) $job->exception, "\n") ?: '', 140),
            ], 'url' => null])->all(),
            'total' => (int) DB::table('failed_jobs')->count(),
            'more_url' => SystemHealth::getUrl(),
            'more_label' => 'Open System Health',
        ];
    }

    private function channelReviewQuery()
    {
        return Show::where('channel_attribution_suspect', true)->where('is_operational', true)->whereDate('show_date', '>=', now()->startOfMonth())->inChannelContext();
    }

    private function financialsRevisedQuery()
    {
        return Show::where('financials_revised_after_lock', true)->where('is_operational', true)->whereDate('show_date', '>=', now()->startOfMonth())->inChannelContext();
    }

    /** Active products holding stock that hasn't sold within the dead-stock window. */
    private function deadStockQuery()
    {
        $cutoff = now()->subDays(ProductInsights::DEAD_DAYS)->toDateString();

        return InventoryItem::query()
            ->where('is_active', true)
            ->whereExists(fn ($query) => $this->scopedStock($query->selectRaw('1')->from('inventory_stock')
                ->whereColumn('inventory_stock.inventory_item_id', 'products.id'))
                ->groupBy('inventory_stock.inventory_item_id')->havingRaw('SUM(inventory_stock.quantity) > 0'))
            ->whereNotExists(fn ($query) => $this->scopedOrders($query->selectRaw('1')->from('whatnot_show_orders')
                ->whereColumn('whatnot_show_orders.inventory_item_id', 'products.id')
                ->where('whatnot_show_orders.show_date', '>=', $cutoff)));
    }

    private function deadStockCount(): int
    {
        try {
            return $this->deadStockQuery()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function zeroStockMappedSalesQuery()
    {
        $cutoff        = now()->subDays(60)->toDateString();
        $restockCutoff = now()->subDays(14);

        return InventoryItem::query()
            ->whereExists(fn ($query) => $this->scopedOrders($query->selectRaw('1')->from('whatnot_show_orders')
                ->whereColumn('whatnot_show_orders.inventory_item_id', 'products.id')
                ->where('whatnot_show_orders.show_date', '>=', $cutoff)))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('inventory_stock')
                    ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                    ->groupBy('inventory_stock.inventory_item_id')
                    ->havingRaw('SUM(inventory_stock.quantity) > 0');
            })
            ->whereNotExists(function ($query) use ($restockCutoff) {
                $query->selectRaw('1')
                    ->from('inventory_movements')
                    ->whereColumn('inventory_movements.inventory_item_id', 'products.id')
                    ->whereIn('inventory_movements.movement_type', ['opening', 'return'])
                    ->where('inventory_movements.created_at', '>=', $restockCutoff);
            });
    }

    private function zeroStockMappedSalesCount(): int
    {
        try {
            return $this->zeroStockMappedSalesQuery()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return \Illuminate\Support\Collection<int, array{streamer: Streamer, trend: array}> */
    private function trendingDownStreamers(): \Illuminate\Support\Collection
    {
        return Streamer::where('status', 'active')
            ->inChannelContext()
            ->get()
            ->filter(fn (Streamer $s) => $s->isPerformanceTrendingDown())
            ->map(fn (Streamer $s) => ['streamer' => $s, 'trend' => $s->performanceTrend()])
            ->values();
    }

    private function trendingDownStreamerCount(): int
    {
        try {
            return $this->trendingDownStreamers()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function lowStockQuery()
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->whereExists(fn ($query) => $this->scopedStock($query->selectRaw('1')->from('inventory_stock')
                ->whereColumn('inventory_stock.inventory_item_id', 'products.id'))
                ->groupBy('inventory_stock.inventory_item_id')
                ->havingRaw('SUM(inventory_stock.quantity) <= products.reorder_level'));
    }

    private function lowStockCount(): int
    {
        try {
            return $this->lowStockQuery()->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
