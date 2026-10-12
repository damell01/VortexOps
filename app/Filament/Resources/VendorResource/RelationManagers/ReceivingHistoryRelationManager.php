<?php

namespace App\Filament\Resources\VendorResource\RelationManagers;

use App\Filament\Pages\PalletReceivingHistory;
use App\Filament\Resources\PalletResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ReceivingHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'pallets';
    protected static ?string $title = 'Receiving history';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['vendor', 'lines.location'])
                ->withSum('lines as expected_cases_sum', 'case_count')
                ->withCount(['attachments', 'missingItems', 'cases as received_cases_count' => fn ($q) => $q->where('status', '!=', 'expected')])
                ->whereIn('status', ['receiving', 'received', 'processed']))
            ->columns(PalletReceivingHistory::receiptColumns())
            ->defaultSort('created_at', 'desc')
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->recordActions([
                Action::make('open')->label('Open delivery')->url(fn ($record) => PalletResource::getUrl('view', ['record'=>$record]))->visible(fn () => PalletResource::canAccess()),
            ])
            ->emptyStateHeading('No receiving history for this vendor')
            ->emptyStateDescription('Deliveries appear here once receiving starts.');
    }
}
