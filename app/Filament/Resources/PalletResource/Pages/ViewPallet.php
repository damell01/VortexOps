<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletAttachment;
use App\Models\PalletLine;
use App\Services\ReceivingService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewPallet extends ViewRecord
{
    protected static string $resource = PalletResource::class;

    public ?array $newAttachments = null;

    public function getRecord(): \App\Models\Pallet
    {
        return parent::getRecord()->load(['vendor', 'lines.inventoryItem', 'lines.location', 'lines.cases', 'attachments']);
    }

    public function getView(): string
    {
        return 'filament.pages.view-pallet';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive')
                ->label('Go to Receiving')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->url(fn () => PalletResource::getUrl('receive', ['record' => $this->getRecord()]))
                ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'shipped', 'receiving'])),

            Action::make('receive_all')
                ->label('Bulk Receive All')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will receive all mapped lines at once. Any unmapped lines will cause this to fail.')
                ->action(function () {
                    try {
                        $result = app(ReceivingService::class)->receivePallet($this->getRecord());
                        Notification::make()
                            ->title("Pallet received — {$result['cases_received']} cases across {$result['lines_processed']} lines")
                            ->success()
                            ->send();
                        $this->refreshRecord();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                })
                ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'shipped', 'receiving'])),

            Action::make('upload_manifest')
                ->label('Upload Manifest')
                ->icon('heroicon-o-document-arrow-up')
                ->color('violet')
                ->url(fn () => PalletResource::getUrl('import-manifest', ['record' => $this->getRecord()]))
                ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'shipped', 'receiving'])),

            Action::make('map_line')
                ->label('Map Line to Item')
                ->icon('heroicon-o-link')
                ->color('info')
                ->form([
                    Select::make('pallet_line_id')
                        ->label('Manifest Line')
                        ->options(fn () => $this->getRecord()->lines
                            ->mapWithKeys(fn ($l) => [$l->id => "Line {$l->line_number}: {$l->description}"])
                            ->toArray())
                        ->required()
                        ->searchable()
                        ->live(),
                    Select::make('inventory_item_id')
                        ->label('Inventory Item')
                        ->options(fn ($get) => InventoryItem::suggestForDescription(
                            \App\Models\PalletLine::find($get('pallet_line_id'))?->description ?? ''
                        ))
                        ->getSearchResultsUsing(fn (string $search) => InventoryItem::where('is_active', true)
                            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhere('barcode', $search))
                            ->orderBy('name')
                            ->limit(30)
                            ->pluck('name', 'id')
                            ->toArray())
                        ->getOptionLabelUsing(fn ($value) => InventoryItem::find($value)?->name)
                        ->required()
                        ->searchable()
                        ->helperText('Suggestions based on previous show history. Type a name, SKU, or barcode to search all items.'),
                    Select::make('inventory_location_id')
                        ->label('Receive Into Location')
                        ->options(fn () => InventoryLocation::activeOptions())
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $line     = PalletLine::where('id', $data['pallet_line_id'])
                        ->where('pallet_id', $this->record->id)
                        ->firstOrFail();
                    $item     = InventoryItem::findOrFail($data['inventory_item_id']);
                    $location = InventoryLocation::findOrFail($data['inventory_location_id']);
                    app(ReceivingService::class)->mapLine($line, $item, $location);
                    Notification::make()->title('Line mapped successfully')->success()->send();
                    $this->refreshRecord();
                }),

            EditAction::make(),
        ];
    }
}
