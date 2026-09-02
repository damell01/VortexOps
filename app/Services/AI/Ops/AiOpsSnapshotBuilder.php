<?php

namespace App\Services\AI\Ops;

use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds small, deterministic fact packets for background AI work.
 *
 * Important: this class never calls Ollama. SQL/PHP computes every number and
 * every hard business fact first. The model only receives a bounded packet to
 * summarize/explain later, which keeps the web request path AI-free and keeps
 * model context/RAM usage predictable on a small VPS.
 */
class AiOpsSnapshotBuilder
{
    /** @return array<string,mixed> */
    public function build(string $scope = 'operations', ?int $sourceId = null): array
    {
        $scope = strtolower(trim($scope));

        return match ($scope) {
            'show'      => $this->showSnapshot($sourceId),
            'inventory' => $this->inventorySnapshot(),
            'payroll'   => $this->payrollSnapshot(),
            'streamers' => $this->streamerSnapshot(),
            'exceptions'=> $this->exceptionSnapshot(),
            'cleanup'   => $this->cleanupSnapshot(),
            'weekly'    => $this->weeklySnapshot(),
            default     => $this->operationsSnapshot(),
        };
    }

    /** @return array<string,mixed> */
    private function operationsSnapshot(): array
    {
        return [
            'scope'       => 'operations',
            'generated_at'=> now()->toIso8601String(),
            'inventory'   => $this->inventorySnapshot(false),
            'shows'       => $this->showSnapshot(null, false),
            'payroll'     => $this->payrollSnapshot(false),
            'streamers'   => $this->streamerSnapshot(false),
            'exceptions'  => $this->exceptionSnapshot(false),
        ];
    }

    /** @return array<string,mixed> */
    private function weeklySnapshot(): array
    {
        $from = now()->subDays(7)->startOfDay();
        $shows = Show::query()
            ->where('show_date', '>=', $from->toDateString())
            ->with(['streamers:id,name'])
            ->orderByDesc('show_date')
            ->limit(40)
            ->get();

        return [
            'scope'        => 'weekly',
            'period_start' => $from->toDateString(),
            'period_end'   => now()->toDateString(),
            'show_count'   => $shows->count(),
            'gross_revenue'=> round((float) $shows->sum('gross_revenue'), 2),
            'whatnot_net'  => round((float) $shows->sum('whatnot_net'), 2),
            'tips'         => round((float) $shows->sum('tips'), 2),
            'units_sold'   => (int) $shows->sum('units_sold'),
            'buyers'       => (int) $shows->sum('buyers_count'),
            'recent_shows' => $shows->take(12)->map(fn (Show $show) => [
                'id'        => $show->id,
                'title'     => $show->title,
                'date'      => optional($show->show_date)->toDateString(),
                'status'    => $show->status,
                'gross'     => (float) $show->gross_revenue,
                'net'       => (float) $show->whatnot_net,
                'units'     => (int) $show->units_sold,
                'streamers' => $show->streamers->pluck('name')->values()->all(),
            ])->values()->all(),
            'payroll'      => $this->payrollSnapshot(false),
            'inventory'    => $this->inventorySnapshot(false),
            'exceptions'   => $this->exceptionSnapshot(false),
        ];
    }

