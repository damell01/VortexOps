<?php

namespace App\Filament\Resources\StreamerLoanResource\Pages;

use App\Filament\Resources\StreamerLoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStreamerLoans extends ListRecords
{
    protected static string $resource = StreamerLoanResource::class;

    public function getSubheading(): ?string
    {
        return 'Advances/loans against a streamer\'s future payouts — repayments deduct automatically at pay run time.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
