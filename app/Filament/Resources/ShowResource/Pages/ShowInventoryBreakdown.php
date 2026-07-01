<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\DeductionRequest;
use App\Models\InventoryMovement;
use App\Models\Show;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class ShowInventoryBreakdown extends Page
{
    protected static string $resource = ShowResource::class;

    protected static ?string $title = 'Inventory Breakdown';

    public Show $record;

    /** @var array<int, array{...}> */
    public array $breakdown = [];

    /** Summary totals */
    public int   $totalLines  = 0;
    public int   $matched     = 0;
    public int   $unmatched   = 0;
    public float $totalCogs   = 0.0;
    public float $totalDeducted = 0.0;
    public ?string $deductionStatus = null;

    public function getView(): string
    {
        return 'filament.pages.show-inventory-breakdown';
    }

    public function mount(Show $record): void
    {
        $this->record = $record->load([
            'channel',
            'streamers',
            'latestDeductionRequest.lines.inventoryItem.stock',
            'latestDeductionRequest.lines.location',
        ]);

        $this->refresh();
    }

    public function refresh(): void
    {
        $request = $this->record->latestDeductionRequest;

        if (! $request) {
            $this->breakdown        = [];
            $this->totalLines       = 0;
            $this->matched          = 0;
            $this->unmatched        = 0;
            $this->totalCogs        = 0.0;
            $this->totalDeducted    = 0.0;
            $this->deductionStatus  = null;
            return;
        }

        $this->deductionStatus = $request->status;

        // Sum of deducted quantity per inventory item for this deduction request
        $deductedMap = InventoryMovement::where('reference_type', 'deduction_request')
            ->where('reference_id', $request->id)
            ->selectRaw('inventory_item_id, SUM(quantity) as total')
            ->groupBy('inventory_item_id')
            ->pluck('total', 'inventory_item_id')
            ->map(fn ($v) => (float) $v);

        $this->breakdown = $request->lines->map(function ($line) use ($deductedMap) {
            $item         = $line->inventoryItem;
            $currentStock = $item ? (float) $item->stock->sum('quantity') : null;
            $qtyApproved  = (float) $line->quantity_approved;
            $deducted     = $item ? (float) ($deductedMap[$item->id] ?? 0) : 0.0;
            $pending      = max(0, $qtyApproved - $deducted);

            return [
                'line_id'         => $line->id,
                'raw_description' => $line->raw_description ?? '—',
                'item_id'         => $item?->id,
                'item_name'       => $item?->name,
                'item_sku'        => $item?->sku,
                'location'        => $line->location?->name ?? '—',
                'confidence'      => $line->ai_confidence,
                'qty_suggested'   => (float) $line->quantity_suggested,
                'qty_approved'    => $qtyApproved,
                'unit_cost'       => (float) $line->unit_cost_snapshot,
                'line_total'      => (float) $line->line_total,
                'current_stock'   => $currentStock,
                'qty_deducted'    => $deducted,
                'qty_pending'     => $pending,
            ];
        })->toArray();

        $this->totalLines    = count($this->breakdown);
        $this->matched       = count(array_filter($this->breakdown, fn ($r) => $r['item_id'] !== null));
        $this->unmatched     = $this->totalLines - $this->matched;
        $this->totalCogs     = (float) $request->lines->sum('line_total');
        $this->totalDeducted = (float) $deductedMap->sum();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Show')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => ShowResource::getUrl('view', ['record' => $this->record])),

            Action::make('review_approval')
                ->label('Review Deduction Request')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->visible(fn () => $this->record->latestDeductionRequest !== null)
                ->url(fn () => \App\Filament\Resources\DeductionRequestResource::getUrl('view', [
                    'record' => $this->record->latestDeductionRequest?->id,
                ])),
        ];
    }
}