    /** @return array<string,mixed> */
    private function showSnapshot(?int $showId = null, bool $includeScope = true): array
    {
        if ($showId) {
            $show = Show::query()
                ->with(['streamers:id,name', 'streamerLogEntry', 'payouts'])
                ->find($showId);

            if (! $show) {
                return ['scope' => 'show', 'show_id' => $showId, 'missing' => true];
            }

            $pnl = $show->profitAndLoss();
            $engagement = $show->engagement();

            return [
                'scope'       => 'show',
                'show'        => [
                    'id'                 => $show->id,
                    'title'              => $show->title,
                    'date'               => optional($show->show_date)->toDateString(),
                    'status'             => $show->status,
                    'gross_revenue'      => (float) $show->gross_revenue,
                    'whatnot_net'        => (float) $show->whatnot_net,
                    'tips'               => (float) $show->tips,
                    'units_sold'         => (int) $show->units_sold,
                    'show_duration_min'  => (int) $show->show_duration,
                    'streamers'          => $show->streamers->pluck('name')->values()->all(),
                    'sales_reconciled'   => (bool) $show->sales_reconciled,
                    'channel_suspect'    => (bool) $show->channel_attribution_suspect,
                    'pnl'                => $pnl,
                    'engagement'         => $engagement,
                    'payout_total'       => round((float) $show->payouts->sum('calculated_payout'), 2),
                    'report_status'      => $show->streamerLogEntry?->status,
                    'fulfillment_reviewed'=> $show->streamerLogEntry?->fulfillment_reviewed_at !== null,
                ],
            ];
        }

        $from = now()->subDays(7)->startOfDay();
        $shows = Show::query()
            ->where('show_date', '>=', $from->toDateString())
            ->orderByDesc('show_date')
            ->limit(50)
            ->get();

        $stuck = Show::query()
            ->whereNotIn('status', ['reconciled', 'paid'])
            ->whereNotNull('status_changed_at')
            ->where('status_changed_at', '<', now()->subDays(3))
            ->orderBy('status_changed_at')
            ->limit(15)
            ->get(['id', 'title', 'status', 'status_changed_at']);

        $data = [
            'period_days'       => 7,
            'show_count'        => $shows->count(),
            'gross_revenue'     => round((float) $shows->sum('gross_revenue'), 2),
            'whatnot_net'       => round((float) $shows->sum('whatnot_net'), 2),
            'tips'              => round((float) $shows->sum('tips'), 2),
            'units_sold'        => (int) $shows->sum('units_sold'),
            'channel_suspect'   => Show::where('channel_attribution_suspect', true)->count(),
            'stuck_show_count'  => $stuck->count(),
            'stuck_shows'       => $stuck->map(fn (Show $show) => [
                'id' => $show->id,
                'title' => $show->title,
                'status' => $show->status,
                'days_in_status' => $show->status_changed_at?->diffInDays(now()),
            ])->values()->all(),
        ];

        return $includeScope ? ['scope' => 'shows'] + $data : $data;
    }

    /** @return array<string,mixed> */
    private function inventorySnapshot(bool $includeScope = true): array
    {
        $stockSub = InventoryStock::query()
            ->selectRaw('inventory_item_id, SUM(quantity) as qty')
            ->groupBy('inventory_item_id');

        $lowStock = Product::query()
            ->leftJoinSub($stockSub, 'stock_totals', 'stock_totals.inventory_item_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->whereNotNull('products.reorder_level')
            ->where('products.reorder_level', '>', 0)
            ->whereRaw('COALESCE(stock_totals.qty, 0) <= products.reorder_level')
            ->orderByRaw('COALESCE(stock_totals.qty, 0) ASC')
            ->limit(15)
            ->get([
                'products.id', 'products.name', 'products.sku', 'products.reorder_level',
                \DB::raw('COALESCE(stock_totals.qty, 0) as on_hand'),
            ]);

        $negativeStock = InventoryStock::query()
            ->with(['item:id,name,sku', 'location:id,name'])
            ->where('quantity', '<', 0)
            ->limit(15)
            ->get();

        $products = Product::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'sku', 'upc', 'barcode', 'category', 'unit_cost', 'average_cost'])
            ->get();

