<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Carbon;

class InventoryVelocityService
{
    /**
     * Calculate velocity metrics for an item over a time period.
     * Returns movement rate, turnover ratio, and classification.
     */
    public function calculateItemVelocity(InventoryItem $item, int $daysPeriod = 30): array
    {
        $startDate = now()->subDays($daysPeriod);

        // Get movements in period
        $movements = InventoryMovement::query()
            ->where('inventory_item_id', $item->id)
            ->where('created_at', '>=', $startDate)
            ->where('movement_type', '!=', 'adjustment')
            ->sum('quantity');

        $currentStock = $item->totalQuantity();
        $avgCost = (float) $item->average_cost;

        // Calculate turnover (how many times stock rotated)
        $turnover = $currentStock > 0 ? $movements / $currentStock : 0;

        // Daily velocity
        $dailyVelocity = $movements / $daysPeriod;

        // Days of stock remaining at current velocity
        $daysRemaining = $dailyVelocity > 0 ? round($currentStock / $dailyVelocity) : 999;

        // Classify as fast/slow/dead mover
        $classification = $this->classifyVelocity($dailyVelocity, $turnover, $currentStock, $daysPeriod);

        return [
            'item_id'           => $item->id,
            'item_name'         => $item->name,
            'sku'               => $item->sku,
            'current_stock'     => (float) $currentStock,
            'avg_cost'          => $avgCost,
            'inventory_value'   => $currentStock * $avgCost,
            'period_days'       => $daysPeriod,
            'total_movement'    => (float) $movements,
            'daily_velocity'    => round($dailyVelocity, 2),
            'turnover_ratio'    => round($turnover, 2),
            'days_stock'        => (int) $daysRemaining,
            'classification'    => $classification,
        ];
    }

    /**
     * Get all items ranked by velocity (fast movers first).
     */
    public function getRankedByVelocity(int $daysPeriod = 30): array
    {
        $items = InventoryItem::query()->get();

        $velocities = $items->map(fn ($item) => $this->calculateItemVelocity($item, $daysPeriod))
            ->sortByDesc('daily_velocity')
            ->values()
            ->toArray();

        return $velocities;
    }

    /**
     * Get fast movers (items with high turnover).
     */
    public function getFastMovers(int $daysPeriod = 30, int $limit = 20): array
    {
        return collect($this->getRankedByVelocity($daysPeriod))
            ->where('classification', 'fast')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Get slow movers (items with low turnover).
     */
    public function getSlowMovers(int $daysPeriod = 30, int $limit = 20): array
    {
        return collect($this->getRankedByVelocity($daysPeriod))
            ->where('classification', 'slow')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Get dead stock (items with no movement and high value).
     */
    public function getDeadStock(int $daysPeriod = 30, int $limit = 20): array
    {
        return collect($this->getRankedByVelocity($daysPeriod))
            ->where('classification', 'dead')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Get high-value fast movers (priority to stock).
     */
    public function getHighValueFastMovers(int $daysPeriod = 30, int $limit = 10): array
    {
        return collect($this->getRankedByVelocity($daysPeriod))
            ->where('classification', 'fast')
            ->sortByDesc('inventory_value')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Classify velocity based on turnover and daily movement.
     */
    private function classifyVelocity(float $dailyVelocity, float $turnover, float $stock, int $period): string
    {
        if ($stock == 0) {
            return 'out-of-stock';
        }

        if ($dailyVelocity == 0 && $turnover == 0) {
            return 'dead';
        }

        if ($dailyVelocity > 0 && $turnover == 0) {
            return 'slow';
        }

        // Turnover >= 2 per period = fast mover
        if ($turnover >= 2) {
            return 'fast';
        }

        // Turnover >= 1 per period = normal
        if ($turnover >= 1) {
            return 'normal';
        }

        // Less than 1 turnover in period = slow
        return 'slow';
    }

    /**
     * Get velocity summary statistics.
     */
    public function getSummary(int $daysPeriod = 30): array
    {
        $velocities = $this->getRankedByVelocity($daysPeriod);

        $fastCount = count(array_filter($velocities, fn ($v) => $v['classification'] === 'fast'));
        $normalCount = count(array_filter($velocities, fn ($v) => $v['classification'] === 'normal'));
        $slowCount = count(array_filter($velocities, fn ($v) => $v['classification'] === 'slow'));
        $deadCount = count(array_filter($velocities, fn ($v) => $v['classification'] === 'dead'));

        $totalInventoryValue = array_sum(array_map(fn ($v) => $v['inventory_value'], $velocities));
        $fastMoversValue = array_sum(array_map(fn ($v) => $v['inventory_value'], array_filter($velocities, fn ($v) => $v['classification'] === 'fast')));
        $deadStockValue = array_sum(array_map(fn ($v) => $v['inventory_value'], array_filter($velocities, fn ($v) => $v['classification'] === 'dead')));

        return [
            'total_items'           => count($velocities),
            'fast_movers'           => $fastCount,
            'normal_movers'         => $normalCount,
            'slow_movers'           => $slowCount,
            'dead_stock'            => $deadCount,
            'total_inventory_value' => round($totalInventoryValue, 2),
            'fast_movers_value'     => round($fastMoversValue, 2),
            'dead_stock_value'      => round($deadStockValue, 2),
            'dead_stock_pct'        => $totalInventoryValue > 0 ? round(($deadStockValue / $totalInventoryValue) * 100, 1) : 0,
        ];
    }
}
