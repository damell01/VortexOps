<?php

namespace App\Support;

use App\Models\DeductionRequest;
use App\Models\InventoryContainer;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Show;
use App\Models\WeeklyPayoutBatch;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;

class DemoDataManager
{
    /**
     * @var array<int, string>
     */
    private const SHOW_TITLES = [
        'Mojo Break #41 — Baseball Night',
        'Mojo Break #42 — Hoops & Football',
        'Mojo Break #43 — TCG Night',
    ];

    /**
     * @var array<int, string>
     */
    private const ITEM_SKUS = [
        'BCH-2024-001',
        'TPS-2024-002',
        'PRI-2024-003',
        'OPT-2024-004',
        'PKM-2024-005',
        'MTG-2024-006',
        'SCR-2025-007',
        'NBA-2024-008',
    ];

    public function seed(): array
    {
        $cleared = $this->clear();

        DB::transaction(function (): void {
            app(DemoDataSeeder::class)->run();
        });

        $summary = $this->snapshot();

        return [
            'message' => 'Demo data refreshed successfully.',
            'details' => [
                'Existing demo records cleared first: ' . $cleared['summary'],
                $summary,
                'Demo shows now include suggested sold items through deduction requests so you can test show review and approval flows.',
            ],
        ];
    }

    public function clear(): array
    {
        $counts = DB::transaction(function (): array {
            $showIds = Show::query()
                ->whereIn('title', self::SHOW_TITLES)
                ->pluck('id');

            $itemIds = InventoryItem::query()
                ->whereIn('sku', self::ITEM_SKUS)
                ->pluck('id');

            $batchIds = Payout::query()
                ->whereIn('show_id', $showIds)
                ->whereNotNull('weekly_payout_batch_id')
                ->pluck('weekly_payout_batch_id')
                ->filter()
                ->unique()
                ->values();

            $requestIds = DeductionRequest::query()
                ->whereIn('show_id', $showIds)
                ->pluck('id');

            $deleted = [
                'show_streamer_links' => DB::table('show_streamer')->whereIn('show_id', $showIds)->delete(),
                'deduction_lines' => DB::table('deduction_request_lines')->whereIn('deduction_request_id', $requestIds)->delete(),
                'deduction_requests' => DeductionRequest::query()->whereIn('id', $requestIds)->delete(),
                'payouts' => Payout::query()->whereIn('show_id', $showIds)->delete(),
                'inventory_movements' => InventoryMovement::query()
                    ->whereIn('inventory_item_id', $itemIds)
                    ->orWhere(function ($query) use ($requestIds): void {
                        $query->where('reference_type', 'deduction_request')
                            ->whereIn('reference_id', $requestIds);
                    })
                    ->delete(),
                'inventory_containers' => InventoryContainer::schemaReady()
                    ? InventoryContainer::query()->where('label', 'like', 'DEMO-%')->delete()
                    : 0,
                'inventory_stock' => InventoryStock::query()->whereIn('inventory_item_id', $itemIds)->delete(),
                'shows' => Show::query()->whereIn('id', $showIds)->delete(),
                'inventory_items' => InventoryItem::query()->whereIn('id', $itemIds)->delete(),
                'locations' => InventoryLocation::query()->where('name', 'Demo Receiving Bay')->delete(),
            ];

            if ($batchIds->isNotEmpty()) {
                WeeklyPayoutBatch::query()
                    ->whereIn('id', $batchIds)
                    ->doesntHave('payouts')
                    ->delete();
            }

            return $deleted;
        });

        return [
            'summary' => sprintf(
                '%d shows, %d items, %d deduction requests, %d payout rows, %d container records removed.',
                $counts['shows'],
                $counts['inventory_items'],
                $counts['deduction_requests'],
                $counts['payouts'],
                $counts['inventory_containers']
            ),
        ];
    }

    private function snapshot(): string
    {
        $showCount = Show::query()->whereIn('title', self::SHOW_TITLES)->count();
        $itemCount = InventoryItem::query()->whereIn('sku', self::ITEM_SKUS)->count();
        $requestCount = DeductionRequest::query()
            ->whereIn('show_id', Show::query()->whereIn('title', self::SHOW_TITLES)->pluck('id'))
            ->count();
        $containerCount = InventoryContainer::schemaReady()
            ? InventoryContainer::query()->where('label', 'like', 'DEMO-%')->count()
            : 0;

        return "{$showCount} demo shows, {$itemCount} demo inventory items, {$requestCount} deduction review requests, {$containerCount} demo containers.";
    }
}
