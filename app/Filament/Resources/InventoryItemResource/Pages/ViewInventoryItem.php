<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryCostService;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;

class ViewInventoryItem extends Page
{
    protected static string $resource = InventoryItemResource::class;
    protected static ?string $title = null;

    public InventoryItem $record;
    public string $tab = 'overview';

    public function mount(InventoryItem $record): void
    {
        $this->record = $record;
        $this->loadRelations();
    }

    public function booted(): void
    {
        if ($this->record ?? null) {
            $this->loadRelations();
        }
    }

    private function loadRelations(): void
    {
        $this->record->load([
            'stock.location',
            'lots' => fn ($q) => $q->latest('received_at')->limit(20),
            'identities',
            'preferredVendor',
        ]);
    }

    public function getView(): string
    {
        return 'filament.resources.inventory-item-resource.pages.view-inventory-item';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function getPageTitle(): string
    {
        return $this->record->name;
    }

    public function getStockByLocationProperty(): array
    {
        return $this->record->stock->map(fn ($s) => [
            'location' => $s->location?->name ?? 'Unknown',
            'type' => $s->location?->type ?? '',
            'qty' => (int) $s->quantity,
        ])->sortBy('location')->values()->toArray();
    }

    public function getLotsProperty(): array
    {
        return $this->record->lots()->with('receivingSession.vendor')->orderByDesc('received_at')->get()->map(fn ($lot) => [
            'id' => $lot->id,
            'source' => $lot->source,
            'status' => $lot->status,
            'quantity' => (float) $lot->quantity,
            'remaining' => (float) $lot->remaining_quantity,
            'unit_cost' => number_format((float) $lot->unit_cost, 2),
            'total_cost' => number_format((float) $lot->quantity * (float) $lot->unit_cost, 2),
            'received_at' => $lot->received_at?->format('M d, Y') ?? '—',
            'session_id' => $lot->receiving_session_id,
            'vendor' => $lot->receivingSession?->vendor?->name ?? '—',
            'invoice' => $lot->supplier_invoice ?? '—',
        ])->toArray();
    }

    public function getReceivingHistoryProperty(): array
    {
        return $this->record->palletLines()->with(['pallet.vendor', 'lot', 'cases'])->orderByDesc('created_at')->limit(50)->get()->map(fn ($line) => [
            'id' => $line->id,
            'pallet_id' => $line->pallet_id,
            'pallet' => $line->pallet?->displayName() ?? '—',
            'pallet_url' => $line->pallet ? \App\Filament\Resources\PalletResource::getUrl('items', ['record' => $line->pallet_id]) : null,
            'vendor' => $line->pallet?->vendor?->name ?? '—',
            'date' => ($line->cases->where('status', '!=', 'expected')->max('received_at') ?? $line->created_at)->format('M d, Y'),
            'cases' => (int) $line->case_count,
            'received' => $line->cases->where('status', '!=', 'expected')->count(),
            'unit_cost' => (float) ($line->lot?->unit_cost ?? $line->unit_cost),
            'confidence' => round((float) $line->match_confidence * 100),
            'stage' => $line->match_stage ?? '—',
        ])->toArray();
    }

    public function getMovementsProperty(): array
    {
        return $this->record->movements()->with(['toLocation', 'fromLocation', 'lot'])->orderByDesc('created_at')->limit(200)->get()->map(fn ($m) => [
            'id' => $m->id,
            'type' => $m->movement_type,
            'qty' => $m->signedChange(),
            'location' => $this->movementLocation($m),
            'before' => $m->quantity_before,
            'after' => $m->quantity_after,
            'reason' => $m->reason ?? '—',
            'sort' => $m->created_at,
            'date' => $m->created_at?->diffForHumans(),
            'lot_id' => $m->lot_id,
            'group_key' => implode('|', [$m->movement_type, $m->reason, $m->from_location_id, $m->to_location_id, $m->created_at?->toDateString()]),
        ])->groupBy('group_key')->map(function ($rows) {
            $first = $rows->first();
            if ($rows->count() === 1) return $first + ['grouped' => 1, 'label' => $this->changeLabelFor($first['qty'])];
            $total = $rows->sum('qty');
            return $first + ['qty' => $total, 'before' => $rows->last()['before'], 'after' => $first['after'], 'grouped' => $rows->count(), 'label' => $this->changeLabelFor($total)];
        })->sortByDesc('sort')->take(50)->values()->all();
    }

    private function changeLabelFor(float $change): string
    {
        if ($change > 0) return '+' . number_format($change);
        return $change < 0 ? '-' . number_format(abs($change)) : '0';
    }

    private function movementLocation(\App\Models\InventoryMovement $movement): string
    {
        $from = $movement->fromLocation?->name;
        $to = $movement->toLocation?->name;
        if ($from && $to) return "{$from} → {$to}";
        return $to ?? $from ?? '—';
    }

    public function getAliasesProperty(): array
    {
        return $this->record->identities()->orderByDesc('times_confirmed')->get()->map(fn ($i) => [
            'id' => $i->id,
            'type' => $i->type,
            'value' => $i->value,
            'times' => $i->times_confirmed,
            'confidence' => round((float) $i->auto_confidence * 100),
            'last_seen' => $i->last_confirmed_at?->diffForHumans() ?? 'Never',
            'vendor_id' => $i->vendor_id,
        ])->toArray();
    }

    public function getCostHistoryProperty(): array
    {
        $events = [];

        foreach ($this->record->lots()->get() as $lot) {
            $events[] = [
                'date' => $lot->received_at ?? $lot->created_at ?? now(),
                'display' => $lot->received_at?->format('M d, Y') ?? $lot->created_at?->format('M d, Y') ?? '—',
                'type' => 'lot',
                'unit_cost' => (float) $lot->unit_cost,
                'qty' => (float) $lot->quantity,
                'source' => $lot->source,
                'note' => 'Lot received',
            ];
        }

        foreach ($this->record->movements()->with(['toLocation', 'lot'])->get() as $movement) {
            if ($movement->movement_type === 'opening' || $movement->movement_type === 'adjustment') {
                $events[] = [
                    'date' => $movement->created_at,
                    'display' => $movement->created_at->format('M d, Y g:i A'),
                    'type' => 'movement',
                    'unit_cost' => $this->costForMovement($movement, $movement->lot),
                    'qty' => (float) $movement->quantity,
                    'source' => $movement->movement_type,
                    'note' => $movement->reason ?? 'Stock ' . str_replace('_', ' ', $movement->movement_type),
                ];
            }
        }

        if (empty($events) && $this->record->unit_cost !== null) {
            $events[] = [
                'date' => $this->record->created_at ?? now(),
                'display' => $this->record->created_at?->format('M d, Y g:i A') ?? 'Initial setup',
                'type' => 'initial',
                'unit_cost' => (float) $this->record->unit_cost,
                'qty' => 0,
                'source' => 'initial_import',
                'note' => 'Initial product cost from import / item setup',
            ];
        }

        usort($events, fn ($a, $b) => $b['date'] <=> $a['date']);
        return $events;
    }

    private function costForMovement(\App\Models\InventoryMovement $movement, $lot): ?float
    {
        if ($movement->unit_cost !== null) return (float) $movement->unit_cost;
        if ($lot?->unit_cost) return (float) $lot->unit_cost;

        if ($movement->reference_type === 'inventory_case' && $movement->reference_id) {
            $lineCost = \App\Models\InventoryCase::with('palletLine')->find($movement->reference_id)?->palletLine?->unit_cost;
            if ($lineCost !== null) return (float) $lineCost;
        }
        return null;
    }

    public function getCostBreakdownProperty(): array
    {
        return app(InventoryCostService::class)->getCostBreakdown($this->record);
    }

    public function getCostTrendProperty(): array
    {
        return app(InventoryCostService::class)->getCostTrend($this->record, 15);
    }

    public function getInventoryValueProperty(): float
    {
        return app(InventoryCostService::class)->calculateInventoryValue($this->record);
    }

    public function getCostMetricsProperty(): array
    {
        $breakdown = $this->getCostBreakdownProperty();
        $costs = array_values(array_filter(array_column($breakdown, 'average_cost'), fn ($cost) => $cost !== null && (float) $cost >= 0));
        if (empty($costs) && $this->record->unit_cost !== null) $costs[] = (float) $this->record->unit_cost;

        return [
            'current_average_cost' => (float) ($this->record->costBasis() ?? 0),
            'inventory_value' => $this->getInventoryValueProperty(),
            'min_cost' => !empty($costs) ? min($costs) : 0,
            'max_cost' => !empty($costs) ? max($costs) : 0,
            'vendor_count' => count($breakdown),
            'total_units_received' => (float) $this->record->total_units_received,
            'current_stock' => (float) $this->record->totalQuantity(),
        ];
    }

    public function getCostAnalysisProperty(): array
    {
        $lots = $this->record->lots()->with('receivingSession.vendor')->get();
        $byVendor = [];
        $totalInvested = 0;
        $activeLotDetails = [];

        foreach ($lots as $lot) {
            $vendor = $lot->receivingSession?->vendor?->name ?? 'Unknown Vendor';
            $lotCost = (float) $lot->quantity * (float) $lot->unit_cost;
            $totalInvested += $lotCost;
            if (!isset($byVendor[$vendor])) $byVendor[$vendor] = ['vendor' => $vendor, 'total_units' => 0, 'total_cost' => 0, 'avg_unit_cost' => 0, 'lots_count' => 0];
            $byVendor[$vendor]['total_units'] += (float) $lot->quantity;
            $byVendor[$vendor]['total_cost'] += $lotCost;
            $byVendor[$vendor]['lots_count'] += 1;
            $byVendor[$vendor]['avg_unit_cost'] = $byVendor[$vendor]['total_units'] > 0 ? $byVendor[$vendor]['total_cost'] / $byVendor[$vendor]['total_units'] : 0;

            if ($lot->status === 'active') {
                $activeLotDetails[] = [
                    'vendor' => $vendor,
                    'received_at' => $lot->received_at?->format('M d, Y'),
                    'quantity' => (float) $lot->quantity,
                    'remaining' => (float) $lot->remaining_quantity,
                    'unit_cost' => (float) $lot->unit_cost,
                    'total_cost' => $lotCost,
                    'pct_of_stock' => $this->record->totalQuantity() > 0 ? round((($lot->remaining_quantity / $this->record->totalQuantity()) * 100), 1) : 0,
                ];
            }
        }

        usort($activeLotDetails, fn ($a, $b) => $b['total_cost'] <=> $a['total_cost']);
        usort($byVendor, fn ($a, $b) => $b['total_cost'] <=> $a['total_cost']);
        $currentStock = (float) $this->record->totalQuantity();
        $currentAverage = (float) ($this->record->costBasis() ?? 0);
        $currentValue = $currentStock * $currentAverage;

        return [
            'total_invested' => $totalInvested,
            'current_value' => $currentValue,
            'current_avg_cost' => $currentAverage,
            'current_stock' => $currentStock,
            'by_vendor' => array_values($byVendor),
            'active_lots' => $activeLotDetails,
            'weighted_avg_cost' => $currentAverage,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')->label('Edit')->icon('heroicon-o-pencil')->url(InventoryItemResource::getUrl('edit', ['record' => $this->record]))->color('gray'),
            Action::make('add_stock')
                ->label('Add Stock')->icon('heroicon-o-plus-circle')->color('success')
                ->form([
                    Select::make('location_id')->label('Location')->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))->required()->searchable(),
                    TextInput::make('quantity')->numeric()->required()->minValue(0.01),
                    Select::make('movement_type')->options(['opening' => 'Opening Stock / Restock', 'adjustment' => 'Adjustment', 'return' => 'Return'])->default('opening')->live()->required(),
                    TextInput::make('unit_cost')->label('Unit Cost ($)')->numeric()->minValue(0)->visible(fn (Get $get) => $get('movement_type') === 'opening')->helperText('Blends into this item\'s weighted average cost. Leave blank to add stock without changing the average.'),
                    Textarea::make('reason')->rows(2),
                ])
                ->action(function (array $data): void {
                    $location = InventoryLocation::findOrFail($data['location_id']);
                    app(InventoryService::class)->addStock(
                        $this->record,
                        $location,
                        (float) $data['quantity'],
                        $data['movement_type'],
                        $data['reason'] ?? null,
                        isset($data['unit_cost']) && $data['unit_cost'] !== null && $data['unit_cost'] !== '' ? (float) $data['unit_cost'] : null,
                    );
                    Notification::make()->title('Stock added')->success()->send();
                    $this->record->load('stock.location');
                }),
        ];
    }
}
