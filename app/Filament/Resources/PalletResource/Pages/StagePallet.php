<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use League\Csv\Reader;

class StagePallet extends Page
{
    protected static string $resource = PalletResource::class;

    protected static ?string $title = 'Stage Pallet Manifest';

    public Pallet $record;

    public ?array $csvData = null;

    public function getView(): string
    {
        return 'filament.pages.stage-pallet';
    }

    public function mount(Pallet $record): void
    {
        $this->record = $record->load(['vendor', 'lines']);
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        if (! in_array($this->record->status, ['staged'])) {
            abort(403, 'This pallet is no longer in staging phase.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Pallet')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => PalletResource::getUrl('view', ['record' => $this->record])),

            Action::make('ready_to_receive')
                ->label('✓ Ready to Receive')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Finish Staging?')
                ->modalDescription('All manifest lines have been entered. When the pallet arrives, you\'ll scan items on the Receive page.')
                ->action(function () {
                    if ($this->record->lines()->count() === 0) {
                        Notification::make()
                            ->title('Add at least one manifest line')
                            ->danger()
                            ->send();
                        return;
                    }

                    $this->record->update(['status' => 'receiving']);
                    Notification::make()
                        ->title('Pallet ready! Start receiving when it arrives.')
                        ->success()
                        ->send();

                    $this->redirect(PalletResource::getUrl('receive', ['record' => $this->record]));
                }),
        ];
    }

    public function importCsv(array $data): void
    {
        if (! isset($data['csv_file'])) {
            Notification::make()->title('No file selected')->danger()->send();
            return;
        }

        $file = $data['csv_file'][0] ?? null;
        if (! $file) {
            return;
        }

        try {
            $path     = storage_path('app/' . $file);
            $reader   = Reader::createFromPath($path);
            $reader->setHeaderOffset(0);
            $records  = $reader->getRecords();

            $lineNumber = $this->record->lines()->max('line_number') ?? 0;
            $imported   = 0;

            foreach ($records as $row) {
                if (empty($row['description'] ?? null)) {
                    continue;
                }

                $lineNumber++;
                $quantity  = (float) ($row['quantity'] ?? 1);
                $casesQty  = (float) ($row['case_count'] ?? 1);
                $unitCost  = (float) ($row['unit_cost'] ?? 0);

                PalletLine::create([
                    'pallet_id'            => $this->record->id,
                    'line_number'          => $lineNumber,
                    'description'          => trim($row['description']),
                    'case_count'           => $casesQty,
                    'quantity_per_case'    => $quantity,
                    'unit_cost'            => $unitCost,
                    'inventory_item_id'    => null,
                    'inventory_location_id' => null,
                ]);

                $imported++;
            }

            Notification::make()
                ->title("Imported {$imported} manifest lines")
                ->success()
                ->send();

            // Refresh the record
            $this->record = $this->record->fresh();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
