<?php

namespace App\AI\Tools;

use App\AI\Contracts\Tool;
use App\AI\DTOs\ToolResult;
use App\Models\Product;
use App\Models\User;

/**
 * "What do we need to reorder?" — active products whose tracked stock has
 * fallen to or below their reorder level.
 */
final class ReorderListTool implements Tool
{
    private const LIMIT = 20;

    public function name(): string
    {
        return 'inventory.reorder';
    }

    public function description(): string
    {
        return 'List active products at or below their reorder level (low stock) so they can be restocked. Use for "what needs reordering" or "what are we low on".';
    }

    public function parameters(): array
    {
        return [];
    }

    public function authorize(?User $user): bool
    {
        return (bool) ($user?->isAdmin() || $user?->isOwner());
    }

    public function run(array $arguments): ToolResult
    {
        $items = Product::query()
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            // Tracked stock summed across locations is at/below the reorder level.
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('inventory_stock')
                    ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                    ->groupBy('inventory_stock.inventory_item_id')
                    ->havingRaw('SUM(quantity) <= products.reorder_level');
            })
            ->withSum('stock as stock_on_hand', 'quantity')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        if ($items->isEmpty()) {
            return ToolResult::ok('Nothing is at or below its reorder level — stock levels are healthy.', ['items' => []]);
        }

        $rows = $items->map(fn (Product $p) => [
            'name'          => $p->name,
            'sku'           => $p->sku,
            'stock_on_hand' => (float) ($p->stock_on_hand ?? 0),
            'reorder_level' => (float) $p->reorder_level,
        ])->all();

        $lines = collect($rows)->map(
            fn ($r) => "{$r['name']} (SKU {$r['sku']}): {$r['stock_on_hand']} on hand, reorder at {$r['reorder_level']}"
        )->implode('; ');

        $note = $items->count() === self::LIMIT ? ' (showing the first ' . self::LIMIT . ')' : '';

        return ToolResult::ok(count($rows) . " product(s) need reordering{$note}: {$lines}.", ['items' => $rows]);
    }
}
