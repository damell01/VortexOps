<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Streamer;
use Illuminate\Support\Collection;

/**
 * A streamer's own stock, in one step: their own location (made if they
 * don't have one), then stock moved into it from wherever it is now.
 */
class StreamerInventorySetup
{
    /** Their own active streamer-inventory location, if they have one. */
    public function ownLocation(Streamer $streamer): ?InventoryLocation
    {
        return InventoryLocation::query()
            ->where('streamer_id', $streamer->id)
            ->where('type', 'streamer_inventory')
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }

    /**
     * Their own location, made if they don't have one: "{Name}'s Inventory",
     * listed under their business and channel.
     *
     * @return array{0: InventoryLocation, 1: bool} the location, and whether it was just made
     */
    public function ensure(Streamer $streamer, ?string $name = null): array
    {
        if ($existing = $this->ownLocation($streamer)) {
            return [$existing, false];
        }

        return [InventoryLocation::create([
            'name' => trim((string) $name) !== '' ? trim($name) : "{$streamer->name}'s Inventory",
            'type' => 'streamer_inventory',
            'status' => 'active',
            'streamer_id' => $streamer->id,
            'business_id' => $streamer->business_id,
            'whatnot_channel_id' => $streamer->whatnot_channel_id,
        ]), true];
    }

    /**
     * Everyone in $streamers gets their own location, and — if lines are
     * given — the same items each. Fulfillment staff are skipped.
     *
     * @param  iterable<Streamer>  $streamers
     * @param  list<array{inventory_item_id:int|string, quantity:int|float|string}>  $lines  given to each person
     * @return array{created:int, existing:int, stocked:int, problems:list<string>}
     */
    public function setUpMany(iterable $streamers, ?InventoryLocation $from = null, array $lines = []): array
    {
        $summary = ['created' => 0, 'existing' => 0, 'stocked' => 0, 'problems' => []];
        foreach ($streamers as $streamer) {
            if ($streamer->isFulfillment()) {
                continue;
            }
            [$location, $created] = $this->ensure($streamer);
            $summary[$created ? 'created' : 'existing']++;
            if ($from && $lines) {
                $result = $this->stock($streamer, $location, $from, $lines);
                $summary['stocked'] += $result['moved'] > 0 ? 1 : 0;
                foreach ($result['problems'] as $problem) {
                    $summary['problems'][] = "{$streamer->name} — {$problem}";
                }
            }
        }

        return $summary;
    }

    /** The streamers a business's setup covers: active streamers in it, or everyone with no business given. */
    public function streamersFor(?int $businessId): \Illuminate\Database\Eloquent\Collection
    {
        return Streamer::query()->streamers()->where('status', 'active')
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->orderBy('name')->get();
    }

    /** "3 set up, 2 already had one, 3 stocked." */
    public static function describe(array $summary): string
    {
        $parts = ["{$summary['created']} set up"];
        if ($summary['existing']) {
            $parts[] = "{$summary['existing']} already had one";
        }
        if ($summary['stocked']) {
            $parts[] = "{$summary['stocked']} stocked";
        }

        return implode(', ', $parts) . '.' . ($summary['problems'] ? ' Not moved: ' . implode('; ', array_slice($summary['problems'], 0, 5)) . (count($summary['problems']) > 5 ? '…' : '') : '');
    }

    /**
     * Where stock can come from: active, sellable locations that aren't a
     * person's own. @return Collection<int, InventoryLocation>
     */
    public function sources(): Collection
    {
        return InventoryLocation::query()
            ->where('status', 'active')->whereNull('streamer_id')
            ->whereIn('type', InventoryLocation::POOL_TYPES)
            ->orderBy('name')->get();
    }

    /** Where their stock most likely comes from: their business's own storage, else the receiving warehouse, else Main Storage. */
    public function defaultSource(?Streamer $streamer): ?InventoryLocation
    {
        $sources = $this->sources();
        $businessId = (int) $streamer?->business_id;
        if ($businessId && InventoryLocation::businessHasPool($businessId)) {
            $own = $sources->first(fn (InventoryLocation $l) => $l->type === 'main_storage' && $l->businesses()->whereKey($businessId)->exists());
            if ($own) {
                return $own;
            }
        }

        return $sources->firstWhere('id', InventoryLocation::defaultReceivingId())
            ?? $sources->first(fn (InventoryLocation $l) => $l->type === 'main_storage' && ! $l->businesses()->exists())
            ?? $sources->first();
    }

    /** Items with stock at a location, "Name · 12 on hand". @return array<int, string> */
    public function itemOptions(?int $locationId): array
    {
        if (! $locationId) {
            return [];
        }

        return InventoryStock::query()
            ->where('inventory_location_id', $locationId)->where('quantity', '>', 0)
            ->with('item:id,name')
            ->get()
            ->filter(fn ($s) => $s->item)
            ->sortBy(fn ($s) => $s->item->name)
            ->mapWithKeys(fn ($s) => [$s->inventory_item_id => $s->item->name . ' · ' . rtrim(rtrim(number_format((float) $s->quantity, 2), '0'), '.') . ' on hand'])
            ->all();
    }

    /**
     * Move each line into their location. A line that can't move (not enough
     * stock) is reported and the rest still go.
     *
     * @param  list<array{inventory_item_id:int|string, quantity:int|float|string}>  $lines
     * @return array{moved:int, problems:list<string>}
     */
    public function stock(Streamer $streamer, InventoryLocation $to, InventoryLocation $from, array $lines): array
    {
        $moved = 0;
        $problems = [];
        foreach ($lines as $line) {
            $item = InventoryItem::find($line['inventory_item_id'] ?? null);
            $qty = (float) ($line['quantity'] ?? 0);
            if (! $item || $qty <= 0) {
                continue;
            }
            try {
                app(InventoryService::class)->transferStock(item: $item, from: $from, to: $to, quantity: $qty, reason: "Set up {$streamer->name}'s inventory");
                $moved++;
            } catch (\RuntimeException $e) {
                $problems[] = "{$item->name}: {$e->getMessage()}";
            }
        }

        return ['moved' => $moved, 'problems' => $problems];
    }
}
