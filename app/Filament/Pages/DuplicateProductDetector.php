<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Models\DeductionRequestLine;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\PalletLine;
use App\Models\Product;
use App\Models\ProductIdentity;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuplicateProductDetector extends Page
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'purchasing';
    protected static ?string $title = 'Duplicate Detector';
    protected static ?string $navigationLabel = 'Duplicate Detector';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public function getView(): string
    {
        return 'filament.pages.duplicate-product-detector';
    }

    public function getSubheading(): ?string
    {
        return 'Finds likely duplicate products (same name, close SKU, etc.) so you can merge them before they split stock and sales across two catalogue entries.';
    }

    // ── State ──────────────────────────────────────────────────────────────────

    public bool $scanning = false;
    public array $pairs   = [];
    public array $ignored = [];

    public ?string $flashOk    = null;
    public ?string $flashError = null;

    // ── Scan ──────────────────────────────────────────────────────────────────

    public function scan(): void
    {
        $this->scanning = true;
        $this->pairs    = [];

        $products = Product::where('is_active', true)
            ->select(['id', 'name', 'brand', 'sport', 'year', 'set_name', 'product_type', 'configuration', 'upc'])
            ->get();

        $found = [];
        $seen  = [];

        foreach ($products as $a) {
            foreach ($products as $b) {
                if ($a->id >= $b->id) {
                    continue;
                }

                $key = min($a->id, $b->id) . '-' . max($a->id, $b->id);
                if (isset($seen[$key]) || in_array($key, $this->ignored)) {
                    continue;
                }

                $score = $this->similarityScore($a, $b);
                if ($score >= 0.80) {
                    $seen[$key] = true;
                    $found[]    = [
                        'key'       => $key,
                        'product_a' => ['id' => $a->id, 'name' => $a->name, 'brand' => $a->brand, 'year' => $a->year, 'type' => $a->product_type],
                        'product_b' => ['id' => $b->id, 'name' => $b->name, 'brand' => $b->brand, 'year' => $b->year, 'type' => $b->product_type],
                        'score'     => round($score * 100, 1),
                        'reasons'   => $this->similarityReasons($a, $b),
                    ];
                }
            }
        }

        usort($found, fn ($x, $y) => $y['score'] <=> $x['score']);
        $this->pairs    = $found;
        $this->scanning = false;
    }

    public function ignore(string $key): void
    {
        $this->ignored[] = $key;
        $this->pairs     = array_values(array_filter($this->pairs, fn ($p) => $p['key'] !== $key));
    }

    /**
     * Merge product B into product A — reassign all FKs then soft-delete B.
     */
    public function merge(int $keepId, int $dropId): void
    {
        if ($keepId === $dropId) {
            return;
        }

        $keep = Product::find($keepId);
        $drop = Product::find($dropId);

        if (! $keep || ! $drop) {
            $this->flashError = 'Product not found.';
            return;
        }

        try {
            DB::transaction(function () use ($keep, $drop) {
                PalletLine::where('inventory_item_id', $drop->id)->update(['inventory_item_id' => $keep->id]);
                InventoryLot::where('product_id', $drop->id)->update(['product_id' => $keep->id]);
                InventoryMovement::where('inventory_item_id', $drop->id)->update(['inventory_item_id' => $keep->id]);
                ProductIdentity::where('product_id', $drop->id)->update(['product_id' => $keep->id]);
                DeductionRequestLine::where('inventory_item_id', $drop->id)->update(['inventory_item_id' => $keep->id]);

                // Consolidate stock rows: add drop quantities into keep rows at matching locations,
                // then delete the drop rows to avoid violating the UNIQUE(inventory_item_id, location) constraint.
                foreach (InventoryStock::where('inventory_item_id', $drop->id)->get() as $dropStock) {
                    $keepStock = InventoryStock::where('inventory_item_id', $keep->id)
                        ->where('inventory_location_id', $dropStock->inventory_location_id)
                        ->first();

                    if ($keepStock) {
                        $keepStock->increment('quantity', $dropStock->quantity);
                        $dropStock->delete();
                    } else {
                        $dropStock->update(['inventory_item_id' => $keep->id]);
                    }
                }

                // Merge totals
                $keep->increment('total_units_received', (float) $drop->total_units_received);

                // Recalculate WAC
                $lotValue = InventoryLot::where('product_id', $keep->id)
                    ->where('status', 'active')
                    ->selectRaw('SUM(remaining_quantity * unit_cost) as val, SUM(remaining_quantity) as qty')
                    ->first();

                if ($lotValue && $lotValue->qty > 0) {
                    $keep->update(['average_cost' => $lotValue->val / $lotValue->qty]);
                }

                $drop->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Product merge failed', [
                'keep_id' => $keepId,
                'drop_id' => $dropId,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            $this->flashError = 'Merge failed: ' . $e->getMessage();
            return;
        }

        $this->pairs   = array_values(array_filter($this->pairs, fn ($p) => $p['product_a']['id'] !== $dropId && $p['product_b']['id'] !== $dropId));
        $this->flashOk = 'Merged "' . $drop->name . '" into "' . $keep->name . '".';
    }

    // ── Similarity ─────────────────────────────────────────────────────────────

    private function similarityScore(Product $a, Product $b): float
    {
        $score   = 0.0;
        $weights = 0.0;

        // Name similarity (highest weight)
        $nameSim = 0.0;
        similar_text(strtolower($a->name), strtolower($b->name), $nameSim);
        $score   += ($nameSim / 100) * 0.40;
        $weights += 0.40;

        // Brand match
        if ($a->brand && $b->brand) {
            $score   += (strtolower($a->brand) === strtolower($b->brand) ? 1.0 : 0.0) * 0.15;
            $weights += 0.15;
        }

        // Year match
        if ($a->year && $b->year) {
            $score   += ($a->year === $b->year ? 1.0 : 0.0) * 0.15;
            $weights += 0.15;
        }

        // Set name similarity
        if ($a->set_name && $b->set_name) {
            $setSim = 0.0;
            similar_text(strtolower($a->set_name), strtolower($b->set_name), $setSim);
            $score   += ($setSim / 100) * 0.10;
            $weights += 0.10;
        }

        // Product type match
        if ($a->product_type && $b->product_type) {
            $score   += (strtolower($a->product_type) === strtolower($b->product_type) ? 1.0 : 0.0) * 0.10;
            $weights += 0.10;
        }

        // UPC exact match (strong signal — counted in both score and weights)
        if ($a->upc && $b->upc && $a->upc === $b->upc) {
            $score   += 0.30;
            $weights += 0.30;
        }

        // Sport match
        if ($a->sport && $b->sport) {
            $score   += (strtolower($a->sport) === strtolower($b->sport) ? 1.0 : 0.0) * 0.10;
            $weights += 0.10;
        }

        return $weights > 0 ? min(1.0, $score / $weights * 0.85 + ($score > 0 ? 0.15 : 0)) : 0.0;
    }

    private function similarityReasons(Product $a, Product $b): array
    {
        $reasons = [];

        $nameSim = 0.0;
        similar_text(strtolower($a->name), strtolower($b->name), $nameSim);
        $reasons[] = sprintf('Name similarity: %.1f%%', $nameSim);

        if ($a->brand && $b->brand && strtolower($a->brand) === strtolower($b->brand)) {
            $reasons[] = 'Brand matches';
        }
        if ($a->year && $b->year && $a->year === $b->year) {
            $reasons[] = 'Year matches (' . $a->year . ')';
        }
        if ($a->set_name && $b->set_name) {
            $setSim = 0.0;
            similar_text(strtolower($a->set_name), strtolower($b->set_name), $setSim);
            if ($setSim >= 80) {
                $reasons[] = sprintf('Set name similar: %.0f%%', $setSim);
            }
        }
        if ($a->product_type && $b->product_type && strtolower($a->product_type) === strtolower($b->product_type)) {
            $reasons[] = 'Product type matches';
        }
        if ($a->upc && $b->upc && $a->upc === $b->upc) {
            $reasons[] = 'Identical UPC';
        }

        return $reasons;
    }
}
