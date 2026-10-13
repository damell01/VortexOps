<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyPayoutBatches extends ListRecords
{
    use \App\Filament\Concerns\DownloadsTableCsv;

    protected static string $resource = WeeklyPayoutBatchResource::class;

    public function getSubheading(): ?string
    {
        return 'Weekly pay runs group each streamer\'s payouts. Preview before finalizing, then export for payment.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Pay Run')->icon('heroicon-o-plus')->color('primary'),
            \Filament\Actions\Action::make('rates')->label('Rates & Adjustments')->icon('heroicon-o-adjustments-horizontal')->color('gray')->url(\App\Filament\Pages\PaymentStructures::getUrl()),
        ];
    }
}
