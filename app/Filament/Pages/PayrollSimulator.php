<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Streamer;
use App\Support\ProfitShareFormula;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use UnitEnum;

class PayrollSimulator extends Page
{
    protected static ?string $title = 'Payroll Simulator';
    protected static ?string $navigationLabel = 'Payroll Simulator';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static string|UnitEnum|null $navigationGroup = 'Payouts';
    protected static ?int $navigationSort = 3;

    public string $mode = 'month';
    public array $shows = [];

    public function getView(): string
    {
        return 'filament.pages.payroll-simulator';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getSubheading(): ?string
    {
        return 'A read-only sandbox that uses the same weekly streamer-pay formula and individual adjustments as payroll.';
    }

    public function mount(): void
    {
        $this->loadPreset('month');
    }

    public function loadPreset(string $mode): void
    {
        $this->mode = in_array($mode, ['single', 'week', 'month'], true) ? $mode : 'single';
        $count = match ($this->mode) {
            'single' => 1,
            'week' => 4,
            default => 12,
        };

        $streamerIds = array_keys($this->streamerOptions());
        $productIds = array_keys($this->productOptions());
        $start = now()->startOfWeek();

        $this->shows = [];
        for ($i = 0; $i < $count; $i++) {
            $weekOffset = $this->mode === 'month' ? intdiv($i, 3) : 0;
            $dayOffset = $this->mode === 'month' ? (($i % 3) * 2) : min($i * 2, 6);
            $streamerId = $streamerIds[$i % max(1, count($streamerIds))] ?? null;
            $productA = $productIds[($i * 2) % max(1, count($productIds))] ?? null;
            $productB = $productIds[(($i * 2) + 1) % max(1, count($productIds))] ?? null;

            $this->shows[] = [
                'name' => 'Mock Show ' . ($i + 1),
                'date' => $start->copy()->addWeeks($weekOffset)->addDays($dayOffset)->toDateString(),
                'streamer_id' => $streamerId,
                'gross' => 4200 + (($i % 4) * 850),
                'hours' => 4 + (($i % 3) * .5),
                'shipments' => 45 + (($i % 5) * 11),
                'tips' => 40 + (($i % 4) * 15),
                'products' => array_values(array_filter([
                    $productA ? ['product_id' => $productA, 'quantity' => 6 + ($i % 3)] : null,
                    $productB ? ['product_id' => $productB, 'quantity' => 3 + ($i % 2)] : null,
                ])),
            ];
        }
    }

    public function addShow(): void
    {
        $streamerId = array_key_first($this->streamerOptions());
        $productId = array_key_first($this->productOptions());
        $this->shows[] = [
            'name' => 'Mock Show ' . (count($this->shows) + 1),
            'date' => now()->toDateString(),
            'streamer_id' => $streamerId,
            'gross' => 5000,
            'hours' => 4,
            'shipments' => 60,
            'tips' => 50,
            'products' => $productId ? [['product_id' => $productId, 'quantity' => 5]] : [],
        ];
    }

    public function removeShow(int $index): void
    {
        unset($this->shows[$index]);
        $this->shows = array_values($this->shows);
    }

    public function addProduct(int $showIndex): void
    {
        if (! isset($this->shows[$showIndex])) return;
        $productId = array_key_first($this->productOptions());
        if (! $productId) return;
        $this->shows[$showIndex]['products'][] = ['product_id' => $productId, 'quantity' => 1];
    }

    public function removeProduct(int $showIndex, int $productIndex): void
    {
        if (! isset($this->shows[$showIndex]['products'][$productIndex])) return;
        unset($this->shows[$showIndex]['products'][$productIndex]);
        $this->shows[$showIndex]['products'] = array_values($this->shows[$showIndex]['products']);
    }

    public function productOptions(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN average_cost > 0 THEN 0 WHEN unit_cost > 0 THEN 1 ELSE 2 END')
            ->orderByDesc('total_units_received')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => $product->name . ($product->sku ? ' · ' . $product->sku : '')])
            ->all();
    }

    public function streamerOptions(): array
    {
        return Streamer::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->where('member_type', 'streamer')->orWhereNull('member_type'))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->mapWithKeys(function (Streamer $streamer): array {
                $pay = $streamer->effectiveCompensation();
                $pct = (float) ($pay['effective']['payout_percentage'] ?? 0);
                $custom = count($pay['overrides'] ?? []) > 0;
                return [$streamer->id => $streamer->name . ' · ' . number_format($pct, 2) . '% ' . ($custom ? 'custom' : 'team default')];
            })
            ->all();
    }

    public function simulation(): array
    {
        $productIds = collect($this->shows)->flatMap(fn ($show) => collect($show['products'] ?? [])->pluck('product_id'))->filter()->unique();
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
        $streamerIds = collect($this->shows)->pluck('streamer_id')->filter()->unique();
        $streamers = Streamer::query()->whereIn('id', $streamerIds)->get()->keyBy('id');

        $rows = collect($this->shows)->map(function (array $show, int $index) use ($products, $streamers) {
            $streamer = $streamers->get((int) ($show['streamer_id'] ?? 0));
            $productRows = collect($show['products'] ?? [])->map(function (array $row) use ($products) {
                $product = $products->get((int) ($row['product_id'] ?? 0));
                if (! $product) return null;
                $quantity = max(0, (float) ($row['quantity'] ?? 0));
                $unitCost = max(0, (float) ($product->costBasis() ?? 0));
                return [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => round($quantity * $unitCost, 2),
                ];
            })->filter()->values();

            $gross = max(0, (float) ($show['gross'] ?? 0));
            $hours = max(0, (float) ($show['hours'] ?? 0));
            $shipments = max(0, (float) ($show['shipments'] ?? 0));
            $tips = max(0, (float) ($show['tips'] ?? 0));
            $productCost = round((float) $productRows->sum('line_total'), 2);
            $pay = $this->simulateStreamerPay($streamer, $gross, $productCost, $hours, $shipments, $tips);
            $business = round($gross - $productCost - $pay['burden'] - $pay['streamer_pay'], 2);
            $date = Carbon::parse($show['date'] ?? now()->toDateString());

            return [
                'index' => $index,
                'name' => $show['name'] ?: 'Mock Show ' . ($index + 1),
                'date' => $date,
                'week_key' => $date->copy()->startOfWeek()->toDateString(),
                'week_label' => $date->copy()->startOfWeek()->format('M j') . ' – ' . $date->copy()->endOfWeek()->format('M j'),
                'streamer' => $streamer,
                'products' => $productRows,
                'product_cost' => $productCost,
                'gross' => $gross,
                'hours' => $hours,
                'shipments' => $shipments,
                'tips' => $tips,
                'burden' => $pay['burden'],
                'streamer_pay' => $pay['streamer_pay'],
                'pay_rate' => $pay['pay_rate'],
                'using_custom' => $pay['using_custom'],
                'note' => $pay['note'],
                'business_after_payroll' => $business,
            ];
        })->values();

        $weeks = $rows->groupBy('week_key')->map(function ($weekRows) {
            return [
                'label' => $weekRows->first()['week_label'],
                'shows' => $weekRows->count(),
                'gross' => round((float) $weekRows->sum('gross'), 2),
                'cogs' => round((float) $weekRows->sum('product_cost'), 2),
                'burden' => round((float) $weekRows->sum('burden'), 2),
                'streamer_pay' => round((float) $weekRows->sum('streamer_pay'), 2),
                'business' => round((float) $weekRows->sum('business_after_payroll'), 2),
            ];
        })->values();

        $people = $rows->groupBy(fn ($row) => $row['streamer']?->id ?: 0)->map(function ($personRows) {
            return [
                'name' => $personRows->first()['streamer']?->name ?? 'No streamer',
                'shows' => $personRows->count(),
                'pay' => round((float) $personRows->sum('streamer_pay'), 2),
            ];
        })->values();

        return [
            'rows' => $rows,
            'weeks' => $weeks,
            'people' => $people,
            'gross' => round((float) $rows->sum('gross'), 2),
            'cogs' => round((float) $rows->sum('product_cost'), 2),
            'burden' => round((float) $rows->sum('burden'), 2),
            'streamer_pay' => round((float) $rows->sum('streamer_pay'), 2),
            'business' => round((float) $rows->sum('business_after_payroll'), 2),
        ];
    }

    private function simulateStreamerPay(?Streamer $streamer, float $gross, float $productCost, float $hours, float $shipments, float $tips): array
    {
        if (! $streamer) {
            return ['streamer_pay' => 0.0, 'burden' => 0.0, 'pay_rate' => 0.0, 'using_custom' => false, 'note' => 'Choose a streamer to apply the standard weekly pay calculation.'];
        }

        $resolved = $streamer->effectiveCompensation();
        $effective = $resolved['effective'] ?? [];
        $percentage = max(0, (float) ($effective['payout_percentage'] ?? 0));
        $includeTips = (bool) ($effective['include_tips'] ?? true);
        $working = ProfitShareFormula::forShow($gross, $productCost, $hours, $shipments, $percentage);
        $burden = round((float) $working['burden'], 2);
        $basePay = round((float) $working['earnings'], 2);
        $streamerPay = $basePay + ($includeTips ? $tips : 0);
        $usingCustom = count($resolved['overrides'] ?? []) > 0;

        $note = 'Gross $' . number_format($gross, 2)
            . ' − product cost $' . number_format($productCost, 2)
            . ' − burden $' . number_format($burden, 2)
            . ' = $' . number_format(max(0, $gross - $productCost - $burden), 2)
            . ' × ' . number_format($percentage, 2) . '% = $' . number_format($basePay, 2)
            . ($includeTips ? ' + tips $' . number_format($tips, 2) : '') . '.';

        return [
            'streamer_pay' => round(max(0, $streamerPay), 2),
            'burden' => $burden,
            'pay_rate' => $percentage,
            'using_custom' => $usingCustom,
            'note' => $note,
        ];
    }
}
