<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyPayoutBatches extends ListRecords
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    public function getSubheading(): ?string
    {
        return 'Payroll history by week — review totals and status, then open a run for the payout-level detail.';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('payroll_center')->label('Payroll Center')->icon('heroicon-o-banknotes')->color('gray')->url(\App\Filament\Pages\PayrollOverview::getUrl()),
            \Filament\Actions\Action::make('reports')->label('Reports Overview')->icon('heroicon-o-chart-bar-square')->color('gray')->url(\App\Filament\Pages\Reports::getUrl()),
            CreateAction::make()->label('New Pay Run'),
        ];
    }
}
