<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryCase;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Services\ReceivingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ReceivePallet extends Page
{
    protected static string $resource = PalletResource::class;

    protected static ?string $title = 'Receive Pallet';

    public Pallet $record;

    public string $barcodeInput = '';
    public ?string $lastScannedResult = null;
    public bool $lastScanSuccess = false;

    /** @var array<int, array{id:int, line_number:int, description:string, case_count:int, received:int, mapped:bool}> */
    public array $lineProgress = [];

    public function getView(): string
    {
        return 'filament.pages.receive-pallet';
    }

    public function mount(Pallet $record): void
    {
        $this->record = $record->load(['vendor', 'lines.cases', 'lines.inventoryItem', 'lines.location']);
        $this->refreshProgress();
    }

    public function refreshProgress(): void
    {
        $this->lineProgress = $this->record->lines->map(fn (PalletLine $line) => [
            'id'          => $line->id,
            'line_number' => $line->line_number,
            'description' => $line->description,
            'case_count'  => $line->case_count,
            'received'    => $line->cases->where('status', '!=', 'expected')->count(),
            'mapped'      => $line->isFullyMapped(),
            'item_name'   => $line->inventoryItem?->name,
            'location'    => $line->location?->name,
        ])->toArray();
    }

    public function submitBarcode(): void
    {
        $barcode = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if ($barcode === '') {
            return;
        }

        try {
            $case = app(ReceivingService::class)->receiveCaseByBarcode($barcode);
            $this->lastScannedResult = "✓ Received case {$barcode} — {$case->palletLine->inventoryItem?->name}";
            $this->lastScanSuccess   = true;
        } catch (\RuntimeException $e) {
            $this->lastScannedResult = "✗ {$e->getMessage()}";
            $this->lastScanSuccess   = false;
        }

        $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
        $this->refreshProgress();
    }

    public function receiveLine(int $lineId): void
    {
        $line = PalletLine::findOrFail($lineId);

        try {
            $count = app(ReceivingService::class)->receiveAllCasesForLine($line);
            Notification::make()
                ->title("Received {$count} cases for line #{$line->line_number}")
                ->success()
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }

        $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
        $this->refreshProgress();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_pallet')
                ->label('Back to Pallet')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => PalletResource::getUrl('view', ['record' => $this->record])),

            Action::make('map_line')
                ->label('Map Line to Item')
                ->icon('heroicon-o-link')
                ->color('info')
                ->form([
                    Select::make('pallet_line_id')
                        ->label('Manifest Line')
                        ->options(fn () => $this->record->lines
                            ->mapWithKeys(fn ($l) => [$l->id => "Line {$l->line_number}: {$l->description}"])
                            ->toArray())
                        ->required()
                        ->searchable(),
                    Select::make('inventory_item_id')
                        ->label('Inventory Item')
                        ->options(fn () => InventoryItem::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray())
                        ->required()
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')->required(),
                            TextInput::make('sku')->maxLength(100),
                            TextInput::make('category')->maxLength(100),
                        ])
                        ->createOptionUsing(function (array $data) {
                            return InventoryItem::create(array_merge($data, ['is_active' => true]))->getKey();
                        }),
                    Select::make('inventory_location_id')
                        ->label('Receive Into Location')
                        ->options(fn () => InventoryLocation::activeOptions())
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $line     = PalletLine::findOrFail($data['pallet_line_id']);
                    $item     = InventoryItem::findOrFail($data['inventory_item_id']);
                    $location = InventoryLocation::findOrFail($data['inventory_location_id']);
                    app(ReceivingService::class)->mapLine($line, $item, $location);
                    Notification::make()->title('Line mapped')->success()->send();
                    $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
                    $this->refreshProgress();
                }),
        ];
    }
}
