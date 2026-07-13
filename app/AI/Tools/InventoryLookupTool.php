<?php

namespace App\AI\Tools;

use App\AI\Contracts\Tool;
use App\AI\DTOs\ToolResult;
use App\Models\Product;
use App\Models\User;

/**
 * "How much of X do we have?" — looks up active products by name/SKU/barcode
 * and reports stock on hand.
 */
final class InventoryLookupTool implements Tool
{
    public function name(): string
    {
        return 'inventory.lookup';
    }

    public function description(): string
    {
        return 'Look up a product and its current stock on hand by name, SKU, or barcode. Use for questions like "how many X do we have" or "is Y in stock".';
    }

    public function parameters(): array
    {
        return [
            'query' => ['type' => 'string', 'description' => 'Product name, SKU, or barcode to search for', 'required' => true],
        ];
    }

    public function authorize(?User $user): bool
    {
        return (bool) ($user?->isAdmin() || $user?->isOwner());
    }

    public function run(array $arguments): ToolResult
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '') {
            return ToolResult::error('No search term was provided.');
        }

        $matches = Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('barcode', $query)
                    ->orWhere('upc', $query);
            })
            ->withSum('stock as stock_on_hand', 'quantity')
            ->orderBy('name')
            ->limit(5)
            ->get();

        if ($matches->isEmpty()) {
            return ToolResult::error("No active product matches \"{$query}\".");
        }

        $rows = $matches->map(fn (Product $p) => [
            'id'            => $p->id,
            'name'          => $p->name,
            'sku'           => $p->sku,
            'stock_on_hand' => (float) ($p->stock_on_hand ?? 0),
            'reorder_level' => $p->reorder_level,
        ])->all();

        $lines = collect($rows)->map(
            fn ($r) => "{$r['name']} (SKU {$r['sku']}): {$r['stock_on_hand']} on hand"
        )->implode('; ');

        return ToolResult::ok("Found " . count($rows) . " match(es): {$lines}.", ['matches' => $rows]);
    }
}
