<?php

namespace App\Filament\Resources\PayoutResource\Pages;

use App\Filament\Resources\PayoutResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    public function getSubheading(): ?string
    {
        return (auth()->user()?->isAdmin() ?? false)
            ? 'All streamer payouts. Admins now run pay runs from Payouts under Pay Runs — this flat list stays for reference and direct links.'
            : 'Your payout history — every show you were part of and what you earned or are owed.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()?->isAdmin())
                ->url(fn () => route('export.payouts'))
                ->openUrlInNewTab(),
        ];
    }
}
