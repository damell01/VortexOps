<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'products';

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget('filter:item_categories');
        static::saved($bust);
        static::deleted($bust);

        // is_container is NOT NULL, and "container or not" has no third state
        // — an item is one or the other. A form that submits neither is saying
        // "not a container", which is the safe reading: treating an unanswered
        // question as a case would put contents on something that holds none.
        static::saving(function (self $product) {
            if ($product->is_container === null) {
                $product->is_container = false;
            }
        });

        // Every product gets a SKU, however it was created.
        //
        // The create form had a default, so anything typed in by hand had one —
        // and nothing else did. A product created by scanning an unknown
        // barcode at a pallet, or through quick add, arrived with the column
        // blank and stayed that way, because a form default only fires on the
        // form. Putting it here covers every path there is, including the ones
        // written next year.
        static::creating(function (self $product) {
            if (blank($product->sku)) {
                $product->sku = static::generateSku();
            }
        });
    }

    /**
     * A SKU nothing else is using.
     *
     * Random rather than sequential: sequential needs the highest existing
     * number, which is a read that two concurrent receipts can both win. The
     * loop is what makes it safe rather than merely unlikely — and the column
     * is uniquely indexed, so a collision that slipped through would be a
     * constraint violation rather than two products sharing a code.
     */
    public static function generateSku(): string
    {
        do {
            $sku = 'VB' . date('ymd') . strtoupper(\Illuminate\Support\Str::random(4));
        } while (static::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    protected $fillable = [
        // Core identity
        'sku',
        'barcode',
        'name',
        'upc',
        // Card-specific fields (all nullable — populated gradually)
        'brand',
        'sport',
        'year',
        'set_name',
        'product_type',
        'sold_as',
        'configuration',
        'manufacturer',
        // General
        'category',
        'description',
        'image_path',
        'unit_cost',
        'average_cost',
        'sale_price',
        'total_units_received',
        'reorder_level',
        'is_active',
        'is_container',
        'preferred_vendor_id',
        'notes',
    ];

    /**
     * The disk product images live on.
     *
     * Named here for the same reason pallet attachments name theirs: the app's
     * default filesystem disk is `local`, an upload field that does not say
     * otherwise uses it, and a file written there is invisible to the public
     * symlink these are served through — the image uploads fine and then 404s.
     */
    public const IMAGE_DISK = 'public';

    /** Whether a real photo has been uploaded, as opposed to falling back. */
    public function hasImage(): bool
    {
        return filled($this->image_path);
    }

    /**
     * A URL for this product's photo, falling back to the site logo.
     *
     * Every surface that shows one of these is a grid or a list, and a missing
     * image there is a hole in the layout rather than information. The brand
     * mark fills it and reads as "no photo yet" without anyone having to say
     * so — which is also why callers wanting to know the difference should ask
     * hasImage() rather than compare against this.
     */
    public function imageUrl(): ?string
    {
        if ($this->hasImage()) {
            return \Illuminate\Support\Facades\Storage::disk(self::IMAGE_DISK)->url($this->image_path);
        }

        return self::placeholderImageUrl();
    }

    /**
     * The stand-in for a product with no photo.
     *
     * An uploaded logo wins, following the same precedence as the sidebar —
     * the active channel's, then the global one — so the placeholder is
     * whatever this install already brands itself with.
     *
     * Falling back, it takes the square mark rather than the sidebar wordmark
     * the panel would hand back. Every slot showing one of these is a square
     * thumbnail, and a 220x48 wordmark letterboxed into 80x80 reads as an
     * empty box, which is the thing the placeholder exists to avoid.
     */
    public static function placeholderImageUrl(): ?string
    {
        $channel = \App\Support\ChannelContext::current();

        if ($channel?->logo_path && file_exists(storage_path('app/public/' . $channel->logo_path))) {
            return asset('storage/' . $channel->logo_path);
        }

        $configured = Setting::get('logo_path');

        if ($configured && file_exists(storage_path('app/public/' . $configured))) {
            return asset('storage/' . $configured);
        }

        if (file_exists(public_path('images/vb-logo.svg'))) {
            return asset('images/vb-logo.svg');
        }

        return \App\Providers\Filament\AdminPanelProvider::resolveBrandLogo($channel, $configured);
    }

    protected $casts = [
        'unit_cost'            => 'decimal:2',
        'average_cost'         => 'decimal:4',
        'sale_price'           => 'decimal:2',
        'total_units_received' => 'decimal:2',
        'is_active'            => 'boolean',
        'is_container'         => 'boolean',
        'year'                 => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    // ── Margin ─────────────────────────────────────────────────────────────────

    /**
     * The cost to measure a sale price against.
     *
     * The weighted average is the truth once anything has been received, and
     * it is the number every other costing surface in the app already uses.
     * Before the first receipt there is no average, so the unit cost stands in
     * — an item imported from a cost sheet has one of those and nothing else,
     * and a margin that reads as the whole sale price until someone receives
     * a pallet would be worse than no margin at all.
     */
    public function costBasis(): ?float
    {
        $average = (float) ($this->average_cost ?? 0);

        if ($average > 0) {
            return $average;
        }

        $unit = (float) ($this->unit_cost ?? 0);

        return $unit > 0 ? $unit : null;
    }

    /**
     * Money left over on one unit at the target price.
     *
     * Null rather than zero when either side is unknown: "no target set" and
     * "sells for exactly what it cost" are different facts, and a column that
     * shows $0.00 for both hides the one worth acting on.
     */
    /**
     * How this product is sold, as the cost sheet says it.
     *
     * The three the sheet uses, and nothing stops a fourth arriving — the
     * column is free text so an unexpected value from a vendor's sheet is
     * kept rather than dropped on the floor. These are what the pickers offer.
     *
     * @return array<string, string>
     */
    public static function soldAsOptions(): array
    {
        return [
            'Auction' => 'Auction',
            'BIN'     => 'Buy It Now',
            'Both'    => 'Both',
        ];
    }

    /** The label for whatever is stored, including a value we have never seen. */
    public function soldAsLabel(): ?string
    {
        if (blank($this->sold_as)) {
            return null;
        }

        return static::soldAsOptions()[$this->sold_as] ?? $this->sold_as;
    }

    public function marginPotential(): ?float
    {
        $cost = $this->costBasis();
        $sale = (float) ($this->sale_price ?? 0);

        if ($cost === null || $sale <= 0) {
            return null;
        }

        return round($sale - $cost, 2);
    }

    /** The same margin as a share of the sale price. */
    public function marginPercent(): ?float
    {
        $margin = $this->marginPotential();
        $sale   = (float) ($this->sale_price ?? 0);

        if ($margin === null || $sale <= 0) {
            return null;
        }

        return round(($margin / $sale) * 100, 1);
    }

    /** Total margin available across everything currently on the shelf. */
    public function marginPotentialOnHand(): ?float
    {
        $margin = $this->marginPotential();

        if ($margin === null) {
            return null;
        }

        return round($margin * (float) ($this->stock_sum_quantity ?? $this->stock()->sum('quantity')), 2);
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function lots(): HasMany
    {
        // Explicit FK: the InventoryItem alias would otherwise infer inventory_item_id.
        return $this->hasMany(InventoryLot::class, 'product_id');
    }

    public function identities(): HasMany
    {
        // Explicit FK: the InventoryItem alias would otherwise infer inventory_item_id.
        return $this->hasMany(ProductIdentity::class, 'product_id');
    }

    public function palletLines(): HasMany
    {
        return $this->hasMany(PalletLine::class, 'inventory_item_id');
    }

    /** Sold order lines mapped to this product (used for margin / sell-through). */
    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\WhatnotShowOrder::class, 'inventory_item_id');
    }

    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'preferred_vendor_id');
    }

    /** The embedding vector, in its own table so it never bloats product reads. */
    public function embedding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        // Explicit FK: the InventoryItem alias would otherwise infer inventory_item_id.
        return $this->hasOne(ProductEmbedding::class, 'product_id');
    }

    public function childContents(): HasMany
    {
        return $this->hasMany(InventoryItemContent::class, 'parent_inventory_item_id');
    }

    public function parentContents(): HasMany
    {
        return $this->hasMany(InventoryItemContent::class, 'child_inventory_item_id');
    }

    // ── Lookups ────────────────────────────────────────────────────────────────

    /**
     * Find by barcode, SKU, or UPC — used by the scanner.
     */
    public static function findByScan(string $code): ?static
    {
        return static::where('barcode', $code)
            ->orWhere('sku', $code)
            ->orWhere('upc', $code)
            ->first()
            // fall through to identity table for vendor SKUs / aliases
            ?? ProductIdentity::where('value', $code)
                ->whereIn('type', ['barcode', 'upc', 'vendor_sku', 'manufacturer_sku'])
                ->with('product')
                ->first()
                ?->product;
    }

    /**
     * Suggest products based on a description (deduction history ranking).
     *
     * @return array<int, string>  id → name
     */
    public static function suggestForDescription(string $description, int $limit = 8): array
    {
        $words = collect(explode(' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $description)))
            ->map('strtolower')
            ->filter(fn ($w) => strlen($w) >= 3)
            ->unique()
            ->values();

        if ($words->isEmpty()) {
            return [];
        }

        $ids = DeductionRequestLine::query()
            ->selectRaw('inventory_item_id, COUNT(*) as freq')
            ->whereNotNull('inventory_item_id')
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('raw_description', 'like', "%{$word}%");
                }
            })
            ->groupBy('inventory_item_id')
            ->orderByDesc('freq')
            ->limit($limit)
            ->pluck('inventory_item_id');

        if ($ids->isEmpty()) {
            return [];
        }

        return static::whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    // ── Computed ───────────────────────────────────────────────────────────────

    public function effectiveCost(): float
    {
        $avg = (float) $this->average_cost;
        return $avg > 0 ? $avg : (float) $this->unit_cost;
    }

    public function totalQuantity(): float
    {
        if ($this->relationLoaded('stock')) {
            return (float) $this->stock->sum('quantity');
        }

        return (float) $this->stock()->sum('quantity');
    }

    public function isLowStock(): bool
    {
        if ($this->reorder_level === null) {
            return false;
        }

        return $this->totalQuantity() <= $this->reorder_level;
    }

    /**
     * Suggested reorder quantity from trailing sales velocity, projected across
     * the vendor's lead time plus a safety-stock buffer, minus what's already on
     * hand. Null when there's no recent sales history to estimate a rate from, or
     * when on-hand stock already covers projected demand — a forecast built on
     * zero data is worse than no forecast.
     */
    public function suggestedReorderQuantity(int $trailingDays = 30, int $safetyDays = 7, int $defaultLeadTimeDays = 14): ?int
    {
        $trailingUnitsSold = (float) $this->orders()
            ->where('show_date', '>=', now()->subDays($trailingDays)->toDateString())
            ->sum('quantity');

        $velocity = $trailingUnitsSold / $trailingDays;
        if ($velocity <= 0) {
            return null;
        }

        $leadTimeDays = $this->relationLoaded('preferredVendor')
            ? ($this->preferredVendor?->lead_time_days ?? $defaultLeadTimeDays)
            : ($this->preferredVendor()->value('lead_time_days') ?? $defaultLeadTimeDays);

        $reorderPoint = $velocity * ($leadTimeDays + $safetyDays);
        $onHand       = $this->totalQuantity();

        return $onHand < $reorderPoint ? (int) ceil($reorderPoint - $onHand) : null;
    }

    /** Most recent time stock was added to this item (opening or return), or null if never. */
    public function lastRestockedAt(): ?\Illuminate\Support\Carbon
    {
        $max = $this->relationLoaded('movements')
            ? $this->movements->whereIn('movement_type', ['opening', 'return'])->max('created_at')
            : $this->movements()->whereIn('movement_type', ['opening', 'return'])->max('created_at');

        return $max ? \Illuminate\Support\Carbon::parse($max) : null;
    }

    /**
     * True when this item currently has zero stock everywhere and hasn't been
     * restocked in at least $days days (or ever) — a signal that a sale mapped
     * to it is likely a mis-mapping rather than a real transaction.
     */
    public function hasBeenOutOfStockFor(int $days = 14): bool
    {
        if ($this->totalQuantity() > 0) {
            return false;
        }

        $lastRestock = $this->lastRestockedAt();

        return $lastRestock === null || $lastRestock->lt(now()->subDays($days));
    }

    /**
     * Human-readable card label, e.g. "2025 Topps Chrome Baseball Hobby Box".
     */
    public function cardLabel(): string
    {
        return implode(' ', array_filter([
            $this->year,
            $this->manufacturer ?? $this->brand,
            $this->set_name,
            $this->sport,
            $this->product_type,
        ])) ?: $this->name;
    }

    /**
     * Return FIFO-ordered active lots with remaining stock.
     */
    public function activeLotsFifo(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->lots()
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->get();
    }
}
