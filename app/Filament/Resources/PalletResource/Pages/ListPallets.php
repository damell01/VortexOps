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
            // One way in. The other button opened the packing-slip reader,
            // which put a second half-built pallet flow beside this one and is
            // parked for now — a pallet is staged by hand on its own page.
            // A page, not a modal. Creating a pallet is the front of a
            // workflow — details, then lines, then receiving — and a workflow
            // squeezed into a dialog is what made this feel like filling in a
            // popup. The create page already lays these fields out three to a
            // row and has the whole screen to do it in.
            Action::make('create')
                ->label('Add Pallet')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn () => PalletResource::getUrl('create')),
        ];
    }
}
