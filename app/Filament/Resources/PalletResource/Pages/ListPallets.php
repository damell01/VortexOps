<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\Pallet;
use App\Models\Vendor;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListPallets extends ListRecords
{
    protected static string $resource = PalletResource::class;

    public function getSubheading(): ?string
    {
        return 'Incoming vendor shipments. Create a pallet, map each line to a product, then receive by barcode scan or all at once.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quick_create')
                ->label('New Pallet + Import Slip')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->modalHeading('Create Pallet & Upload Packing Slip')
                ->modalDescription('Fill in the basics — you\'ll upload the vendor slip on the next screen.')
                ->modalSubmitActionLabel('Create & Upload Slip')
                ->form([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(fn () => Vendor::activeOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('reference')
                        ->label('PO / Reference #')
                        ->placeholder('e.g. PO-100')
                        ->maxLength(255),
                    DatePicker::make('received_date')
                        ->label('Expected / Received Date')
                        ->default(now()->toDateString()),
                ])
                ->action(function (array $data) {
                    $pallet = Pallet::create([
                        'vendor_id'     => $data['vendor_id'],
                        'reference'     => $data['reference'] ?: null,
                        'received_date' => $data['received_date'] ?: null,
                        'status'        => 'pending',
                        'created_by'    => auth()->id(),
                    ]);

                    return redirect(PalletResource::getUrl('import-manifest', ['record' => $pallet]));
                }),

            CreateAction::make()->label('New Pallet (Manual)'),
        ];
    }
}