        $data = [
            'product_count'       => $products->count(),
            'missing_category'    => $products->whereNull('category')->count() + $products->where('category', '')->count(),
            'missing_upc_barcode' => $products->filter(fn (Product $p) => blank($p->upc) && blank($p->barcode))->count(),
            'missing_cost'        => $products->filter(fn (Product $p) => (float) ($p->unit_cost ?? 0) <= 0 && (float) ($p->average_cost ?? 0) <= 0)->count(),
            'low_stock_count'     => $lowStock->count(),
            'low_stock'           => $lowStock->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'on_hand' => (float) $row->on_hand,
                'reorder_level' => (float) $row->reorder_level,
            ])->values()->all(),
            'negative_stock_count'=> $negativeStock->count(),
            'negative_stock'      => $negativeStock->map(fn (InventoryStock $stock) => [
                'product_id' => $stock->inventory_item_id,
                'product' => $stock->item?->name,
                'location' => $stock->location?->name,
                'quantity' => (float) $stock->quantity,
            ])->values()->all(),
        ];

        return $includeScope ? ['scope' => 'inventory'] + $data : $data;
    }

    /** @return array<string,mixed> */
    private function payrollSnapshot(bool $includeScope = true): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $drafts = Payout::query()->where('status', 'draft');
        $unbatched = (clone $drafts)->whereNull('weekly_payout_batch_id');

        $batches = WeeklyPayoutBatch::query()
            ->where('week_end', '>=', now()->subWeeks(4)->toDateString())
            ->orderByDesc('week_end')
            ->limit(8)
            ->get();

        $data = [
            'current_week_start' => $weekStart->toDateString(),
            'current_week_end'   => $weekEnd->toDateString(),
            'draft_payouts'      => (clone $drafts)->count(),
            'unbatched_drafts'   => (clone $unbatched)->count(),
            'unbatched_amount'   => round((float) (clone $unbatched)->sum('calculated_payout'), 2),
            'missing_calculation'=> Payout::query()->where('status', 'draft')->whereNull('calculated_payout')->count(),
            'recent_pay_runs'    => $batches->map(fn (WeeklyPayoutBatch $batch) => [
                'id' => $batch->id,
                'week_start' => optional($batch->week_start)->toDateString(),
                'week_end' => optional($batch->week_end)->toDateString(),
                'status' => $batch->status,
            ])->values()->all(),
        ];

        return $includeScope ? ['scope' => 'payroll'] + $data : $data;
    }

    /** @return array<string,mixed> */
    private function streamerSnapshot(bool $includeScope = true): array
    {
        $from = now()->subDays(30)->startOfDay();

        $rows = Streamer::query()
            ->withSum(['payouts as payout_30d' => fn ($q) => $q->where('created_at', '>=', $from)], 'calculated_payout')
            ->withCount(['payouts as payout_count_30d' => fn ($q) => $q->where('created_at', '>=', $from)])
            ->orderByDesc('payout_30d')
            ->limit(25)
            ->get();

        $data = [
            'period_days' => 30,
            'streamer_count' => Streamer::count(),
            'performance' => $rows->map(fn (Streamer $streamer) => [
                'id' => $streamer->id,
                'name' => $streamer->name,
                'member_type' => $streamer->member_type ?? 'streamer',
                'payout_type' => $streamer->payout_type,
                'payout_count' => (int) ($streamer->payout_count_30d ?? 0),
                'payout_total' => round((float) ($streamer->payout_30d ?? 0), 2),
            ])->values()->all(),
        ];

        return $includeScope ? ['scope' => 'streamers'] + $data : $data;
    }

    /** @return array<string,mixed> */
    private function exceptionSnapshot(bool $includeScope = true): array
    {
        $failedImports = ShowIngestionLog::query()
            ->with('channel:id,name')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->latest()
            ->limit(12)
            ->get();

        $data = [
            'failed_imports_7d' => $failedImports->count(),
            'failed_imports' => $failedImports->map(fn (ShowIngestionLog $log) => [
                'id' => $log->id,
                'channel' => $log->channel?->name,
                'source' => $log->sourceLabel(),
                'failure_type' => $log->failureTypeLabel(),
                'summary' => Str::limit((string) $log->error_message, 240),
                'created_at' => optional($log->created_at)->toIso8601String(),
            ])->values()->all(),
            'negative_stock' => InventoryStock::where('quantity', '<', 0)->count(),
            'channel_attribution_suspect' => Show::where('channel_attribution_suspect', true)->count(),
            'stale_pipeline_shows' => Show::query()
                ->whereNotIn('status', ['reconciled', 'paid'])
                ->whereNotNull('status_changed_at')
                ->where('status_changed_at', '<', now()->subDays(7))
                ->count(),
            'unbatched_draft_payouts' => Payout::query()->where('status', 'draft')->whereNull('weekly_payout_batch_id')->count(),
        ];

        return $includeScope ? ['scope' => 'exceptions'] + $data : $data;
    }

    /** @return array<string,mixed> */
    private function cleanupSnapshot(): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'sku', 'upc', 'barcode', 'category', 'brand', 'sport', 'year', 'set_name', 'product_type'])
            ->limit(2500)
            ->get();

        $duplicateGroups = $products
            ->groupBy(fn (Product $p) => $this->normalizeName($p->name))
            ->filter(fn (Collection $group, string $key) => $key !== '' && $group->count() > 1)
            ->take(20)
            ->map(fn (Collection $group) => $group->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'upc' => $p->upc ?: $p->barcode,
            ])->values()->all())
            ->values()
            ->all();

        $normalizationCandidates = $products
            ->filter(fn (Product $p) => $p->name !== trim(preg_replace('/\s+/', ' ', $p->name)))
            ->take(30)
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'normalized' => trim(preg_replace('/\s+/', ' ', $p->name)),
            ])->values()->all();

        return [
            'scope' => 'cleanup',
            'duplicate_group_count' => count($duplicateGroups),
            'duplicate_groups' => $duplicateGroups,
            'normalization_candidate_count' => count($normalizationCandidates),
            'normalization_candidates' => $normalizationCandidates,
            'missing_category_count' => $products->filter(fn (Product $p) => blank($p->category))->count(),
            'category_sample' => $products->filter(fn (Product $p) => blank($p->category))->take(40)->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'brand' => $p->brand,
                'sport' => $p->sport,
                'year' => $p->year,
                'set' => $p->set_name,
                'type' => $p->product_type,
            ])->values()->all(),
        ];
    }

    private function normalizeName(?string $name): string
    {
        $name = Str::lower(trim((string) $name));
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?: '';
        return trim(preg_replace('/\s+/', ' ', $name) ?: '');
    }
}
