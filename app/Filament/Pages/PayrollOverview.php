<?php

namespace App\Filament\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayRunReadinessService;
use App\Services\ShowWorkflowService;
use App\Support\ProfitShareFormula;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class PayrollOverview extends Page
{
    protected static ?string $title = 'Payroll Dashboard';
    protected static ?string $navigationLabel = 'Payroll';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|UnitEnum|null $navigationGroup = 'Payouts';
    protected static ?int $navigationSort = 1;

    public array $mockProducts = [];
    public float $mockGrossRevenue = 5000.00;
    public float $mockHours = 4.00;
    public int $mockShipments = 60;
    public float $mockPercentage = 8.00;
    public float $mockTips = 0.00;

    public function mount(): void
    {
        if ($this->mockProducts === []) {
            $this->mockProducts = $this->catalogProductsForSimulation(2);
        }
    }

    public function addMockProduct(): void
    {
        $usedIds = collect($this->mockProducts)->pluck('product_id')->filter()->all();
        $product = Product::query()
            ->where('is_active', true)
            ->whereNotIn('id', $usedIds)
            ->orderByRaw('CASE WHEN average_cost > 0 THEN 0 WHEN unit_cost > 0 THEN 1 ELSE 2 END')
            ->orderBy('name')
            ->first();

        if (! $product) {
            $this->dispatch('notify', message: 'No additional active catalog products are available.');
            return;
        }

        $this->mockProducts[] = $this->simulationProductRow($product);
    }

    public function removeMockProduct(int $index): void
    {
        unset($this->mockProducts[$index]);
        $this->mockProducts = array_values($this->mockProducts);
    }

    public function getView(): string
    {
        return 'filament.pages.payroll-overview';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Current weekly payroll, show-by-show calculations, blockers and resolution actions in one place.';
    }

    public function currentPayRun(): ?WeeklyPayoutBatch
    {
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        return WeeklyPayoutBatch::query()
            ->withCount('payouts')
            ->whereDate('week_start', '<=', $weekEnd)
            ->whereDate('week_end', '>=', $weekStart)
            ->latest('week_start')
            ->first();
    }

    /**
     * Read-only payroll preview using current calculated payout records.
     * This never creates, updates, batches, finalizes, exports or marks paid.
     */
    public function mockPayRun(): array
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        $payouts = Payout::query()
            ->whereHas('show', fn ($q) => $q
                ->inChannelContext()
                ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
                ->whereNotIn('status', ['cancelled']))
            ->with(['show:id,title,show_date', 'streamer:id,name,member_type,payout_type'])
            ->get();

        $rows = $payouts
            ->groupBy('streamer_id')
            ->map(function (Collection $group) {
                $streamer = $group->first()?->streamer;
                return [
                    'name' => $streamer?->name ?: 'Unknown',
                    'member_type' => $streamer?->member_type ?: 'streamer',
                    'payout_type' => $streamer?->payout_type ?: '—',
                    'shows' => $group->pluck('show_id')->filter()->unique()->count(),
                    'amount' => round((float) $group->sum('calculated_payout'), 2),
                    'entries' => $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        $streamerTotal = (float) $payouts
            ->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())
            ->sum('calculated_payout');
        $fulfillmentTotal = (float) $payouts
            ->filter(fn (Payout $p) => $p->streamer?->isFulfillment())
            ->sum('calculated_payout');

        return [
            'week_start' => $start,
            'week_end' => $end,
            'rows' => $rows,
            'people' => $rows->count(),
            'entries' => $payouts->count(),
            'streamer_total' => round($streamerTotal, 2),
            'fulfillment_total' => round($fulfillmentTotal, 2),
            'total' => round($streamerTotal + $fulfillmentTotal, 2),
            'real_run_exists' => $this->currentPayRun() !== null,
        ];
    }

    /**
     * In-memory show calculator backed by real products from the catalog. The
     * quantities and show inputs are simulated, but product identity and cost
     * basis are always re-read from the database so this mirrors production
     * costing instead of relying on invented products or editable fake costs.
     */
    public function mockShowCalculation(): array
    {
        $ids = collect($this->mockProducts)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $catalog = Product::query()->whereIn('id', $ids)->get()->keyBy('id');

        $products = collect($this->mockProducts)
            ->map(function (array $row) use ($catalog): ?array {
                $product = $catalog->get((int) ($row['product_id'] ?? 0));
                if (! $product) return null;

                $quantity = max(0, (float) ($row['quantity'] ?? 0));
                $unitCost = max(0, (float) ($product->costBasis() ?? 0));

                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => round($quantity * $unitCost, 2),
                ];
            })
            ->filter()
            ->values();

        $productCost = round((float) $products->sum('line_total'), 2);
        $grossRevenue = max(0, (float) $this->mockGrossRevenue);
        $hours = max(0, (float) $this->mockHours);
        $shipments = max(0, (float) $this->mockShipments);
        $percentage = max(0, (float) $this->mockPercentage);
        $tips = max(0, (float) $this->mockTips);

        $working = ProfitShareFormula::forShow(
            $grossRevenue,
            $productCost,
            $hours,
            $shipments,
            $percentage,
        );

        $projectedPayout = round($working['earnings'] + $tips, 2);
        $businessAfterPayroll = round($grossRevenue - $productCost - $working['burden'] - $projectedPayout, 2);

        return [
            'products' => $products,
            'product_cost' => $productCost,
            'gross_revenue' => $grossRevenue,
            'hours' => $hours,
            'shipments' => $shipments,
            'percentage' => $percentage,
            'tips' => $tips,
            'burden' => $working['burden'],
            'rate_per_shipment' => $working['rate_per_shipment'],
            'rate_per_hour' => $working['rate_per_hour'],
            'net_revenue' => $working['net_revenue'],
            'base_share' => $working['earnings'],
            'projected_payout' => $projectedPayout,
            'business_after_payroll' => $businessAfterPayroll,
            'explanation' => ProfitShareFormula::explain($working),
        ];
    }

    private function catalogProductsForSimulation(int $limit): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN average_cost > 0 THEN 0 WHEN unit_cost > 0 THEN 1 ELSE 2 END')
            ->orderByDesc('total_units_received')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => $this->simulationProductRow($product))
            ->values()
            ->all();
    }

    private function simulationProductRow(Product $product): array
    {
        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_cost' => (float) ($product->costBasis() ?? 0),
        ];
    }

    public function needsAttention(): array
    {
        $warnings = [];
        $run = $this->currentPayRun();

        $membersMissingStructure = Streamer::query()
            ->where('status', 'active')
            ->get()
            ->filter(function (Streamer $member): bool {
                try {
                    $comp = $member->effectiveCompensation();
                    return blank($comp['structure'] ?? null);
                } catch (\Throwable) {
                    return true;
                }
            })
            ->count();

        if ($membersMissingStructure > 0) {
            $warnings[] = $membersMissingStructure . ' active team member(s) need a payment structure reviewed.';
        }

        foreach ($this->allCurrentWeekShows() as $show) {
            foreach ($show->getAttribute('workflow_state')['blockers'] ?? [] as $blocker) {
                $warnings[] = $show->title . ': ' . $blocker;
            }
            foreach ($show->getAttribute('payrun_problems') ?? [] as $problem) {
                $warnings[] = $problem;
            }
        }

        if (! $run) {
            $warnings[] = 'No pay run exists for the current week. Create it after the shows you intend to pay are payroll-ready.';
            return array_values(array_unique($warnings));
        }

        if ($run->status === 'draft') {
            foreach (app(PayRunReadinessService::class)->problems($run) as $problem) {
                $warnings[] = $problem;
            }
        }

        return array_values(array_unique($warnings));
    }

    public function currentBreakdown(): array
    {
        $run = $this->currentPayRun();
        if (! $run) {
            return ['people' => 0, 'streamers' => 0, 'fulfillment' => 0, 'streamer_total' => 0.0, 'fulfillment_total' => 0.0];
        }

        $payouts = Payout::query()
            ->where('weekly_payout_batch_id', $run->id)
            ->with('streamer:id,member_type')
            ->get();

        $people = $payouts->pluck('streamer_id')->filter()->unique();
        $streamerIds = $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->pluck('streamer_id')->filter()->unique();
        $fulfillmentIds = $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->pluck('streamer_id')->filter()->unique();

        return [
            'people' => $people->count(),
            'streamers' => $streamerIds->count(),
            'fulfillment' => $fulfillmentIds->count(),
            'streamer_total' => (float) $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->sum('calculated_payout'),
            'fulfillment_total' => (float) $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->sum('calculated_payout'),
        ];
    }

    public function currentWeekShows(): Collection
    {
        $shows = $this->allCurrentWeekShows();
        $filter = request()->string('workflow')->toString();

        if ($filter === '' || $filter === 'all') {
            return $shows;
        }

        return $shows->filter(function (Show $show) use ($filter): bool {
            $key = $show->getAttribute('workflow_state')['key'] ?? '';
            $hasPayRunProblems = ($show->getAttribute('payrun_problems') ?? []) !== [];

            return match ($filter) {
                'blocked' => $hasPayRunProblems || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true),
                'ready' => ! $hasPayRunProblems && $key === 'payroll_ready',
                'in_run' => ! $hasPayRunProblems && $key === 'payroll',
                'paid' => $key === 'paid',
                default => $key === $filter,
            };
        })->values();
    }

    public function workflowBreakdown(): array
    {
        $shows = $this->allCurrentWeekShows();

        return [
            'all' => $shows->count(),
            'blocked' => $shows->filter(function (Show $show): bool {
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) !== []
                    || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'ready' => $shows->filter(fn (Show $show) => ($show->getAttribute('payrun_problems') ?? []) === []
                && ($show->getAttribute('workflow_state')['key'] ?? '') === 'payroll_ready')->count(),
            'in_run' => $shows->filter(fn (Show $show) => ($show->getAttribute('payrun_problems') ?? []) === []
                && ($show->getAttribute('workflow_state')['key'] ?? '') === 'payroll')->count(),
            'paid' => $shows->filter(fn (Show $show) => ($show->getAttribute('workflow_state')['key'] ?? '') === 'paid')->count(),
        ];
    }

    /** @return array{label:string,url:string,tone:string} */
    public function showResolution(Show $show): array
    {
        $state = $show->getAttribute('workflow_state');
        $key = $state['key'] ?? '';
        $log = $show->streamerLogEntry;
        $payRunProblems = $show->getAttribute('payrun_problems') ?? [];

        if ($payRunProblems !== [] && $this->currentPayRun()) {
            return ['label' => 'Recalculate Run', 'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->currentPayRun()]), 'tone' => 'warning'];
        }

        if (in_array($key, ['streamer_log', 'admin_review'], true) && $log) {
            return ['label' => $key === 'admin_review' ? 'Review Log' : 'Open Log', 'url' => StreamerLogResource::getUrl('edit', ['record' => $log]), 'tone' => 'warning'];
        }

        if ($key === 'fulfillment') {
            return ['label' => 'Resolve Fulfillment', 'url' => FulfillmentResource::getUrl('view', ['record' => $show]), 'tone' => 'primary'];
        }

        if ($key === 'payroll' && $show->payouts->first(fn (Payout $p) => $p->batch)?->batch) {
            $batch = $show->payouts->first(fn (Payout $p) => $p->batch)?->batch;
            return ['label' => 'Open Pay Run', 'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $batch]), 'tone' => 'primary'];
        }

        if ($key === 'payroll_ready' && $this->currentPayRun()) {
            return ['label' => 'Review Pay Run', 'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->currentPayRun()]), 'tone' => 'success'];
        }

        return ['label' => in_array($key, ['payroll_review'], true) ? 'Fix Show Inputs' : 'Open Show', 'url' => ShowResource::getUrl('view', ['record' => $show]), 'tone' => $key === 'payroll_review' ? 'warning' : 'gray'];
    }

    public function readinessSummary(): array
    {
        $shows = $this->allCurrentWeekShows();
        return [
            'shows' => $shows->count(),
            'ready' => $shows->filter(function (Show $show): bool {
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) === [] && in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'review' => $shows->filter(function (Show $show): bool {
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) !== [] || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'show_payroll' => (float) $shows->sum(fn (Show $show) => (float) ($show->getAttribute('pnl_summary')['payouts'] ?? 0)),
        ];
    }

    public function recentPayRuns(): Collection
    {
        return WeeklyPayoutBatch::query()->withCount('payouts')->latest('week_start')->limit(6)->get();
    }

    private function allCurrentWeekShows(): Collection
    {
        $run = $this->currentPayRun();
        $start = $run?->week_start ?? now()->startOfWeek();
        $end = $run?->week_end ?? now()->endOfWeek();
        $workflow = app(ShowWorkflowService::class);
        $payRunProblems = $run && $run->status === 'draft' ? app(PayRunReadinessService::class)->problems($run) : [];

        return Show::query()
            ->inChannelContext()
            ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->with(['streamers','streamerLogEntry.streamer','streamerLogEntry.items.inventoryItem','fulfillmentUsers','payouts.batch','latestDeductionRequest.lines.inventoryItem'])
            ->withSum('payouts', 'calculated_payout')
            ->orderByDesc('show_date')
            ->get()
            ->map(function (Show $show) use ($workflow, $payRunProblems) {
                $show->setAttribute('workflow_state', $workflow->stateFor($show));
                $show->setAttribute('pnl_summary', $show->profitAndLoss());
                $prefix = ($show->title ?: "Show #{$show->id}") . ' — ';
                $show->setAttribute('payrun_problems', array_values(array_filter($payRunProblems, fn (string $problem) => str_starts_with($problem, $prefix))));
                return $show;
            });
    }
}
