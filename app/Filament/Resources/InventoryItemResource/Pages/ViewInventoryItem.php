<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\ProductIdentity;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ViewInventoryItem extends Page
{
    protected static string $resource = InventoryItemResource::class;
    protected static ?string $title = null;

    public InventoryItem $record;
    public string $tab = 'overview';

    public function mount(InventoryItem $record): void
    {
        $this->record = $record->load([
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

    // ── Computed properties ────────────────────────────────────────────────────

    public function getPageTitle(): string
    {
        return $this->record->name;
    }

    public function getStockByLocationProperty(): array
    {
        return $this->record->stock->map(fn ($s) => [
            'location' => $s->location?->name ?? 'Unknown',
            'type'     => $s->location?->type ?? '',
            'qty'      => (int) $s->quantity,
        ])->sortBy('location')->values()->toArray();
    }

    public function getLotsProperty(): array
    {
        return $this->record->lots()
            ->with('receivingSession.vendor')
            ->orderByDesc('received_at')
            ->get()
            ->map(fn ($lot) => [
                'id'               => $lot->id,
                'source'           => $lot->source,
                'status'           => $lot->status,
                'quantity'         => (float) $lot->quantity,
                'remaining'        => (float) $lot->remaining_quantity,
                'unit_cost'        => number_format((float) $lot->unit_cost, 2),
                'total_cost'       => number_format((float) $lot->quantity * (float) $lot->unit_cost, 2),
                'received_at'      => $lot->received_at?->format('M d, Y') ?? '—',
                'session_id'       => $lot->receiving_session_id,
                'vendor'           => $lot->receivingSession?->vendor?->name ?? '—',
                'invoice'          => $lot->supplier_invoice ?? '—',
            ])->toArray();
    }

    public function getReceivingHistoryProperty(): array
    {
        return $this->record->palletLines()
            ->with(['receivingSession.vendor', 'receivingSession'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($line) => [
                'id'          => $line->id,
                'session_id'  => $line->receiving_session_id,
                'vendor'      => $line->receivingSession?->vendor?->name ?? '—',
                'date'        => $line->created_at->format('M d, Y'),
                'cases'       => $line->case_count,
                'unit_cost'   => number_format((float) $line->unit_cost, 2),
                'confidence'  => round((float) $line->match_confidence * 100),
                'stage'       => $line->match_stage ?? '—',
            ])->toArray();
    }

    public function getMovementsProperty(): array
    {
        return $this->record->movements()
            ->with(['location', 'lot'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'type'       => $m->movement_type,
                'qty'        => (float) $m->quantity,
                'location'   => $m->location?->name ?? '—',
                'reason'     => $m->reason ?? '—',
                'date'       => $m->created_at->diffForHumans(),
                'lot_id'     => $m->lot_id,
            ])->toArray();
    }

    public function getAliasesProperty(): array
    {
        return $this->record->identities()
            ->orderByDesc('times_confirmed')
            ->get()
            ->map(fn ($i) => [
                'id'            => $i->id,
                'type'          => $i->type,
                'value'         => $i->value,
                'times'         => $i->times_confirmed,
                'confidence'    => round((float) $i->auto_confidence * 100),
                'last_seen'     => $i->last_confirmed_at?->diffForHumans() ?? 'Never',
                'vendor_id'     => $i->vendor_id,
            ])->toArray();
    }

    public function getCostHistoryProperty(): array
    {
        return $this->record->lots()
            ->select(['id', 'unit_cost', 'quantity', 'received_at', 'source'])
            ->orderBy('received_at')
            ->get()
            ->map(fn ($lot) => [
                'date'      => $lot->received_at?->format('M Y') ?? '—',
                'unit_cost' => (float) $lot->unit_cost,
                'qty'       => (float) $lot->quantity,
                'source'    => $lot->source,
            ])->toArray();
    }

    // ── Actions ────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil')
                ->url(InventoryItemResource::getUrl('edit', ['record' => $this->record]))
                ->color('gray'),

            Action::make('add_stock')
                ->label('Add Stock')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Select::make('location_id')
                        ->label('Location')
                        ->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->minValue(0.01),
                    Select::make('movement_type')
                        ->options(['opening' => 'Opening Stock', 'adjustment' => 'Adjustment', 'return' => 'Return'])
                        ->default('opening')
                        ->required(),
                    Textarea::make('reason')->rows(2),
                ])
                ->action(function (array $data): void {
                    $location = InventoryLocation::findOrFail($data['location_id']);
                    app(InventoryService::class)->addStock(
                        $this->record,
                        $location,
                        (float) $data['quantity'],
                        $data['movement_type'],
                        $data['reason'] ?? null
                    );
                    Notification::make()->title('Stock added')->success()->send();
                    $this->record->load('stock.location');
                }),
        ];
    }
}
